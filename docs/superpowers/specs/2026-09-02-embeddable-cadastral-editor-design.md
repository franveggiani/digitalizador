# Embeddable Cadastral Editor — Design Specification

## Purpose

Build an autonomous cadastral geometry editor that can replace the day-to-day parcel digitizing operations normally performed in a desktop GIS while remaining safe to embed in unrelated host systems.

The product is not a generic GIS. It is a constrained cadastral editing engine with explicit domain operations, deterministic validation and an embeddable browser UI.

## Non-goals

- No authentication, users, roles, ACLs or permission model.
- No generic layer editor, print composer, raster analysis, field calculator or plugin system.
- No direct coupling to host-system tables.
- No silent geometry repair.
- No last-write-wins updates.
- No dependence on WMS/vector tiles for authoritative editing coordinates.

## Architecture

Monorepo with three deployable/consumable surfaces:

1. `services/api`: Laravel 13 / PHP 8.3+ REST API. Owns geometry sessions, canonical workspace features, operations, validation, concurrency and history.
2. `packages/sdk`: framework-agnostic TypeScript SDK. Owns API contracts, typed client, editor state machine and integration events.
3. `packages/web-component`: standards-based `<cadastral-digitizer>` custom element using OpenLayers 10.10.x. Owns map interactions, editing UX, snapping, coordinate entry and operation previews.
4. `apps/demo`: reference host application. It must consume the public Web Component/SDK interfaces rather than importing private internals.

PostgreSQL/PostGIS is the geometric source of truth. Browser calculations are previews only.

## Embedding contract

The host system controls whether and where the editor is available. The editor receives no roles or permissions.

Minimal host configuration:

```ts
interface DigitizerConfig {
  apiBaseUrl: string;
  datasetId: string;
  srid: number;
  precisionGrid: number;
  snapTolerancePx?: number;
  display?: {
    initialCenter?: [number, number];
    initialZoom?: number;
  };
}
```

Web Component:

```html
<cadastral-digitizer></cadastral-digitizer>
```

Initialization:

```ts
const editor = document.querySelector('cadastral-digitizer');
editor.configure(config);
```

Public events:

- `digitizer-ready`
- `digitizer-session-started`
- `digitizer-draft-changed`
- `digitizer-validation-changed`
- `digitizer-operation-committed`
- `digitizer-operation-cancelled`
- `digitizer-conflict`
- `digitizer-error`

Events expose operation/session identifiers and result DTOs, never internal OpenLayers objects.

## Host data integration

Host schemas are opaque to the editor. Canonical workspace features use:

- `dataset_id`: logical parcel dataset / jurisdiction.
- `external_id`: stable host identifier, stored as text.
- `revision`: integer optimistic-lock version.
- `geometry`: canonical PostGIS geometry.
- `properties`: optional JSON object with display-only host metadata.

Host systems can upsert canonical parcel snapshots through the API and can consume committed operation results to update their own domain model.

The editor never assumes fields such as `padron`, `nomenclatura`, `parcel_id`, ownership or taxation data.

## Spatial reference and precision

Each dataset has one configured projected cadastral SRID and precision grid. All authoritative operations execute in that SRID.

`precision_grid`, visual snap tolerance and business tolerances are separate concepts. They must not share one setting.

Canonical geometry normalization uses PostGIS precision functions and rejects unsupported geometry types rather than silently coercing them.

Initial supported geometry type: `Polygon` without Z/M. `MultiPolygon` and holes are dataset policy switches added only after real-data audit.

## Core data model

### datasets

- `id` UUID
- `external_key` string unique
- `name` string
- `srid` integer
- `precision_grid` decimal
- `area_tolerance` decimal
- `minimum_area` decimal nullable
- `allow_holes` boolean
- `allow_multipolygon` boolean
- timestamps

### parcels

- `id` UUID
- `dataset_id` UUID
- `external_id` text
- `revision` bigint default 1
- `geom` geometry
- `properties` jsonb
- `active` boolean
- timestamps
- unique (`dataset_id`, `external_id`)
- GiST index on `geom`

### edit_sessions

- `id` UUID
- `dataset_id` UUID
- `operation_type` enum-like string
- `status`: `editing|validating|ready|committed|discarded|conflict`
- timestamps + optional expiry
- `metadata` jsonb

