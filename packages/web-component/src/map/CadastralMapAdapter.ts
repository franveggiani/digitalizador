import type {
  FeatureCollectionDto,
  GeoJsonGeometry,
  ParcelFeatureDto,
} from '@digitalizador/sdk';
import Map from 'ol/Map.js';
import View from 'ol/View.js';
import Feature from 'ol/Feature.js';
import GeoJSON from 'ol/format/GeoJSON.js';
import Modify from 'ol/interaction/Modify.js';
import Select from 'ol/interaction/Select.js';
import Snap from 'ol/interaction/Snap.js';
import VectorLayer from 'ol/layer/Vector.js';
import type Geometry from 'ol/geom/Geometry.js';
import Projection from 'ol/proj/Projection.js';
import { addProjection, get as getProjection } from 'ol/proj.js';
import { register } from 'ol/proj/proj4.js';
import VectorSource from 'ol/source/Vector.js';
import { Fill, Stroke, Style } from 'ol/style.js';
import { buffer as bufferExtent } from 'ol/extent.js';
import proj4 from 'proj4';
import type { NormalizedDigitizerConfig } from '../config.js';

export interface VertexSelection {
  readonly ringIndex: number;
  readonly vertexIndex: number;
  readonly coordinate: readonly [number, number];
}

type ExternalIdListener = (externalId: string) => void;
type DraftListener = (geometry: GeoJsonGeometry) => void;
type CoordinateListener = (coordinate: readonly [number, number]) => void;
type SnapListener = (snapped: boolean) => void;
type VertexListener = (selection: VertexSelection | null) => void;

export class CadastralMapAdapter {
  public readonly contextSource = new VectorSource();
  public readonly selectionSource = new VectorSource();
  public readonly draftSource = new VectorSource();
  public readonly helperSource = new VectorSource();

  private readonly geoJson = new GeoJSON();
  private readonly projection: Projection;
  private readonly map: Map;
  private readonly contextLayer: VectorLayer<VectorSource>;
  private readonly selectionLayer: VectorLayer<VectorSource>;
  private readonly draftLayer: VectorLayer<VectorSource>;
  private readonly selectInteraction: Select;
  private modifyInteraction: Modify | null = null;
  private snapInteraction: Snap | null = null;
  private selectListener: ExternalIdListener | null = null;
  private draftListener: DraftListener | null = null;
  private pointerListener: CoordinateListener | null = null;
  private snapListener: SnapListener | null = null;
  private vertexListener: VertexListener | null = null;

  public constructor(
    target: HTMLElement,
    private readonly config: NormalizedDigitizerConfig,
  ) {
    this.projection = resolveProjection(config);

    this.contextLayer = new VectorLayer({
      source: this.contextSource,
      style: new Style({
        fill: new Fill({ color: 'rgba(72, 92, 101, 0.07)' }),
        stroke: new Stroke({ color: '#71848d', width: 1 }),
      }),
    });
    this.selectionLayer = new VectorLayer({
      source: this.selectionSource,
      style: new Style({
        fill: new Fill({ color: 'rgba(11, 100, 119, 0.12)' }),
        stroke: new Stroke({ color: '#0b6477', width: 2 }),
      }),
    });
    this.draftLayer = new VectorLayer({
      source: this.draftSource,
      style: new Style({
        fill: new Fill({ color: 'rgba(182, 96, 43, 0.10)' }),
        stroke: new Stroke({ color: '#b6602b', width: 2.5 }),
      }),
    });
    const helperLayer = new VectorLayer({ source: this.helperSource });

    this.map = new Map({
      target,
      layers: [this.contextLayer, this.selectionLayer, this.draftLayer, helperLayer],
      controls: [],
      view: new View({
        projection: this.projection,
        center: [...config.initialCenter],
        zoom: config.initialZoom,
      }),
    });

    this.selectInteraction = new Select({ layers: [this.contextLayer] });
    this.selectInteraction.on('select', (event) => {
      const feature = event.selected[0];
      const externalId = feature?.get('external_id');
      if (typeof externalId === 'string' && externalId.length > 0) {
        this.selectListener?.(externalId);
      }
    });
    this.map.addInteraction(this.selectInteraction);

    this.map.on('pointermove', (event) => {
      this.pointerListener?.([event.coordinate[0], event.coordinate[1]]);
    });

    this.map.on('singleclick', (event) => {
      if (this.modifyInteraction === null) {
        return;
      }
      this.vertexListener?.(this.findVertexAtPixel(event.pixel));
    });
  }

