import { describe, expect, it } from 'vitest';
import { normalizeDigitizerConfig } from '../src/config.js';

describe('normalizeDigitizerConfig', () => {
  it('normalizes the API base URL and applies deterministic editor defaults', () => {
    expect(normalizeDigitizerConfig({ apiBaseUrl: '/digitizer/', srid: 22182 })).toEqual({
      apiBaseUrl: '/digitizer',
      srid: 22182,
      snapTolerancePx: 12,
      contextBufferMapUnits: 50,
      contextLimit: 2000,
      initialCenter: [0, 0],
      initialZoom: 2,
    });
  });

  it('rejects missing API URL and invalid SRID', () => {
    expect(() => normalizeDigitizerConfig({ apiBaseUrl: ' ', srid: 22182 })).toThrow('API_BASE_URL_REQUIRED');
    expect(() => normalizeDigitizerConfig({ apiBaseUrl: '/api', srid: 0 })).toThrow('INVALID_SRID');
  });
});
