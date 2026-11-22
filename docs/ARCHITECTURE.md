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
│       ├── Pos/             # sesi kasir, order POS, pembayaran
│       └── Membership/      # contoh modul kustom yang memperluas Kontak
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

- Modul di-install / upgrade / uninstall seperti Apps di Odoo (status di tabel `modules`). Uninstall menghapus tabel & data modul permanen. Detail & cara membuat modul: [MODULES.md](MODULES.md).
- Modul bisa memperluas modul lain (field model + slot form frontend) tanpa mengubah kodenya.
- Komunikasi antar modul lewat Service & Event (mis. `SalesOrderConfirmed` → Inventory membuat stock move).
- Frontend membaca menu dari `/api/menu` (hanya modul aktif & item yang boleh diakses user)
  dan daftar semua modul dari `/api/modules` (halaman Pengaturan → Modul).
- Route semua modul selalu terdaftar; middleware `module:{Nama}` menolak (404) jika modul belum terpasang.
- Migration modul tidak ikut `php artisan migrate`; dijalankan saat install / upgrade.
- Perintah: `php artisan module:list | module:install | module:upgrade | module:uninstall | module:sync`.

## Fitur inti untuk modul

- **Kolom audit:** `$table->auditColumns()` di migration + trait `HasAudit` di model → `created_by` / `updated_by` terisi otomatis.
- **List standar:** `App\Core\Http\ListQuery::paginate()` mendukung `?search=`, `?sort=`, `?direction=`, `?per_page=`, `?page=`;
  `ListQuery::filterActive()` untuk `?active=1|0|all` (arsip ala Odoo).
- **Penomoran dokumen:** modul mendeklarasikan `sequences.php`; ambil nomor dengan
  `app(SequenceService::class)->next('sales.order')` (aman untuk transaksi bersamaan, reset tahunan/bulanan).
- **Data awal modul:** `"seeders"` di `module.json`, dijalankan saat modul di-install / upgrade (harus idempoten).

## Frontend

- Setiap modul punya `src/modules/{modul}/` berisi `api.ts` (hook TanStack Query), `types.ts`, `pages/`, dan `routes.ts`
  (daftar halaman + permission). Router menggabungkan `routes.ts` semua modul.
- Komponen bersama: `DataTable` (sort & paginasi server), `SearchInput`, `ArchiveFilter`, `PageHeader`,
  `RecordPicker` (field relasi dengan pencarian), `Field`/`FormSection`, `ConfirmDialog`, `useFormState` (error 422 → field).
- State list (pencarian, filter, halaman) disimpan di URL.

## Alur antar modul (Fase 2)

```
Purchase ──konfirmasi──▶ Inventory: transfer penerimaan ──validasi──▶ event PickingDone ──▶ Purchase: qty_received
         ──buat tagihan─▶ Invoicing: tagihan (in_invoice) ──ubah/posting/batal──▶ event InvoiceChanged ──▶ Purchase: qty_billed
Sales    ──konfirmasi──▶ Inventory: transfer pengiriman ──validasi──▶ PickingDone ──▶ Sales: qty_delivered
         ──buat faktur──▶ Invoicing: faktur (out_invoice) ──▶ InvoiceChanged ──▶ Sales: qty_invoiced
```

- Inventory & Invoicing tidak tahu apa-apa tentang Sales/Purchase. Dokumen menyimpan asalnya di
  `origin_type` / `origin_id` (dan `origin_line_type` / `origin_line_id` per baris); listener di provider
  Sales/Purchase memperbarui jumlahnya.
- Hanya produk `storable` yang mengubah stok. `consumable` dicatat perpindahannya; `service` tidak bisa dipindahkan.
- Validasi transfer gagal (422) bila stok di lokasi asal tidak cukup.
- Nominal: float dibulatkan 2 desimal per baris (`App\Core\Support\LineAmounts`), sama seperti Odoo.
- Pelanggaran aturan bisnis dilempar sebagai `App\Core\Exceptions\BusinessException` → HTTP 422 dengan pesan untuk user.

## POS (Fase 3)

Mengikuti `point_of_sale` Odoo 19 Community (dipelajari dari source-nya, kode ditulis sendiri):

```
Dasbor POS ─buka─▶ sesi: opening_control ─kas awal─▶ opened ─tutup (kas terhitung)─▶ closed
                                         │
                          layar kasir: keranjang (localStorage) → bayar → POST pesanan
                                         │
     pos_orders (paid) ──▶ Inventory: transfer pengiriman/retur divalidasi saat itu juga (boleh stok minus)
                       └─▶ Invoicing: faktur diposting & dilunasi bila "Faktur" dipilih (state → done)
     tutup sesi ──▶ semua pesanan paid → done (diposting)
```

- Kembalian = baris `pos_payments` tunai negatif (`is_change`), sehingga kas seharusnya = kas awal + Σ pembayaran tunai + Σ kas masuk/keluar.
- Retur = pesanan dengan jumlah negatif yang merujuk `refunded_line_id`; jumlah maksimal = jumlah asal − yang sudah diretur.
- Satu sesi aktif per Point of Sale.

## Autentikasi

- Login `POST /api/auth/login` → token Sanctum (Bearer), disimpan frontend di localStorage.
- `GET /api/auth/me` mengembalikan user + roles + permissions; role `super-admin` lolos semua izin.
- Role & permission selalu di guard `web` (lihat `User::$guard_name`), walau request memakai token Sanctum.

## Tampilan

- Layar utama berisi ikon aplikasi per modul aktif (seperti Odoo).
- Di dalam modul: bar atas berisi nama modul + menu item modul; menu user di kanan.

## Modul MVP

1. **Base** — login, user, role/permission, profil perusahaan, penomoran dokumen (sequence), pengaturan.
2. **Contacts** — partner (perorangan/perusahaan), alamat, flag customer/vendor.
3. **Product** — produk (barang/jasa), kategori, satuan (UoM), harga jual/beli, barcode.
4. **Inventory** — gudang, lokasi, stok (quant, jumlah saja), transfer (receipt, delivery, internal), penyesuaian stok.
5. **Invoicing (Faktur)** — faktur pelanggan & tagihan pemasok, posting (nomor), pembayaran.
6. **Sales** — penawaran → pesanan → pengiriman → faktur.
7. **Purchase** — RFQ → pesanan pembelian → penerimaan → tagihan.
8. **POS** — sesi kasir (buka/tutup), transaksi cepat, metode pembayaran, struk, mengurangi stok.

Akuntansi penuh (jurnal, COA), HR, dan Manufaktur masuk tahap berikutnya.
Untuk MVP, faktur/tagihan adalah dokumen dengan status (draft, posted, cancelled) + status bayar, belum membuat jurnal akuntansi.

## Konvensi

- API: `/api/{modul}/{resource}`, respons JSON lewat API Resource, paginasi standar Laravel.
- Dokumen bisnis punya `state` (mis. `draft`, `confirmed`, `done`, `cancelled`) dan nomor dari sequence (mis. `SO/2026/0001`).
- Semua tabel punya `created_by`, `updated_by`, timestamps; data master pakai soft delete.
- Uang disimpan `numeric(18,2)`, kuantitas `numeric(18,4)`.