No `user_id` field is required by the core.

### edit_features

- `id` UUID
- `session_id` UUID
- `parcel_id` UUID nullable
- `role`: `source|result|context|helper`
- `original_geom` geometry nullable
- `draft_geom` geometry nullable
- `original_revision` bigint nullable
- `metadata` jsonb

### cadastral_operations

- `id` UUID
- `session_id` UUID
- `dataset_id` UUID
- `type`: `CREATE|MODIFY|SPLIT|MERGE|RESHAPE_BOUNDARY`
- `status`
- `parameters` jsonb
- `validation_result` jsonb
- `created_at`, `committed_at`

### parcel_geometry_history

- `id` UUID
- `parcel_id` UUID nullable
- `external_id` text
- `operation_id` UUID
- `geom_before` geometry nullable
- `geom_after` geometry nullable
- `properties_before/after` jsonb
- `revision_before/after` bigint nullable
- `created_at`

### parcel_lineage

- `operation_id`
- `source_external_id`
- `result_external_id`
- `relationship`: `split_from|merged_from|replaces`

## API surface

Version all public endpoints under `/api/v1`.

### Datasets and workspace synchronization

- `PUT /datasets/{externalKey}` create/update dataset geometry policy.
- `PUT /datasets/{externalKey}/parcels/{externalId}` upsert authoritative host parcel snapshot with expected revision semantics.
- `GET /datasets/{externalKey}/parcels/{externalId}` fetch precise canonical feature.
- `GET /datasets/{externalKey}/context?bbox=minx,miny,maxx,maxy` fetch bounded vector editing context.

### Sessions

- `POST /datasets/{externalKey}/sessions`
- `GET /sessions/{sessionId}`
- `PUT /sessions/{sessionId}/draft`
- `POST /sessions/{sessionId}/validate`
- `POST /sessions/{sessionId}/commit`
- `DELETE /sessions/{sessionId}` discard

### Domain operations

Operation intent lives in the session payload. Initial vertical slice supports `MODIFY`; later endpoints/payloads add `SPLIT`, `MERGE`, and `RESHAPE_BOUNDARY` without changing session lifecycle.

## Session lifecycle

1. Host/editor selects parcel and creates edit session with source external IDs + expected revisions.
2. API snapshots source geometry into `edit_features`.
3. Browser modifies only draft state.
4. Meaningful edit events persist draft; pointer movements do not.
5. Validation executes server-side against canonical geometry and neighboring parcels.
6. Commit starts DB transaction, locks affected rows in deterministic ID order, verifies revisions, recalculates/normalizes output, validates again, writes history/lineage, updates canonical parcels, commits transaction.
7. Revision mismatch returns HTTP `409` with machine code `REVISION_CONFLICT`.

## Validation pipeline

Every operation passes the same stages:

1. request/schema validation
2. dataset/SRID validation
3. geometry normalization to dataset precision
4. geometry type policy
5. `ST_IsValid` / detailed validity reason
6. minimum area / holes / multipart policy
7. topology checks against active neighbors
8. operation-specific invariants
9. revision/concurrency checks

Validation response:

```json
{
  "valid": false,
  "errors": [
    {
      "code": "SELF_INTERSECTION",
      "message": "The parcel contains a self-intersection.",
      "coordinate": [2534231.21, 6364829.13],
      "details": {}
    }
  ],
  "warnings": []
}
```

Machine codes are stable public API. Human messages may evolve.

## Modify operation

MVP behavior:

- select one parcel
- create session snapshot
- move, insert and delete vertices
- snap to configured context vertex/edge/intersection
- exact X/Y editing
- undo/redo in browser
- persist draft
- server validation
- before/after review
- commit
- revision increment
- geometry history entry

The client cannot commit a geometry whose source revision changed after session start.

## Split operation

First supported split is exactly 1 source parcel to 2 result polygons using one cut line.

PostGIS computes the result with `ST_Split`; output is polygon-extracted and checked with spatial invariants. Reject tangential/non-crossing cuts, >2 polygons, invalid results, disallowed slivers or geometry policy violations.

Conservation invariant is geometric, not area-only:

`ST_Area(ST_SymDifference(original, ST_Union(results))) <= area_tolerance`.

