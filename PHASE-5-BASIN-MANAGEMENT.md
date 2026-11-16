# Phase 5 — Basin and Sub-basin Management

Delivered module: basin registry, sub-basin hierarchy, boundary import, documents, activity history and the authorization that guards each operation.

Phase 4 (dashboard and layout) remains intentionally deferred at the user's request; the Phase 1 shell and dashboard are still in place and were not rebuilt.

---

## 1. What was built

| Area | Delivered |
|---|---|
| Basin listing | Search, status/boundary/record filters, sortable columns, pagination, CSV export, view/edit/delete actions |
| Basin create/edit | Validated form with identity, geography, provenance and status sections |
| Basin detail | Seven tabs: Overview, Sub-basins, Monitoring stations, Hydrological data, Geographic information, Documents, Activity history |
| Sub-basin management | Listing with parent-basin filter, create, edit (including reassignment to another basin), delete, detail page with stations, boundary and documents |
| Boundary import | GeoJSON → WKT conversion, WGS84 range checks, SRID 4326 storage, primary-version handling, boundary removal |
| Documents | Private-disk upload, permission-checked download, removal, audit entries |
| Authorization | `BasinPolicy`, `SubBasinPolicy`, route middleware and form-request authorization on every write |
| Audit | Append-only `activity_logs` entries for creates, updates, deletes, boundary imports/removals and document changes |

No hydrological value, area, centroid or polygon was invented. Basins seeded in Phase 3 remain `pending` until verified geography is supplied.

---

## 2. Schema additions

Two migrations were required:

### `2026_11_08_0200_add_elevation_and_notes_to_geographic_tables.php`

| Table | Column | Type | Notes |
|---|---|---|---|
| `basins` | `elevation_m` | `decimal(8,2)` nullable | Mean elevation, never a station reading |
| `sub_basins` | `elevation_m` | `decimal(8,2)` nullable | Same rule |
| `sub_basins` | `source_note` | `text` nullable | Parity with `basins.source_note` |

### `2026_11_08_0201_create_documents_table.php`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `documentable_type`, `documentable_id` | morphs | Basin and sub-basin today, stations later |
| `title` | string(180) | Defaults to the file name stem |
| `original_name` | string(255) | Name shown on download |
| `file_path` | string(500) | Relative path on the private `local` disk |
| `mime_type` | string(150) nullable | Client-reported type |
| `size_bytes` | unsigned bigint | Display only |
| `uploaded_by` | FK users, null on delete | |
| `timestamps`, `deleted_at` | | Soft deletes keep the audit trail coherent |

Existing Phase 3 tables were not restructured. `basins.boundary` remains the simplified display geometry; `basin_boundaries` remains the authoritative version store with the spatial index.

---

## 3. Model layer

| Model | Change |
|---|---|
| `Basin` | `HasFactory`, `elevation_m` cast/fillable, `documents()` morphMany, `activityLogs()` morphMany |
| `SubBasin` | Same, plus `source_note` |
| `Document` | New: `documentable()` morphTo, `uploader()` belongsTo, soft deletes |

No existing relationship was renamed, so Phase 3 code and tests continue to work.

---

## 4. Services

| Service | Responsibility |
|---|---|
| `App\Support\GeoJson\GeoJsonConverter` | Parses `Polygon`, `MultiPolygon`, single-feature `Feature`/`FeatureCollection`; rejects unclosed rings, non-numeric or out-of-range coordinates, unsupported geometry types and more than 20,000 positions; returns WKT + bbox |
| `App\Services\Geography\BoundaryService` | Transactional import for basins (version row + display geometry + de-primary of the previous version) and sub-basins, plus boundary removal |
| `App\Services\Geography\BasinService` | Create/update with code normalisation and change logging; delete guard for geographic children |
| `App\Services\Geography\SubBasinService` | Create/update including parent reassignment; delete guard for stations |
| `App\Services\Documents\DocumentService` | Stores files under `documents/{type}/{id}`, writes audit entries, removes file and row together |
| `App\Services\Audit\ActivityLogger` | Central append-only audit writer with explicit or request-scoped actor |

---

## 5. Endpoints

All routes live inside the `auth` group and additionally require a Spatie permission.

### Basins

