import {
  DigitizerClient,
  type GeoJsonGeometry,
  type ParcelFeatureDto,
} from '@digitalizador/sdk';
import { LitElement, html, nothing } from 'lit';
import type { PropertyValues } from 'lit';
import { normalizeDigitizerConfig, type DigitizerConfig, type NormalizedDigitizerConfig } from './config.js';
import { GeometryHistory } from './editor/GeometryHistory.js';
import { replacePolygonVertex, type PolygonGeometry } from './editor/VertexEditor.js';
import { CadastralMapAdapter, type VertexSelection } from './map/CadastralMapAdapter.js';
import { digitizerStyles } from './styles.js';

export type DigitizerMode = 'select' | 'modify';

export interface DraftGeometry {
  readonly external_id: string;
  readonly revision: number | null;
  readonly srid: number;
  readonly geometry: GeoJsonGeometry;
}

export class CadastralDigitizerElement extends LitElement {
  public static override styles = digitizerStyles;

  public static override properties = {
    apiBaseUrl: { type: String, attribute: 'api-base-url' },
    srid: { type: Number },
    projectionDefinition: { type: String, attribute: 'projection-definition' },
    mode: { state: true },
    selectedParcel: { state: true },
    draftGeometry: { state: true },
    cursorCoordinate: { state: true },
    snapped: { state: true },
    contextCount: { state: true },
    selectedVertex: { state: true },
    errorMessage: { state: true },
  };

  public apiBaseUrl = '';
  public srid = 0;
  public projectionDefinition = '';

  protected mode: DigitizerMode = 'select';
  protected selectedParcel: ParcelFeatureDto | null = null;
  protected draftGeometry: GeoJsonGeometry | null = null;
  protected cursorCoordinate: readonly [number, number] | null = null;
  protected snapped = false;
  protected contextCount = 0;
  protected selectedVertex: VertexSelection | null = null;
  protected errorMessage = '';

  private normalizedConfig: NormalizedDigitizerConfig | null = null;
  private client: DigitizerClient | null = null;
  private mapAdapter: CadastralMapAdapter | null = null;
  private history: GeometryHistory | null = null;

  public configure(config: DigitizerConfig): void {
    const normalized = normalizeDigitizerConfig(config);
    this.normalizedConfig = normalized;
    this.apiBaseUrl = normalized.apiBaseUrl;
    this.srid = normalized.srid;
    this.projectionDefinition = normalized.projectionDefinition ?? '';
    this.client = new DigitizerClient({ apiBaseUrl: normalized.apiBaseUrl });
    this.errorMessage = '';

    this.mapAdapter?.destroy();
    this.mapAdapter = null;
    void this.updateComplete.then(() => this.initializeMap());
  }

  public async openParcel(externalId: string): Promise<void> {
    try {
      const { client, map, config } = await this.requireRuntime();
      const parcel = await client.getSourceParcel(externalId);

      if (parcel.srid !== config.srid) {
        throw new Error(`SOURCE_SRID_MISMATCH:${parcel.srid}`);
      }

      map.setSelectedParcel(parcel);
      const bbox = map.getSelectedContextBbox(config.contextBufferMapUnits);
      const context = await client.getParcelContext(bbox, config.contextLimit);
      map.setContext(context);
      map.fitSelected();

      this.selectedParcel = parcel;
      this.draftGeometry = null;
      this.history = null;
      this.selectedVertex = null;
      this.contextCount = map.contextCount;
      this.mode = 'select';
      this.errorMessage = '';

      this.emit('parcel-selected', {
        external_id: parcel.external_id,
        revision: parcel.revision,
        label: parcel.label,
        srid: parcel.srid,
        geometry: parcel.geometry,
      });
    } catch (error) {
      this.reportError(error);
      throw error;
    }
  }

  public setMode(mode: DigitizerMode): void {
    if (mode === this.mode) return;

    try {
      const map = this.requireMap();
      if (mode === 'modify') {
        if (this.selectedParcel === null) {
          throw new Error('NO_SELECTED_PARCEL');
        }
        const draft = map.enterModify();
        this.history = new GeometryHistory(draft);
        this.draftGeometry = draft;
        this.selectedVertex = null;
      } else {
        map.exitModify();
        this.selectedVertex = null;
      }

      this.mode = mode;
      this.emit('mode-changed', { mode });
    } catch (error) {
      this.reportError(error);
      throw error;
    }
  }

  public undo(): void {
    if (this.history === null || !this.history.canUndo) return;
    const geometry = this.history.undo();
    this.requireMap().setDraftGeometry(geometry);
    this.applyDraftState(geometry, 'undo');
  }

  public redo(): void {
    if (this.history === null || !this.history.canRedo) return;
    const geometry = this.history.redo();
    this.requireMap().setDraftGeometry(geometry);
    this.applyDraftState(geometry, 'redo');
  }

