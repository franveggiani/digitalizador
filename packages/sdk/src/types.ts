export type Bbox = readonly [number, number, number, number];

export interface GeoJsonGeometry {
  readonly type: string;
  readonly coordinates: unknown;
}

export interface ParcelFeatureDto {
  readonly external_id: string;
  readonly revision: number | null;
  readonly label: string;
  readonly srid: number;
  readonly geometry: GeoJsonGeometry;
  readonly properties: Readonly<Record<string, unknown>>;
}

export interface GeoJsonFeatureDto {
  readonly type: 'Feature';
  readonly id: string;
  readonly properties: Readonly<Record<string, unknown>>;
  readonly geometry: GeoJsonGeometry;
}

export interface FeatureCollectionDto {
  readonly type: 'FeatureCollection';
  readonly features: readonly GeoJsonFeatureDto[];
}
