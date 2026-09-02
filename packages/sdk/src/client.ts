export interface DigitizerClientOptions {
  readonly apiBaseUrl: string;
  readonly fetch?: typeof globalThis.fetch;
}

export interface GeoJsonGeometry {
  readonly type: string;
  readonly coordinates: unknown;
}

export interface ParcelDto {
  readonly external_id: string;
  readonly revision: number;
  readonly geometry: GeoJsonGeometry;
  readonly properties: Readonly<Record<string, unknown>>;
}

interface DataEnvelope<T> {
  readonly data: T;
}

interface ApiErrorEnvelope {
  readonly error?: {
    readonly code?: string;
    readonly message?: string;
    readonly details?: Readonly<Record<string, unknown>>;
  };
}

export class DigitizerApiError extends Error {
  public readonly status: number;
  public readonly code: string;
  public readonly details: Readonly<Record<string, unknown>>;

  public constructor(
    status: number,
    code: string,
    message: string,
    details: Readonly<Record<string, unknown>> = {},
  ) {
    super(message);
    this.name = 'DigitizerApiError';
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

export class DigitizerClient {
  private readonly apiBaseUrl: string;
  private readonly fetchFn: typeof globalThis.fetch;

  public constructor(options: DigitizerClientOptions) {
    const normalizedBaseUrl = options.apiBaseUrl.trim().replace(/\/+$/, '');

    if (normalizedBaseUrl.length === 0) {
      throw new Error('API_BASE_URL_REQUIRED');
    }

    this.apiBaseUrl = normalizedBaseUrl;
    this.fetchFn = options.fetch ?? globalThis.fetch;

    if (typeof this.fetchFn !== 'function') {
      throw new Error('FETCH_IMPLEMENTATION_REQUIRED');
    }
  }

  public async getParcel(datasetExternalKey: string, parcelExternalId: string): Promise<ParcelDto> {
    const dataset = encodeURIComponent(datasetExternalKey);
    const parcel = encodeURIComponent(parcelExternalId);

    return this.request<ParcelDto>(
      `${this.apiBaseUrl}/api/v1/datasets/${dataset}/parcels/${parcel}`,
      { method: 'GET' },
    );
  }

  private async request<T>(url: string, init: RequestInit): Promise<T> {
    const response = await this.fetchFn(url, {
      ...init,
      headers: {
        accept: 'application/json',
        ...init.headers,
      },
    });

    const payload = await this.readJson(response);

    if (!response.ok) {
      const errorPayload = payload as ApiErrorEnvelope;
      throw new DigitizerApiError(
        response.status,
        errorPayload.error?.code ?? 'HTTP_ERROR',
        errorPayload.error?.message ?? `Request failed with status ${response.status}`,
        errorPayload.error?.details ?? {},
      );
    }

    if (!this.isDataEnvelope<T>(payload)) {
      throw new DigitizerApiError(
        response.status,
        'INVALID_RESPONSE',
        'The digitizer API returned an invalid response envelope.',
      );
    }

    return payload.data;
  }

  private async readJson(response: Response): Promise<unknown> {
    const contentType = response.headers.get('content-type') ?? '';
    if (!contentType.includes('application/json')) {
      return {};
    }

    try {
      return await response.json() as unknown;
    } catch {
      return {};
    }
  }

  private isDataEnvelope<T>(payload: unknown): payload is DataEnvelope<T> {
    return typeof payload === 'object' && payload !== null && 'data' in payload;
  }
}
