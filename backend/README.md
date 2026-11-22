# ERP App — Backend (Laravel API)

## Kebutuhan
PHP 8.3+, Composer, PostgreSQL 16.

## Setup
```bash
cp .env.example .env   # isi DB_PASSWORD, dll
composer install
php artisan key:generate
php artisan migrate --seed     # juga menjalankan module:sync & membuat admin
php artisan serve              # http://localhost:8000
```

Login default: `admin@example.com` / `password` (ubah via `ADMIN_EMAIL` / `ADMIN_PASSWORD` sebelum seed).

## Modul (Apps)
Modul ada di `modules/{Nama}`. Install / uninstall lewat **Pengaturan → Modul** atau CLI:

```bash
php artisan module:list
php artisan module:install Sales      # dependensi ikut terpasang
php artisan module:upgrade            # jalankan migration baru modul terpasang
php artisan module:uninstall Sales    # HAPUS PERMANEN data modul
```

Migration modul tidak ikut `php artisan migrate`, tapi dijalankan saat install / upgrade.
Panduan lengkap & cara membuat modul kustom: [`docs/MODULES.md`](../docs/MODULES.md).

## Tes
Butuh database `erp_app_test` (lihat `phpunit.xml`).
```bash
php artisan test
```
