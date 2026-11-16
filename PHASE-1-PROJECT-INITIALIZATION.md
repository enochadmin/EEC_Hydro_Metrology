# EEC-BHDMS — Phase 1: Project Initialization

**Status:** Complete and verified
**Scope:** Environment audit, stack configuration, project structure, application shell, theme system
**Out of scope (deliberately):** basin management, GIS layers, Excel processing, hydrological data

---

## 1. Environment audit

| Component | Required | Found on this machine |
|---|---|---|
| PHP | 8.2+ | 8.2.12 (ZTS, XAMPP) |
| Laravel | 12 | 12.71.0 |
| Composer | — | 2.9.5 |
| Node / npm | — | 22.23.2 / 10.9.8 |
| Database | MySQL 8 | **MariaDB 10.4.32** (`hydro_db` exists, migrations ran) |
| React | — | 18.3.1 |
| Inertia (server / client) | 2.x | 2.0 / 2.0.3 |
| Tailwind CSS | 3.x | 3.4.19 |
| Icons | Lucide React | 0.475.0 |

> **Database note:** the blueprint specifies MySQL 8, but the local server is MariaDB 10.4.32. The application uses Laravel's standard `mysql` driver, so nothing in Phase 1 depends on the difference. MariaDB 10.4 does support `GEOMETRY`, `SRID` and `SPATIAL INDEX`, but the server edition must be confirmed before the Phase 6 GIS work, because SRID-aware spatial functions differ from MySQL 8.

The project already existed as the Laravel 12 React starter kit, so no new project was scaffolded. Instead the existing installation was audited, repaired and rebranded.

---

## 2. Defects found during the audit

These were real problems, not stylistic preferences:

1. **Production build was broken.** `resources/js/app.jsx` imported `./bootstrap`, but no `bootstrap.js` existed. `npm run build` failed with `Could not resolve "./bootstrap"`.
2. **No `@` path alias.** Every page imports `@/Components/...`, but `vite.config.js` defined no alias. The build would fail as soon as a page was bundled.
3. **Case-sensitive directory mismatch.** Folders were `components`, `layouts`, `pages`, `hooks`, while imports used `@/Components`, `@/Layouts`. This works on Windows only and breaks on a Linux server.
4. **Duplicated, conflicting dependency declarations.** `package.json` declared React 19 and Tailwind 4 while the lockfile had installed React 18.3.1 and Tailwind 3.4.19. The next `npm install` would have broken the CSS pipeline (`@tailwind base` is v3 syntax).
5. **Every page was registered as a Vite entry** in `app.blade.php`. The production manifest only contains real entry points, so the first non-dev page load threw `Unable to locate file in Vite manifest`.
6. **Seven failing tests.** The settings test suite expected `/settings/profile` and `/settings/password`; the application served `/profile`. The suite was red before this phase started.
7. **Unused TypeScript starter-kit tree.** `app.tsx`, `ssr.jsx`, `components/ui/*.tsx` and `pages/*.tsx` were dead code sitting beside the live `.jsx` tree.

All seven are resolved.

---

## 3. Fixes applied

| # | Fix | File(s) |
|---|---|---|
| 1 | Removed the dangling `./bootstrap` import, wired `ThemeProvider` around the Inertia app | `resources/js/app.jsx` |
| 2 | Added `resolve.alias['@'] = /resources/js` | `vite.config.js` |
| 3 | Renamed `components/layouts/pages/hooks` → `Components/Layouts/Pages/Hooks`; `Pages/auth` → `Auth`, `Pages/settings` → `Settings` | `resources/js/**` |
| 4 | Aligned `package.json` with the installed versions (React 18.3.1, Tailwind 3.4.19), removed the duplicate `@inertiajs/react`, `@vitejs/plugin-react`, `@tailwindcss/vite` and `tailwindcss@4` entries | `package.json` |
| 5 | Reduced `@vite()` to the single real entry point | `resources/views/app.blade.php` |
| 6 | Consolidated account screens under `/settings/*` with `settings.*` route names, updated tests | `routes/web.php`, `tests/Feature/*` |
| 7 | Left the TypeScript tree untouched but unreferenced (see §9) | — |

---

## 4. Resulting structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php
│   │   ├── DashboardController.php
│   │   ├── HomeController.php
│   │   └── Settings/
│   │       ├── AppearanceController.php
│   │       ├── PasswordController.php
│   │       └── ProfileController.php
│   ├── Middleware/HandleInertiaRequests.php
│   └── Requests/ProfileUpdateRequest.php
├── Models/User.php
├── Policies/            (created in Phase 2)
└── Services/SystemStatusService.php

config/eec.php           application identity, map defaults, module roadmap
routes/web.php           shell + settings routes
database/seeders/DemoUserSeeder.php

