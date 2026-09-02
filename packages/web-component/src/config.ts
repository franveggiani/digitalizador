export interface DigitizerConfig {
  readonly apiBaseUrl: string;
  readonly srid: number;
  readonly projectionDefinition?: string;
  readonly snapTolerancePx?: number;
  readonly contextBufferMapUnits?: number;
  readonly contextLimit?: number;
  readonly initialCenter?: readonly [number, number];
  readonly initialZoom?: number;
}

export interface NormalizedDigitizerConfig {
  readonly apiBaseUrl: string;
  readonly srid: number;
  readonly projectionDefinition?: string;
  readonly snapTolerancePx: number;
  readonly contextBufferMapUnits: number;
  readonly contextLimit: number;
  readonly initialCenter: readonly [number, number];
  readonly initialZoom: number;
}

export function normalizeDigitizerConfig(config: DigitizerConfig): NormalizedDigitizerConfig {
  const apiBaseUrl = config.apiBaseUrl.trim().replace(/\/+$/, '');
  if (apiBaseUrl.length === 0) {
    throw new Error('API_BASE_URL_REQUIRED');
  }

  if (!Number.isInteger(config.srid) || config.srid <= 0) {
    throw new Error('INVALID_SRID');
  }

  const snapTolerancePx = positiveNumber(config.snapTolerancePx ?? 12, 'INVALID_SNAP_TOLERANCE');
  const contextBufferMapUnits = nonNegativeNumber(
    config.contextBufferMapUnits ?? 50,
    'INVALID_CONTEXT_BUFFER',
  );
  const contextLimit = positiveInteger(config.contextLimit ?? 2000, 'INVALID_CONTEXT_LIMIT');
  const initialZoom = Number.isFinite(config.initialZoom ?? 2) ? (config.initialZoom ?? 2) : 2;
  const initialCenter = config.initialCenter ?? [0, 0] as const;

  if (initialCenter.length !== 2 || initialCenter.some((coordinate) => !Number.isFinite(coordinate))) {
    throw new Error('INVALID_INITIAL_CENTER');
  }

  const normalized: NormalizedDigitizerConfig = {
    apiBaseUrl,
    srid: config.srid,
    snapTolerancePx,
    contextBufferMapUnits,
    contextLimit,
    initialCenter: [initialCenter[0], initialCenter[1]],
    initialZoom,
  };

  const projectionDefinition = config.projectionDefinition?.trim();
  if (projectionDefinition) {
    return { ...normalized, projectionDefinition };
  }

  return normalized;
}

function positiveNumber(value: number, code: string): number {
  if (!Number.isFinite(value) || value <= 0) {
    throw new Error(code);
  }
  return value;
}

function nonNegativeNumber(value: number, code: string): number {
  if (!Number.isFinite(value) || value < 0) {
    throw new Error(code);
  }
  return value;
}

function positiveInteger(value: number, code: string): number {
  if (!Number.isInteger(value) || value < 1) {
    throw new Error(code);
  }
  return value;
}
