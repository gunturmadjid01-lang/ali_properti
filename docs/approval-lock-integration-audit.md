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

- Transaksi Kas & Bank Manual: **SUPPORT PENUH** melalui resource `financial-transaction`. Pemasukan non-customer dan pengeluaran manual kini disimpan sebagai draft privat, tidak langsung mengubah Kas/Bank atau jurnal. Pembuat mengunci transaksi melalui Setting Approval 0–3 tahap, tabel menampilkan tahap aktif serta aksi review dari `canReview()`, unlock membatalkan request pending, reject mengembalikan transaksi ke draft, dan approval final membentuk jurnal secara idempoten hanya jika `journal_id` belum terisi. Transaksi otomatis dari modul bisnis asal tetap mengikuti lifecycle modul asal dan tidak diajukan ulang sebagai transaksi manual.
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
- Pengawasan lapangan: defect, perubahan pekerjaan, tenaga kerja/alat, K3, dan serah terima internal. UI sudah dipisah per section dengan permission granular `field-supervision.{section}.{action}`, sementara approval final tetap memakai workflow pusat `field-supervision` agar relasi data, lock, dan review tetap satu jalur.
- Seluruh transaksi arsip Aset Perusahaan.
- Seluruh transaksi arsip Alat Berat.
- Modul yang sudah terdaftar di `ApprovalResources` dan memakai alur lock terpusat.

### PERLU DIPERBAIKI SELANJUTNYA

Catatan: modul yang sudah dapat membuat `ApprovalRequest` tetapi tabelnya belum menampilkan status tahap dan tombol review dikategorikan parsial, bukan support penuh. Setting Approval tidak otomatis menambahkan kolom/tombol ke halaman modul.

| Modul | Status | Kekurangan utama |
| --- | --- | --- |
| Stock opname material | SUPPORT PENUH | Draft tidak mengubah saldo; lock memakai Setting Approval dan approval final mem-posting koreksi, sedangkan unlock membalik transaksi stok lalu menandai approval sebagai reversed |
| Template SPK | BELUM SUPPORT | Belum memiliki finalisasi dan approval |
| Referensi material | BELUM SUPPORT | Belum memiliki lock/unlock |
| Kelompok material | BELUM SUPPORT | Belum memiliki lock/unlock |

### DIKECUALIKAN

- Chat, profil pengguna, pemilih perumahan aktif, dashboard/report read-only, dan Setting Approval.
- Modul tersebut bukan dokumen/transaksi bisnis yang memerlukan status final.
- Monitoring Jatuh Tempo Piutang Customer dikecualikan karena hanya membaca jadwal pembayaran resmi yang sudah terkunci. Pengaturan ambang peringatan bersifat operasional, dilindungi permission `receivables.settings`, dan tidak membuat posting keuangan maupun finalisasi transaksi.
- Laporan Keuangan (Buku Besar, Neraca Saldo, Laba Rugi, Neraca, Arus Kas, Aging Piutang, dan Aging Hutang) dikecualikan karena bersifat read-only. Seluruh angka berasal dari jurnal yang sudah diposting atau transaksi domain yang sudah final; filter perumahan dan periode diterapkan kembali di backend, sedangkan PDF/Excel memerlukan `laporan.export`.
- Jurnal Umum Manual: **SUPPORT PENUH**. Penyimpanan membuat draft privat; lock mengirim resource `manual-journal` ke Setting Approval; jurnal draft/locked tidak masuk laporan; unlock membatalkan request pending; approval final memberi nomor jurnal deterministik dan mem-posting jurnal secara idempoten.

Dokumen ini harus diperbarui setiap kali modul baru dibuat atau status integrasi berubah.

## Workspace dan Approval Pengawasan Lapangan (2026-08-02)