  public cancelDraft(): void {
    if (this.selectedParcel === null || this.mapAdapter === null) return;
    this.mapAdapter.exitModify();
    this.draftGeometry = null;
    this.history = null;
    this.selectedVertex = null;
    this.mode = 'select';
    this.emit('mode-changed', { mode: 'select' });
    this.emit('draft-changed', { draft: null, reason: 'cancel' });
  }

  public getDraft(): DraftGeometry | null {
    if (this.selectedParcel === null || this.draftGeometry === null) {
      return null;
    }

    return cloneSerializable({
      external_id: this.selectedParcel.external_id,
      revision: this.selectedParcel.revision,
      srid: this.selectedParcel.srid,
      geometry: this.draftGeometry,
    });
  }

  public override disconnectedCallback(): void {
    this.mapAdapter?.destroy();
    this.mapAdapter = null;
    super.disconnectedCallback();
  }

  protected override firstUpdated(): void {
    if (this.normalizedConfig === null && this.apiBaseUrl.trim() !== '' && this.srid > 0) {
      const config: DigitizerConfig = {
        apiBaseUrl: this.apiBaseUrl,
        srid: this.srid,
        ...(this.projectionDefinition.trim() !== ''
          ? { projectionDefinition: this.projectionDefinition }
          : {}),
      };
      this.configure(config);
      return;
    }

    this.initializeMap();
  }

  protected override updated(changed: PropertyValues<this>): void {
    if (changed.has('selectedVertex') && this.selectedVertex !== null) {
      const x = this.renderRoot.querySelector<HTMLInputElement>('input[name="vertex-x"]');
      const y = this.renderRoot.querySelector<HTMLInputElement>('input[name="vertex-y"]');
      if (x) x.value = String(this.selectedVertex.coordinate[0]);
      if (y) y.value = String(this.selectedVertex.coordinate[1]);
    }
  }

  protected override render() {
    const parcelTitle = this.selectedParcel?.label ?? this.selectedParcel?.external_id ?? 'Sin parcela seleccionada';
    const vertex = this.selectedVertex;
    const displayedSrid = this.normalizedConfig?.srid ?? (this.srid > 0 ? this.srid : '—');

    return html`
      <section class="shell" aria-label="Digitalizador catastral">
        <nav class="tool-rail" data-role="tool-rail" aria-label="Herramientas">
          <button class="tool" title="Seleccionar" aria-label="Seleccionar" aria-pressed=${this.mode === 'select'}
            @click=${() => this.setMode('select')}>SEL</button>
          <button class="tool" title="Modificar geometría" aria-label="Modificar geometría"
            aria-pressed=${this.mode === 'modify'} ?disabled=${this.selectedParcel === null}
            @click=${() => this.setMode('modify')}>MOD</button>
          <button class="tool" title="Deshacer" aria-label="Deshacer" ?disabled=${!this.history?.canUndo}
            @click=${() => this.undo()}>↶</button>
          <button class="tool" title="Rehacer" aria-label="Rehacer" ?disabled=${!this.history?.canRedo}
            @click=${() => this.redo()}>↷</button>
          <button class="tool" title="Cancelar borrador" aria-label="Cancelar borrador" ?disabled=${this.draftGeometry === null}
            @click=${() => this.cancelDraft()}>ESC</button>
        </nav>

        <main class="map-wrap">
          <div class="map" data-role="map"></div>
          ${this.selectedParcel === null
            ? html`<div class="empty-hint">Seleccione una parcela o abra una mediante <span class="mono">openParcel()</span></div>`
            : nothing}
        </main>

        <aside class="inspector" data-role="inspector">
          <p class="eyebrow">Parcela activa</p>
          <h2>${parcelTitle}</h2>

          <div class="section">
            <div class="row"><span>ID externo</span><strong class="mono">${this.selectedParcel?.external_id ?? '—'}</strong></div>
            <div class="row"><span>Revisión</span><strong class="mono">${this.selectedParcel?.revision ?? '—'}</strong></div>
            <div class="row"><span>Modo</span><strong class="mono">${this.mode.toUpperCase()}</strong></div>
          </div>

          <div class="section">
            <p class="eyebrow">Vértice exacto</p>
            ${vertex === null
              ? html`<div class="row"><span>Selección</span><strong>Haga clic sobre un vértice en MOD</strong></div>`
              : html`
                <form class="vertex-form" @submit=${this.applyVertexCoordinates}>
                  <div class="row"><span>Anillo / vértice</span><strong class="mono">${vertex.ringIndex} / ${vertex.vertexIndex}</strong></div>
                  <label>X <input name="vertex-x" inputmode="decimal" required .value=${String(vertex.coordinate[0])}></label>
                  <label>Y <input name="vertex-y" inputmode="decimal" required .value=${String(vertex.coordinate[1])}></label>
                  <button class="apply" type="submit">Aplicar coordenadas</button>
                </form>
              `}
          </div>

          ${this.errorMessage
            ? html`<div class="section" role="alert"><p class="eyebrow">Error</p><div>${this.errorMessage}</div></div>`
            : nothing}
        </aside>

        <footer class="status" data-role="status">
          <span>XY ${formatCoordinate(this.cursorCoordinate)}</span>
          <span class="optional">EPSG:${displayedSrid}</span>
          <span class="optional">CTX ${this.contextCount}</span>
          <span class=${this.snapped ? 'snap-on' : 'snap-off'}>${this.snapped ? 'SNAP ✓' : 'SNAP —'}</span>
        </footer>
      </section>
    `;
  }

