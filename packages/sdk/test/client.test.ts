import { describe, expect, it } from 'vitest';
import { DigitizerApiError, DigitizerClient } from '../src/client.js';

describe('DigitizerClient', () => {
  it('builds versioned parcel URLs and escapes host identifiers', async () => {
    let requestedUrl = '';
    const fetchFn: typeof fetch = async (input) => {
      requestedUrl = String(input);
      return new Response(JSON.stringify({
        data: {
          external_id: 'PAD 1/2',
          revision: 1,
          geometry: { type: 'Polygon', coordinates: [] },
          properties: {},
        },
      }), { status: 200, headers: { 'content-type': 'application/json' } });
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
