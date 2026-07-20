# Audit Integrasi Lock dan Approval

Tanggal audit: 16 Juli 2026

## Standar wajib modul baru

Setiap modul bisnis baru wajib:

1. Memiliki status `draft` dan `locked`, beserta `locked_at` dan `locked_by`.
2. Menolak perubahan dan penghapusan ketika data sudah locked.
3. Mendaftarkan model dan label modul di `App\Support\ApprovalResources`.
4. Saat lock memanggil `ApprovalWorkflowService::submitLocked()`.
5. Saat unlock memanggil `ApprovalWorkflowService::cancelPendingLock()`.
6. Menampilkan tombol approval hanya setelah data locked/final.
7. Mengikuti 0 tahap (auto approve), 1 tahap, 2 tahap, atau 3 tahap dari Setting Approval.
8. Memiliki feature test untuk auto approve, approval bertahap, reject, dan unlock.

## Status audit

### SUPPORT

- Pengisian Kas Kecil: **SUPPORT PENUH**. Setiap user otomatis memiliki satu rekening kas kecil pribadi dengan saldo awal Rp0; tidak ada lagi pengajuan pembentukan rekening. Pemilik rekening membuat draft pengisian dan menguncinya melalui `petty-cash-funding`; tahap serta role reviewer dibaca dari Setting Approval, reject mengembalikan status pengajuan, dan setelah approval final dana tetap menunggu pencairan oleh user berizin keuangan. Saldo, ledger, dan jurnal baru dibentuk secara idempoten setelah bukti transfer pencairan diunggah.
- Reservasi Unit dan Booking Fee: **SUPPORT PENUH**. Marketing mengisi data reservasi, metode penerimaan, rekening proyek/Kas Kecil, dan bukti Booking Fee dalam satu draft. Lock hanya mengirim satu resource `housing-reservation`. Tahap 1 dari Setting Approval merupakan tahap verifikasi transaksi Keuangan: reviewer melengkapi waktu dana diterima, bukti penyelesaian, dan catatan verifikasi. Tahap 2 dan tahap selanjutnya memakai tombol approve/reject biasa tanpa membuka ulang form verifikasi Keuangan. Reservasi dengan metode pembayaran `cash` melewati tahap verifikasi transaksi Keuangan dan langsung diteruskan ke tahap approval berikutnya; bila tidak ada tahap berikutnya, approval diselesaikan otomatis. Approval final secara idempoten menahan unit, menerbitkan invoice, membukukan penerimaan, melunasi Booking Fee, dan mengisi Kas/Bank atau Kas Kecil sesuai kanal. Reject mengembalikan reservasi menjadi draft privat dan melepaskan unit.
- Penyetoran Kas Kecil: **SUPPORT PENUH** melalui `petty-cash-deposit`. Pemegang Kas Kecil membuat draft setoran, memilih rekening Kas Perusahaan tujuan, dan mengunggah bukti serah-terima, lalu lock memakai Setting Approval. Approval final secara idempoten mengurangi saldo Kas Kecil, menambah rekening Kas/Bank tujuan melalui jurnal yang membawa `master_bank_id`, dan membentuk ledger keluar; reject dan unlock tidak memindahkan saldo.
- Penggajian Pegawai: **SUPPORT PENUH**. Role Keuangan memiliki akses default melalui permission `payroll.view/manage`; menu berada di Keuangan & Akuntansi > Akuntansi; satu draft privat dapat memuat banyak pegawai aktif yang sudah memiliki jabatan, wajib memilih perumahan pembebanan dan rekening pembayaran perusahaan, lock melalui `employee-payroll`, tabel menampilkan tahap dan aksi review dari `canReview()`, unlock membatalkan request pending, approval final mengesahkan nilai batch dan membentuk satu jurnal Beban Gaji terhadap Kas/Bank secara idempoten dengan referensi perumahan dan rekening, dan invoice transaksi mencetak seluruh slip gaji dengan watermark selama belum final.
- Panjar Pegawai: **SUPPORT PENUH**. Draft privat memilih pegawai aktif dengan jabatan, periode potong, perumahan pembebanan, dan rekening sumber perusahaan; lock melalui `employee-advance`, approval final membentuk satu jurnal Piutang Pegawai terhadap Kas/Bank secara idempoten dengan referensi perumahan dan rekening, lalu otomatis dialokasikan sebagai potongan slip payroll pada periode tujuan yang sama dan dilunasi melalui jurnal payroll. Statistik panjar dan payroll mendukung rentang bulan, mengikuti perumahan aktif, serta mengisi bulan kosong dengan nilai nol.
- Penerimaan Customer: **SUPPORT PENUH**. Draf privat milik pembuat, bukti transfer dan alokasi tersimpan, lock mengirim `customer-receipt` ke Setting Approval 0–3 tahap, tabel menampilkan tahap aktif dan `canReview()`, unlock membatalkan request pending, dan approval final secara idempotent mengalokasikan pembayaran, memperbarui piutang, membentuk jurnal, serta mengaktifkan kuitansi final.
- Tagihan Tambahan & Talangan Customer: **SUPPORT PENUH**. Draf privat, finalisasi melalui `customer-charge`, approval final membentuk invoice/piutang dan jurnal secara idempotent. Talangan mewajibkan rekening sumber, penerima, dan bukti pembayaran. Reversal memakai resource approval terpisah `customer-charge-reversal`, ditolak bila invoice sudah memiliki pembayaran, serta membentuk jurnal pembalik tanpa mengubah SPR/kontrak final.
- Pembayaran SPR lama: **DIHAPUS / DIKONSOLIDASIKAN**. Controller, route, halaman, model, permission, registry approval, `spr_payments`, dan `spr_billing_schedules` telah dihapus. Booking Fee, DP, pembayaran tagihan, percepatan, dan pembayaran lebih memakai Penerimaan Customer sebagai satu-satunya sumber.
- Reservasi Perumahan: **TERINTEGRASI** melalui satu resource `housing-reservation` untuk reservasi sekaligus penerimaan Booking Fee; tidak ada pengajuan pembayaran kedua. Tahap dan role reviewer seluruhnya berasal dari Setting Approval. Lock/final approval menandai unit sebagai `booking` secara idempoten, sedangkan reject, kedaluwarsa, atau pembatalan yang sah melepaskannya kembali menjadi `tersedia`.
- Tahapan Penjualan sampai Customer Menempati Unit: **SUPPORT PENUH**. Cash Bertahap, KPR Developer, dan KPR Bank memiliki template proses berbeda tetapi berlanjut ke pembangunan, inspeksi mutu, serah terima internal, BAST dan kunci, mulai dihuni, masa pemeliharaan, hingga transaksi selesai. Setiap tahap berurutan, menyimpan tanggal/catatan/bukti, lock melalui `sales-process-step`, menampilkan tahap approval dan `canReview()`, serta side effect final bersifat idempoten. Unit berubah menjadi `terjual` ketika kontrak/akad final disetujui atau unit transaksi sudah siap huni, dan menjadi `ditempati` setelah tahap mulai huni; pemanggilan ulang approval juga merekonsiliasi side effect yang pernah tertinggal.
- Workspace Operasional Tahapan Penjualan: **SUPPORT PENUH**. Form generik telah diganti definisi field per tahap (analisis kemampuan bayar, validasi dokumen, SLIK, appraisal, keputusan bank, SP3K, persiapan/akad, pencairan, pembangunan, QC, BAST, huni, garansi). Setiap tahap mendukung PIC, field terstruktur, checklist wajib, banyak dokumen dengan nomor/tanggal/masa berlaku, dependency paralel, dan validasi domain sebelum lock. QC menolak unit di bawah 100% atau defect kritis terbuka; transaksi tidak dapat ditutup jika piutang masih tersisa.
- Akad/Serah Terima/Refund SPR legacy: **DIHAPUS**. Menu, route, controller, halaman milestone, model, permission hard-coded, dan tabel milestone lama dihapus. Akad dan serah terima kini merupakan tahap transaksi universal; reversal/refund tidak lagi boleh memakai approval manager-owner hard-coded.
- Kontrak Cash Bertahap dan KPR Developer: **SUPPORT** untuk finalisasi jadwal. Jadwal utama tidak dibuat pada approval SPR. Cash Bertahap dibuat idempoten setelah tahap `Penandatanganan Kontrak` disetujui final; KPR Developer dibuat setelah tahap `Persetujuan Pembiayaan Developer` disetujui final. Approval model kontrak tersendiri tidak lagi menjadi pemicu tagihan.
- Uang Muka Penjualan: **SUPPORT**. Approval final SPR membentuk satu tagihan Uang Muka secara idempoten dengan jatuh tempo dari SPR. Jika Booking Fee termasuk DP, tagihan hanya sebesar kekurangan DP. Pokok Cash Bertahap, KPR Developer, dan pelunasan Cash memakai harga final setelah dikurangi Booking Fee/DP yang diperhitungkan agar tidak terjadi tagihan ganda.
- Struktur Pembiayaan dan Pencairan KPR Bank: **SUPPORT**. Struktur harga, plafon, DP, kekurangan, biaya dan SP3K menghasilkan tagihan internal/pencairan hanya setelah approval final `bank-kpr-financing`. Pencairan dapat bertahap, wajib memiliki bukti transfer, dan baru mengalokasikan piutang serta membentuk jurnal setelah approval final `bank-kpr-disbursement`; kedua side effect idempotent.
- Daftar Piutang dan Detail Transaksi Penjualan: **DIKECUALIKAN DARI APPROVAL** karena read-only. Keduanya membaca sumber tagihan/penerimaan yang sama; akses tab detail dikendalikan permission granular.
- Pengajuan SPR: **REFERENSI UTAMA**. Create menghasilkan draft privat milik pembuat; Lock memvalidasi ulang dan mengirim Setting Approval; data locked immutable; Manager/Owner hanya dapat Unlock; draft hasil Unlock kembali privat; tabel menampilkan tahap, role aktif, dan tombol review dari `canReview()`; workflow penjualan baru berjalan setelah approval final.
- Paket Persyaratan Dokumen Pelanggan: **SUPPORT**. Dibuat melalui wizard, dapat diterapkan lintas bank/produk/perusahaan/perumahan/kontrak kerja sama, memiliki draft/lock, menggunakan `submitLocked()`, menampilkan tahap dan aksi review dari `canReview()`, serta baru menjadi sumber checklist setelah approval final. Modul, URL, model, dan tabel persyaratan bank lama telah dimigrasikan lalu dihapus; endpoint SPR dan seeder hanya membaca paket terintegrasi ini.
- Master Bank Kredit, Cabang Bank, Produk Kredit Bank, dan Kerja Sama Bank-Perumahan: **SUPPORT**. Data baru tersimpan sebagai draf dan hanya terlihat pada halaman master asal. Tombol Finalisasi mengubah data menjadi locked dan memanggil `submitLocked()`; tabel menampilkan status approval, tahap aktif, serta approve/reject berdasarkan `canReview()`. Data locked tidak dapat diedit atau dihapus sebelum unlock, dan hanya data locked yang tersedia bagi dropdown serta modul lain.
- Filter Finalisasi Lintas Modul: **ATURAN GLOBAL QUERY**. Builder `finalized()` menjadi kontrak untuk seluruh lookup, dropdown, dashboard, laporan, dan relasi lintas modul. Query halaman asal sengaja tidak memakai filter ini agar pengguna tetap dapat menyelesaikan draf. Record lama pada master penjualan terintegrasi di-backfill menjadi locked untuk menjaga kelanjutan operasional; record baru selalu dimulai sebagai draf.
- Penanganan Proses Penjualan Gagal: **SUPPORT**. Usulan mengulang tahap, mengalihkan metode pembayaran, atau menutup transaksi sebagai gagal dibuat sebagai draf privat `sales-resolution-request`. Lock memanggil `submitLocked()`, tabel menampilkan tahap dan aksi dari `canReview()`, unlock membatalkan approval pending, dan side effect final idempoten. Pengalihan metode menghasilkan revisi SPR yang wajib dikoreksi dan diajukan kembali melalui lifecycle approval SPR; penutupan gagal melepaskan unit dan menyimpan sebab serta perlakuan dana untuk audit/statistik.
- Refund Booking Fee & Uang Muka: **SUPPORT PENUH**. Penutupan penjualan gagal dengan perlakuan dana `refund` otomatis membentuk work item Keuangan `customer-refund`. Nilai maksimal hanya berasal dari `paid_amount` tagihan Booking Fee/DP, potongan wajib dijelaskan dan refund plus potongan harus sama dengan dana eligible. Rekening sumber, tujuan, referensi, dan bukti transfer wajib sebelum lock; tahap/role dibaca dari Setting Approval dan tombol review memakai `canReview()`. Approval final mengalokasikan refund ke invoice asal, membatalkan sisa tagihan, membentuk jurnal Uang Muka Customer terhadap Kas/Bank serta pendapatan penalti bila ada, dan seluruh side effect idempoten.
- Repository Dokumen Customer: **DIKECUALIKAN DARI APPROVAL** karena merupakan penyimpanan dan versi file pendukung customer. SPR hanya memilih hubungan dokumen dari repository; persyaratan dan finalisasi tetap divalidasi serta di-approval pada modul SPR.
- Infrastruktur lock terpusat pada controller yang menggunakan `HandlesCrudLock`; status tabel per modul tetap harus diverifikasi dengan kontrak UI di `docs/ai-module-approval-contract.md`.
- Marketing Campaign, Template, Target, dan Komisi.
- Milestone KPR/Akad/Serah Terima.
- Pengawasan lapangan: defect, perubahan pekerjaan, tenaga kerja/alat, K3, dan serah terima internal.
- Seluruh transaksi arsip Aset Perusahaan.
- Seluruh transaksi arsip Alat Berat.
- Modul yang sudah terdaftar di `ApprovalResources` dan memakai alur lock terpusat.

