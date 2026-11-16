# EEC-BHDMS — Phase 2: Authentication, Sanctum, Roles & Permissions

**Status:** Complete and verified
**Scope:** Session authentication, Sanctum for machine clients, Spatie RBAC, policies, user management, role matrix, permission-aware UI

---

## 1. What was installed

| Package / component | Version | Notes |
|---|---|---|
| `spatie/laravel-permission` | 6.25.0 | Config and migration published, unmodified defaults |
| `laravel/sanctum` | 4.3.3 | `install:api` published `personal_access_tokens`, `routes/api.php`, `HasApiTokens` |
| Inertia + Laravel session auth | existing | Browser navigation stays on the session guard |

Published files:

- `config/permission.php`, `database/migrations/2026_11_08_003828_create_permission_tables.php`
- `database/migrations/2026_11_08_003956_create_personal_access_tokens_table.php`
- `routes/api.php`

---

## 2. Database changes

| Table | Change |
|---|---|
| `permissions`, `roles`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Spatie defaults |
| `personal_access_tokens` | Sanctum API tokens |
| `users` | `is_active` (bool, default true, indexed), `last_login_at`, `deactivated_at` |

`User` now uses `HasRoles` and `HasApiTokens`, defaults `is_active` to `true` at the model level (so freshly instantiated accounts are active before the DB default applies), and exposes `isActive()`, `isSuperAdmin()`, `markLoggedIn()`, `scopeActive()`.

---

## 3. Access model

`config/access.php` is the single source of truth: roles, the permission catalogue grouped by module, and the role → permission mapping. Seeders and the UI both read it.

### Roles (7)

| Role | Responsibility | Permissions |
|---|---|---|
| Super Admin | Unrestricted access | 34 (all) |
| System Administrator | Users, roles, configuration | 15 |
| Basin Manager | Basins, sub-basins, boundaries | 14 |
| GIS Officer | Map layers, geographic boundaries | 8 |
| Data Manager | Stations, uploads, transformation, QC | 15 |
| Data Analyst | Analysis and reports | 10 |
| Viewer | Read-only | 7 |

### Permission catalogue (34, across 9 modules)

| Module | Permissions |
|---|---|
| Dashboard (1) | `view dashboard` |
| Basin (5) | `view basins`, `create basins`, `edit basins`, `delete basins`, `import basin boundaries` |
| Sub-basin (4) | `view`, `create`, `edit`, `delete sub-basins` |
| GIS (3) | `view map`, `manage map layers`, `manage geographic boundaries` |
| Monitoring Stations (4) | `view`, `create`, `edit`, `delete stations` |
| Hydrological Data (6) | `view`, `upload`, `transform`, `approve`, `delete`, `export data` |
| Reports (3) | `view`, `generate`, `export reports` |
| Users (5) | `view`, `create`, `edit`, `deactivate users`, `assign roles` |
| System (3) | `manage settings`, `view activity logs`, `manage permissions` |

> The table above compresses repeated prefixes; the stored names are the full strings from the brief (e.g. `create basins`, `manage map layers`).

---

## 4. Enforcement layers

Authorization is enforced in four places, so hiding a button is never the only protection:

1. **Route middleware** — `permission:` on every protected route (Spatie `PermissionMiddleware`, aliased in `bootstrap/app.php` alongside `role` and `role_or_permission`).
2. **Policies** — `UserPolicy` (`viewAny`, `view`, `create`, `update`, `deactivate`, `assignRoles`) re-checked with `Gate::authorize()` inside the controller.
3. **Service guards** — `App\Services\Access\UserAccountService` owns account lifecycle rules and role-escalation guards.
4. **Account middleware** — `EnsureUserIsActive` runs on every web request and ends the session of a deactivated user; `LoginRequest` refuses authentication for inactive accounts.

### Business rules enforced server-side

| Rule | Where |
|---|---|
| Only a Super Admin can grant or revoke the Super Admin role | `UserAccountService::guardRoleAssignment` |
| A Super Admin cannot remove their own Super Admin role | `guardSelfRoleRemoval` |
| Nobody can deactivate their own account | `setActive` + `UserPolicy::deactivate` |
| At least one active Super Admin must remain | `setActive` |
| The Super Admin role always holds every permission (cannot be edited) | `RoleController::update` → 403 |
| An administrator cannot strip `manage permissions` from a role they hold themselves | `RoleController::guardOwnAccessManagement` |
| Deactivated accounts cannot sign in, and lose live sessions | `LoginRequest`, `EnsureUserIsActive` |

`Gate::before` returns `true` for Super Admin and `null` for everyone else, so the normal policy chain stays intact for all other roles.

---

## 5. Routes

| Method | URI | Name | Protection |
|---|---|---|---|
| GET | `/dashboard` | `dashboard` | `auth` + `permission:view dashboard` |
| GET | `/users` | `users.index` | `permission:view users` |
| GET | `/users/create` | `users.create` | `permission:create users` |
| POST | `/users` | `users.store` | `permission:create users` |
| GET | `/users/{user}/edit` | `users.edit` | `permission:edit users` |
| PATCH | `/users/{user}` | `users.update` | `permission:edit users` |
| PATCH | `/users/{user}/status` | `users.status` | `permission:deactivate users` |
| GET | `/access-control/roles` | `access.roles.index` | `permission:manage permissions` |
| GET | `/access-control/roles/{role}/edit` | `access.roles.edit` | `permission:manage permissions` |
| PUT | `/access-control/roles/{role}` | `access.roles.update` | `permission:manage permissions` |
| GET | `/api/v1/me` | — | `auth:sanctum` + `EnsureUserIsActive` |

