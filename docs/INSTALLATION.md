# Installation

EEC-BHDMS runs on Laravel 12 with a React/Inertia frontend, MariaDB (or MySQL 8)
and PHP 8.2+.

## Requirements

| Component | Version | Notes |
|---|---|---|
| PHP | 8.2 or newer | extensions: `pdo_mysql`, `mbstring`, `gd`, `zip`, `curl`, `fileinfo` |
| Composer | 2.x | |
| Node.js | 20 or newer | only needed to build assets |
| MariaDB | 10.4+ | MySQL 8 works; the code avoids features MariaDB lacks |
| Web server | nginx or Apache | document root must be `public/` |

## Local setup

```bash
git clone <repository> eec-bhdms
cd eec-bhdms

composer install
cp .env.example .env
php artisan key:generate
```

Create the database and point `.env` at it:

```sql
CREATE DATABASE eec_bhdms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```dotenv
DB_DATABASE=eec_bhdms
DB_USERNAME=root
DB_PASSWORD=
```

Then:

```bash
php artisan migrate
php artisan db:seed          # roles, units, parameters, basins, station categories
npm install
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`. Local demo accounts are created by `UserSeeder`
when `APP_ENV=local`; the password is the same for each and is documented in
`database/seeders/UserSeeder.php`. Change it before exposing the system.

## First administrator

On a fresh production database `UserSeeder` does not run. Create the first
account from the console:

```bash
php artisan tinker
>>> $u = App\Models\User::create(['name' => 'Administrator', 'email' => 'admin@example.org', 'password' => bcrypt('change-me-now'), 'is_active' => true, 'email_verified_at' => now()]);
>>> $u->assignRole('Super Admin');
```

Super Admin bypasses individual permission checks by design; create ordinary
administrators with the *System Administrator* role instead.

## Exercise the pipeline with the supplied workbook

```bash
php artisan test tests/Feature/Excel     # transformation against Rainfall Data.xlsx
php artisan test tests/Feature           # full application suite
```

## Storage

```bash
php artisan storage:link
```

Writable paths: `storage/`, `bootstrap/cache/`. On Linux:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

## Common problems

| Symptom | Cause |
|---|---|
| Blank page after login | a stale `public/hot` file points at a dev server that is not running. Delete it, or start `npm run dev`. |
| `mysqldump was not found` | set `DB_DUMP_BINARY` to the full path, e.g. `C:\xampp\mysql\bin\mysqldump.exe`. |
| Map tiles show "API KEY REQUIRED" | a CARTO style is selected without a valid key. Remove `CARTO_BASEMAPS_KEY` or run `php artisan gis:basemap-key`. |
| Uploads rejected | check `excel.limits.max_upload_kb` in System settings and `client_max_body_size` in nginx. |
