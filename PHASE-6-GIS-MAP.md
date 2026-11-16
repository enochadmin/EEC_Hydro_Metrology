# Phase 6 — Interactive GIS Map Integration

Leaflet map surface for EEC-BHDMS: basin and sub-basin polygons, monitoring stations, reference layers, search, layer controls and GeoJSON import.

---

## 1. Data status first

No verified EEC boundary or station dataset is bundled with this repository. The map therefore starts with an **empty geography** and says so: layers only appear in the controls when their table contains geometry, and raster layers are listed as *awaiting dataset* rather than drawn as blank tiles.

Nothing was invented to make the map look populated. The seeded twelve basins remain `pending` with no polygons until a GIS Officer imports the official files. Import is available at **GIS → Import GeoJSON**; basin and sub-basin boundaries can also be imported from their registry pages.

Tests use small synthetic polygons as fixtures only; they never enter the development database.

---

## 2. What was built

| Area | Delivered |
|---|---|
| Full-page map | Leaflet + React Leaflet, Ethiopia extent, OSM/CARTO/Esri basemaps |
| Basin polygons | Distinct palette colour per basin, hover highlight + name tooltip, click → panel |
| Sub-basin drill-down | Selecting a basin loads its sub-basins, draws dashed polygons, enables sub-basin clicks |
| Stations | Coloured markers by type, marker clustering, per-type filters, detail panel |
| Map controls | Zoom, pan, scale, fullscreen, reset to national extent, basemap selection |
| Layer panel | Only layers with data are toggleable; unavailable rasters explained separately |
| Search | Basins, sub-basins, stations and imported features, debounced server search |
| Legend | Basin colours and station-type colours, driven by server data |
| GeoJSON import | National boundary, rivers and lakes with geometry, CRS and duplicate validation |
| Dashboard | Embedded mini map; clicking a basin opens `/gis?basin=<id>` |
| Performance | No observations on the map; simplified geometry; lazy layer loading; clustering |

---

## 3. Architecture

```
Inertia page  /gis                      → metadata only
   layers, station types, legend, counts, abilities

JSON endpoints /gis/data/*              → geometry, summaries, details
   basins, basin detail, sub-basin detail, stations, station detail,
   features, watersheds, search

Services
   GisLayerService        availability + palette + station vocabulary
   GisDataService         GeoJSON payloads, simplification, search, station details
   GeoFeatureImportService import transaction, duplicate hash, audit
   GeoJsonFeatureReader   multi-feature reader, CRS/geometry validation
   GeoJsonSimplifier      Douglas–Peucker in PHP (MariaDB 10.4 has no ST_Simplify)
```

Geometry is decoded from `ST_AsGeoJSON(geom, 6)` and simplified in PHP before it reaches the browser. MariaDB 10.4 lacks `ST_Simplify` and `ST_IsValid`; both limitations are handled explicitly rather than assumed.

---

## 4. Schema

### `geo_features` (new)

| Column | Type | Notes |
|---|---|---|
| `layer_type` | string(40) | `national_boundary`, `river`, `lake` |
| `name`, `code` | string | display / optional reference |
| `geometry_type` | string(30) | Polygon, MultiPolygon, LineString, MultiLineString |
| `geom` | GEOMETRY NOT NULL | `ST_GeomFromText(..., 4326)`, spatial index |
| `properties` | JSON | original feature properties + import timestamp |
| `min_lng` … `max_lat` | decimal | bounding box for viewport/search |
| `content_hash` | string(64) unique | SHA-256 of layer + canonical WKT (duplicate guard) |
| `basin_id` | FK nullable | optional parent-basin association |
| `created_by`, `data_source_id` | FK nullable | provenance |
| `source_note` | text | free-text provenance |
| `is_active`, timestamps, `deleted_at` | | a new national boundary deactivates the previous one |

Basins, sub-basins and watersheds keep using their existing tables and boundaries. Station points continue to come from `latitude`/`longitude`; `location` remains optional because coordinates are nullable at registration.

---

## 5. Layer availability

The server computes the list from actual row counts. `available=false` layers are not offered as toggles.

