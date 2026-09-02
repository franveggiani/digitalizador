import { describe, expect, it } from 'vitest';
import { GeometryHistory } from '../src/editor/GeometryHistory.js';

const a = { type: 'Polygon', coordinates: [[[0, 0], [10, 0], [10, 10], [0, 0]]] };
const b = { type: 'Polygon', coordinates: [[[0, 0], [12, 0], [10, 10], [0, 0]]] };
const c = { type: 'Polygon', coordinates: [[[0, 0], [12, 0], [12, 12], [0, 0]]] };

describe('GeometryHistory', () => {
  it('undoes, redoes and truncates future history after a new edit', () => {
    const history = new GeometryHistory(a);
    history.push(b);
    history.push(c);

    expect(history.undo()).toEqual(b);
    expect(history.undo()).toEqual(a);
    expect(history.redo()).toEqual(b);

    history.push(c);
    expect(history.canRedo).toBe(false);
  });

  it('returns defensive clones so callers cannot mutate history', () => {
    const history = new GeometryHistory(a);
    const current = history.current as { coordinates: number[][][] };
    current.coordinates[0]![0]![0] = 999;

    expect((history.current.coordinates as number[][][])[0]![0]![0]).toBe(0);
  });
});
