# Pemetaan Area, Peran, dan Data yang Dikelola

Dokumen ini menjelaskan secara sederhana apa saja yang diolah oleh sistem informasi properti ini, lalu memetakan kebutuhan tiap area kerja ke data yang paling relevan.

Catatan:
- Penjelasan ini disusun dari struktur database, model, controller, dan permission yang sudah ada di project.
- Beberapa nama area seperti `owner`, `pimpro`, `pengawas`, atau `admin KPR` adalah istilah operasional. Di code, sebagian besar dipetakan lewat `role` dan `permission`, jadi implementasinya bisa disesuaikan.

## 1. Gambaran Besar Sistem

Sistem ini pada dasarnya mengolah 6 kelompok data utama:

1. Data organisasi dan akses
   - Cabang perusahaan
   - User
   - Role
   - Permission
   - Approval workflow

2. Data proyek properti
   - Perumahan
   - Detail rumah / unit
   - Progress pembangunan
   - Foto dan video perumahan
   - Promosi perumahan

3. Data konsumen dan penjualan
   - Customer
   - Dokumen customer
   - Booking / follow-up

4. Data legalitas
   - Dokumen legalitas perumahan
   - Dokumen legalitas rumah / unit

5. Data keuangan dan HPP
   - Transaksi keuangan
   - Master bank
   - Tipe post
   - Kelompok HPP
   - Perumahan HPP
   - Detail HPP
   - Realisasi HPP

6. Data logistik / gudang
   - Barang material
   - Stok material
   - Transaksi logistik
   - Detail transaksi logistik

## 2. Pemetaan Area Kerja

### 2.1 Area Owner

Fokus utama:
- Melihat ringkasan seluruh bisnis.
- Memantau performa proyek, penjualan, legalitas, keuangan, dan stok.
- Melihat approval yang penting.

Data yang biasanya dilihat:
- Semua perumahan dan cabang.
- Ringkasan customer dan status penjualan.
- Laporan keuangan.
- Laporan HPP.
- Status legalitas.
- Progres pembangunan.

Kemungkinan permission yang relevan:
- `dashboard.view`
- `laporan.view`
- `laporan.export`
- `approval.view`
- `approval.manage`

### 2.2 Area Manajer / Pimpro

Fokus utama:
- Mengawasi jalannya proyek perumahan.
- Mengkoordinasikan tim teknik, legal, gudang, dan marketing.
- Memastikan progress proyek sesuai target.

Data yang dikelola / dipantau:
- Data perumahan.
- Detail rumah / unit.
- Progress pembangunan.
- Status legalitas proyek.
- Ketersediaan material dan kebutuhan lapangan.

Kemungkinan permission yang relevan:
- `perumahan.view`
- `detail-rumah.view`
- `progress.view`
- `progress.create`
- `progress.update`
- `dokumen-legalitas.view`

### 2.3 Admin Keuangan

Fokus utama:
- Mencatat transaksi masuk/keluar.
- Menyusun laporan keuangan.
- Mengelola HPP dan realisasi biaya.
- Menyiapkan data bank dan kategori biaya.

Data yang dikelola:
- `transaksi_keuangans`
  - tanggal
  - nominal
  - keterangan
  - cabang
  - tipe post
  - user input
- `mater_banks`
  - kode bank
  - nama bank
  - nomor rekening
  - nama rekening
  - status
- `tipe_posts`
  - nama post
  - jenis pemasukan / pengeluaran
  - status
- `kelompok_hpps`
  - kategori biaya
  - nama item HPP
- `perumahan_hpps`
  - HPP per proyek
- `detail_perumahan_hpps`
  - volume
  - satuan
  - harga satuan
  - jumlah RAB
- `hpp_realisasis`
  - realisasi biaya aktual
  - target perumahan / rumah / kelompok HPP

Kemungkinan permission yang relevan:
- `keuangan.view`
- `keuangan.create`
- `keuangan.update`
- `keuangan.delete`
- `hpp.view`
- `hpp.create`
- `hpp.update`
- `hpp.delete`
- `master-bank.manage`
- `tipe-post.manage`
- `kelompok-hpp.manage`
- `laporan.view`
- `laporan.export`