- **Workspace Harian Pengawas: DIKECUALIKAN DARI APPROVAL.** Workspace hanya merangkum konteks perumahan, unit, jadwal, progres, laporan, material, tenaga/alat, kualitas, defect, K3, perubahan pekerjaan, dan serah-terima dari modul sumber. Workspace tidak menyimpan transaksi sendiri.
- Laporan Lapangan memakai resource `site-report`, sedangkan Kontrol Kualitas memakai `quality-inspection`. Defect, perubahan pekerjaan, tenaga/alat, K3, dan serah-terima internal masing-masing memakai `field-defect`, `field-work-change`, `field-manpower`, `field-safety`, dan `field-handover`.
- Semua tombol approve/reject membaca request terbaru dan hanya tampil/berjalan ketika `ApprovalWorkflowService::canReview()` benar; controller tidak lagi menyetujui data dengan update status langsung.
- Tahap reviewer dan role berasal dari Setting Approval. Tabel menampilkan tahap aktif serta total tahap, sedangkan approval final dijalankan oleh `ApprovalWorkflowEffectService` agar hasilnya sama dari halaman modul maupun Approval pusat.
- Approval final Kontrol Kualitas membuat atau memperbarui defect deterministik `DEF-QC-{id}` secara idempoten. Approval final Serah Terima Internal menyinkronkan status/progres unit secara idempoten.
- Pengujian integrasi mencakup approval kualitas dua tahap dan role tiap tahap, side effect defect hanya setelah tahap final, idempotensi efek, reject K3, auto-approve serah-terima, serta akses dan filter konteks Workspace Harian Pengawas.

## Kontrak Penambahan Mutu Bangunan (2026-07-26)

- `quality-upgrade` merupakan modul kontrak mandiri berbasis customer dan unit; `spr_id` hanya referensi opsional sehingga unit yang sudah lama terjual tetap dapat diproses.
- Draft memiliki halaman form terpisah, mendukung banyak item pekerjaan, snapshot spesifikasi/harga, perusahaan pelaksana dan penerima pendapatan, rekening perusahaan, cash atau cicilan, jadwal, garansi, serta dokumen kontrak cetak.
- Lock memakai `ApprovalWorkflowService::submitLocked()`. Tahap dan role reviewer seluruhnya berasal dari Setting Approval, sedangkan approval final secara idempoten membentuk jadwal piutang dan jurnal Penambahan Mutu.
- `company_id` disimpan pada kontrak dan `cabang_perusahaan_id` pada jurnal agar transaksi Ali Cipta Energi tidak bercampur dengan badan usaha penjual rumah. Rekening penerimaan divalidasi harus milik perusahaan yang dipilih.
- Pembayaran memakai modul Penerimaan Customer canonical, Payment Schedule, alokasi tagihan, bukti transfer, approval, kuitansi, dan jurnal yang sama. Piutang dan uang muka memakai akun khusus Penambahan Mutu.

### Penyempurnaan lifecycle (2026-07-27)

