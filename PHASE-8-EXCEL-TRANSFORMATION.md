# Phase 8 — Advanced Excel Data Transformation

Workbook inspection, structure detection, wide-to-long transformation, validation, comparison, export and controlled import, built and tested against the supplied **Rainfall Data.xlsx**.

---

## 1. The workbook as it actually is

Inspected with PhpSpreadsheet before any code was written.

### Sheet 1 — `Arba Minch Raw Data`

| Property | Observed value |
|---|---|
| Used range | 433 rows × 39 columns (A–AM) |
| Header | `Name, Elevation, Geogr1, Geogr2, element, Year, Month, Time`, then numeric headers 1–31 |
| Data rows | 432 monthly records |
| Station | `Arba Minch`, 1207 m, 37.55783 E, 6.057167 N |
| Element | `PRECIP` |
| Months present | 431 distinct months — **2011-01 appears twice, 2012-01 is absent** |
| Value marker | `-` for missing; blanks also occur; `0` is a real measurement |

The duplicate month is a property of the file, not of the parser. The validation report flags the 31 repeated dates instead of quietly repairing the data.

### Sheet 2 — `Arba Minch column`

| Property | Observed value |
|---|---|
| Reported range | 18,303 rows × 603 columns (A–WE); data ends at row 18,299 |
| Header rows | 1 = year, 2 = month number, 3 = month name |
| Day rows | 4–34, day labels in column C |
| Period columns | D–WE = 600 months, January 1973 → December 2022 |
| Matrix cells | 18,600; 12,413 numeric (7,688 of them zero), 948 hyphens, 5,208 blanks |
| Date series | A/B rows 38–18,299 = 18,262 dates |
| **Column B is not independent** | every value is a formula such as `=D4`, `=WE29` referencing the matrix |

That last point drives the comparison design: the series is resolved formula-by-formula to the matrix cell it points at, then its date is checked against the referenced cell's calendar date.

---

## 2. Flow

```
upload (private disk, original untouched)
  → inspect every sheet (bounded reads)
  → detect structures (content-based, no sheet-name rules)
  → confirm mapping (editable)
  → transform to NDJSON (one row per calendar date)
  → validate (issues + station list)
  → compare (optional, matrix vs date series)
  → match stations
  → import in a transaction
  → export transformed XLSX / error CSV at any point
```

Batch stages are persisted on `data_import_batches` (`uploaded → mapped → previewed → validated → compared → imported`) so the workflow is resumable and auditable.

---

## 3. Structure detection

`ExcelStructureDetectionService` reads a bounded window of cells and returns every matching structure with a confidence score, the header row, data start row, column map and issues.

| Structure | Detection signals |
|---|---|
| `monthly_daily_columns` | metadata header aliases plus ≥20 numeric day headers 1–31 |
| `multi_header_matrix` | a year row (1900–2200), a following month row 1–12, a day column containing 1…31 in order |
| `daily_series` | adjacent columns with ≥3 date-like and ≥3 value cells; flags formula-driven series |

The supplied file produces:

- sheet 1 → `monthly_daily_columns`, 31 day columns, station=A, year=F, month=G, confidence 0.99;
- sheet 2 → `multi_header_matrix` (600 periods, day column C) **and** `daily_series` (A/B, formula-based), so both are offered.

No worksheet name is hardcoded; names are only shown to the user.

---

## 4. Transformation

`ExcelTransformationService` writes NDJSON to the private disk — nothing is inserted into the time-series tables at this step.

### Monthly wide sheet

For every row: station, elevation, longitude, latitude, element, year, month, time. For every day column 1…31, a date is generated only when it exists in that month (`checkdate` / days-in-month), and the cell is classified:

| Cell | Result |
|---|---|
| Number, including `0` | `valid`, value preserved (decimal precision kept) |
| `-`, `—`, `na`, blank | `missing`, value `null`, flagged |
| Text or unevaluated formula | `invalid`, reported with the raw value |
| Day beyond month length | no row generated |

Measured results for sheet 1:

