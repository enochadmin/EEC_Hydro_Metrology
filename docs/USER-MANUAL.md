# User manual

## Signing in

Use the email and password issued by your administrator. A deactivated account is
refused at sign-in. Sessions expire after two hours of inactivity.

The sidebar shows only the modules your role can open.

## Dashboard

Counts of basins, sub-basins, stations and observations, a small basin map with a
link to the full GIS view, the import pipeline and system health.

## Basins and sub-basins

**Basins** lists the twelve major basins with code, area, sub-basin and station
counts. Search, filter by status and sort; export the list to CSV.

Open a basin for seven tabs: Overview, Sub-basins, Monitoring stations,
Hydrological data, Geographic information, Documents and Activity history.

- **Add basin** requires code, name, area and location. A basin is saved as
  `pending` until verified geography exists.
- **Sub-basins** are created under a basin; the parent is fixed at creation, so a
  catchment cannot be re-parented by accident.
- Deletion is a recoverable archive. A basin with sub-basins or stations is
  refused; archive those first.

## GIS map

The map opens on Ethiopia. Pan, zoom, search for a basin or station, and switch
basemap (Terrain, Satellite, Streets; CARTO styles appear when a key is
configured).

- Click a basin to highlight it, see its name and open its report.
- Select a basin to reveal its sub-basins.
- Station markers are coloured by type and clustered; click one for code, name,
  coordinates, parent basin, sub-basin, parameters and data availability.
- The layer panel toggles boundaries, sub-basins, stations and reference layers.
  Layers without a loaded dataset are hidden rather than drawn empty.

## Excel import

1. **Upload** a workbook (`.xlsx`, `.xls`, `.csv`). The original file is kept
   unchanged.
2. **Preview** both worksheets; the detector reports each sheet's structure.
3. **Map columns** — confirm the fields the detector guessed. Station metadata is
   entered here because matrix sheets carry none.
4. **Transform** into one row per calendar date: zero is a valid reading, a
   hyphen or blank is missing, an impossible date is invalid. Nothing is
   committed yet.
5. **Validate** — review issues, match or create the station, then import the
   approved rows inside a transaction.

If a second sheet restates the first, use **Comparison** to check it by calendar
date. Differences are reported, never silently reconciled.

## Reports

**Reports → Basins** and **Stations** give specifications, geography, stations,
parameters, coverage, missing days and quality indicators. Each has an Export
menu: CSV, Excel, PDF, and GeoJSON for geographic records.

**Hydrological analysis** aggregates one parameter over a period:

- rainfall and other accumulations are **summed**;
- temperature and streamflow are **averaged**, with minima and maxima shown;
- totals are reported as *not applicable* for a state parameter.

Choose parameter, basin, station, date range and daily/monthly/annual grouping.
Charts: time series, monthly profile, station comparison, basin comparison and
distribution.

## Quality and permissions

Only approved observations are counted by default. Provisional, flagged and
rejected readings appear when you choose *All records*, and exporting them
requires the restricted-export permission.

If a page says you lack a permission, ask an administrator; the interface hides
actions you cannot perform, and the server refuses them regardless.