- Halaman detail menjadi dossier kontrak: nilai, tagihan, pembayaran, piutang, progres tertimbang, biaya aktual material/upah/lainnya, dan margin perusahaan.
- DP dikenali sebagai jadwal pertama bertipe `quality_upgrade_down_payment`; jumlah seluruh jadwal tetap harus sama dengan nilai kontrak agar tidak terbentuk piutang tersembunyi.
- Pengawasan dicatat per item melalui `quality_upgrade_progresses`. Nilai progres kontrak dihitung tertimbang berdasarkan nilai pekerjaan, sedangkan biaya material mengutamakan pemakaian stok yang sudah diposting.
- `material_usages` dapat ditautkan ke kontrak dan item Penambahan Mutu sehingga pengurangan stok dan HPP tidak dibuat melalui ledger paralel.
- Pembatalan hanya diizinkan sebelum progres dan setelah seluruh penerimaan direversal/refund. Invoice dan jurnal yang belum dibayar dibalik melalui service canonical sebelum status `cancelled`.
- Master paket tersedia pada `quality_upgrade_catalogs`; snapshot pekerjaan tetap berada pada item kontrak agar perubahan harga master tidak mengubah kontrak historis.
- Pengujian integrasi permanen memastikan registry approval, route lifecycle, kolom audit, hubungan pembayaran, dan hubungan material tetap tersedia.
- Addendum memakai resource approval mandiri `quality-upgrade-addendum`. Approval final idempoten menambah nilai kontrak, versi dokumen, invoice, dan jurnal; unlock membalik seluruh efek tersebut dan ditolak bila invoice addendum sudah dibayar.
- Bukti pengawasan disimpan per laporan progres. Pemakaian material dapat dibuat dari dossier kontrak dan wajib menunjuk item pekerjaan sehingga biaya stok terposting masuk ke realisasi biaya kontrak yang benar.
- Koreksi COA 2026-07-27 memisahkan Persediaan Material `1-1300` dari Piutang Penambahan Mutu `1-1500`. Jurnal lama bertipe invoice Penambahan Mutu dimigrasikan ke akun piutang baru.
- Permintaan material dapat dibuat langsung dari dossier serta menyimpan kontrak dan item pekerjaan. Approval pemakaian material membentuk jurnal HPP Penambahan Mutu versus Persediaan Material secara idempoten; unlock menghapus jurnal tersebut dan memulihkan biaya aktual.
- Serah terima memakai resource `quality-upgrade-handover`. Approval final hanya dapat berjalan pada progres 100% tanpa defect terbuka, lalu mengubah status menjadi `handed_over` dan menghitung akhir garansi dari tanggal serah terima. Unlock mengembalikan status `completed` dan menghapus periode garansi.
- Defect menyimpan tingkat risiko, target perbaikan, bukti temuan, catatan penyelesaian, dan bukti penyelesaian. Defect terbuka menjadi dependency wajib serah terima.
- Unlock membalik invoice dan jurnal hanya bila belum ada pembayaran yang diproses. Jika sudah ada penerimaan pending/posted, reversal penerimaan wajib diselesaikan terlebih dahulu agar saldo dan audit trail tidak rusak.
- Kolom progres, biaya material, tenaga kerja, biaya lain, dan status pekerjaan sudah tersedia pada item sebagai titik integrasi pengawasan mutu tahap berikutnya; halaman pengawasan sengaja belum diaktifkan pada tahap ini.

## CRM Marketing Terpadu (2026-07-22)

- **Meja Kerja Harian Marketing: READ-ONLY/ORKESTRASI.** Halaman `/admin/marketing` tidak membuat transaksi paralel; halaman ini menjadi pintu input Prospek, Follow-up, Kunjungan, Survei, dan Aktivitas Lain pada modul sumber masing-masing serta menampilkan jejak kerja hari berjalan. Karena tidak menyimpan record sendiri, halaman ini dikecualikan dari approval.
- **Laporan Kunjungan Customer: SUPPORT.** Draft memiliki halaman form terpisah, permission CRUD/lock/unlock, relasi customer-marketing-perumahan, hasil dan tindak lanjut, lalu finalisasi memakai resource `marketing-visit` dan Setting Approval.
- Kunjungan mendukung nama/alamat lokasi, koordinat GPS, akurasi, foto, waktu aktual mulai-selesai dari waktu server, hasil, respons, kendala, serta tindak lanjut. Data aktual tidak dapat lagi diketik dari form jadwal: marketing wajib memakai check-in dan check-out. Laporan hanya dapat di-lock setelah kedua tahap lengkap; approval final mengubah verifikasi menjadi `verified`, reject menjadi `needs_revision`, dan seluruh kejadian masuk audit aktivitas customer.
- **Action Plan Marketing: SUPPORT.** Draft memiliki PIC, customer, prioritas, periode, target, hasil aktual, hambatan, halaman form terpisah, permission granular, lock/unlock, serta resource approval `marketing-action-plan`.
- **Workspace harian Marketing: READ-ONLY/OPERASIONAL.** Halaman ini merangkum lead jatuh tempo, reminder, kunjungan, skor prioritas, dan kekurangan dokumen dari modul sumber. Workspace tidak memiliki finalisasi sendiri sehingga dikecualikan dari Setting Approval; setiap mutasi tetap dilakukan melalui Customer, Follow Up, Kunjungan, Action Plan, Checklist Dokumen, Reservasi, atau SPR yang memiliki kontrak masing-masing.
- **Checklist Dokumen Customer: SUPPORT.** Checklist tersambung ke customer dan tahapan proses, menghitung persentase kelengkapan, memiliki halaman form terpisah, permission granular, lock/unlock, serta resource approval `customer-document-checklist`.
- **Laporan CRM Owner: DIKECUALIKAN DARI APPROVAL** karena read-only. Owner hanya menerima `marketing.owner-report.view`; halaman menyajikan funnel, SLA respons pertama, follow-up, kunjungan terverifikasi, target versus realisasi, skor kinerja, aging customer/lead/aktivitas, action plan terlambat, dokumen belum lengkap, cetak, dan ekspor CSV tanpa mutation action.
- Distribusi lead tidak lagi mengubah `created_by`. Penanggung jawab berada pada `assigned_marketing_id`, sedangkan setiap perpindahan dicatat dalam `marketing_lead_assignments` agar audit pencipta data tetap utuh.
# Master Dokumen / Persuratan

