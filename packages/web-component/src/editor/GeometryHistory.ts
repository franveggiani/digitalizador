import type { GeoJsonGeometry } from '@digitalizador/sdk';

export class GeometryHistory {
  private past: GeoJsonGeometry[] = [];
  private present: GeoJsonGeometry;
  private future: GeoJsonGeometry[] = [];

  public constructor(initial: GeoJsonGeometry) {
    this.present = cloneGeometry(initial);
  }

  public get current(): GeoJsonGeometry {
    return cloneGeometry(this.present);
  }

  public get canUndo(): boolean {
    return this.past.length > 0;
  }

  public get canRedo(): boolean {
    return this.future.length > 0;
  }

  public push(geometry: GeoJsonGeometry): GeoJsonGeometry {
    const next = cloneGeometry(geometry);
    if (JSON.stringify(next) === JSON.stringify(this.present)) {
      return this.current;
    }

    this.past.push(cloneGeometry(this.present));
    this.present = next;
    this.future = [];
    return this.current;
  }

  public undo(): GeoJsonGeometry {
    const previous = this.past.pop();
    if (previous === undefined) {
      return this.current;
    }

    this.future.unshift(cloneGeometry(this.present));
    this.present = previous;
    return this.current;
  }

  public redo(): GeoJsonGeometry {
    const next = this.future.shift();
    if (next === undefined) {
      return this.current;
    }

    this.past.push(cloneGeometry(this.present));
    this.present = next;
    return this.current;
  }

  public reset(geometry: GeoJsonGeometry): GeoJsonGeometry {
    this.past = [];
    this.present = cloneGeometry(geometry);
    this.future = [];
    return this.current;
  }
}

export function cloneGeometry<T extends GeoJsonGeometry>(geometry: T): T {
  return JSON.parse(JSON.stringify(geometry)) as T;
}