| Method | URI | Name | Permission |
|---|---|---|---|
| GET | `/basins` | `basins.index` | `view basins` |
| GET | `/basins/export` | `basins.export` | `view basins` |
| GET | `/basins/create` | `basins.create` | `create basins` |
| POST | `/basins` | `basins.store` | `create basins` |
| GET | `/basins/{basin}` | `basins.show` | `view basins` |
| GET | `/basins/{basin}/edit` | `basins.edit` | `edit basins` |
| PATCH | `/basins/{basin}` | `basins.update` | `edit basins` |
| DELETE | `/basins/{basin}` | `basins.destroy` | `delete basins` |
| POST | `/basins/{basin}/boundary` | `basins.boundary.store` | `import basin boundaries` |
| DELETE | `/basins/{basin}/boundary/{boundary}` | `basins.boundary.destroy` | `import basin boundaries` |
| POST | `/basins/{basin}/documents` | `basins.documents.store` | `edit basins` |
| GET | `/basins/{basin}/documents/{document}` | `basins.documents.download` | `view basins` |
| DELETE | `/basins/{basin}/documents/{document}` | `basins.documents.destroy` | `edit basins` |

### Sub-basins

| Method | URI | Name | Permission |
|---|---|---|---|
| GET | `/sub-basins` | `sub-basins.index` | `view sub-basins` |
| GET | `/sub-basins/export` | `sub-basins.export` | `view sub-basins` |
| GET | `/sub-basins/create` | `sub-basins.create` | `create sub-basins` |
| POST | `/sub-basins` | `sub-basins.store` | `create sub-basins` |
| GET | `/sub-basins/{subBasin}` | `sub-basins.show` | `view sub-basins` |
| GET | `/sub-basins/{subBasin}/edit` | `sub-basins.edit` | `edit sub-basins` |
| PATCH | `/sub-basins/{subBasin}` | `sub-basins.update` | `edit sub-basins` |
| DELETE | `/sub-basins/{subBasin}` | `sub-basins.destroy` | `delete sub-basins` |
| POST | `/sub-basins/{subBasin}/boundary` | `sub-basins.boundary.store` | `import basin boundaries` |
| DELETE | `/sub-basins/{subBasin}/boundary` | `sub-basins.boundary.destroy` | `import basin boundaries` |
| POST | `/sub-basins/{subBasin}/documents` | `sub-basins.documents.store` | `edit sub-basins` |
| GET | `/sub-basins/{subBasin}/documents/{document}` | `sub-basins.documents.download` | `view sub-basins` |
| DELETE | `/sub-basins/{subBasin}/documents/{document}` | `sub-basins.documents.destroy` | `edit sub-basins` |

---

## 6. Authorization matrix

Route middleware stops the request first; the controller repeats the decision through the policy. Hiding a button in React is never the control.

| Operation | Super Admin | System Admin | Basin Manager | GIS Officer | Data Manager | Data Analyst | Viewer |
|---|---|---|---|---|---|---|---|
| View basins / sub-basins | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Create basin | ✅ | — | ✅ | — | — | — | — |
| Edit basin | ✅ | — | ✅ | — | — | — | — |
| Delete basin | ✅ | — | ✅ | — | — | — | — |
| Import basin boundary | ✅ | — | ✅ | ✅ | — | — | — |
| Create/edit/delete sub-basin | ✅ | — | ✅ | — | — | — | — |
| Import sub-basin boundary | ✅ | — | ✅ | ✅ | — | — | — |
| Upload/remove basin documents | ✅ | — | ✅ | — | — | — | — |

`Gate::before` still grants the Super Admin bypass; every other role is resolved through Spatie permissions.

### Policy methods

`BasinPolicy` / `SubBasinPolicy`: `viewAny`, `view`, `create`, `update`, `delete`, `importBoundary`, `manageDocuments`, `export`.

`importBoundary` is deliberately separate from `update`: a GIS Officer can publish polygons without being able to change the registry record.

---

## 7. Validation and duplicate protection

| Rule | Behaviour |
|---|---|
| Basin code | Required, ≤20 chars, letters/numbers/`.`/`-`/`_`/`/`, normalised to upper case, unique against **live and archived** rows |
| Sub-basin code | Same, ≤30 chars, unique across every basin |
| Parent basin | Required for sub-basins, must exist and must not be soft-deleted |
| Coordinates | Latitude −90…90, longitude −180…180; both or neither |
| Area / elevation | Numeric, non-negative area, elevation −500…9,000 m |
| `verified` status | Refused unless area, latitude and longitude are present |
| Boundary file | ≤20 MB, `.geojson`/`.json`/`.txt`, one Polygon or MultiPolygon, closed rings, ≤20,000 positions |
| Documents | ≤10 MB, allow-listed extensions, stored privately |

Duplicate strategy is unchanged from Phase 3: codes are the natural unique key; names are not unique because official naming can repeat across administrative lists.

### Delete guards

- A basin with sub-basins **or** stations cannot be deleted — the request returns a `basin` validation error instead of orphaning children.
- A sub-basin with stations cannot be deleted.
- Deletion is a soft delete (archive). Activity entries remain readable.

---

## 8. Boundary import pipeline