- Status: dikecualikan dari Setting Approval.
- Alasan: modul ini merupakan master konfigurasi desain surat (tanpa finalisasi transaksi atau pengajuan). Dokumen hasil cetak hanya membaca data domain yang sudah ada dan tidak menimbulkan side effect bisnis.
# Absensi Pegawai (2026-07-17)

- `attendance` terdaftar pada `ApprovalResources` dan tampil pada Setting Approval.
- Setiap absen masuk/pulang dibuat sebagai event terpisah, langsung berstatus `locked`, lalu memanggil `ApprovalWorkflowService::submitLocked()`.
- Titik cabang wajib sudah locked/final; jarak dihitung ulang di server dan foto wajib disimpan sebagai bukti.
- Akses pegawai tanpa dashboard memakai Nomor Pegawai + PIN Absensi yang di-hash. Sesi tersebut hanya dilayani oleh endpoint `/absensi`.
- Data Pegawai dipisahkan dari Data Pengguna pada route dan menu tersendiri dengan permission `employee.view/create/update/delete`. Keduanya tetap memakai model `User` dan resource approval `user` yang sama agar identitas pegawai, akun login, lock, serta approval tidak membentuk workflow paralel.
- Rekap ringkas hari berjalan ditampilkan pada dashboard utama untuk pengguna yang memiliki akses organisasi/payroll.
- Absensi di luar radius dapat diteruskan setelah konfirmasi eksplisit dan disimpan dengan penanda `is_within_radius=false` untuk audit admin.
- `attendance-setting` adalah master Pengaturan Jam Absensi terpisah dari cabang, terdaftar di `ApprovalResources`, dan penyimpanannya memanggil `submitLocked()`.
- Modul admin daftar/detail dilindungi `attendance.view`; konfigurasi jadwal dilindungi `attendance.settings`.
- Periode Tagihan Air & Pembayaran Air: **SUPPORT PENUH**. Periode dikelola per perumahan oleh Admin dan pembayaran dicatat Keuangan berdasarkan pemilik unit aktif. Keduanya memakai draft/lock, `submitLocked()`, tahap/role dari Setting Approval, review melalui `canReview()`, unlock membatalkan request pending, dan status pembayaran menjadi lunas secara idempoten hanya setelah approval final.
# Pembaruan 22 Juli 2026 - Procurement dan Inventory Cost

- `material-purchase` tetap menjadi resource approval induk untuk data pembelian, ekspedisi, upah buruh logistik, dan biaya perolehan. Seluruh komponen biaya ikut terkunci bersama pembelian; tidak dibuat approval paralel.
- Harga master tidak boleh berubah dari draft pembelian. Perubahan hanya terjadi setelah pemeriksaan fisik menerima stok, berdasarkan moving-average landed cost dan dicatat ke riwayat harga.
- Pemeriksaan barang masuk merekonsiliasi pesanan, faktur supplier, fisik tiba, diterima baik, cacat, ditolak, kurang, dan lebih. Setiap penerimaan yang sah membentuk lot stok.
- Laporan persediaan adalah read-only sehingga dikecualikan sebagai resource approval. Laporan dipisahkan menjadi nilai modal persediaan, barang masuk, barang keluar, pemakaian proyek, rekonsiliasi penerimaan, dan stok opname.
- Approval pembelian kini menggunakan request `material-purchase/lock` dan `ApprovalWorkflowService::canReview()`; approval final memanggil efek idempoten `MaterialPurchaseService::approve()` baik dari daftar pembelian maupun Approval pusat.

## Pembaruan alur material 22 Juli 2026

