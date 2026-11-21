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

## Modul
Setiap modul ada di `modules/{Nama}` dengan `module.json`, `permissions.php`,
`routes/api.php`, dan `Database/Migrations`. Lihat `docs/ARCHITECTURE.md` di root repo.

```bash
php artisan module:list
php artisan module:enable Sales     # dependensi ikut aktif
php artisan module:disable Sales
php artisan module:sync             # setelah menambah modul / permission baru
```

## Tes
Butuh database `erp_app_test` (lihat `phpunit.xml`).
```bash
php artisan test
```
