# Panduan Modul (Apps)

ERP App memakai sistem modul seperti "Apps" di Odoo: setiap fitur bisnis adalah modul yang bisa
**di-install**, **di-upgrade**, dan **di-uninstall**, serta bisa **memperluas modul lain** tanpa mengubah kodenya.

Contoh lengkap modul kustom: **Keanggotaan** (`backend/modules/Membership` + `frontend/src/modules/membership`).
Modul ini menambah nomor member, level, dan tanggal bergabung ke form Kontak.

---

## 1. Siklus hidup modul

| Aksi | Yang terjadi |
|---|---|
| **Install** | Dependensi dipasang dulu → migration modul dijalankan → permission & penomoran dokumen dibuat → provider modul aktif → seeder (data awal) dijalankan. |
| **Upgrade** | Muncul bila versi di `module.json` naik atau ada migration baru. Menjalankan migration baru dan memperbarui permission, penomoran, dan data awal. |
| **Uninstall** | Modul yang bergantung padanya ikut di-uninstall (ditampilkan dulu di dialog konfirmasi). Migration dibatalkan (`down()`), jadi **tabel & data dihapus permanen**. Permission (dan relasinya ke role) serta penomoran dokumen modul juga dihapus. Semua langkah berjalan dalam satu transaksi PostgreSQL: bila gagal di tengah jalan, tidak ada yang berubah. |

Modul `Base` wajib dan tidak bisa di-uninstall. Semua modul otomatis bergantung pada `Base`.

> ⚠️ Backup database sebelum uninstall di server produksi.

Lewat UI: **Pengaturan → Modul**. Lewat CLI:

```bash
php artisan module:list
php artisan module:install Sales          # dependensi ikut terpasang
php artisan module:upgrade                # semua modul yang perlu upgrade
php artisan module:upgrade Sales
php artisan module:uninstall Sales        # minta konfirmasi; --force untuk melewati
php artisan module:sync                   # daftarkan modul baru di disk & pasang modul wajib
```

Catatan: `php artisan migrate` hanya menjalankan migration inti (`backend/database/migrations`).
Migration modul dijalankan oleh install / upgrade. Setelah `migrate:fresh`, jalankan `db:seed`
(atau `module:sync`) untuk memasang modul Base lagi.

---

## 2. Membuat modul baru

### Backend: `backend/modules/{Nama}/`

```
modules/Loyalty/
├── module.json                  # wajib
├── permissions.php              # opsional: ['loyalty.point.view' => 'Lihat poin', ...]
├── sequences.php                # opsional: penomoran dokumen
├── Database/Migrations/         # dijalankan saat install, dibatalkan saat uninstall
├── Database/Seeders/            # data awal (harus idempoten)
├── Models/  Http/  Services/
├── Providers/LoyaltyProvider.php  # opsional: untuk memperluas modul lain
└── routes/api.php               # otomatis di bawah /api/{route_prefix}
```

`module.json`:

```json
{
    "name": "Loyalty",
    "slug": "loyalty",
    "label": "Poin Loyalti",
    "description": "Poin belanja untuk pelanggan.",
    "category": "Penjualan",
    "author": "Tim Anda",
    "version": "1.0.0",
    "sequence": 70,
    "icon": "badge",
    "depends": ["Contacts", "Pos"],
    "providers": ["Modules\\Loyalty\\Providers\\LoyaltyProvider"],
    "seeders": ["Modules\\Loyalty\\Database\\Seeders\\LoyaltySeeder"],
    "menu": [
        { "label": "Poin", "path": "/loyalty/points", "permission": "loyalty.point.view" }
    ]
}
```

