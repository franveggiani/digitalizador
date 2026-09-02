export interface PolygonGeometry {
  readonly type: 'Polygon';
  readonly coordinates: readonly (readonly (readonly number[])[])[];
}

export function replacePolygonVertex(
  geometry: PolygonGeometry,
  ringIndex: number,
  vertexIndex: number,
  coordinate: readonly [number, number],
): PolygonGeometry {
  if (!Number.isFinite(coordinate[0]) || !Number.isFinite(coordinate[1])) {
    throw new Error('INVALID_VERTEX_COORDINATE');
  }

  const coordinates = geometry.coordinates.map((ring) => ring.map((point) => [...point]));
  const ring = coordinates[ringIndex];
  if (ring === undefined) {
    throw new Error('RING_NOT_FOUND');
  }

  if (ring[vertexIndex] === undefined) {
    throw new Error('VERTEX_NOT_FOUND');
  }

  const next: [number, number] = [coordinate[0], coordinate[1]];
  ring[vertexIndex] = next;

  if (ring.length >= 2) {
    const lastIndex = ring.length - 1;
    if (vertexIndex === 0) {
      ring[lastIndex] = [...next];
    } else if (vertexIndex === lastIndex) {
      ring[0] = [...next];
    }
  }

  return { type: 'Polygon', coordinates };
}
