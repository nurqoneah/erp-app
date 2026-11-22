# Roadmap

## Fase 0 — Fondasi ✅
- [x] Scaffold `backend/` (Laravel 12 + PostgreSQL + Sanctum + Spatie Permission)
- [x] Scaffold `frontend/` (React + TS + Vite + Tailwind + shadcn/ui + TanStack)
- [x] Kernel modul (registry, `module.json`, aktivasi modul)
- [x] Auth (login/logout), layout aplikasi, menu dinamis
- [x] CI GitHub Actions (tes backend + lint/tes/build frontend)

## Fase 1 — Data master ✅
- [x] Base: user, role, permission, profil perusahaan, sequence
- [x] Contacts
- [x] Product (kategori, UoM)
- [x] Pesan validasi Bahasa Indonesia

## Fase 1.5 — Sistem App ala Odoo ✅
- [x] Install / upgrade / uninstall modul (uninstall menghapus data permanen, dependensi ikut)
- [x] Migration modul hanya dijalankan saat install / upgrade
- [x] Perluasan antar modul: field model, validasi, API, dan slot form frontend
- [x] Contoh modul kustom "Keanggotaan" + panduan `docs/MODULES.md`

## Fase 2 — Operasional ✅
- [x] Inventory: gudang & lokasi, stok per lokasi (jumlah saja), transfer (penerimaan, pengiriman, internal), penyesuaian stok
- [x] Faktur (modul baru): faktur pelanggan, tagihan pemasok, posting, pembayaran sebagian/lunas
- [x] Sales: penawaran → pesanan → pengiriman → faktur
- [x] Purchase: RFQ → pesanan pembelian → penerimaan → tagihan
- [x] Code splitting halaman per modul

Catatan batasan Fase 2 (menyusul):
- Nilai persediaan (FIFO / rata-rata) — bersama modul Akuntansi
- Pengiriman / penerimaan sebagian (backorder)
- Satuan beli berbeda dari satuan stok di baris pesanan (saat ini semua baris memakai satuan produk)
- Daftar pajak terdefinisi (saat ini tarif pajak diisi per baris dalam %)

## Fase 3 — POS
- [ ] Sesi kasir, layar kasir, pembayaran, struk, integrasi stok

## Berikutnya
- Akuntansi (COA, jurnal, laporan), HR, Manufaktur, multi-company, laporan & dashboard
