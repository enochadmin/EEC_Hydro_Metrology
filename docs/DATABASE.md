# Database

MariaDB 10.4+ / MySQL 8, `utf8mb4`, InnoDB. Migrations live in
`database/migrations`; run `php artisan migrate` for the full schema.

## Core tables

| Table | Purpose | Key relations |
|---|---|---|
| `basins` | the twelve major basins | has many `sub_basins`, `monitoring_stations`, `basin_boundaries` |
| `sub_basins` | nested catchments | `basin_id` → basins |
| `monitoring_stations` | gauges and sampling points | `basin_id`, `sub_basin_id`, `watershed_id` |
| `station_parameters` | which parameter a station measures | `monitoring_station_id`, `parameter_id`, `measurement_unit_id` |
| `parameters` | parameter catalogue | `default_unit_id` → measurement_units |
| `measurement_units` | units and dimensions | — |
| `hydrological_observations` | the readings | `monitoring_station_id`, `parameter_id`, `measurement_unit_id` |
| `data_quality_issues` | QC findings | `monitoring_station_id`, `observation_id` |
| `data_import_batches` | upload → transform → import lifecycle | `user_id` |
| `geo_features` | rivers, lakes and national boundary | optional `basin_id` |
| `basin_boundaries` | basin polygons (EPSG:4326) | `basin_id` |
| `documents` | polymorphic attachments | `documentable_type/id` |
| `activity_logs` | append-only audit trail | `user_id`, nullable morph `subject` |
| `system_settings` | administrator overrides | `updated_by` |
| `station_categories` | display metadata for station types | — |

## Observations

```sql
hydrological_observations (
  monitoring_station_id, parameter_id, measurement_unit_id,
  measured_at, value DECIMAL(12,4), quality_status, record_type, series_no
)
```

- `value` is a decimal with four places; rounding happens at presentation time.
- A missing reading is a stored `quality_status = 'missing'` with a null value —
  it is never rewritten as zero.
- `quality_status` drives report filters: `approved` by default, everything else
  is provisional and needs the restricted-export permission.

Indexes to keep: `(monitoring_station_id, parameter_id, measured_at)` for series
queries and `(measured_at)` for range scans. Reports group by date, so a covering
index on those three columns plus `value` is the difference between seconds and
minutes on a large table.

## Spatial data

- Geometry columns are `geometry` with SRID 4326 and a SPATIAL index.
- MariaDB 10.4 has no `ST_Simplify`, so simplification runs in PHP
  (`GeoJsonSimplifier`) before geometry is returned to the browser.
- GeoJSON is the transfer format; `ST_AsGeoJSON(geom, 6)` serialises geometry.

## Seeding order

`RolePermissionSeeder` → `MeasurementUnitSeeder` → `ParameterSeeder` →
`BasinSeeder` → `StationCategorySeeder`; `UserSeeder` only in `local`.

## Useful queries

```sql
-- coverage per parameter for one basin
SELECT p.code, COUNT(*) records, MIN(o.measured_at), MAX(o.measured_at)
FROM hydrological_observations o
JOIN monitoring_stations s ON s.id = o.monitoring_station_id
JOIN parameters p ON p.id = o.parameter_id
WHERE s.basin_id = ? GROUP BY p.code;

-- open quality issues
SELECT status, COUNT(*) FROM data_quality_issues GROUP BY status;
```