### 2.4 Admin Konsumen

Fokus utama:
- Mengelola data customer.
- Menyimpan dokumen customer.
- Memantau proses follow-up dan booking.

Data yang dikelola:
- `costumers`
  - identitas customer
  - kontak
  - kebutuhan unit
  - status
- `dokumen_costumers`
  - jenis dokumen
  - file
  - status
- booking / follow-up customer
  - status kontak
  - kebutuhan unit
  - progres komunikasi

Kemungkinan permission yang relevan:
- `customer.view`
- `customer.create`
- `customer.update`
- `customer.delete`
- `customer.follow-up`
- `booking.manage`
- `dokumen-customer.view`
- `dokumen-customer.create`
- `dokumen-customer.update`

### 2.5 User Area Gudang

Fokus utama:
- Mengelola material dan stok.
- Mencatat keluar-masuk barang.
- Menyambungkan pemakaian material ke proyek atau rumah tertentu.

Data yang dikelola:
- `barang_materials`
  - kode barang
  - nama barang
  - satuan
  - harga HPP
  - status
- `stok_materials`
  - barang
  - cabang
  - qty stok
- `transaksi_logistiks`
  - kode transaksi
  - tanggal
  - jenis transaksi
  - perumahan / detail rumah / kelompok HPP tujuan
  - total nominal
  - keterangan
  - user input
- `transaksi_logistik_details`
  - barang
  - qty
  - satuan
  - harga satuan
  - subtotal

Kemungkinan permission yang relevan:
- `hpp.view`
- `hpp.create`
- `laporan.view`

### 2.6 Pengawas

Fokus utama:
- Memantau progres lapangan.
- Mencatat tahapan pembangunan.
- Mendokumentasikan kondisi proyek dengan foto.

Data yang dikelola:
- `progress_pembangunans`
  - detail rumah
  - tanggal
  - tahapan
  - persentase
  - keterangan
  - foto
  - user pencatat

Kemungkinan permission yang relevan:
- `progress.view`
- `progress.create`
- `progress.update`
- `progress.delete`

### 2.7 Bagian Legal

Fokus utama:
- Menyimpan dokumen legalitas proyek.
- Memastikan masa berlaku dokumen tidak lewat.
- Mengelola file legal per perumahan dan per unit.

Data yang dikelola:
- `dokumen_legalitas`
  - perumahan
  - nama dokumen
  - nomor dokumen
  - tanggal terbit
  - tanggal berakhir
  - file
  - status
- `dokumen_legalitas_rumahs`
  - perumahan
  - nama dokumen
  - tanggal terbit
  - tanggal berakhir
  - file
  - status

Kemungkinan permission yang relevan:
- `dokumen-legalitas.view`
- `dokumen-legalitas.create`
- `dokumen-legalitas.update`
- `dokumen-legalitas.delete`

### 2.8 Supervisor Marketing

Fokus utama:
- Mengawal lead dan calon pembeli.
- Memantau promosi dan ketersediaan unit.
- Mengatur follow-up dan booking.

Data yang dikelola:
- customer lead
- dokumen customer
- booking unit
- data perumahan dan detail rumah untuk informasi unit tersedia
- promosi perumahan
- video perumahan

Data pendukung yang juga sering dipakai:
- `perumahans`
- `detail_rumahs`
- `promosi_perumahans`
- `video_perumahans`
- `dokumen_costumers`

Kemungkinan permission yang relevan:
- `customer.view`
- `customer.create`
- `customer.update`
- `customer.follow-up`
- `booking.manage`
- `dokumen-customer.view`
- `dokumen-customer.create`
- `dokumen-customer.update`
- `perumahan.view`
- `detail-rumah.view`

### 2.9 Teknik

Fokus utama:
- Mengelola data teknis proyek.
- Memantau unit rumah, progress, dan kebutuhan lapangan.
- Berkoordinasi dengan gudang dan legal bila dibutuhkan.

