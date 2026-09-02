# Configured PGSQL + OpenLayers Web Component v1 — Design Specification

## Purpose

Build the first usable embeddable cadastral digitizer UI. The editor reads authoritative parcel geometries directly from the PostgreSQL/PostGIS connection already configured in Laravel and exposes a framework-agnostic Web Component powered by OpenLayers.

This phase deliberately stops before transactional commit/history. Its output is a precise editable draft and public integration events that later session/commit work can consume.

## Approved architecture decision

Use configurable table/column mapping against Laravel's configured `pgsql` connection.

The browser never receives database credentials or SQL configuration. OpenLayers only calls REST endpoints. Laravel validates configuration, safely quotes configured identifiers, queries PostGIS, serializes GeoJSON, and remains the only database boundary.

## Global constraints

- No users, roles, permissions, ACLs or authentication subsystem.
- Use Laravel's configured PostgreSQL connection; do not create a second database connection requirement.
- Do not require host systems to copy parcel rows into the internal `parcels` workspace before viewing/editing.
- Table and column names are configuration, never request parameters.
- Do not accept arbitrary SQL expressions from environment variables or HTTP requests.
- Authoritative geometries come directly from PostGIS.
- Initial geometry type is 2D Polygon/MultiPolygon for display; editing v1 operates on Polygon.
- Browser calculations are interaction previews only.
- Web Component must remain usable from plain HTML, Blade, React, Vue or other frameworks.
- SDK stays independent of OpenLayers and DOM internals.

## Laravel datasource configuration

Add `config/cadastral.php` with environment-backed values:

```env
CADASTRAL_PARCELS_TABLE=parcelas
CADASTRAL_PARCELS_ID_COLUMN=id
CADASTRAL_PARCELS_GEOMETRY_COLUMN=geom
CADASTRAL_PARCELS_REVISION_COLUMN=
CADASTRAL_PARCELS_LABEL_COLUMN=
CADASTRAL_PARCELS_WHERE_ACTIVE_COLUMN=
CADASTRAL_PARCELS_WHERE_ACTIVE_VALUE=1
CADASTRAL_PARCELS_SRID=22182
```

Required:

- `table`
- `id_column`
- `geometry_column`
- `srid`

Optional:

- `revision_column`
- `label_column`
- active filter column/value

Identifiers must match `^[A-Za-z_][A-Za-z0-9_]*$`. `table` may optionally be schema-qualified using exactly two valid identifiers (`schema.table`). Invalid configuration fails with a stable API configuration error rather than interpolating unsafe SQL.

## Backend repository boundary

Create a datasource abstraction:

```php
interface ParcelDataSource
{
    public function find(string $externalId): ?ParcelFeature;
    public function context(BoundingBox $bbox, int $limit = 2000): ParcelFeatureCollection;
}
```

Initial implementation:

```php
ConfiguredPgsqlParcelDataSource
```

Responsibilities:

- obtain config from `config('cadastral.parcels.*')`
- quote validated identifiers
- use Laravel `DB::connection(config('database.default'))`
- query the configured table directly
- obtain actual geometry SRID/type from PostGIS
- serialize exact GeoJSON using `ST_AsGeoJSON(..., 9)`
- use `geom && ST_MakeEnvelope(...)` plus `ST_Intersects(...)`
- exclude null/empty geometries
- apply optional configured active predicate
- enforce bounded context result limit

The repository does not know `padron`, `nomenclatura` or any municipal-specific fields.

## Public API v1

Add source-backed endpoints independent of the legacy workspace endpoints:

```text
GET /api/v1/parcels/{externalId}
GET /api/v1/parcels/context?bbox=minX,minY,maxX,maxY&limit=2000
```

`GET /parcels/{externalId}` response:

```json
{
  "external_id": "12345",
  "revision": null,
  "label": "12345",
  "srid": 22182,
  "geometry": {
    "type": "Polygon",
    "coordinates": []
  },
  "properties": {}
}
```

`revision` is populated only when configured. `label` falls back to `external_id`.

Context response is GeoJSON:

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "id": "12345",
      "properties": {
        "external_id": "12345",
        "revision": null,
        "label": "12345"
      },
      "geometry": {}
    }
  ]
}
```

Stable errors:

- `CADASTRAL_DATASOURCE_NOT_CONFIGURED`
- `INVALID_CADASTRAL_IDENTIFIER`
- `PARCEL_NOT_FOUND`
- `INVALID_BBOX`
- `CONTEXT_LIMIT_EXCEEDED`
- `UNSUPPORTED_SOURCE_GEOMETRY`

## SDK additions

Add source-backed client methods without DOM/OpenLayers dependencies:

```ts
getParcel(externalId: string): Promise<ParcelFeatureDto>
getParcelContext(bbox: [number, number, number, number], limit?: number): Promise<FeatureCollectionDto>
```

The SDK remains the stable integration boundary for API errors and DTO types.

## Web Component public contract

Custom element:

```html
<cadastral-digitizer
  api-base-url="/api"
  srid="22182">
