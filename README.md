# ERP App

Aplikasi ERP (terinspirasi Odoo) dengan Laravel (backend API) + React (frontend SPA) + PostgreSQL.

- Arsitektur & keputusan desain: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- Roadmap: [docs/ROADMAP.md](docs/ROADMAP.md)

Modul MVP: Base, Contacts, Product, Inventory, Sales, Purchase, POS.

## Menjalankan secara lokal

Kebutuhan: PHP 8.3+, Composer, Node 22+, PostgreSQL 16.

```bash
# 1. Database
createuser -P erp            # password: secret (atau sesuaikan di backend/.env)
createdb -O erp erp_app
createdb -O erp erp_app_test # untuk tes

# 2. Backend (http://localhost:8000)
cd backend
cp .env.example .env         # isi DB_PASSWORD
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve

# 3. Frontend (http://localhost:5173) — terminal lain
cd frontend
cp .env.example .env
npm install
npm run dev
```

Login: `admin@example.com` / `password`. Aktifkan modul di **Pengaturan → Modul**.
