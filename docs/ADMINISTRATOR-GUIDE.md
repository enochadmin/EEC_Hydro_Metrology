# Administrator guide

Administration lives under **Administration**: Users, Roles & permissions,
Activity logs and System settings.

## Users

**Users → New user** creates an active account with a name, email, password and
roles.

- **Edit** changes name, email, roles and optionally the password.
- **Deactivate** blocks sign-in and ends the session without deleting history;
  reactivate at any time.
- **Reset password** (account page) sets a new password. It is hashed on save and
  never written to the activity log.
- **Account activity** lists that user's audit entries.

An administrator cannot remove their own access-management permission, and only a
Super Admin can exceed their own permission set.

## Roles

**Roles & permissions → New role** creates an empty role; open it to grant or
remove permissions.

- The Super Admin role is locked: it always holds every capability through a Gate
  bypass and cannot be edited or deleted.
- A role with members cannot be deleted; reassign them first.
- A non-Super-Admin cannot grant a permission they do not hold, which blocks
  privilege escalation through this screen.

## System settings

| Tab | Controls | Effect |
|---|---|---|
| Application | app name, organisation, sender address, map centre/zoom, default basemap, upload limits, preview rows, import chunk, quality targets | applied to configuration on the next request |
| Parameters | code, name, category, value type, unit, decimals, expected range, active | drives report aggregation and import validation |
| Units | code, name, symbol, dimension, decimals | selectable as a parameter's default unit |
| Station categories | label, colour, active state, order | labels used across station screens |

Destructive rules:

- A parameter with observations cannot be deleted; deactivate it instead.
- A unit referenced by a parameter or observation cannot be deleted.
- A parameter's value type cannot change after creation, because that would
  silently alter how existing readings are aggregated.

## Activity logs

Recorded: sign-in and sign-out, record creation/updates/deletion, workbook
uploads, dataset approvals, GIS boundary imports, permission and role changes,
and settings changes — with user, event, affected record, changed values, IP and
timestamp.

Credentials are never recorded: the logger takes a description and a property
array, and the observer redacts password, token and secret fields before writing.
Entries are append-only and filterable by event, user and date.

## Routine tasks

| Task | How |
|---|---|
| Verify the nightly backup | files in `storage/app/backups`; see `docs/BACKUP-RECOVERY.md` |
| Audit a change | filter the activity log by event or record |
| Retire a user | deactivate, then remove roles if no access should remain |

## Roles at a glance

| Role | Intended use |
|---|---|
| Super Admin | unrestricted, break-glass only |
| System Administrator | users, roles, settings, activity logs |
| Data Manager | imports, approvals, stations, restricted exports |
| Basin Manager | basin/sub-basin registry and boundaries |
| GIS Officer | map layers and boundary imports, no registry edits |
| Data Analyst | analysis and approved exports |
| Viewer | read-only access to published data |
