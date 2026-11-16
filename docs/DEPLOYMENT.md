# Deployment

Target: Ubuntu 22.04+, nginx, PHP-FPM 8.2, MariaDB 10.4+, the application in
`/var/www/eec-bhdms` owned by `www-data`.

## 1. Release the code

```bash
cd /var/www/eec-bhdms
git pull --ff-only
composer install --no-dev --optimize-autoloader
```

## 2. Environment

Copy `.env.production.example` to `.env` and fill every value. Then:

```bash
php artisan key:generate --force   # once, on a new server
```

Required production values:

| Key | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` — a stack trace must never reach a user |
| `APP_URL` | `https://` URL |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_ENCRYPT` | `true` |
| `QUEUE_CONNECTION` | `database` |
| `LOG_CHANNEL` | `daily`, `LOG_LEVEL=warning` |

## 3. Database

```bash
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force   # idempotent
php artisan db:seed --class=MeasurementUnitSeeder --force
php artisan db:seed --class=ParameterSeeder --force
php artisan db:seed --class=StationCategorySeeder --force
```

## 4. Assets

```bash
npm ci
npm run build          # writes public/build and removes any stale public/hot
```

Never deploy with `public/hot` present: it makes every page request load
JavaScript from a dev server that is not running, which shows as a blank page.

## 5. Storage and permissions

```bash
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

## 6. Web server and TLS

Install `deploy/nginx.conf` as `/etc/nginx/sites-available/eec-bhdms`, enable it,
then obtain a certificate:

```bash
sudo ln -s /etc/nginx/sites-available/eec-bhdms /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d bhdms.example.org
```

HSTS, `X-Frame-Options`, `X-Content-Type-Options` and a referrer policy are set
in the server block.

## 7. Queue worker

```bash
sudo cp deploy/supervisor-worker.conf /etc/supervisor/conf.d/eec-bhdms-worker.conf
sudo supervisorctl reread && sudo supervisorctl update
```

Workbook transformation and large exports run on the queue; without a worker
those jobs stay pending.

## 8. Scheduler

One cron entry runs everything, including the nightly `eec:backup`:

```cron
* * * * * cd /var/www/eec-bhdms && php artisan schedule:run >> /dev/null 2>&1
```

Verify with `php artisan schedule:list`.

## 9. Optimise

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Re-run these after every deployment that changes config, routes or views. To
roll back: `php artisan optimize:clear`.

## 10. Smoke test after each release

```bash
curl -sI https://bhdms.example.org/login | head -1        # 200
php artisan eec:backup --prune-only                       # exit 0
php artisan queue:monitor default:100
```

Then sign in and confirm: dashboard renders, `/gis` draws tiles, `/excel`
lists workbooks, `/reports` opens.

## Logging and monitoring

- `storage/logs/laravel-YYYY-MM-DD.log` — application log, rotated daily, 30 days.
- `storage/logs/worker.log` — queue worker output.
- `/excel/history` shows failed or abandoned imports.
- Activity logs (`/activity-logs`) record sign-ins, record changes, imports and
  permission changes.
- Watch disk usage under `storage/app/private` (uploads keep their original
  workbook) and `storage/app/backups`.

## Rollback

```bash
git reset --hard <previous-tag>
composer install --no-dev --optimize-autoloader
php artisan migrate:rollback --step=1   # only if the release added migrations
npm ci && npm run build
php artisan optimize:clear && php artisan optimize
sudo systemctl reload php8.2-fpm
```
