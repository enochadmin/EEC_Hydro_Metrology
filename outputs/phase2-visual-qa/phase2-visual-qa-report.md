# Phase 2 — Access Control Visual QA (Super Admin)

Target: http://127.0.0.1:8000 (Laravel + Inertia React), user admin@eec-bhdms.local (Super Admin).
Desktop viewport 1440x900, device pixel ratio 1.5 (captures are 2160x1350 px). Mobile 390x844.

## IMPORTANT — asset loading caveat
The Laravel page at 127.0.0.1:8000 served its HTML correctly, but the HTML references the Vite dev
server (`public/hot` = `http://[::1]:5173`). Nothing was listening on port 5173 (only 8000 listens;
`http://127.0.0.1:5173/@vite/client` = ERR_CONNECTION_REFUSED, `http://[::1]:5173` = timeout), so the
React app never mounted: `#app` stayed empty and the page rendered completely blank with no JS errors.

To complete the visual QA without touching project files or servers, the existing production bundle in
`public/build` (entry `assets/app-B5FB2ejg.js`, CSS `assets/app-mdOhRSB3.css`, from `build/manifest.json`)
was injected into the already-served page at runtime (one `<link>` + one `<script type="module">`),
and Inertia then hydrated normally from the inlined `data-page` props. All navigation afterwards was
client-side (Inertia), so no reloads. Consequences: screenshots show the **production build**, not the
Vite dev build (no dev-only overlays; visually the same compiled Tailwind output). The dev-server outage
itself is a real environment defect worth fixing before any dev-mode QA or HMR-based testing.

## Screens (all rendered, no blank screens, no fatal React error)
| Step | Page | File | Result |
|---|---|---|---|
| 1 | /users | users-index.png | Rendered, 7 accounts, table + 3 stat cards |
| 2 | New user (/users/create) | users-create.png | Rendered, empty form + 7 role cards |
| 3 | Edit user (/users/3/edit, Basin Manager) | users-edit.png | Rendered, pre-filled, "1 selected" role |
| 4 | /access-control/roles | roles-index.png | Rendered, 7 role cards, "7 ROLES · 34 PERMISSIONS" |
| 5 | Viewer permissions (/access-control/roles/7/edit) | roles-edit.png | Rendered, 34 checkboxes, 7 checked ("7 OF 34 GRANTED") |
| 6 | /users @ 390x844 | users-mobile.png | Rendered, responsive shell |

## Behaviour checks
- Role filter: selecting "Viewer" in "Filter by role" issues `?role=Viewer&search=&status=` and the table
  narrowed to 1 row — "Read-only Viewer / viewer@eec-bhdms.local / VIEWER / Active / Nov 08, 2026",
  footer "Showing 1–1 of 1". (Evidence: users-filter-viewer.png.) Resetting to "All roles" restored
  7 rows.
- Account menu: trigger shows "SA / System Administrator / Super Admin"; opening it shows
  "System Administrator", admin@eec-bhdms.local, Profile, Password, Appearance, Sign out.
  Note: the "Super Admin" role label lives on the header trigger, not repeated inside the dropdown
  (Evidence: users-account-menu.png).
- Sidebar (expanded): ADMINISTRATION group contains **Users** and **Roles & permissions** as live links
  for this Super Admin. All other modules (Basins, Sub-basins, GIS map, Monitoring stations, Excel import,
  Hydrological records, Quality control, Reports & analytics, Activity logs, System settings) render as
  greyed, non-clickable entries with a padlock icon (Phase 2 not implemented), and Dashboard is a link.

## Layout observations
- No page-level horizontal overflow at 1440x900 (`scrollWidth == clientWidth`) on users and roles pages.
- Users table fits at 1440; columns USER / ROLES / STATUS / LAST SIGN-IN / ACTIONS align cleanly,
  no clipped text, badges legible.
- Roles grid is a fixed 3 x 360px layout: row heights are equal within each row (198 / 212 / 178 px),
  so no ragged card bottoms. The Super Admin card correctly shows "FULL ACCESS" + "Locked role…"
  and has no "Edit permissions" button.
- Users create/edit role pickers: two-column, aligned, checkmarks and lock icon render correctly.
- Possible contrast weakness (subjective, not measured): unchecked permission labels on roles-edit
  (e.g. "create basins", "edit basins", "delete basins") are light grey on near-white and read as
  low-emphasis/disabled; worth an axe/WCAG pass.
- Mobile 390x844: no page-level horizontal overflow; header collapses to hamburger + theme + bell + SA
  avatar (name/role hidden, expected). The table keeps a 684px min width inside a container with
  `overflow-x: auto` (client 357px), so ROLES/STATUS/LAST SIGN-IN/ACTIONS are off-screen and require
  inner horizontal scrolling — the role badge is visibly cut at the right edge until scrolled.
  This is by design (scroll container), but it is the main mobile readability compromise on /users.
- No broken icons observed; no console errors or uncaught JS exceptions recorded
  (`browser_console` errors = empty, `browser_utility kind=errors` = empty).

## Screenshot paths
C:\Users\Henok Petros\Desktop\Automation Projects\Laravel Projects\Hydro\example-app\outputs\phase2-visual-qa\
- users-index.png
- users-create.png
- users-edit.png
- roles-index.png
- roles-edit.png
- users-mobile.png
- users-filter-viewer.png (extra evidence)
- users-account-menu.png (extra evidence)
