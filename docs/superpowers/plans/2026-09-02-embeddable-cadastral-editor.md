# Embeddable Cadastral Editor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver an autonomous PostGIS-backed cadastral editor exposed as a framework-agnostic SDK and Web Component, with a complete transactional parcel-modification vertical slice and extension points for split, merge and shared-boundary reshape.

**Architecture:** A monorepo separates the Laravel API, pure TypeScript SDK, OpenLayers Web Component and demo host. PostgreSQL/PostGIS is authoritative; the browser only edits drafts and sends operation intent. Host systems remain opaque and integrate through dataset/external IDs and public API/events.

**Tech Stack:** PHP 8.3+, Laravel 13, PostgreSQL 16 + PostGIS, TypeScript 5.x, pnpm, Vite, Vitest, OpenLayers 10.10.x, Lit, Playwright, Docker Compose, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-02-embeddable-cadastral-editor-design.md`

## Global Constraints

- No authentication, users, roles, ACLs or permission system.
- Never couple API code to host-system cadastral table schemas.
- PostGIS is authoritative for validation and commit geometry.
- Public SDK must not import OpenLayers or DOM APIs.
- Web Component is the primary embedding surface.
- First geometry type is projected 2D Polygon; precision is dataset configuration.
- No silent `ST_MakeValid` during normal edit/commit flows.
- Revision conflicts return HTTP 409 and never overwrite silently.

---

### Task 1: Monorepo and executable development environment

**Files:** root `package.json`, `pnpm-workspace.yaml`, `.gitignore`, `.editorconfig`, `docker-compose.yml`, `Makefile`, `.env.example`, `.github/workflows/ci.yml`; package manifests under `packages/*` and `apps/demo`; API skeleton under `services/api`.

**Interfaces:** Produces repeatable commands `pnpm test`, `pnpm build`, `docker compose up`, API health endpoint `/api/v1/health`.

- [ ] Write smoke-test/CI expectations before implementation: SDK test command must fail until package exists; API feature test expects `{status:"ok"}`.
- [ ] Scaffold workspace manifests and Laravel 13 application dependencies.
- [ ] Add PostGIS-backed Docker Compose with healthchecks and API environment wiring.
- [ ] Add API `/api/v1/health` route + feature test.
- [ ] Add CI jobs for TypeScript and PHP/PostGIS.
- [ ] Run package/API tests and commit `chore: scaffold embeddable digitizer monorepo`.

### Task 2: Dataset and canonical parcel workspace

**Files:** API migrations/models/controllers/requests/services for `datasets` and `parcels`; integration tests; `docs/openapi.yaml`.

**Interfaces:** `PUT /api/v1/datasets/{externalKey}`, `PUT|GET /api/v1/datasets/{externalKey}/parcels/{externalId}`, `GET .../context?bbox=`.

- [ ] Write PostGIS integration tests for dataset policy creation, parcel upsert, precise fetch and bounded context.
- [ ] Verify tests fail because tables/endpoints do not exist.
- [ ] Add migrations with geometry column, unique external key/id constraints and GiST index.
- [ ] Implement requests/controllers/repositories with no host-domain assumptions.
- [ ] Reject SRID/type mismatch explicitly.
- [ ] Add OpenAPI contracts and run tests.
- [ ] Commit `feat(api): add cadastral dataset workspace`.

### Task 3: Geometry validation engine

**Files:** `services/api/app/Domain/Cadastre/Geometry/*`, validation DTOs/errors, integration tests.

**Interfaces:** `GeometryValidator::validate(Dataset $dataset, GeometryInput $geometry, ValidationContext $context): ValidationResult`.

- [ ] Write failing tests for valid polygon, self-intersection, empty geometry, wrong SRID, hole policy, multipart policy and precision reduction.
- [ ] Verify RED against PostGIS.
- [ ] Implement normalization/validation SQL using `ST_ReducePrecision`, `ST_IsValid`, validity detail, geometry type and policy checks.
- [ ] Return stable machine codes + optional coordinate.
- [ ] Verify GREEN and commit `feat(api): add authoritative geometry validation`.

### Task 4: Editing sessions, draft persistence and optimistic locking

**Files:** session/edit-feature/operation/history migrations/models; session service/controller; tests.

**Interfaces:** create/get/update-draft/validate/commit/discard session endpoints.

- [ ] Write failing tests for source snapshot, persisted draft, invalid state transition, successful modify commit and stale revision conflict.
- [ ] Add session schema and explicit status enum/value object.
- [ ] Snapshot source geometry/revision when session starts.
- [ ] Persist drafts independently from canonical parcel geometry.
- [ ] Implement validation endpoint through GeometryValidator.
- [ ] Implement transactional commit: deterministic `FOR UPDATE`, revision check, revalidation, history insert, parcel update and revision increment.
- [ ] Return 409 `REVISION_CONFLICT` on stale source.
- [ ] Run full API suite and commit `feat(api): add transactional edit sessions`.

### Task 5: Framework-agnostic SDK

**Files:** `packages/sdk/src/{types,client,state,events,errors}.ts`, package exports, Vitest tests.

**Interfaces:** `DigitizerClient`, `EditorState`, `editorReducer`, event payload types, public DTOs matching OpenAPI.

- [ ] Write failing tests for URL construction, API error mapping, editor state transitions and illegal transitions.
- [ ] Implement DTOs independent of DOM/OpenLayers.
- [ ] Implement fetch-based API client with injectable fetch for tests.
- [ ] Implement finite-state reducer for IDLE/SELECT/MODIFY/... modes.
- [ ] Export stable public surface and run tests/build.
- [ ] Commit `feat(sdk): add typed digitizer client and state machine`.

### Task 6: Web Component map shell

**Files:** `packages/web-component/src/*`, tests, styles.

**Interfaces:** custom element `<cadastral-digitizer>`, method `configure(config)`, public custom events from spec.

- [ ] Write failing tests for registration, configure-before-ready behavior, ready event and invalid configuration.
- [ ] Implement Lit custom element with Shadow DOM and OpenLayers map adapter isolated from UI component.
- [ ] Register cadastral projection from supplied SRID/projection definition boundary.
- [ ] Build map-first drafting layout: tool rail, map, inspector, validation/status instrument.
- [ ] Keep display/context/edit/helper/error vector sources separate.
- [ ] Run component tests/build and commit `feat(web): add embeddable cadastral map shell`.

### Task 7: Modify vertical slice

**Files:** map interactions, editor controller, vertex inspector, SDK/session integration, API tests, Playwright flow.

**Interfaces:** select → session → modify → draft → validate → review → commit.

- [ ] Write failing unit/E2E tests for selecting parcel, entering MODIFY, draft change event, exact coordinate edit, undo/redo, cancel, validation and commit event.
- [ ] Implement OpenLayers Select/Modify/Snap with vertex/edge/intersection snapping only against authoritative context source.
- [ ] Add exact X/Y vertex inspector and selected-vertex instrument.
- [ ] Add command-based undo/redo snapshots at meaningful edit boundaries.
- [ ] Persist draft on modify-end/coordinate apply, never pointermove.
- [ ] Add server validation panel and map markers for coordinate-bearing errors.
- [ ] Add before/after review and transactional commit.
- [ ] Restore active session/draft after component reload when session ID is supplied by host.
- [ ] Run all suites and commit `feat: complete parcel modify workflow`.

### Task 8: Split 1→2

**Files:** API split operation class/tests; SDK DTO; Web Component interaction/E2E.

**Interfaces:** one source polygon + one cutting LineString → exactly two result polygons.

- [ ] Write failing PostGIS cases: crossing cut, outside cut, tangent, cut through vertex, >2 pieces, conservation failure.
- [ ] Implement `ST_Split` operation and polygon extraction.
- [ ] Enforce result count=2 and sym-difference conservation tolerance.
- [ ] Add draw-line preview/validation/review to Web Component.
- [ ] Add lineage/history results.
- [ ] Run suites and commit `feat: add cadastral parcel split`.

### Task 9: Merge N→1

**Files:** API merge operation/tests; SDK DTO; selection interaction/E2E.

**Interfaces:** contiguous source parcel IDs → one canonical Polygon.

- [ ] Write failing tests for edge-adjacent sources, corner-only touching, disconnected sources, three-parcel chain and disallowed MultiPolygon.
- [ ] Implement connectivity through positive-length shared boundary graph.
- [ ] Compute output with precision-aware `ST_Union` and validate.
- [ ] Write history/lineage and retire/replace source external IDs according to operation result contract.
- [ ] Implement multi-select preview and host-provided result external ID input contract.
- [ ] Run suites and commit `feat: add cadastral parcel merge`.

### Task 10: Shared-boundary reshape

**Files:** API operation/tests; boundary editor interaction/E2E.

**Interfaces:** exactly two edge-adjacent parcels + replacement boundary → two parcels preserving outer union.

- [ ] Write failing tests for normal reshape, outer-envelope modification, boundary not crossing envelope, third-parcel invasion and conservation failure.
- [ ] Extract/edit shared boundary; split original union with replacement boundary.
- [ ] Map outputs back to sources by maximal overlap with originals.
- [ ] Enforce unchanged union through sym-difference tolerance.
- [ ] Implement dedicated shared-boundary UI that never exposes independent whole-polygon modification.
- [ ] Run suites and commit `feat: add shared cadastral boundary reshape`.

### Task 11: CAD construction helpers and tracing

**Files:** SDK geometry helper contracts, Web Component tools/tests.

**Interfaces:** coordinate, delta, distance+azimuth, perpendicular/intersection helper operations; OpenLayers trace behavior.

- [ ] Write deterministic math tests in projected units.
- [ ] Implement helper calculations as pure TypeScript functions, with server validation still authoritative.
- [ ] Add trace against context features and construction helper source.
- [ ] Add keyboard-accessible numeric forms.
- [ ] Run tests and commit `feat(web): add cadastral construction tools`.

### Task 12: Hardening, packaging and integration documentation

**Files:** README, integration guides/examples for HTML/React/Vue/Laravel Blade, changelog/versioning docs, CI/release config.

**Interfaces:** package exports and deployment artifacts are the compatibility boundary.

- [ ] Add contract tests ensuring demo imports only public package exports.
- [ ] Add examples that use Web Component through standards APIs, not framework internals.
- [ ] Add structured correlation IDs/logging and error-envelope consistency tests.
- [ ] Add production Dockerfiles and health/readiness checks.
- [ ] Run PHP tests, TS tests, builds, E2E and migration-from-clean-db verification.
- [ ] Review OpenAPI/public events for accidental auth/host-schema coupling.
- [ ] Commit `docs: finalize embeddable digitizer integration contract`.

## Verification gate

Before merge, all must pass from a clean checkout:

```bash
pnpm install --frozen-lockfile
pnpm test
pnpm build
docker compose up -d db
# API dependencies/migrations according to services/api README
php artisan test
pnpm --filter @digitalizador/demo test:e2e
```

Additionally inspect DB invariants for successful and rejected operations and verify that no source code defines users, roles, permissions, policies or authentication middleware.