resources/js/
├── app.jsx
├── Components/
│   ├── Avatar.jsx  Badge.jsx  Button.jsx  Card.jsx
│   ├── EmptyState.jsx  PageHeader.jsx  Popover.jsx  StatCard.jsx
│   ├── branding/   AppLogo.jsx  AppLogoMark.jsx
│   └── layout/     AppSidebar.jsx  AppTopbar.jsx  MobileSidebar.jsx
│                   NotificationMenu.jsx  SidebarContent.jsx
│                   ThemeToggle.jsx  UserMenu.jsx
├── Hooks/useTheme.jsx
├── Layouts/AppLayout.jsx  SettingsLayout.jsx
├── Pages/
│   ├── Dashboard.jsx
│   ├── Auth/…              (existing Breeze screens)
│   └── Settings/Profile.jsx  Password.jsx  Appearance.jsx
└── Utils/ cn.js  navigation.js
```

---

## 5. Backend wiring

### Routes (`routes/web.php`)

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `/` | `home` | `HomeController` → dashboard or login |
| GET | `/dashboard` | `dashboard` | `DashboardController` |
| GET | `/settings/profile` | `settings.profile.edit` | `Settings\ProfileController@edit` |
| PATCH | `/settings/profile` | `settings.profile.update` | `Settings\ProfileController@update` |
| DELETE | `/settings/profile` | `settings.profile.destroy` | `Settings\ProfileController@destroy` |
| GET | `/settings/password` | `settings.password.edit` | `Settings\PasswordController@edit` |
| PUT | `/settings/password` | `settings.password.update` | `Settings\PasswordController@update` |
| GET | `/settings/appearance` | `settings.appearance.edit` | `Settings\AppearanceController` |

All application routes use controller classes, never closures, so `php artisan route:cache` remains available.

### Shared Inertia props (`HandleInertiaRequests`)

- `auth.user` → `id`, `name`, `email`, `email_verified_at`, plus `roles` and `permissions`.
  Roles and permissions are read through `method_exists()` guards; they return `[]` today and start returning Spatie values automatically once Phase 2 installs the trait. No Phase 2 rewrite of the shell is needed.
- `flash.success|error|warning` → lazy session reads for future CRUD screens.
- `application` → `name`, `short_name`, `full_name`, `version`, `phase`, `environment` for the sidebar footer, page titles and browser tab.

### Dashboard data (`DashboardController` + `SystemStatusService`)

- `stats` — four indicators (`basins`, `sub_basins`, `stations`, `records`) with `value: null` until the owning module ships.
- `modules` — eight roadmap entries from `config/eec.php` with phase and status.
- `systemStatus` — live facts: database version via `select version()`, storage writability, environment, PHP/Laravel runtime, timezone.
- `basinTarget` — `12`, the registry scope defined in the blueprint.

**Deliberate rule:** no hydrological number is ever invented. Empty indicators render as *"Not yet available"* with the phase that will supply the data. This satisfies the "never use dummy data as actual official hydrological information" constraint at the UI level, not just in prose.

---

## 6. Design system

### Brand

- Mark: gauge droplet over a basin contour (`AppLogoMark`), reused as `/favicon.svg`.
- Wordmark: `EEC-BHDMS` / `BASIN & HYDROLOGY`.
- Palette: `brand.50–950` (deep gauging-station teal, `#1F6076` primary), rose for destructive actions, emerald/amber for state dots.

### Semantic tokens (light / dark)

CSS variables in `resources/css/app.css` are exposed to Tailwind so every module inherits them:

| Token | Light | Dark |
|---|---|---|
| `canvas` | `244 247 249` | `9 15 23` |
| `surface` | `255 255 255` | `17 25 36` |
| `line` | `226 232 240` | `34 45 58` |
| `ink` | `15 23 42` | `226 232 240` |
| `muted` | `100 116 139` | `148 163 184` |

### Theme behaviour

`darkMode: 'class'`, preference in `localStorage['eec-theme']` (`light` / `dark` / `system`), applied by an inline script in `<head>` before first paint so there is no flash, then kept in sync by `useTheme`. The `System` option listens to `prefers-color-scheme` changes live.

### Shell

- Fixed 272px sidebar, collapsible to 76px, preference persisted in `localStorage['eec-sidebar']`.
- Off-canvas drawer below `lg`, closes on navigation and on backdrop click, locks body scroll while open.
- Sticky topbar: breadcrumb, disabled global-search placeholder (enabled in Phase 5), theme menu, notification menu, account menu.
- Navigation items for undelivered modules render as locked rows with an "Available in Phase N" tooltip instead of dead links, so no route can 404 from the sidebar.

---

## 7. Commands executed