</cadastral-digitizer>
```

Programmatic API:

```ts
interface CadastralDigitizerElement extends HTMLElement {
  configure(config: DigitizerConfig): void;
  openParcel(externalId: string): Promise<void>;
  setMode(mode: 'select' | 'modify'): void;
  undo(): void;
  redo(): void;
  cancelDraft(): void;
  getDraft(): DraftGeometry | null;
}
```

Configuration:

```ts
interface DigitizerConfig {
  apiBaseUrl: string;
  srid: number;
  projectionDefinition?: string;
  snapTolerancePx?: number;
  contextBufferMapUnits?: number;
  contextLimit?: number;
  initialCenter?: [number, number];
  initialZoom?: number;
}
```

If the SRID is not built into OpenLayers, `projectionDefinition` is required and registered through proj4.

## Public events

The component dispatches composed+bubbling `CustomEvent`s with serializable details only:

- `digitizer-ready`
- `parcel-selected`
- `draft-changed`
- `mode-changed`
- `snap-changed`
- `digitizer-error`

No event leaks `ol/Feature`, `ol/Map`, sources, interactions or other OpenLayers objects.

## OpenLayers architecture

Keep OpenLayers isolated in a map adapter/controller rather than in the custom element rendering code.

Sources:

- `contextSource`: bounded neighboring parcels; read-only; snapping source
- `selectionSource`: exact selected source parcel
- `draftSource`: editable clone only
- `helperSource`: snap/vertex/error helpers

Interactions:

- `Select` for picking parcel context
- `Modify` for moving/inserting/deleting vertices
- `Snap` against `contextSource` with vertex/edge/intersection enabled

The source parcel is never mutated directly. Entering modify creates a deep geometry clone in `draftSource`.

## Context loading

When a parcel is opened or selected:

1. Fetch exact source geometry by external ID.
2. Fit map to geometry.
3. Expand geometry extent by `contextBufferMapUnits`.
4. Request bounded context endpoint.
5. Load context source.
6. Create draft clone only when entering MODIFY.

Map movement may refresh context using a debounced BBOX request, but never on pointer movement.

## Precision editing v1

Support:

- move vertex with OpenLayers Modify
- insert vertex on segment
- delete vertex through OpenLayers modify deletion condition
- snapping to neighboring vertices
- snapping to edges
- snapping to calculated intersections
- exact X/Y input for selected vertex
- undo/redo
- cancel draft

Undo/redo stores geometry snapshots only at meaningful boundaries:

- `modifystart` captures before state
- `modifyend` pushes one command/snapshot
- exact X/Y apply pushes one command/snapshot

No history entry per pointer movement.

## UI layout

The first version is a compact cadastral drafting surface, not a dashboard:

- narrow vertical tool rail: select, modify, undo, redo, cancel
- map occupies the dominant area
- right inspector shows parcel ID, mode and selected vertex X/Y
- bottom status strip shows cursor X/Y, SRID, snap state and context count

Shadow DOM styles must not depend on host CSS. Expose CSS custom properties for host theming rather than leaking global selectors.

## Testing

### PHP/PostGIS

Use a real PostgreSQL/PostGIS service in CI and create a fixture source table distinct from the internal workspace table. Tests must prove the API reads configured source data directly.

Cases:

- source parcel fetch
- source SRID is preserved
- BBOX excludes out-of-range rows
- null/empty geom excluded
- optional active filter
- revision/label optional mapping
- invalid configured identifier rejected
- unknown external ID gives stable 404

### TypeScript

- SDK URL/error contracts
- custom element registration
- configuration validation
- map adapter source separation
- openParcel loads exact parcel + context
- draft is a clone, source remains immutable
- modify end produces one draft event/history entry
- X/Y edit updates the correct vertex
- undo/redo restores exact geometry snapshots
- no OpenLayers object appears in event details

### E2E boundary

A demo host can instantiate the component and load a fixture parcel. Full transactional persistence is intentionally outside this phase.

## Completion criterion

The phase is complete when a host can configure Laravel to point at its real PostGIS parcel table, embed `<cadastral-digitizer>`, open/select a parcel, edit vertices precisely with snapping/X-Y/undo-redo, and retrieve/observe the resulting draft without copying source parcels into the internal workspace.