### PERLU DIPERBAIKI SELANJUTNYA

Catatan: modul yang sudah dapat membuat `ApprovalRequest` tetapi tabelnya belum menampilkan status tahap dan tombol review dikategorikan parsial, bukan support penuh. Setting Approval tidak otomatis menambahkan kolom/tombol ke halaman modul.

| Modul | Status | Kekurangan utama |
| --- | --- | --- |
| Master tukang | BELUM SUPPORT | Belum memiliki finalisasi dan approval |
| Gaji tukang | BELUM SUPPORT | Belum memiliki finalisasi dan approval |
| Stock opname material | PARSIAL | Transaksi tersedia, tetapi controller belum memakai kontrak lock terpusat |
| Template SPK | BELUM SUPPORT | Belum memiliki finalisasi dan approval |
| Referensi material | BELUM SUPPORT | Belum memiliki lock/unlock |
| Kelompok material | BELUM SUPPORT | Belum memiliki lock/unlock |

### DIKECUALIKAN

- Chat, profil pengguna, pemilih perumahan aktif, dashboard/report read-only, dan Setting Approval.
- Modul tersebut bukan dokumen/transaksi bisnis yang memerlukan status final.
- Monitoring Jatuh Tempo Piutang Customer dikecualikan karena hanya membaca jadwal pembayaran resmi yang sudah terkunci. Pengaturan ambang peringatan bersifat operasional, dilindungi permission `receivables.settings`, dan tidak membuat posting keuangan maupun finalisasi transaksi.

