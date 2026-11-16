# Backup and recovery

## What is backed up

| Content | Where | Why it matters |
|---|---|---|
| Database | `storage/app/backups/eec-bhdms-db-*.sql.gz` | basins, stations, observations, users, audit trail |
| Uploaded workbooks and documents | `eec-bhdms-files-*.zip` | the original evidence for every imported record |
| GIS boundary files | inside the file archive (`storage/app/private`) | imported polygons |
| Application code | Git | reproducible from a tag |

Generated report exports (`storage/app/private/reports`) are disposable: they are
deleted after download and can be regenerated from the data.

## Running a backup

```bash
php artisan eec:backup                 # dump + archive + prune
php artisan eec:backup --keep=7        # this run only, keep 7 days
php artisan eec:backup --prune-only    # delete expired files, create nothing
```

The command writes a compressed logical dump with `mysqldump --single-transaction`,
so it does not lock the tables during the copy. The database password is passed
through the child environment, never as a command-line argument.

If `mysqldump` is not on `PATH`, set the full path:

```dotenv
DB_DUMP_BINARY="C:\xampp\mysql\bin\mysqldump.exe"
# or /usr/bin/mysqldump
```

## Retention

`BACKUP_RETENTION_DAYS` (default 30) controls pruning. The nightly schedule runs
at `BACKUP_TIME` (default 01:30):

```cron
* * * * * cd /var/www/eec-bhdms && php artisan schedule:run >> /dev/null 2>&1
```

Copy backups off the server. A backup that only exists on the machine it protects
does not survive a disk failure:

```bash
rsync -az /var/www/eec-bhdms/storage/app/backups/ backup-host:/srv/eec-backups/
```

## Recovery

### Restore the database

```bash
gunzip -c eec-bhdms-db-YYYYmmdd-HHMMSS.sql.gz | mysql -u root -p eec_bhdms
```

If the dump is being restored into an existing database, drop and recreate it
first so stale rows cannot survive the restore.

### Restore files

```bash
cd /var/www/eec-bhdms/storage
unzip -o /path/to/eec-bhdms-files-YYYYmmdd-HHMMSS.zip
sudo chown -R www-data:www-data app
```

### Restore the application code

```bash
git fetch --tags
git checkout <tag>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize:clear && php artisan optimize
```

### Finish

```bash
php artisan storage:link
php artisan config:cache
php artisan queue:restart
```

Then sign in and check `/dashboard`, `/gis`, `/excel` and `/reports`. Re-run
`php artisan gis:basemap-key` if a CARTO key is configured, because the probe
cache is cleared by `optimize:clear`.

## Recovery drill

Once per quarter, on a staging copy:

1. Restore the most recent dump and file archive.
2. Run `php artisan migrate --force` and confirm `php artisan migrate:status` is clean.
3. Open a basin report and confirm the observation counts match production.
4. Record the elapsed time; that number is the real recovery time objective.

## Retention matrix

| Age | Where |
|---|---|
| 0–30 days | `storage/app/backups` on the server |
| 0–90 days | off-site copy (`rsync` target) |
| 90+ days | archive storage, kept for the audit period |