| Layer | Source | Available when |
|---|---|---|
| Major basins | `basins.boundary` | at least one polygon imported |
| Sub-basins | `sub_basins.boundary` | at least one polygon imported |
| Watersheds | `watersheds.boundary` | at least one polygon imported |
| National boundary | `geo_features` | one active feature |
| Rivers | `geo_features` | one line feature |
| Lakes | `geo_features` | one polygon feature |
| Monitoring stations | station coordinates | one station with lat/lng |
| Rainfall distribution | — | never until a verified raster exists |
| Temperature distribution | — | never until a verified raster exists |
| Elevation / DEM | — | never until a verified raster exists |

---

## 6. Endpoints

All routes sit behind `auth` and the `view map` permission.

| Method | URI | Purpose |
|---|---|---|
| GET | `/gis` | Inertia map page (metadata only) |
| GET | `/gis/data/basins` | basin FeatureCollection (+ counts, colour, href) |
| GET | `/gis/data/basins/{basin}` | basin panel, sub-basin FeatureCollection, station FeatureCollection |
| GET | `/gis/data/sub-basins/{subBasin}` | sub-basin specification, bbox and stations |
| GET | `/gis/data/stations` | station FeatureCollection; filters `types[]`, `basin_id`, `sub_basin_id` |
| GET | `/gis/data/stations/{station}` | station detail, parameters, observation period |
| GET | `/gis/data/features/{layer}` | `national_boundary`, `rivers`, `lakes` |
| GET | `/gis/data/watersheds` | watershed polygons from the registry |
| GET | `/gis/data/search?q=` | cross-layer search (minimum two characters) |
| POST | `/gis/features` | GeoJSON import (`manage geographic boundaries`) |
| DELETE | `/gis/features/{feature}` | remove an imported feature (same permission) |

`tolerance` (0–0.05 degrees) controls simplification; defaults are 0.0015 for detail and 0.01 for overview.

---

## 7. Interaction model

- **Hover** a basin: boundary thickens and the name follows the cursor.
- **Click** a basin: panel shows name, code, area, sub-basin count, station count and a link to `/basins/{id}`; the view fits the basin bbox; its sub-basins load automatically.
- **Click** a sub-basin: dashed polygon highlights, panel shows area, elevation, parent basin, station count and a link to `/sub-basins/{id}`.
- **Click** a station: panel shows code, name, coordinates, parent basin, sub-basin, type, configured parameters and the observation period. No readings are sent.
- **Search** returns basins, sub-basins, stations and features; choosing a result zooms and opens the relevant panel.
- **Layers** toggle independently; station types can be switched off individually while the station layer stays on.
- **Reset** returns to the Ethiopian extent and clears the selection.
- **Fullscreen** uses the browser Fullscreen API on the map shell, so overlays and panels remain available.

---

## 8. Import validation

`GeoJsonFeatureReader` enforces:

1. valid JSON and a Feature, FeatureCollection or bare geometry;
2. declared CRS must be WGS84 / EPSG:4326 / CRS84 — projected declarations are rejected;
3. every coordinate numeric and inside −180…180 / −90…90 (catches unreported projected data);
4. polygon rings closed, at least four positions, non-zero area;
5. geometry family matches the layer — polygon for national boundary and lakes, lines for rivers;
6. national boundary must be exactly one feature;
7. budgets: 5,000 features and 20,000 positions per upload, 20 MB per file.

`GeoFeatureImportService::import()` adds:

- SHA-256 duplicate guard per layer, including soft-deleted rows;
- optional parent basin association (validated against live basins);
- a single transaction, so a bad feature leaves no partial layer;
- deactivation of the previous national boundary;
- an `activity_logs` entry with layer, feature count, positions and SRID.

### Shapefile and KML

Not accepted yet. The reader is isolated behind `GeoFeatureImportService`; adding GDAL/ogr2ogr later means converting to GeoJSON before the same validation path. The UI states this instead of pretending other formats work.

---

## 9. Performance decisions

- The Inertia page carries **no geometry**; the map paints immediately and polygons arrive in one request.
- Simplification runs on the server; a 12-basin country view is a few hundred KB at most, not the raw survey geometry.
- Sub-basin geometry is fetched **only after a basin is selected**.
- Reference layers are fetched the first time they are enabled and cached in component state.
- Observations are never loaded on the map; station detail runs `COUNT/MIN/MAX` on an indexed station column.
- Stations are clustered with `leaflet.markercluster` and the payload is capped at 5,000 points (`meta.truncated` reports a cap hit).
- `geo_features` carries bbox columns for future viewport filtering; the union index supports cheap range queries on MariaDB 10.4.

