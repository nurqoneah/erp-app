# Arsitektur ERP App

Dokumen ini mencatat keputusan desain awal. Perbarui jika ada perubahan.

## Keputusan

| Topik | Keputusan |
|---|---|
| Struktur repo | Monorepo: `backend/` (Laravel API) + `frontend/` (React SPA) |
| Backend | Laravel 12, PHP 8.3, REST API (JSON) |
| Frontend | React + TypeScript + Vite |
| Database | PostgreSQL 16 |
| Auth | Laravel Sanctum (token / SPA cookie) |
| Hak akses | Spatie Laravel Permission (role & permission per modul) |
| UI | Tailwind CSS + shadcn/ui |
| Data fetching & tabel | TanStack Query + TanStack Table |
| Multi-company | Belum (single company). Tabel tetap disiapkan agar mudah ditambah nanti |

## Struktur folder

```
erp-app/
├── backend/                 # Laravel API
│   ├── app/
│   │   └── Core/            # Kernel modul: registry, base model, helper
│   └── modules/
│       ├── Base/            # user, role, setting, company profile, sequence
│       ├── Contacts/        # partner (customer & vendor)
│       ├── Product/         # produk, kategori, UoM
│       ├── Inventory/       # gudang, lokasi, stock move, stock quant
│       ├── Sales/           # quotation, sales order, invoice
│       ├── Purchase/        # RFQ, purchase order, vendor bill
│       └── Pos/             # sesi kasir, order POS, pembayaran
├── frontend/                # React SPA
│   └── src/
│       ├── app/             # router, layout, provider
│       ├── components/      # komponen UI bersama
│       └── modules/         # halaman per modul (cermin backend/modules)
└── docs/
```

## Sistem modul (ala Odoo)

Setiap modul backend berdiri sendiri:

```
modules/Sales/
├── module.json              # nama, versi, dependensi (mis. ["Contacts","Product"])
├── Database/Migrations/
├── Database/Seeders/
├── Models/
├── Http/Controllers/
├── Http/Requests/
├── Http/Resources/
├── Services/                # logika bisnis (konfirmasi SO, buat invoice, dll)
├── routes/api.php           # prefix /api/sales
└── permissions.php          # daftar permission modul
```

- Modul bisa diaktifkan / dinonaktifkan (tabel `modules`), dependensi dicek saat aktivasi.
- Komunikasi antar modul lewat Service & Event (mis. `SalesOrderConfirmed` → Inventory membuat stock move).
- Frontend membaca daftar modul aktif dari `/api/modules` untuk menampilkan menu.

## Modul MVP

1. **Base** — login, user, role/permission, profil perusahaan, penomoran dokumen (sequence), pengaturan.
2. **Contacts** — partner (perorangan/perusahaan), alamat, flag customer/vendor.
3. **Product** — produk (barang/jasa), kategori, satuan (UoM), harga jual/beli, barcode.
4. **Inventory** — gudang, lokasi, stok (quant), perpindahan stok (receipt, delivery, adjustment).
5. **Sales** — quotation → sales order → delivery → invoice.
6. **Purchase** — RFQ → purchase order → receipt → vendor bill.
7. **POS** — sesi kasir (buka/tutup), transaksi cepat, metode pembayaran, struk, mengurangi stok.

Akuntansi penuh (jurnal, COA), HR, dan Manufaktur masuk tahap berikutnya.
Untuk MVP, invoice/bill cukup dokumen dengan status (draft, posted, paid) dan pembayaran sederhana.

## Konvensi

- API: `/api/{modul}/{resource}`, respons JSON lewat API Resource, paginasi standar Laravel.
- Dokumen bisnis punya `state` (mis. `draft`, `confirmed`, `done`, `cancelled`) dan nomor dari sequence (mis. `SO/2026/0001`).
- Semua tabel punya `created_by`, `updated_by`, timestamps; data master pakai soft delete.
- Uang disimpan `numeric(18,2)`, kuantitas `numeric(18,4)`.