| Metric | Value |
|---|---|
| Rows generated | 13,149 |
| Valid calendar dates | 13,118 |
| Numeric measurements | 12,413 |
| Zero measurements | 7,688 |
| Missing | 736 |
| Invalid | 0 |

`1987-01-04` → `10.5`; `1987-01-01` → `0` and remains valid; `1987-02-29` does not exist and is not generated.

### Matrix sheet

Periods come from the year/month header rows (600 columns). For each period, days 1…days-in-month map to row `day_start + day − 1`. Produces 18,262 rows for 18,262 real dates; 12,413 numeric, 5,849 missing, 0 invalid.

### Date series

Dates are parsed from Excel serials or date strings. Formula values are resolved by parsing `=D4`-style references against the matrix rather than trusting a recalculated workbook; unsupported formulas become invalid rows with a code.

---

## 5. Validation

`ExcelValidationService` streams the NDJSON and records:

- missing station names;
- missing or out-of-range coordinates;
- unknown parameters (the element must already exist in `parameters`);
- impossible dates;
- invalid measurements;
- duplicate station/date/parameter combinations **within the file**;
- missing values as warnings;
- station coordinate gaps once per station.

Findings are written to `validation.json`, mirrored into `data_import_errors`, and summarized on the batch. The report for sheet 1 is 767 findings: 736 missing-value warnings, 31 duplicate-date errors from the repeated `2011-01` month. Missing values are not errors; duplicates are.

---

## 6. Comparison

`ExcelComparisonService` compares the matrix with the date series by date and resolves every formula target:

| Result | Meaning |
|---|---|
| Matching | both sides numeric and equal |
| Different | both numeric, values differ |
| Missing in daily / matrix | date exists on one side only |
| Missing values | one side is blank or hyphen |
| Alignment errors | column A date ≠ calendar date of the formula's target cell |

Actual result for the supplied sheet:

| Metric | Value |
|---|---|
| Matching | 12,254 |
| Different values | 153 |
| Missing in date series | 14 |
| Missing values | 5,841 |
| Alignment errors | 746 |
| Formula rows | 18,262 |

The 746 alignment errors are the expected consequence of a sequential formula chain that includes day cells beyond short months. The UI states that the series mirrors the matrix and is not independent evidence.

---

## 7. Import

`ExcelImportService`:

1. resolves each distinct station through the matching screen (existing station, or a new one under a chosen basin);
2. refuses to create a station whose code or name already exists;
3. resolves the element to an existing `Parameter` — **parameters are never created from a file**;
4. takes the unit from the parameter's default unit;
5. inserts valid rows in chunks of 500 with `insertOrIgnore` against the unique `(station, parameter, measured_at, series_no)` key;
6. records batch id, source row number, importer and `approved` quality status;
7. updates the batch counts and completes inside one transaction.

Missing and invalid rows are not imported; they remain in the validation report and the error export.

---

## 8. Endpoints

All routes require `auth` plus the stated permission.

| Method | URI | Permission |
|---|---|---|
| GET | `/excel` | `view data` |
| GET | `/excel/upload` | `upload data` |
| POST | `/excel/upload` | `upload data` |
| GET | `/excel/history` | `view data` |
| GET | `/excel/{batch}/preview` | `view data` |
| GET | `/excel/{batch}/sheets/{sheet}` | `view data` |
| GET/POST | `/excel/{batch}/mapping` | `transform data` |
| GET | `/excel/{batch}/transformation` | `view data` |
| POST | `/excel/{batch}/transform` | `transform data` |
| POST | `/excel/{batch}/compare` | `transform data` |
| GET | `/excel/{batch}/comparison` | `view data` |
| GET | `/excel/{batch}/validation` | `view data` |
| POST | `/excel/{batch}/validate` | `transform data` |
| GET | `/excel/{batch}/export/transformed` | `export data` |
| GET | `/excel/{batch}/export/errors` | `export data` |
| POST | `/excel/{batch}/import` | `approve data` |

Upload is limited to XLSX/XLS/CSV, 30 MB. The original is stored at `excel/{uuid}/source.{ext}` on the private disk and never rewritten.

---