Data yang dikelola:
- `perumahans`
- `detail_rumahs`
- `progress_pembangunans`
- `dokumen_legalitas`
- `hpp_realisasis`

Kemungkinan permission yang relevan:
- `perumahan.view`
- `detail-rumah.view`
- `detail-rumah.update`
- `progress.view`
- `progress.create`
- `progress.update`
- `dokumen-legalitas.view`
- `hpp.view`

### 2.10 Admin KPR

Di schema yang ada sekarang, belum terlihat tabel khusus KPR.
Namun secara fungsi, admin KPR biasanya mengelola:

- Data customer yang akan diajukan pembiayaan.
- Kelengkapan dokumen customer.
- Status proses pengajuan KPR.
- Referensi bank.
- Follow-up administrasi ke bank.

Data yang paling dekat di project saat ini:
- `costumers`
- `dokumen_costumers`
- `mater_banks`
- `transaksi_keuangans` jika ada biaya admin yang dicatat

Jika nanti ingin dibuat modul KPR penuh, biasanya akan ditambah:
- tabel pengajuan KPR
- status tahapan approval bank
- data akad
- data survey BI checking / SLIK

### 2.11 Area Marketing

Fokus utama:
- Menangani calon pembeli dari awal.
- Memberi informasi unit, brosur, dan jadwal survei.
- Menjaga pipeline penjualan sampai booking.

Data yang dikelola:
- `costumers`
- `dokumen_costumers`
- `perumahans`
- `detail_rumahs`
- `video_perumahans`
- `promosi_perumahans`

Permission yang cocok:
- `customer.view`
- `customer.create`
- `customer.update`
- `customer.follow-up`
- `booking.manage`
- `dokumen-customer.view`
- `dokumen-customer.create`
- `dokumen-customer.update`
- `perumahan.view`
- `detail-rumah.view`

## 3. Ringkasan Data Inti per Tabel

- `cabang_perusahaans`: identitas cabang, kontak, manager, status, alamat, koordinat.
- `perumahans`: data proyek perumahan, cabang, luas lahan, jumlah unit, lokasi, status.
- `detail_rumahs`: data unit rumah, nomor rumah, tipe, luas tanah, harga jual, status.
- `costumers`: data calon pembeli / customer.
- `dokumen_costumers`: file dokumen customer.
- `dokumen_legalitas`: legalitas perumahan.
- `dokumen_legalitas_rumahs`: legalitas unit rumah.
- `progress_pembangunans`: progres pekerjaan lapangan.
- `transaksi_keuangans`: transaksi keuangan per cabang.
- `mater_banks`: data rekening bank.
- `tipe_posts`: master jenis pemasukan dan pengeluaran.
- `kelompok_hpps`: master kategori biaya HPP.
- `perumahan_hpps` dan `detail_perumahan_hpps`: RAB / HPP per proyek.
- `barang_materials`: master material.
- `stok_materials`: stok material per cabang.
- `transaksi_logistiks` dan `transaksi_logistik_details`: mutasi material dan pemakaian.
- `hpp_realisasis`: realisasi biaya aktual terhadap target.
- `users`, `roles`, `permissions`: akses pengguna dan otorisasi.
- `approval_settings`, `approval_requests`: alur persetujuan.

## 4. Kesimpulan Singkat

Kalau disederhanakan, sistem ini mengolah:

- siapa user dan hak aksesnya,
- proyek properti apa yang dikerjakan,
- unit rumah apa saja yang tersedia,
- siapa customer-nya,
- dokumen legal apa yang sudah ada,
- uang keluar masuknya bagaimana,
- material gudang dan pemakaiannya bagaimana,
- serta progress pembangunan di lapangan.

Jadi, daftar area yang kamu lihat di website itu sebenarnya adalah pembagian kerja operasional di dalam sistem.
Masing-masing area fokus ke data yang berbeda, tetapi semuanya tetap terhubung ke `perumahan`, `detail rumah`, `customer`, `legal`, `keuangan`, dan `logistik`.