```powershell
# dependency graph aligned with installed versions
npm install --no-audit --no-fund

# production asset build
npm run build
# expected: "✓ built in …s" and public/build/manifest.json regenerated

# database (already migrated; command kept for a fresh clone)
php artisan migrate

# local-only demonstration account
php artisan db:seed --class=DemoUserSeeder
# expected: "Local demo account ready: admin@eec-bhdms.local"

# run the app
php artisan serve --host=127.0.0.1 --port=8000
# in a second terminal, for hot reload:
npm run dev

# verification
php artisan test --compact
vendor\bin\pint --format agent
```

**Expected test result:** `Tests: 39 passed (178 assertions)`.

---

## 8. Testing instructions

### Automated

```powershell
php artisan test --compact
```

Covers: guest redirects from `/`, authenticated redirect to `/dashboard`, dashboard Inertia payload (auth context, four stats, eight modules, five system checks, basin target, application meta), empty indicators staying `null`, all three settings screens, guest protection, profile update/delete, password update.

### Manual walkthrough

| # | Action | Expected result |
|---|---|---|
| 1 | `php artisan serve`, open `http://127.0.0.1:8000` | Redirects to `/login` |
| 2 | Log in as `admin@eec-bhdms.local` / `Password@123` | Lands on `/dashboard` |
| 3 | Inspect the four KPI cards | "Not yet available" + the phase that will deliver each module |
| 4 | Toggle theme (sun/moon) → Light, Dark, System | Immediate switch, survives refresh, no flash on reload |
| 5 | Click **Collapse** at the sidebar bottom, reload | Sidebar stays at 76px with icon-only navigation |
| 6 | Resize to 390px, tap the hamburger | Drawer opens over a dimmed, scroll-locked page; closes on backdrop or link |
| 7 | Click a locked nav item (e.g. *GIS map*) | Nothing navigates; tooltip reads "Available in Phase 6" |
| 8 | Account menu → Profile, save a change | Field-level validation errors appear inline; success shows "Saved" |
| 9 | Account menu → Password, submit a wrong current password | Error under *Current password*, focus returns to the field |
| 10 | Account menu → Appearance, pick Dark | Theme applies instantly across the shell |
| 11 | Sign out | Session ends, redirected to `/login` |

### Windows notes observed during this phase

- If a page returns **500 `rename(... storage\framework\views ...): Access is denied`**, run `php artisan view:cache` (or `view:clear`) once. A file watcher briefly locks the compiled view during the atomic rename.
- If pages render **blank** while `public/hot` exists, a stale Vite dev server is being referenced. Delete `public/hot` and restart `npm run dev`. The production build is not affected.

---

## 9. Notes, deferrals and deviations

| Item | Decision |
|---|---|
| Laravel/React scaffolding | Not re-created: the existing Laravel 12 + Inertia 2 project was audited and repaired instead. Reinstalling would have destroyed working authentication. |
| Welcome page | `/` no longer serves the Laravel marketing page; it routes to the dashboard or login. The `Pages/Welcome.jsx` file remains but is unreferenced. |
| Legacy TypeScript tree | `app.tsx`, `ssr.jsx`, `Components/ui/*.tsx`, `Pages/**/*.tsx`, `lib/`, `types/` are unreferenced by `app.jsx`. They are left in place to avoid deleting files without approval; safe to remove once you confirm the SSR path is not wanted. |
| Demo account | `DemoUserSeeder` refuses to run outside `local`, and is not called from `DatabaseSeeder`. Phase 2 replaces it with real user management. |
| Sanctum | Already installed (`laravel/sanctum ^4.0`) but unused: the Inertia app is session-authenticated as required. API tokens arrive only when an API exists. |
| Spatie permissions | Not installed yet by design; Phase 2 owns it. `HandleInertiaRequests` already exposes `roles` and `permissions`. |
| Search / notifications | Present in the shell but explicitly inert, with the enabling phase stated in the UI. |
| Excel, GIS, Leaflet, Recharts | Not installed: they belong to Phases 6, 8 and 10, and unused dependencies were intentionally avoided. |

---

## 10. Phase gate

- [x] Laravel 12.71.0 confirmed, dependencies audited
- [x] React 18 + Inertia 2 + Vite configured, `npm run build` green
- [x] Tailwind design tokens, light/dark theme with no flash
- [x] Lucide React icons across shell and navigation
- [x] MySQL/MariaDB connectivity verified, migrations applied
- [x] PascalCase, case-safe directory structure matching imports
- [x] Reusable component library and two layouts
- [x] Shared auth/flash/application props
- [x] Dashboard and settings screens built, no fabricated hydrological data
- [x] 39 tests passing, Pint clean

**Ready for Phase 2 — Authentication and role management** (Spatie installation, permission matrix, user CRUD, policies, role-aware navigation).