## 9. Interface

| Page | Purpose |
|---|---|
| `Excel/Index` | Stats, recent workbooks, workflow |
| `Excel/Upload` | Drag-and-drop, file information, data rules |
| `Excel/Preview` | Worksheet tabs, detected structures with confidence, raw preview |
| `Excel/ColumnMapping` | Editable field/day mapping, station and parameter defaults |
| `Excel/Transformation` | Summary counters, transformed preview, original preview, export, validate, compare |
| `Excel/Validation` | Findings table, station matching, import approval, error export |
| `Excel/Comparison` | Match/difference/alignment report |
| `Excel/ImportHistory` | File, worksheet, uploader, type, counts, status |

The sidebar entry is now **Excel import → `/excel`** for every role with `view data`; write actions remain permission-gated server-side.

---

## 10. Performance and limits

- Sheet 2 is never loaded whole. Detection reads 40 rows; the matrix reads rows 1–34 × 603 columns; the date series reads two columns across 18k rows. Parsed windows are memoized per request.
- Transformation streams to NDJSON; validation and export stream it back. Imports chunk at 500 rows.
- Measured times on this machine: detection ≈3 s per sheet, monthly transform ≈3 s, matrix transform ≈7 s, comparison ≈15 s, export of 12k rows ≈5 s.
- Processing is synchronous. The 18k-row workbook completes within a normal request; a queue job can wrap the same service for substantially larger files without changing the contracts.
- MariaDB stores observations with `decimal(18,6)`; values keep their source precision up to six decimals.
- The duplicate month in the supplied file is reported, never auto-corrected.

---

## 11. Verification

```
php artisan test tests/Feature/Excel --compact
```

Covers, with the real workbook as a fixture:

1. both worksheets detected; sheet 1 monthly daily columns, sheet 2 matrix + daily series; 600 periods; 31 day columns; formula flag;
2. monthly transformation: 433×39 source, 13,149 rows, zeros preserved, hyphens missing, 1987-01-04 = 10.5, no 1987-02-29;
3. matrix transformation: 18,262 dates, 2000-02-29 present, 2001-02-29 absent; comparison formula mirror, matching >12,000, alignment errors >0;
4. validation counts; station creation with coordinates from the file; 12,413 observations imported inside a transaction; XLSX and error CSV exports;
5. upload → preview page for a Data Manager, two worksheets, detection payload; Data Analyst denied upload.

Final Excel suite: **5 passed (64 assertions)**. Full application suite: see the closing summary of the delivery message.

### Manual QA

1. Sign in as `data.manager@eec-bhdms.local` / `Password@123`.
2. **Excel import → Upload workbook**, choose `Rainfall Data.xlsx`.
3. On **Inspect**, confirm both worksheets and both structures on sheet 2.
4. Choose **Monthly records · daily columns** → confirm mapping → generate.
5. Check summary: 7,688 zeros, 736 missing, 0 invalid; find 1987-01-04 = 10.5.
6. Download the transformed XLSX; run validation; inspect the 31 duplicate dates.
7. Match **Arba Minch** to a new station under a basin (coordinates come from the file) and approve the import.
8. Open **Import history** and confirm counts and status.
9. Repeat with **Arba Minch column → Multi-header matrix**, then **Compare sheet structures**; review matching/differences/alignment.
10. Sign in as `viewer@eec-bhdms.local`: the pages are readable, but upload, transform, import and export are refused.

---

## 12. Known limits

- Processing is synchronous; no queue worker is required for this workbook.
- Formula comparison resolves single-cell references only (`=D4`); range formulas are reported as unsupported rather than evaluated.
- Shapefile/CSV variants with merged multi-row headers beyond the detected pattern require manual mapping adjustments.
- Duplicate months in a source file are reported but not automatically resolved; the operator decides which record to keep.
- The sheet-1 duplicate `2011-01` is a data-quality finding for EEC, not something the importer corrects.
- Station creation requires a basin; sub-basin is optional.
- Excel export includes every generated row (valid, missing, invalid) so the transformation is fully auditable; only valid rows enter MySQL.