Account settings (`/settings/profile|password|appearance`) remain available to every authenticated user. All routes use controller classes, so `route:cache` still works.

**Public self-registration is disabled.** Accounts are provisioned by administrators; `GET/POST /register` return 404. `RegisteredUserController` and `Auth/Register.jsx` remain in the tree, unrouted, for a future invitation flow.

---

## 6. Frontend integration

| File | Purpose |
|---|---|
| `resources/js/Hooks/useAuthorization.jsx` | Reads `auth.user`; exposes `can()`, `canAny()`, `hasRole()`, `isSuperAdmin` |
| `resources/js/Components/Can.jsx` | `<Can permission="create users">…</Can>` conditional rendering |
| `resources/js/Utils/navigation.js` | Every nav item carries a `permission`; unavailable modules also carry `phase` |
| `resources/js/Components/layout/SidebarContent.jsx` | Filters groups and items by permission; hides empty groups |
| `resources/js/Pages/Users/*` | Index (search, role/status filters, pagination), create, edit with role assignment and status control |
| `resources/js/Pages/AccessControl/Roles/*` | Role overview and the grouped permission matrix with per-group grant/clear and a sticky save bar |
| `resources/js/Components/Pagination.jsx`, `FlashMessage.jsx` | Shared table pagination and server-flash feedback |

Shared props now include `auth.user.roles`, `auth.user.permissions`, `auth.user.is_super_admin`, plus `flash` and `application`.

---

## 7. Seeded accounts (local only)

`DemoUserSeeder` refuses to run outside `local`. Password for all: `Password@123`.

| Role | Email |
|---|---|
| Super Admin | admin@eec-bhdms.local |
| System Administrator | sysadmin@eec-bhdms.local |
| Basin Manager | basin.manager@eec-bhdms.local |
| GIS Officer | gis.officer@eec-bhdms.local |
| Data Manager | data.manager@eec-bhdms.local |
| Data Analyst | data.analyst@eec-bhdms.local |
| Viewer | viewer@eec-bhdms.local |

`DatabaseSeeder` always runs `AccessControlSeeder`; demo accounts are added only in `local`.

---

## 8. Commands

```powershell
php artisan migrate                 # permission tables, tokens, user lifecycle columns
php artisan db:seed                 # roles + permissions (+ demo accounts when local)
php artisan test --compact          # expected: 57 passed (237 assertions)
npm run build                       # expected: built successfully
vendor\bin\pint --format agent      # formatting
```

Sanctum usage (machine clients only):

```powershell
# issue a token for a user, e.g. in tinker
$user->createToken('reporting-service')->plainTextToken

curl.exe -H "Authorization: Bearer <token>" http://127.0.0.1:8000/api/v1/me
```

---

## 9. Verification

### Automated (57 tests)

RBAC-specific coverage in `tests/Feature/AccessControlTest.php`:

- 7 roles / 34 permissions seeded; Super Admin holds all 34
- Viewer permission set contains only read capabilities
- Super Admin reaches `/users`; Viewer and Basin Manager receive 403
- Data Manager's roles/permissions arrive in Inertia props
- Deactivated account cannot sign in; live session ends on next request
- Self-deactivation blocked; last active Super Admin cannot be removed
- User creation assigns roles; non-Super-Admin cannot grant Super Admin
- Role permissions update; Super Admin role cannot be edited
- Sanctum token authenticates `/api/v1/me`; guests get 401
- Registration endpoints return 404

### Live HTTP matrix (verified against the running app)

| Account | `/dashboard` | `/users` | `/users/create` | `/access-control/roles` | `/settings/profile` |
|---|---|---|---|---|---|
| Super Admin | 200 | 200 | 200 | 200 | 200 |
| System Administrator | 200 | 200 | 200 | 200 | 200 |
| Viewer | 200 | **403** | **403** | **403** | 200 |
| Data Manager | 200 | **403** | **403** | **403** | 200 |

Sidebar for a Viewer shows Dashboard plus locked (Phase-labelled) data modules only; Users and Roles are absent. Super Admin sees both as live links.

### Visual QA

`outputs/phase2-visual-qa/` — users index/create/edit, roles index/edit, mobile users table. No overflow at 1440×900, table aligned, role grid equal-height, Super Admin card locked with "Full access".

---

## 10. Decisions and deviations

| Item | Decision |
|---|---|
| Registration | Disabled (404). Self-service accounts would expose basin data to anyone with the URL. |
| Sanctum | Configured and exercised through `/api/v1/me`; browser navigation never uses tokens. |
| System Administrator scope | User/role/configuration management plus read-only visibility of data modules; no upload or approval rights. |
| Super Admin role | Locked in the UI and rejected by the controller; it always holds all permissions. |
| `is_active` default | Set on the model, not only the column, so factory- and code-created users are active immediately. |
| Dead dev-server pointer | `public/hot` advertised a Vite server that was not running, which blanked pages during QA. It was removed; the app now serves `public/build`. Run `npm run dev` when you want HMR. |
| Unused `Auth/Register.jsx` + `RegisteredUserController` | Kept for a future admin-invited onboarding flow; not routed. |

---

## 11. Phase gate

- [x] Session login/logout, forgot/reset password, profile and password change working
- [x] Sanctum installed, token-authenticated endpoint verified
- [x] Spatie installed, config/migrations published
- [x] 7 roles and 34 permissions seeded and mapped
- [x] Route middleware, policies and service guards on every sensitive operation
- [x] Permission-aware sidebar, actions and direct-URL protection
- [x] Seven role accounts seeded and exercised
- [x] 57 tests passing; build green; Pint clean

**Ready for Phase 3 — Database migrations and seeders** (basin, sub-basin, station and hydrological schema foundations).