| Kunci | Keterangan |
|---|---|
| `name` | Nama unik (PascalCase), sama dengan nama folder & nama modul frontend. |
| `version` | Naikkan setiap rilis. Modul terpasang akan berstatus "perlu upgrade". |
| `depends` | Modul yang wajib terpasang lebih dulu. |
| `route_prefix` | Default = `slug`. Route modul ada di `/api/{route_prefix}/...`. |
| `providers` | Service provider yang hanya aktif bila modul terpasang. |
| `seeders` | Dijalankan saat install & upgrade. Gunakan `firstOrCreate` agar aman diulang. |
| `menu` | Item menu; disembunyikan bila user tidak punya `permission`. |

Setelah membuat folder, jalankan `composer dump-autoload` lalu `php artisan module:sync`.
Modul akan muncul di halaman Apps.

Aturan migration:
- Selalu tulis `down()` yang benar, karena itulah yang dijalankan saat uninstall.
- Nama file diawali tanggal (`2026_12_01_000000_...`) agar urutannya benar.
- Pakai `$table->auditColumns()` untuk kolom `created_by` / `updated_by`.

### Frontend: `frontend/src/modules/{nama}/`

```ts
// src/modules/loyalty/index.ts
import type { FrontendModule } from '@/app/routes'
import { PointsPage } from './pages/PointsPage'

export const loyaltyModule: FrontendModule = {
  name: 'Loyalty', // sama dengan backend
  routes: [{ path: 'loyalty/points', component: PointsPage, permission: 'loyalty.point.view' }],
}
```

Lalu daftarkan di `src/modules/registry.ts`. Halaman modul yang belum terpasang otomatis
menampilkan "Modul … belum terpasang".

---

## 3. Memperluas modul lain (mirip `_inherit` di Odoo)

Contoh: modul Keanggotaan menambah field ke Kontak.

**a. Tambah kolom lewat migration modul Anda** (`down()` menghapusnya lagi):

```php
Schema::table('partners', function (Blueprint $table) {
    $table->string('member_number', 30)->nullable()->unique();
});
```

**b. Daftarkan field di provider modul Anda.** Field otomatis menjadi fillable dan ter-cast,
divalidasi di API simpan, dan ikut dikirim di respons API:

```php
class MembershipProvider extends ModuleProvider
{
    protected string $module = 'Membership';

    public function boot(): void
    {
        $this->extendModel(Partner::class)
            ->field('member_level', ['nullable', Rule::in(['silver', 'gold'])], label: 'level member')
            ->field('member_since', ['nullable', 'date'], cast: 'date:Y-m-d', label: 'member sejak')
            // aturan yang bergantung pada request → pakai closure
            ->field('member_number', fn () => [
                'nullable', Rule::unique('partners', 'member_number')->ignore(request()->route('partner')?->id),
            ], label: 'nomor member');
    }
}
```

Model yang bisa diperluas saat ini: `Partner` (Kontak) dan `Product` (Produk).
Untuk membuat model lain bisa diperluas, beri model itu trait `App\Core\Extensions\Extensible`, lalu gabungkan
`ModelExtensions::rules()` / `attributes()` di FormRequest-nya dan `ModelExtensions::values()` di Resource-nya
(lihat `PartnerRequest` & `PartnerResource`).

Di provider Anda juga bisa memakai fitur Laravel biasa: menambah relasi
(`Partner::resolveRelationUsing(...)`), mendengarkan event model, mendaftarkan listener, dan lain-lain.

**c. Tampilkan field di form lewat slot frontend:**

```ts
// src/modules/membership/index.ts
export const membershipModule: FrontendModule = {
  name: 'Membership',
  setup: () => registerSlot('contacts.partner.form', { module: 'Membership', component: MembershipFields }),
}
```

Komponen slot menerima `{ values, set, errors, readOnly }` dari form induk, sehingga field tambahan
ikut tersimpan bersama form. Slot yang tersedia:

| Slot | Lokasi |
|---|---|
| `contacts.partner.form` | Form Kontak, sebelum Catatan |
| `product.product.form` | Form Produk, sebelum Deskripsi |

Untuk menambah slot baru di sebuah form, letakkan `<ExtensionSlot point="..." ... />` di form tersebut.