Dokumen ini harus diperbarui setiap kali modul baru dibuat atau status integrasi berubah.
# Master Dokumen / Persuratan

- Status: dikecualikan dari Setting Approval.
- Alasan: modul ini merupakan master konfigurasi desain surat (tanpa finalisasi transaksi atau pengajuan). Dokumen hasil cetak hanya membaca data domain yang sudah ada dan tidak menimbulkan side effect bisnis.
# Absensi Pegawai (2026-07-17)

- `attendance` terdaftar pada `ApprovalResources` dan tampil pada Setting Approval.
- Setiap absen masuk/pulang dibuat sebagai event terpisah, langsung berstatus `locked`, lalu memanggil `ApprovalWorkflowService::submitLocked()`.
- Titik cabang wajib sudah locked/final; jarak dihitung ulang di server dan foto wajib disimpan sebagai bukti.
- Akses pegawai tanpa dashboard memakai Nomor Pegawai + PIN Absensi yang di-hash. Sesi tersebut hanya dilayani oleh endpoint `/absensi`.
- Rekap ringkas hari berjalan ditampilkan pada dashboard utama untuk pengguna yang memiliki akses organisasi/payroll.
- Absensi di luar radius dapat diteruskan setelah konfirmasi eksplisit dan disimpan dengan penanda `is_within_radius=false` untuk audit admin.
- `attendance-setting` adalah master Pengaturan Jam Absensi terpisah dari cabang, terdaftar di `ApprovalResources`, dan penyimpanannya memanggil `submitLocked()`.
- Modul admin daftar/detail dilindungi `attendance.view`; konfigurasi jadwal dilindungi `attendance.settings`.
- Periode Tagihan Air & Pembayaran Air: **SUPPORT PENUH**. Periode dikelola per perumahan oleh Admin dan pembayaran dicatat Keuangan berdasarkan pemilik unit aktif. Keduanya memakai draft/lock, `submitLocked()`, tahap/role dari Setting Approval, review melalui `canReview()`, unlock membatalkan request pending, dan status pembayaran menjadi lunas secara idempoten hanya setelah approval final.