  public onParcelSelected(listener: ExternalIdListener): void {
    this.selectListener = listener;
  }

  public onDraftChanged(listener: DraftListener): void {
    this.draftListener = listener;
  }

  public onPointerCoordinate(listener: CoordinateListener): void {
    this.pointerListener = listener;
  }

  public onSnapChanged(listener: SnapListener): void {
    this.snapListener = listener;
  }

  public onVertexSelected(listener: VertexListener): void {
    this.vertexListener = listener;
  }

  public setSelectedParcel(parcel: ParcelFeatureDto): void {
    this.selectionSource.clear();
    this.draftSource.clear();
    this.exitModify();

    const feature = this.readParcel(parcel);
    feature.set('external_id', parcel.external_id);
    feature.set('revision', parcel.revision);
    feature.set('label', parcel.label);
    this.selectionSource.addFeature(feature);
  }

  public setContext(collection: FeatureCollectionDto): void {
    this.contextSource.clear();
    const features = this.geoJson.readFeatures(collection as never, {
      dataProjection: this.projection,
      featureProjection: this.projection,
    });
    this.contextSource.addFeatures(features);
  }

  public fitSelected(): void {
    const feature = this.selectionSource.getFeatures()[0];
    const geometry = feature?.getGeometry();
    if (geometry === undefined) {
      return;
    }

    this.map.getView().fit(geometry.getExtent(), {
      padding: [80, 80, 80, 80],
      maxZoom: 22,
      duration: 0,
    });
  }

  public getSelectedContextBbox(bufferMapUnits: number): readonly [number, number, number, number] {
    const feature = this.selectionSource.getFeatures()[0];
    const geometry = feature?.getGeometry();
    if (geometry === undefined) {
      throw new Error('NO_SELECTED_PARCEL');
    }

    const extent = bufferExtent(geometry.getExtent(), bufferMapUnits);
    return [extent[0], extent[1], extent[2], extent[3]];
  }

  public enterModify(): GeoJsonGeometry {
    const selected = this.selectionSource.getFeatures()[0];
    if (selected === undefined || selected.getGeometry() === undefined) {
      throw new Error('NO_SELECTED_PARCEL');
    }

    this.exitModify();
    this.draftSource.clear();

    const draft = selected.clone();
    this.draftSource.addFeature(draft);
    this.selectionLayer.setVisible(false);

    this.modifyInteraction = new Modify({ source: this.draftSource });
    this.modifyInteraction.on('modifyend', () => {
      const geometry = this.getDraftGeometry();
      if (geometry !== null) {
        this.draftListener?.(geometry);
      }
    });
    this.map.addInteraction(this.modifyInteraction);

    // OpenLayers processes interactions in reverse order. Snap must be added after Modify.
    this.snapInteraction = new Snap({
      source: this.contextSource,
      vertex: true,
      edge: true,
      intersection: true,
      pixelTolerance: this.config.snapTolerancePx,
    });
    this.snapInteraction.on('snap', () => {
      this.snapListener?.(true);
    });
    this.snapInteraction.on('unsnap', () => {
      this.snapListener?.(false);
    });
    this.map.addInteraction(this.snapInteraction);

    return this.getDraftGeometry() ?? (() => { throw new Error('DRAFT_CREATION_FAILED'); })();
  }

  public exitModify(): void {
    if (this.snapInteraction !== null) {
      this.map.removeInteraction(this.snapInteraction);
      this.snapInteraction = null;
    }
    if (this.modifyInteraction !== null) {
      this.map.removeInteraction(this.modifyInteraction);
      this.modifyInteraction = null;
    }
    this.selectionLayer.setVisible(true);
    this.snapListener?.(false);
    this.vertexListener?.(null);
  }