- `material-request` tidak lagi memakai urutan approver gudang/owner yang di-hard-code. Lock membuat request melalui `ApprovalWorkflowService::submitLocked()`, reviewer tabel memakai `canReview()`, dan approval final menjalankan pengeluaran stok secara idempoten melalui `MaterialWorkflowService`.
- Permintaan dan pemakaian menyimpan satuan input, kuantitas input, faktor konversi, serta kuantitas saldo Level 1. Pengambilan 3,5 kg dari kemasan 40 kg atau 2 meter dari batang 6 meter kini dinormalisasi sebelum memengaruhi stok.
- `material-usage` mem-posting stok dan realisasi HPP hanya pada approval final. Unlock/reject membalik posting secara idempoten melalui `stock_posted_at`.
- Unlock material sekarang memakai permission spesifik module key. Permission unlock pada satu modul tidak lagi membuka seluruh modul lain.
- Unlock permintaan yang sudah mengeluarkan barang membalik transaksi gudang, stok lokasi, pemakaian yang memiliki relasi permintaan, dan realisasi HPP. Reversal ditolak bila stok telah dipakai transaksi lain yang tidak dapat dilacak ke permintaan tersebut.
- Halaman daftar permintaan dan pemakaian menampilkan tahap Setting Approval serta tombol review hanya bagi reviewer tahap aktif.
- Snapshot data database tersedia melalui `CurrentDatabaseSnapshotSeeder`; tabel permintaan material dan approval polymorphic miliknya sengaja dikecualikan.
- Pemeriksaan penerimaan adalah aktivitas operasional turunan dari pembelian yang telah disetujui. Ia memiliki permission terpisah `material-receipt.*`, tetapi tidak membuat approval kedua agar satu penerimaan tidak disahkan dua kali.
- Klaim supplier, shipment, rincian biaya, lot FIFO, saldo kondisi, dan laporan aging adalah turunan/audit dari transaksi induk. Perubahan biaya sebelum finalisasi ikut terkunci bersama pembelian; laporan tidak memerlukan approval.
- Unlock material dijalankan atomik: approval pending/approved berubah menjadi `reversed` dengan riwayat pelaku, lalu stok, nilai persediaan, lot, HPP, jurnal/tagihan, klaim draft, dan status domain dikembalikan. Unlock ditolak bila lot atau stok turunannya sudah dikonsumsi, klaim sudah diproses, atau pembayaran supplier sudah dilepas.
- Tagihan supplier baru menjadi `reconciled` setelah seluruh item selesai dibandingkan antara PO, faktur, fisik tiba, diterima, cacat, ditolak, dan kurang. Nilai payable memakai nilai faktur dikurangi klaim aktif; pembayaran hutang diblokir sebelum rekonsiliasi selesai.
# Refactor CRM dan Evaluasi Marketing (2 Agustus 2026)

