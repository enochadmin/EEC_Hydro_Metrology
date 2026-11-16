# API

The browser application is server-rendered with Inertia, so most routes return
pages. The map and analysis screens also expose JSON endpoints under the same
session authentication (cookies + CSRF), which is what the React charts use.

## Authentication

All endpoints require an authenticated session and the permissions listed below.
There is no public API surface; tokens are not issued to browsers.

## Map

| Method | Path | Permission | Returns |
|---|---|---|---|
| GET | `/gis/data/basins` | `view map` | basin features with sub-basin and station counts |
| GET | `/gis/data/basins/{basin}` | `view map` | one basin with sub-basins, stations and boundary |
| GET | `/gis/data/sub-basins/{subBasin}` | `view map` | sub-basin detail |
| GET | `/gis/data/stations` | `view map` | simplified station markers |
| GET | `/gis/data/stations/{station}` | `view map` | station detail and parameter list |
| GET | `/gis/data/features` | `view map` | rivers, lakes and national boundary |
| GET | `/gis/search?q=` | `view map` | matching basins and stations |

Geometry is GeoJSON in EPSG:4326 (longitude before latitude) and simplified for
the zoom level. Observations are never returned by these endpoints.

## Analysis

| Method | Path | Permission | Returns |
|---|---|---|---|
| GET | `/reports/analysis/data?parameter_id=&basin_id=&station_id=&from=&to=&granularity=&quality=` | `view reports` | series, seasonal profile, station and basin comparisons, distribution, coverage |

`granularity` is `daily`, `monthly` or `annual`. `quality=all` includes
provisional readings and requires `generate reports`.

Example:

```bash
curl -s --cookie "eec_bhdms_session=…" \
  "https://bhdms.example.org/reports/analysis/data?parameter_id=1&granularity=monthly"
```

```json
{
  "series": {
    "metric": "total",
    "metric_label": "Total",
    "points": [{"period": "2020-01-01", "label": "Jan 2020", "value": 42.5, "min": 0, "max": 12.4, "records": 31}],
    "summary": {"records": 372, "total": 980.2, "mean": 2.6, "minimum": 0, "maximum": 48.1}
  },
  "coverage": {"records": 372, "span_days": 366, "observed_days": 340, "missing_days": 26},
  "scope": "All stations"
}
```

## Exports

| Path | Formats |
|---|---|
| `/reports/basins/{basin}/export/{format}` | `csv`, `xlsx`, `pdf`, `geojson` |
| `/reports/stations/{station}/export/{format}` | `csv`, `xlsx`, `pdf`, `geojson` |
| `/reports/analysis/export/{format}` | `csv`, `xlsx`, `pdf` |

Downloads require `export reports`. A report containing provisional observations
requires `export restricted reports`. GeoJSON is a `FeatureCollection` with a
CRS84 declaration.

## Errors

| Status | Meaning |
|---|---|
| 302 | not signed in; redirect to `/login` |
| 403 | signed in but the role lacks the permission |
| 404 | unknown record |
| 422 | validation failure; the JSON body lists the fields |
| 429 | throttled (sign-in) |