  public clearDraft(): void {
    this.exitModify();
    this.draftSource.clear();
    this.selectionLayer.setVisible(true);
  }

  public setDraftGeometry(geometry: GeoJsonGeometry): void {
    const feature = this.draftSource.getFeatures()[0];
    if (feature === undefined) {
      throw new Error('NO_DRAFT');
    }

    feature.setGeometry(this.readGeometry(geometry));
    feature.changed();
  }

  public getDraftGeometry(): GeoJsonGeometry | null {
    const geometry = this.draftSource.getFeatures()[0]?.getGeometry();
    return geometry === undefined ? null : this.writeGeometry(geometry);
  }

  public getSelectedGeometry(): GeoJsonGeometry | null {
    const geometry = this.selectionSource.getFeatures()[0]?.getGeometry();
    return geometry === undefined ? null : this.writeGeometry(geometry);
  }

  public get contextCount(): number {
    return this.contextSource.getFeatures().length;
  }

  public updateSize(): void {
    this.map.updateSize();
  }

  public destroy(): void {
    this.exitModify();
    this.map.setTarget(undefined);
    this.contextSource.clear();
    this.selectionSource.clear();
    this.draftSource.clear();
    this.helperSource.clear();
  }

  private readParcel(parcel: ParcelFeatureDto): Feature<Geometry> {
    return this.geoJson.readFeature({
      type: 'Feature',
      id: parcel.external_id,
      properties: {
        external_id: parcel.external_id,
        revision: parcel.revision,
        label: parcel.label,
      },
      geometry: parcel.geometry,
    } as never, {
      dataProjection: this.projection,
      featureProjection: this.projection,
    });
  }

  private readGeometry(geometry: GeoJsonGeometry): Geometry {
    return this.geoJson.readGeometry(geometry as never, {
      dataProjection: this.projection,
      featureProjection: this.projection,
    });
  }

  private writeGeometry(geometry: Geometry): GeoJsonGeometry {
    return this.geoJson.writeGeometryObject(geometry, {
      dataProjection: this.projection,
      featureProjection: this.projection,
      decimals: 12,
    }) as GeoJsonGeometry;
  }

  private findVertexAtPixel(pixel: readonly number[]): VertexSelection | null {
    const geometry = this.getDraftGeometry();
    if (geometry?.type !== 'Polygon' || !Array.isArray(geometry.coordinates)) {
      return null;
    }

    let best: { distance: number; selection: VertexSelection } | null = null;
    const rings = geometry.coordinates as unknown[][];

    for (let ringIndex = 0; ringIndex < rings.length; ringIndex += 1) {
      const ring = rings[ringIndex];
      if (!Array.isArray(ring)) continue;

      for (let vertexIndex = 0; vertexIndex < ring.length; vertexIndex += 1) {
        const candidate = ring[vertexIndex];
        if (!Array.isArray(candidate) || candidate.length < 2) continue;
        const x = Number(candidate[0]);
        const y = Number(candidate[1]);
        if (!Number.isFinite(x) || !Number.isFinite(y)) continue;

        const candidatePixel = this.map.getPixelFromCoordinate([x, y]);
        const dx = candidatePixel[0] - pixel[0];
        const dy = candidatePixel[1] - pixel[1];
        const distance = Math.hypot(dx, dy);

        if (distance <= this.config.snapTolerancePx && (best === null || distance < best.distance)) {
          best = {
            distance,
            selection: { ringIndex, vertexIndex, coordinate: [x, y] },
          };
        }
      }
    }

    return best?.selection ?? null;
  }
}

function resolveProjection(config: NormalizedDigitizerConfig): Projection {
  const code = `EPSG:${config.srid}`;
  let projection = getProjection(code);
  if (projection !== null) {
    return projection;
  }

  if (config.projectionDefinition !== undefined) {
    proj4.defs(code, config.projectionDefinition);
    register(proj4);
    projection = getProjection(code);
    if (projection !== null) {
      return projection;
    }
  }

  projection = new Projection({ code, units: 'm' });
  addProjection(projection);
  return projection;
}
