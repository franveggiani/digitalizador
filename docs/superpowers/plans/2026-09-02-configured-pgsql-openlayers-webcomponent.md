# Configured PGSQL + OpenLayers Web Component v1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Read parcels directly from Laravel's configured PostGIS connection and deliver an embeddable OpenLayers Web Component that can select and precisely modify parcel drafts with snap, X/Y editing and undo/redo.

**Architecture:** Laravel encapsulates configurable source table/column mapping behind `ParcelDataSource`; HTTP clients never control identifiers or SQL. The framework-agnostic SDK exposes DTO/client methods, while OpenLayers lives only inside `packages/web-component` and edits cloned drafts rather than source features.

**Tech Stack:** PHP 8.3+, Laravel 13, PostgreSQL 16 + PostGIS 3.5, TypeScript 5.9, pnpm 10, Lit 3, OpenLayers 10.10.x, proj4, Vitest, jsdom, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-02-configured-pgsql-openlayers-webcomponent-design.md`

## Global Constraints

- No authentication, users, roles, ACLs or permission system.
- Use Laravel's configured PostgreSQL connection.
- Configured table/column identifiers never come from HTTP requests.
- No arbitrary SQL configuration.
- Browser never receives database credentials.
- PostGIS source geometry is authoritative; drafts are browser clones.
- SDK cannot import DOM or OpenLayers.
- Web Component is framework-agnostic and uses Shadow DOM.
- This phase does not commit edits to the source table.

---

### Task 1: Finish authoritative geometry validator baseline

**Files:**
- Create: `services/api/app/Domain/Cadastre/Geometry/GeometryValidator.php`
- Create: `services/api/app/Domain/Cadastre/Geometry/ValidationResult.php`
- Existing test: `services/api/tests/Integration/GeometryValidatorTest.php`

**Interfaces:** Produces `GeometryValidator::validate(Dataset $dataset, int $srid, array $geometry): ValidationResult` for later server-side operations.

- [ ] Restore the already-prepared implementation on top of the current branch.
- [ ] Run PHPUnit against PostGIS and verify the four RED validator tests become GREEN without regressions.
- [ ] Commit `feat(api): add authoritative PostGIS geometry validator`.

### Task 2: Configured PostgreSQL parcel datasource

**Files:**
- Create: `services/api/config/cadastral.php`
- Modify: `services/api/.env.example`
- Create: `services/api/app/Domain/Cadastre/Source/ParcelDataSource.php`
- Create: `services/api/app/Domain/Cadastre/Source/ConfiguredPgsqlParcelDataSource.php`
- Create: `services/api/app/Domain/Cadastre/Source/ParcelFeature.php`
- Create: `services/api/app/Providers/CadastralServiceProvider.php`
- Modify: `services/api/bootstrap/providers.php`
- Test: `services/api/tests/Feature/ConfiguredParcelSourceTest.php`

**Interfaces:**
- Produces `ParcelDataSource::find(string $externalId): ?ParcelFeature`.
- Produces `ParcelDataSource::context(BoundingBox $bbox, int $limit = 2000): array` returning feature DTOs.

- [ ] Write failing tests that create `source_parcels` with PostGIS geometry and override `cadastral.parcels.*` config.
- [ ] Verify RED because datasource classes do not exist.
- [ ] Implement strict identifier validation for `schema.table` and column names.
- [ ] Query through Laravel's configured default connection using parameter binding for values and validated quoted identifiers for names.
- [ ] Apply null/empty geometry exclusion and optional active predicate.
- [ ] Verify source SRID, revision and label mapping.
- [ ] Run PHPUnit and commit `feat(api): add configured PostGIS parcel datasource`.

### Task 3: Source parcel REST endpoints

**Files:**
- Create: `services/api/app/Http/Controllers/Api/V1/SourceParcelController.php`
- Create: `services/api/app/Http/Controllers/Api/V1/SourceParcelContextController.php`
- Modify: `services/api/routes/api.php`
- Test: `services/api/tests/Feature/ConfiguredParcelSourceApiTest.php`
- Modify: `docs/openapi.yaml`

**Interfaces:**
- `GET /api/v1/parcels/{externalId}`
- `GET /api/v1/parcels/context?bbox=minx,miny,maxx,maxy&limit=N`

- [ ] Write failing API tests for precise fetch, 404, BBOX filtering, malformed BBOX and context limit.
- [ ] Implement controllers using only `ParcelDataSource`.
- [ ] Return GeoJSON FeatureCollection for context.
- [ ] Keep stable public error codes from the spec.
- [ ] Document endpoints in OpenAPI.
- [ ] Run PHPUnit and commit `feat(api): expose configured parcel source API`.

### Task 4: SDK source parcel contracts

**Files:**
- Modify: `packages/sdk/src/client.ts`
- Create: `packages/sdk/src/types.ts`
- Modify: `packages/sdk/src/index.ts`
- Modify: `packages/sdk/test/client.test.ts`

**Interfaces:**
- `DigitizerClient.getParcel(externalId: string): Promise<ParcelFeatureDto>`
- `DigitizerClient.getParcelContext(bbox: Bbox, limit?: number): Promise<FeatureCollectionDto>`

- [ ] Write RED tests for route escaping, BBOX query encoding, optional limit and error mapping.
- [ ] Add DOM/OpenLayers-free DTOs.
- [ ] Implement client methods on the existing fetch abstraction.
- [ ] Run Vitest/build and commit `feat(sdk): add configured parcel source client`.

### Task 5: Web Component package and OpenLayers map shell

**Files:**
- Create: `packages/web-component/package.json`
- Create: `packages/web-component/tsconfig.json`
- Create: `packages/web-component/tsconfig.build.json`
- Create: `packages/web-component/src/index.ts`
- Create: `packages/web-component/src/cadastral-digitizer.ts`
- Create: `packages/web-component/src/map/CadastralMapAdapter.ts`
- Create: `packages/web-component/src/config.ts`
- Create: `packages/web-component/src/styles.ts`
- Test: `packages/web-component/test/component.test.ts`
- Test: `packages/web-component/test/config.test.ts`

**Interfaces:**
- Registers `<cadastral-digitizer>`.
- `configure(config: DigitizerConfig): void`.
- `openParcel(externalId: string): Promise<void>`.

- [ ] Write RED tests for registration/config validation and ready event.
- [ ] Add dependencies `lit`, `ol`, `proj4`, and workspace SDK.
- [ ] Implement Shadow DOM drafting layout and config normalization.
- [ ] Implement map adapter with separate context/selection/draft/helper vector sources.
- [ ] Register non-built-in CRS through proj4 when `projectionDefinition` is supplied.
- [ ] Dispatch `digitizer-ready` with serializable detail.
- [ ] Run Vitest/build and commit `feat(web): add embeddable OpenLayers map shell`.

### Task 6: Parcel loading, selection and bounded context

**Files:**
- Modify: `packages/web-component/src/cadastral-digitizer.ts`
- Modify: `packages/web-component/src/map/CadastralMapAdapter.ts`
- Create: `packages/web-component/src/editor/EditorController.ts`
- Test: `packages/web-component/test/editor-controller.test.ts`

**Interfaces:** `openParcel()` loads exact source + context; `parcel-selected` event detail contains only DTO data.

- [ ] Write RED tests that mock SDK methods and assert exact parcel/context calls.
- [ ] Implement geometry extent fit and context extent buffering.
- [ ] Parse GeoJSON in configured projection without transforming authoritative coordinates.
- [ ] Add OpenLayers Select interaction for context parcels.
- [ ] On selection fetch exact parcel by external ID before exposing it as selected.
- [ ] Dispatch serializable `parcel-selected`.
- [ ] Run tests/build and commit `feat(web): load and select configured source parcels`.

### Task 7: MODIFY interaction and immutable draft

**Files:**
- Create: `packages/web-component/src/editor/GeometryHistory.ts`
- Modify: `packages/web-component/src/editor/EditorController.ts`
- Modify: `packages/web-component/src/map/CadastralMapAdapter.ts`
- Modify: `packages/web-component/src/cadastral-digitizer.ts`
- Test: `packages/web-component/test/geometry-history.test.ts`
- Test: `packages/web-component/test/modify.test.ts`

**Interfaces:**
- `setMode('select'|'modify')`.
- `getDraft(): DraftGeometry | null`.
- `undo()`, `redo()`, `cancelDraft()`.

- [ ] Write RED tests proving draft modification never mutates selected source geometry.
- [ ] Implement deep-cloned draft feature when entering modify.
- [ ] Add OpenLayers Modify with vertex insertion/deletion support.
- [ ] Add Snap against context source with `vertex:true`, `edge:true`, `intersection:true`.
- [ ] Capture one history snapshot per modify end, not pointer movement.
- [ ] Dispatch serializable `draft-changed` and `mode-changed` events.
- [ ] Implement undo/redo/cancel.
- [ ] Run tests/build and commit `feat(web): add precise parcel modify draft`.

### Task 8: Exact X/Y vertex editing and instrument UI

**Files:**
- Create: `packages/web-component/src/editor/VertexEditor.ts`
- Modify: `packages/web-component/src/cadastral-digitizer.ts`
- Modify: `packages/web-component/src/styles.ts`
- Test: `packages/web-component/test/vertex-editor.test.ts`

**Interfaces:** selected vertex index/ring + X/Y inputs update draft through one history command.

- [ ] Write deterministic RED tests for exterior-ring vertex replacement and closure coordinate synchronization.
- [ ] Implement exact coordinate replacement without floating conversion beyond JS number semantics.
- [ ] Wire inspector X/Y form to selected vertex.
- [ ] Keep polygon first/last ring coordinate synchronized when editing the closing vertex.
- [ ] Push one undo snapshot and emit one draft change per Apply.
- [ ] Add bottom status strip for cursor X/Y, SRID, snap state and context count.
- [ ] Run tests/build and commit `feat(web): add exact cadastral vertex coordinates`.

### Task 9: Integration example and verification

**Files:**
- Create/modify: `apps/demo/*`
- Modify: `README.md`
- Modify: `.github/workflows/ci.yml` if jsdom/build dependencies require explicit setup.

**Interfaces:** demo consumes only public SDK/Web Component exports.

- [ ] Add demo fixture configuration pointing at `/api` and instantiate `<cadastral-digitizer>` through its public API.
- [ ] Document Laravel `.env` table mapping and HTML/Blade embedding example.
- [ ] Run full TypeScript test/build suite.
- [ ] Run full PHPUnit/PostGIS suite from clean DB.
- [ ] Verify no Web Component event exposes OpenLayers objects.
- [ ] Verify no auth/permission subsystem was introduced.
- [ ] Commit `docs: add first digitizer embedding example`.

## Verification gate

```bash
pnpm install --no-frozen-lockfile
pnpm test
pnpm build
cd services/api && composer install && vendor/bin/phpunit
```

CI must be GREEN for TypeScript and PHP/PostGIS. The host integration smoke test is successful when a configured source table row can be opened and edited in the Web Component without first upserting that row into the internal `parcels` workspace.