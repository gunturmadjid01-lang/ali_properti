# Audit Scope Data Perumahan Aktif

Tanggal audit: 19 Juli 2026

## Kontrak

- `owner` dan `super_admin` melihat data konsolidasi seluruh perumahan tanpa wajib memilih perumahan.
- Semua role lain hanya boleh membaca, mencari, mencetak, mengekspor, membuka detail, mengubah, atau membuat data untuk perumahan aktif yang ditugaskan kepadanya.
- Scope wajib diterapkan di backend. Menyembunyikan pilihan atau baris di frontend saja tidak memenuhi kontrak.
- Data tanpa perumahan hanya boleh tetap global bila benar-benar merupakan master perusahaan, konfigurasi, chat, profil, atau data organisasi nonproyek.

## Status fondasi

- Payload Inertia menyediakan daftar perumahan yang ditugaskan dan perumahan aktif.
- Owner/super admin tidak lagi diberi perumahan aktif otomatis dan pemilih perumahan disembunyikan.
- Concern `ScopesActivePerumahan` berlaku bagi seluruh role non-owner/non-super-admin, bukan hanya role Marketing.
- Dashboard non-owner menerima `active_perumahan_id`; dashboard owner/super admin selalu konsolidasi.
- Endpoint opsi bisnis dan kelompok controller Marketing sudah memakai concern scope aktif.
- Reservasi sudah memfilter daftar, statistik, tahun, customer, unit, rekening, detail, edit, lock, dan aksi berdasarkan perumahan aktif.
- Mutasi & Saldo Rekening membaca jurnal Kas/Bank berdasarkan rekening dan mengikuti perumahan aktif; parameter rekening dari perumahan lain tidak digunakan.

## Modul yang sudah memakai concern scope aktif

- Opsi bisnis (perumahan/unit/bank/produk/dokumen)
- Marketing: dashboard, calon customer, follow-up, sumber lead, survei, laporan lead/pipeline, operasional, tools, SPR, penjualan cash, dan reservasi
- Jadwal lapangan

## Celah prioritas tinggi

Controller berikut membaca data proyek/perumahan tetapi belum memakai kontrak scope aktif secara konsisten. Masing-masing perlu diperiksa pada daftar, statistik, dropdown, detail/edit berdasarkan ID, mutasi, cetak, dan ekspor:

- Approval pusat
- Rekening bank dan seluruh laporan Keuangan/Akuntansi
- Penerimaan, piutang, tagihan/talangan, dan Kas Kecil
- Penjualan terintegrasi dan seluruh workspace proses penjualan
- Laporan pusat, laporan progress pembangunan, dan laporan pemakaian material
- Unit rumah, pemilik unit, progress pembangunan, inspeksi mutu, pengawasan lapangan, dan laporan lapangan
- Gudang, stok material, permintaan, pembelian, retur, pemakaian, serta transaksi logistik
- SPK kontraktor dan template SPK
- Inventaris perusahaan dan alat berat
- Master/perincian legalitas rumah, rekening perumahan, serta overview manajemen

## Risiko yang masih terbuka

- Sebagian halaman daftar dapat terlihat benar tetapi URL detail/edit langsung masih dapat membuka record perumahan lain.
- Query statistik, chart, `distinct year`, dan total nominal dapat tetap mencampur perumahan walaupun tabel utama difilter.
- Query `DB::table`, relasi tidak langsung (SPR melalui unit, approval melalui model polimorfik), serta ekspor/cetak tidak otomatis mengikuti concern.
- Validasi `exists:*` tanpa pembatas perumahan dapat menerima ID milik proyek lain.
- Test suite SQLite saat ini berhenti pada migration yang menjalankan `SET FOREIGN_KEY_CHECKS=0`, sehingga pengujian isolasi lintas perumahan belum dapat dijalankan sampai migration tersebut dibuat driver-aware.

## Definition of done lanjutan

Setiap kelompok modul dianggap selesai hanya bila feature test membuktikan:

1. User yang ditugaskan ke perumahan A tidak melihat record perumahan B.
2. Pergantian header A ke B mengganti tabel, statistik, chart, opsi, cetak, dan ekspor.
3. URL detail/edit/delete/lock record B ditolak ketika A aktif.
4. Payload create/update yang membawa ID perumahan atau unit B ditolak.
5. Owner dan super admin tetap melihat hasil konsolidasi tanpa session perumahan aktif.