```
GeoJSON file
  → extension/size validation (ImportBoundaryRequest)
  → GeoJsonConverter::parse()
      • JSON object check
      • Feature / single-feature FeatureCollection unwrap
      • Polygon / MultiPolygon only
      • ring ≥ 4 positions and closed
      • coordinate ranges and numeric check
      • position budget 20,000
  → WKT + bbox
  → DB transaction
      • previous primary version demoted
      • basin_boundaries row inserted with ST_GeomFromText(..., 4326)
      • basins.boundary display copy updated when primary
      • activity_logs entry (type, positions, SRID)
```

`ST_SRID()` is asserted in tests, not assumed. SRID is supplied on every write because MariaDB 10.4 has no SRID column attribute.

---

## 9. Frontend

| File | Purpose |
|---|---|
| `Pages/Basins/Index.jsx` | Registry table, summary tiles, filters, sorting, export, delete confirmation |
| `Pages/Basins/Create.jsx`, `Edit.jsx` | Form shells with breadcrumbs and context |
| `Pages/Basins/Partials/BasinForm.jsx` | Identity, geography, provenance and status fields with inline errors |
| `Pages/Basins/Show.jsx` | Seven-tab detail view, boundary versions, documents, audit timeline |
| `Pages/SubBasins/Index.jsx` | Sub-basin table with parent-basin filter |
| `Pages/SubBasins/Create.jsx`, `Edit.jsx` | Parent basin selection and geography form |
| `Pages/SubBasins/Show.jsx` | Specification, stations, boundary, documents, activity |
| `Components/geography/BoundaryPanel.jsx` | Multipart GeoJSON upload with progress and error display |
| `Components/geography/DocumentsPanel.jsx` | Upload, download and removal with confirmation |
| `Components/ConfirmDialog.jsx` | Themed destructive-action dialog |
| `Components/Tabs.jsx` | Accessible tab strip with counts |
| `Components/FormField.jsx` | Shared label/error/input styling |
| `Utils/format.js`, `Utils/basins.js` | Number/date/area formatting and status vocabulary |

Navigation now marks **Basins** and **Sub-basins** as available; the GIS map, stations and later modules remain locked with their phase labels.

---

## 10. Verification

### Automated

```
php artisan test tests/Feature/Basins --compact
```

Result: **32 passed (269 assertions)** — covering listing/filtering/sorting, all CRUD paths, create/edit page rendering, per-role authorization, duplicate and archived-code rejection, verified-status guard, coordinate bounds, delete guards, CSV export, GeoJSON import (including `ST_SRID = 4326`), malformed geometry rejection, document upload/download/removal and cross-record document isolation.

```
php artisan test --compact
```

Full suite result: **100 passed (553 assertions)** (68 pre-existing Phase 1–3 tests plus the 32 Phase 5 tests).

### Manual smoke test

1. Sign in as `basin.manager@eec-bhdms.local` / `Password@123`.
2. Open **Basins**. The twelve seeded basins appear with `Pending` boundary badges.
3. Use search, status, boundary and record filters; sort by area and sub-basin count; page through results.
4. Open a basin → **Geographic information** → import a GeoJSON polygon → the version list shows `Primary`, `SRID 4326` and a WGS84 bounding box.
5. **Documents** → attach a PDF → download it → remove it.
6. **Overview** verifies that empty area/centroid fields read “Not recorded”, never `0`.
7. Create a sub-basin under that basin, then attempt to delete the basin: the request is refused with a readable explanation.
8. Sign in as `viewer@eec-bhdms.local`: navigation shows Basins and Sub-basins, but create/edit/delete buttons are absent and direct URLs return 403.

### Commands

```powershell
composer test          # creates hydro_test if missing, then runs the whole suite
php artisan test tests/Feature/Basins --compact
npm run build
vendor\bin\pint
```

---

## 11. Known limits

- Boundary **export** (GeoJSON download) is not part of this phase; it belongs with GIS exchange in Phase 6.
- There is no polygon editor — boundaries are imported only, by design.
- Basins and sub-basins cannot be restored from the UI yet; soft-deleted rows remain available for an administrative restore screen.
- The `data_sources` table is still empty; the form shows “Not recorded” until sources are registered. No organisation record was invented.
- Watersheds exist in the schema but have no screens yet; they arrive with the GIS hierarchy work.

---

## 12. Phase gate

Phase 5 is complete when:

- the basin and sub-basin lists load with real registry data;
- create, edit, delete and boundary import succeed for an authorized role;
- the same operations return 403 for roles without the permission;
- `php artisan test` is green.

All four conditions are met. **Ready for Phase 4 (dashboard and layout), Phase 6 (GIS map) or Phase 7 (stations)** — whichever brief comes next.