- `marketing-visit`, `marketing-action-plan`, dan `customer-document-checklist`: draft/locked, submit melalui `ApprovalWorkflowService::submitLocked()`, approval pending/final dibalik melalui `reverseLockApproval()` saat unlock, status/tahap/canReview tersedia pada halaman modul, dan side effect timeline kunjungan dijalankan idempotent dari approval pusat maupun modul.
- Checklist berkas Marketing membaca paket `DocumentRequirementSet` berstatus aktif, locked, dan approved berdasarkan metode pembayaran SPR, bank, produk kredit, perumahan, pekerjaan, serta status perkawinan. File yang diunggah Marketing masuk ke Repository Dokumen Customer; item paket wajib tidak dapat dihapus dari form dan kebutuhan baru digabungkan kembali ketika draft checklist diedit. Master Dokumen Pelanggan hanya menjadi fallback bila belum ada paket yang cocok.
- Form Follow-up, Sumber Lead, Jadwal Survey, Hasil Survey, Campaign, Reminder, Target, Komisi, Template, dan transaksi Cash dipisahkan dari halaman daftar. Perubahan ini tidak membuat resource approval baru; lifecycle tetap memakai resource domain yang telah terdaftar.
- Akses monitoring seluruh tim memakai `marketing.activity.view-all`; role Marketing/Area Marketing hanya membaca aktivitasnya sendiri. Permission `marketing-survey.unlock` dicabut dari role operasional biasa dan diberikan kepada role pengawas/manajerial melalui migration terfokus tanpa menjalankan global role seeder.
- Pengujian approval lengkap 0-3 tahap, reject, unlock/resubmit, visibilitas reviewer, dan side effect final tetap berstatus **PENDING** sesuai penjadwalan tahap pengujian berikutnya; build dan contract test Marketing bukan pengganti bukti end-to-end tersebut.
- `marketing-evaluation`: nilai berbasis bukti aktivitas, draft/locked, approval 0-3 tahap dari Setting Approval, tombol approve/reject memakai `canReview()`, serta unlock/resubmit membatalkan request pending.
- `marketing-score-setting`: perubahan bobot wajib draft/locked dan submit ke Setting Approval; total bobot aktif divalidasi tepat 100% sebelum finalisasi.
- Laporan CRM/kinerja adalah read-only sehingga dikecualikan dari lock/approval. Filter, CSV, cetak, drill-down status, dan sumber angka disediakan tanpa mutasi data.
- `marketing-reference-option`: master dropdown marketing terpusat memakai draft/locked, `submitLocked()`, pembatalan pending saat unlock, tahap approval, dan review berbasis `canReview()`. Data lama tidak dihapus; pilihan dapat dinonaktifkan.
- Pusat laporan marketing menyediakan sembilan laporan read-only (aktivitas, follow-up, kunjungan, customer tidak aktif, pipeline, konversi, target, pembatalan, dan kinerja) dengan pagination serta export CSV/Excel/PDF. Karena read-only, laporan dikecualikan dari lifecycle approval.
- `customer_unit_interests` adalah child-data dari Customer/Calon Konsumen, bukan modul finalisasi terpisah. Lock dan approval tetap mengikuti resource `customer`, sedangkan baris minat unit ikut disinkronkan saat customer dibuat atau diperbarui.
- Kunjungan Marketing kini dapat mencatat prospek/event/canvassing sebelum ada customer melalui `contact_name`, `contact_phone`, `organization_name`, dan `lead_source_note`; lifecycle approval tetap memakai resource `marketing-visit`.

# Ruang Kerja Admin Sales (9 Agustus 2026)

- `sales_work_items` adalah antrean operasional internal untuk mengingatkan dan mengukur penyelesaian pekerjaan administrasi. Ia tidak memfinalisasi transaksi, tidak mem-posting jurnal, tidak mengubah unit, dan tidak menggantikan approval resource sumber; karena itu dikecualikan dari Setting Approval. Setiap perubahan status tetap dicatat di `sales_activity_logs`.
- Pemeriksaan lead, follow-up, dan kunjungan oleh Admin Sales adalah metadata quality-control. Follow-up/kunjungan yang dapat dikunci tetap mengikuti approval resource domain existing; reservasi, SPR, KPR, receipt, dan transaksi penjualan tetap mengikuti lifecycle approval masing-masing.
- Dashboard dan monitoring Admin Sales serta laporan Manager/Owner bersifat read-only terhadap transaksi sumber sehingga dikecualikan dari lifecycle approval.
- Kalender Kegiatan Marketing adalah agregasi read-only dari aktivitas domain existing. Kalender tidak membuat atau memfinalisasi transaksi, sehingga dikecualikan dari Setting Approval; aksi perubahan tetap dilakukan pada halaman sumber dan mengikuti lifecycle sumber tersebut.
- Sinkronisasi otomatis work queue hanya membuat pengingat operasional yang mereferensikan data sumber. Ia tidak menjalankan side effect transaksi dan tetap dikecualikan dari Setting Approval.
- Staging import/API lead dan pemeriksaan duplikat adalah proses intake sebelum Customer menjadi transaksi finalizable. Keputusan intake selalu diaudit, tetapi dikecualikan dari Setting Approval; setelah customer terbentuk, lifecycle Customer dan proses penjualan sumber tetap berlaku.
- `marketing_lead` dan kontak hasil Aktivitas Lapangan adalah data pra-transaksi. Keduanya dikecualikan dari Setting Approval, tetapi perubahan tahap dan konversi dicatat. Customer hanya dibuat sebagai draft melalui konversi Lead Qualified; lock/approval Customer dan transaksi reservasi/SPR tetap mengikuti resource existing.