## Merge operation

Supports N contiguous source parcels to one polygon. Sources must form one connected component through shared boundary segments of meaningful length; corner-only contact is insufficient. `ST_Union` calculates output. Reject unexpected `MultiPolygon` when disallowed.

## Shared-boundary reshape

The user edits the shared boundary, not two independent parcel polygons. Compute the original union envelope, split it with the replacement boundary, map the two outputs back to the two source parcels, and enforce exact coverage conservation within configured tolerance.

No part of the outer envelope may change.

## Frontend state machine

Exactly one primary mode:

`IDLE | SELECT | MODIFY | SPLIT | MERGE | RESHAPE_BOUNDARY | COORDINATE_INPUT | REVIEW | VALIDATING | COMMITTING`

State transitions are explicit and testable. Avoid intersecting boolean flags.

OpenLayers sources are separated by authority/purpose:

- display layer: non-authoritative basemap/tiles
- context vector source: nearby canonical geometry for interaction
- editing source: source/draft parcel(s)
- helper source: construction lines
- validation source: error coordinates/features

## UI design direction

Audience: cadastral technicians working for long sessions. Primary job: make geometrically exact changes with immediate confidence about what is authoritative, snapped, invalid or pending.

Visual language: technical drafting board rather than generic SaaS dashboard. Dense map-first workspace, narrow tool rail, coordinate/operation inspector, explicit status strip. Neutral surfaces with restrained cadastral drafting accents. No decorative cards around every control.

Signature element: a persistent coordinate/status instrument that displays cursor coordinate, snapped coordinate/source, selected vertex index and current operation state as one compact drafting readout.

Keyboard accessibility and visible focus are required. Editing remains possible with click/tap controls; keyboard shortcuts are enhancements.

## SDK boundaries

The SDK owns:

- public DTOs
- API client
- state-machine types/reducer
- typed custom-event payloads
- geometry serialization boundaries

It must not import OpenLayers or DOM APIs.

The Web Component may depend on SDK + OpenLayers. Demo may depend only on public package exports.

## Error model

Public error envelope:

```json
{
  "error": {
    "code": "REVISION_CONFLICT",
    "message": "The parcel changed after this editing session started.",
    "details": {}
  }
}
```

HTTP mapping:

- 400 malformed geometry/request
- 404 dataset/parcel/session not found
- 409 revision or session-state conflict
- 422 geometric/business validation rejection
- 500 unexpected failure with correlation identifier

## Observability

Structured logs include `correlation_id`, `dataset_id`, `session_id`, `operation_id`, operation type, source external IDs, validation duration and commit duration. Never require identity information from the host.

## Testing strategy

### Backend

Use PostgreSQL/PostGIS integration tests for all geometry semantics. Do not emulate geometry operations in SQLite.

Test fixtures include rectangles, diagonal cuts, tangential cuts, corner touching, shared edges, self-intersection, near-grid coordinates, holes and multipart candidates.

### SDK

Vitest tests state transitions, DTO parsing and API error mapping.

### Web Component

Vitest/browser-level tests cover configuration, event contracts and state-to-UI behavior. Playwright covers primary map flows.

### Contract

OpenAPI document under `docs/openapi.yaml` is versioned with the implementation. Demo integration is a compatibility test: if public imports/events break, CI fails.

## Deployment

`docker compose up` must provide PostGIS, API and demo development surfaces. Production packaging exposes the JS packages independently and an API container image.

No host system must share the editor database or Laravel runtime.

## Delivery order

1. monorepo/tooling + PostGIS/API health
2. dataset + canonical parcel workspace
3. sessions + validation + optimistic locking/history
4. SDK contracts/client/state machine
5. Web Component map shell + context loading
6. Modify vertical slice
7. Split 1→2
8. Merge N→1
9. Shared-boundary reshape
10. CAD construction helpers and advanced tracing

## Acceptance for first production-capable milestone

A host can embed the Web Component, configure API/dataset/SRID, load a canonical parcel, create an edit session, move/add/delete vertices with snap, edit exact X/Y, undo/redo, recover a persisted draft, receive authoritative validation, preview before/after, commit transactionally, observe revision increment/history, and receive a stable `digitizer-operation-committed` event. A stale revision must be rejected with 409.