  private readonly applyVertexCoordinates = (event: SubmitEvent): void => {
    event.preventDefault();
    if (this.selectedVertex === null || this.draftGeometry?.type !== 'Polygon') return;

    const form = event.currentTarget as HTMLFormElement;
    const data = new FormData(form);
    const x = Number(data.get('vertex-x'));
    const y = Number(data.get('vertex-y'));
    if (!Number.isFinite(x) || !Number.isFinite(y)) {
      this.reportError(new Error('INVALID_VERTEX_COORDINATE'));
      return;
    }

    try {
      const geometry = replacePolygonVertex(
        this.draftGeometry as PolygonGeometry,
        this.selectedVertex.ringIndex,
        this.selectedVertex.vertexIndex,
        [x, y],
      );
      this.history?.push(geometry);
      this.requireMap().setDraftGeometry(geometry);
      this.selectedVertex = {
        ...this.selectedVertex,
        coordinate: [x, y],
      };
      this.applyDraftState(geometry, 'coordinate');
    } catch (error) {
      this.reportError(error);
    }
  };

  private initializeMap(): void {
    if (this.mapAdapter !== null || this.normalizedConfig === null) return;
    const target = this.renderRoot.querySelector<HTMLElement>('[data-role="map"]');
    if (target === null) return;

    const map = new CadastralMapAdapter(target, this.normalizedConfig);
    map.onParcelSelected((externalId) => {
      void this.openParcel(externalId);
    });
    map.onDraftChanged((geometry) => {
      if (this.history === null) {
        this.history = new GeometryHistory(geometry);
      } else {
        this.history.push(geometry);
      }
      this.applyDraftState(geometry, 'modify');
    });
    map.onPointerCoordinate((coordinate) => {
      this.cursorCoordinate = coordinate;
    });
    map.onSnapChanged((snapped) => {
      this.snapped = snapped;
      this.emit('snap-changed', { snapped });
    });
    map.onVertexSelected((selection) => {
      this.selectedVertex = selection;
    });

    this.mapAdapter = map;
    this.emit('digitizer-ready', {
      srid: this.normalizedConfig.srid,
      api_base_url: this.normalizedConfig.apiBaseUrl,
    });
  }

  private async requireRuntime(): Promise<{
    client: DigitizerClient;
    map: CadastralMapAdapter;
    config: NormalizedDigitizerConfig;
  }> {
    await this.updateComplete;
    this.initializeMap();
    if (this.client === null || this.mapAdapter === null || this.normalizedConfig === null) {
      throw new Error('DIGITIZER_NOT_CONFIGURED');
    }
    return { client: this.client, map: this.mapAdapter, config: this.normalizedConfig };
  }

  private requireMap(): CadastralMapAdapter {
    if (this.mapAdapter === null) {
      throw new Error('DIGITIZER_NOT_CONFIGURED');
    }
    return this.mapAdapter;
  }

  private applyDraftState(geometry: GeoJsonGeometry, reason: string): void {
    this.draftGeometry = cloneSerializable(geometry);
    this.emit('draft-changed', {
      draft: this.getDraft(),
      reason,
      can_undo: this.history?.canUndo ?? false,
      can_redo: this.history?.canRedo ?? false,
    });
  }

  private reportError(error: unknown): void {
    const message = error instanceof Error ? error.message : String(error);
    this.errorMessage = message;
    this.emit('digitizer-error', { message });
  }

  private emit(name: string, detail: unknown): void {
    this.dispatchEvent(new CustomEvent(name, {
      detail: cloneSerializable(detail),
      bubbles: true,
      composed: true,
    }));
  }
}

function cloneSerializable<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T;
}

function formatCoordinate(coordinate: readonly [number, number] | null): string {
  if (coordinate === null) return '—';
  return `${coordinate[0].toFixed(3)}  ${coordinate[1].toFixed(3)}`;
}
