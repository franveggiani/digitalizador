import { describe, expect, it } from 'vitest';
import { replacePolygonVertex } from '../src/editor/VertexEditor.js';

describe('replacePolygonVertex', () => {
  it('replaces an exterior vertex and keeps a closed ring synchronized', () => {
    const geometry = {
      type: 'Polygon' as const,
      coordinates: [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]],
    };

    const updated = replacePolygonVertex(geometry, 0, 0, [1.25, 2.5]);

    expect(updated.coordinates[0]?.[0]).toEqual([1.25, 2.5]);
    expect(updated.coordinates[0]?.[4]).toEqual([1.25, 2.5]);
    expect(geometry.coordinates[0]?.[0]).toEqual([0, 0]);
  });

  it('rejects an invalid ring or vertex index', () => {
    const geometry = {
      type: 'Polygon' as const,
      coordinates: [[[0, 0], [10, 0], [10, 10], [0, 0]]],
    };

    expect(() => replacePolygonVertex(geometry, 2, 0, [1, 1])).toThrow('RING_NOT_FOUND');
    expect(() => replacePolygonVertex(geometry, 0, 99, [1, 1])).toThrow('VERTEX_NOT_FOUND');
  });
});
