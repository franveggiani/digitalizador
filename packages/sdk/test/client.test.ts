import { describe, expect, it } from 'vitest';
import { DigitizerApiError, DigitizerClient } from '../src/client.js';

describe('DigitizerClient', () => {
  it('builds versioned workspace parcel URLs and escapes host identifiers', async () => {
    let requestedUrl = '';
    const fetchFn: typeof fetch = async (input) => {
      requestedUrl = String(input);
      return jsonResponse({
        data: {
          external_id: 'PAD 1/2',
          revision: 1,
          geometry: { type: 'Polygon', coordinates: [] },
          properties: {},
        },
      });
    };

    const client = new DigitizerClient({
      apiBaseUrl: 'https://digitizer.example.test/',
      fetch: fetchFn,
    });

    await client.getParcel('catastro central', 'PAD 1/2');

    expect(requestedUrl).toBe(
      'https://digitizer.example.test/api/v1/datasets/catastro%20central/parcels/PAD%201%2F2',
    );
  });

  it('reads an authoritative configured source parcel without a dataset key', async () => {
    let requestedUrl = '';
    const fetchFn: typeof fetch = async (input) => {
      requestedUrl = String(input);
      return jsonResponse({
        data: {
          external_id: 'PAD 1/2',
          revision: 9,
          label: 'Padrón 1/2',
          srid: 22182,
          geometry: { type: 'Polygon', coordinates: [] },
          properties: {},
        },
      });
    };

    const client = new DigitizerClient({ apiBaseUrl: '/digitizer', fetch: fetchFn });
    const parcel = await client.getSourceParcel('PAD 1/2');

    expect(requestedUrl).toBe('/digitizer/api/v1/parcels/PAD%201%2F2');
    expect(parcel).toMatchObject({ external_id: 'PAD 1/2', revision: 9, srid: 22182 });
  });

  it('encodes BBOX and optional limit for authoritative context', async () => {
    let requestedUrl = '';
    const fetchFn: typeof fetch = async (input) => {
      requestedUrl = String(input);
      return jsonResponse({ data: { type: 'FeatureCollection', features: [] } });
    };

    const client = new DigitizerClient({ apiBaseUrl: '/digitizer/', fetch: fetchFn });
    await client.getParcelContext([2534000.1, 6364000.2, 2534010.3, 6364010.4], 250);

    expect(requestedUrl).toBe(
      '/digitizer/api/v1/parcels/context?bbox=2534000.1%2C6364000.2%2C2534010.3%2C6364010.4&limit=250',
    );
  });

  it('preserves public API error status, code and details', async () => {
    const fetchFn: typeof fetch = async () => new Response(JSON.stringify({
      error: {
        code: 'REVISION_CONFLICT',
        message: 'The parcel changed after this editing session started.',
        details: { expected_revision: 4, actual_revision: 5 },
      },
    }), { status: 409, headers: { 'content-type': 'application/json' } });

    const client = new DigitizerClient({
      apiBaseUrl: 'https://digitizer.example.test',
      fetch: fetchFn,
    });

    try {
      await client.getParcel('dataset', 'parcel');
      throw new Error('Expected request to fail');
    } catch (error) {
      expect(error).toBeInstanceOf(DigitizerApiError);
      expect(error).toMatchObject({
        status: 409,
        code: 'REVISION_CONFLICT',
        details: { expected_revision: 4, actual_revision: 5 },
      });
    }
  });
});

function jsonResponse(payload: unknown): Response {
  return new Response(JSON.stringify(payload), {
    status: 200,
    headers: { 'content-type': 'application/json' },
  });
}