---

## 10. Permissions

| Action | Permission |
|---|---|
| Open the map and read every data endpoint | `view map` |
| Import / delete GeoJSON layers | `manage geographic boundaries` |
| Import a basin boundary from the registry | `import basin boundaries` (Phase 5) |

Every role currently holds `view map`; only GIS Officer and Super Admin hold `manage geographic boundaries`. The import button is hidden without it, and the POST/DELETE routes return 403 regardless of the UI.

---

## 11. Verification

### Automated

```
php artisan test tests/Feature/Gis --compact
```

Result: **17 passed (87 assertions)** — page metadata and abilities, role denial, basin FeatureCollection with SRID 4326, basin detail with sub-basins and stations, station type filtering, station detail, station parameters and observation period, cross-layer search, national boundary import, river import with geometry-type rejection, projected coordinate and CRS rejection, duplicate geometry, multi-feature collection, unclosed ring, single active national boundary, permission denials, deletion with audit entry and search-length validation.

```
composer test
```

Full suite result: **117 passed (653 assertions)** (100 from Phases 1–5 plus the 17 GIS tests).

### Real-world file check

The parser and import service were exercised against the public **geoBoundaries ETH-ADM0 simplified** dataset (Open Data Commons ODbL 1.0, source: Open Africa / Code for Ethiopia):

| Measure | Result |
|---|---|
| Geometry | Polygon |
| Positions | 1,848 |
| Bounding box | 33.0022 E, 3.4004 N → 47.9593 E, 14.8460 N |
| Reader parse | 16 ms |
| Simplify to 0.01° | 22 ms, 1,848 → 419 vertices |
| End-to-end import | 1 feature, SRID 4326, rolled back afterwards (registry unchanged) |

The file was used for verification only and is not bundled or seeded, so no third-party boundary is presented as EEC geography.

### Build

```
npm run build
```

Passed with React Leaflet and marker clustering in dedicated chunks.

### Manual QA with real files

1. Sign in as `gis.officer@eec-bhdms.local` / `Password@123`.
2. Open **GIS map**. With no data imported, the empty-state card explains the gap.
3. Import a basin boundary from **Basins → a basin → Geographic information**, then reload **GIS**.
4. The basin polygon appears in its palette colour; hover shows the name; click opens the panel and fits the basin.
5. Import a national boundary GeoJSON from the map's **Import GeoJSON** dialog; enable **National boundary** in the layer panel.
6. Import a river LineString and a lake Polygon; toggle each layer.
7. Add a station with coordinates (Phase 7 UI is not built yet; a record can be inserted for QA), then filter station types.
8. Click a station: coordinates, parent basin and parameters appear; the data availability block reports zero readings.
9. Use search for a basin code, choose the result, and confirm the map zooms and opens the panel.
10. Test fullscreen, reset, basemap switching and the scale bar.
11. Open the dashboard: the embedded map shows the same polygons; clicking one opens `/gis?basin=<id>`.

---

## 12. Known limits

- **No official geography is bundled.** This is deliberate; the map is only as complete as the imported EEC datasets.
- No polygon drawing/editing — boundaries are imported, not sketched.
- MariaDB 10.4 cannot validate self-intersections (`ST_IsValid` absent); ring closure, area and range checks are performed, but a topologically invalid self-crossing polygon would still be accepted.
- Simplification is Douglas–Peucker in degrees; adequate at national scale, not a topology-preserving simplifier.
- Raster layers (rainfall, temperature, DEM) have no storage or renderer yet.
- Shapefile/KML import is deferred to a GDAL-backed conversion step.
- Watershed import has no UI; the layer reads the watershed registry when boundaries exist.
- Feature deletion exists in the API but not yet in the layer panel.
- A station has no dedicated page until Phase 7; the panel links to its basin instead.

---

## 13. Phase gate

- Map renders with Leaflet controls, basemaps, scale and legend.
- Basins, sub-basins and stations are selectable with information panels.
- Layers appear only when data exists; import validates geometry, CRS and duplicates.
- Permissions are enforced on both the page and every JSON route.
- `composer test` is green.

All conditions are met. The next module is Phase 7 (monitoring stations), followed by Excel import and the hydrological database.
