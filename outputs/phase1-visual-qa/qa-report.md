# Phase 1 Visual QA — EEC-BHDMS (http://127.0.0.1:8000)

Date: 2026-11-07 · Browser: built-in (DPR 1.5) · User: admin@eec-bhdms.local

## Page load results

| Page / state | Loaded | Notes |
|---|---|---|
| /login | PARTIAL (fatal) | HTTP 200, HTML delivered, but React never mounts: `Error: Page not found: ./Pages/Auth/Login.jsx`. No login form in DOM. Login was completed with an authenticated POST to /login instead. |
| /dashboard (light, 1440x900) | OK | Full dashboard rendered. |
| /dashboard (dark, 1440x900) | OK | `html.dark` applied, `localStorage["eec-theme"]="dark"`. |
| /dashboard (light, 390x844, drawer open) | OK | Off-canvas drawer 280px + backdrop rendered. |
| /dashboard (sidebar collapsed, 1440x900) | OK | Sidebar 272px -> 76px, header/main shift to x=76. |
| /settings/profile | BLANK (fatal) | HTTP 200 with Inertia component `Settings/Profile`, but `Error: Page not found: ./Pages/Settings/Profile.jsx`; `#app` has 0 children, body text empty. |

## Confirmed front-end blocker (production build)

The build manifest on disk contains lowercase page folders:

```
./Pages/auth/Login.jsx
./Pages/auth/Register.jsx
./Pages/settings/Profile.jsx
./Pages/settings/Appearance.jsx
./Pages/settings/Password.jsx
```

but Inertia asks for `Pages/Auth/Login.jsx` and `Pages/Settings/Profile.jsx`. The resolver builds `./Pages/${name}.jsx`, so every page whose folder is lowercase fails. Working: `Dashboard`, `Welcome`, `Profile/Edit`. Broken: all `Auth/*` (login page) and all `Settings/*` pages.

Fix direction: make the PHP component names, the React folder casing, and the Vite glob keys agree (e.g. rename `resources/js/pages` -> `Pages`, or emit `auth/Login`), then rebuild.

## Layout observations (what is actually visible)

Desktop 1440x900, light and dark identical apart from palette:
- No content hidden under the fixed sidebar or topbar: aside 0-272px, header/main start at x=272, main starts at y=65 (below 65px topbar).
- No horizontal scrolling: `documentElement.scrollWidth` = 1440 = viewport (both themes).
- No overlapping elements, no elements crossing the right viewport edge (overflow scan returned none).
- All icons render (inline SVGs), no missing-glyph boxes; disabled/locked nav items show lock icons + tooltips ("Available in Phase 5" etc.).
- Minor: the "PHASE 7" badge on the *Monitoring stations* KPI card wraps to two lines ("PHASE" / "7"), unlike the other three cards; the two-line "MONITORING STATIONS" / "HYDROLOGICAL RECORDS" labels push it.
- Minor: KPI value slots show a short grey placeholder bar (no number) — consistent with the on-screen copy that indicators stay blank until modules land, but reads like an unfinished skeleton.
- Minor contrast: locked sidebar entries (Basins, Sub-basins, GIS map, ...) and the "Collapse" label at the sidebar bottom are low-contrast grey on white / near-white on dark. Readable, but faint.

Mobile 390x844 (light):
- `scrollWidth` = 390 = viewport: no horizontal overflow.
- Hamburger ("Open navigation") opens a 280px drawer with backdrop, close (X), all nav labels fitting without clipping; tapping the backdrop closes it and restores `overflow` auto.
- Drawer hides the desktop "Collapse" control, as expected.

Collapsed sidebar (76px):
- Icons centred, no clipped label fragments, no overflow; header and content reflow correctly; phase footer collapses to a status dot; collapse icon remains at the bottom.

Themes:
- Light: `html` without `dark`, body background `rgb(244,247,249)`, stored preference `light`.
- Dark: `html.dark`, body `rgb(9,15,23)`, main text `rgb(226,232,240)`, preference `dark`. No light-theme leftovers observed in the dark screenshot.
- The theme control is a top-right sun/moon button that opens Light / Dark / System options; selection applies immediately and persists in `localStorage`.

## Console

- Dashboard, profile, mobile: no `console.error` output.
- Only recorded errors are the two fatal Inertia resolves above (one per affected page load).
- No favicon or HMR noise observed on the production build.

## Files

- dashboard-light.png
- dashboard-dark.png
- mobile-nav.png
- sidebar-collapsed.png
- settings-profile.png  (blank page — evidence of the /settings/profile crash)
- _diag-sidebar-collapsed.png (cropped sidebar, diagnostic)
