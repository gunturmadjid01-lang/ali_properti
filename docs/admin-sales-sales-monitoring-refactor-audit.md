# Audit Refactor Admin Sales dan Monitoring Penjualan

Tanggal audit: 9 Agustus 2026. Checkout: `D:\PROGRAM\BACKUP\ali_properti`.

## Keputusan arsitektur

- Data customer tetap memakai `costumers`; tidak dibuat master customer kedua.
- Lead Marketing dan lead perusahaan dibedakan melalui `lead_ownership_type`, kanal, status verifikasi, PIC Admin Sales, dan assignment status. Riwayat perpindahan tetap memakai `marketing_lead_assignments`.
- Follow-up dan laporan kunjungan tetap milik Marketing. Pemeriksaan Admin Sales disimpan di kolom review terpisah dan `sales_activity_logs`, sehingga isi asli tidak ditimpa.
- Reservasi, booking fee/receipt, SPR, KPR, pembayaran, akad, dan closing tetap memakai modul transaksi existing beserta Setting Approval dan side effect existing.
- `sales_work_items` menjadi antrean administrasi lintas proses dengan PIC, prioritas, tenggat, status, dan audit. Tabel ini menyimpan referensi, bukan menyalin data transaksi sumber.
- Pada 9 Agustus 2026, satu paket data CRM legacy `CST-00001` beserta follow-up, activity, dan work item turunannya dihapus atas instruksi pemilik data. Pemeriksaan sebelum penghapusan memastikan paket tersebut tidak memiliki reservasi, SPR, transaksi penjualan, receipt, dokumen, atau checklist. Master, user, perumahan, dan transaksi lain tidak ikut dihapus.

## Modul existing yang dipertahankan

Customer/lead, follow-up, aktivitas dan kunjungan Marketing, minat unit, repository/checklist dokumen, reservasi perumahan, SPR, integrated sales, KPR Bank/Developer, Cash/Cash Bertahap, piutang dan receipt customer, Approval pusat, laporan Marketing, notifikasi, serta assignment history tetap digunakan.

## Gap yang ditutup pada tahap ini

- Dashboard khusus Admin Sales dengan kartu yang membuka data sumber.
- Antrean lead perusahaan belum diverifikasi, belum dibagikan, dan melewati SLA respons.
- Antrean pemeriksaan follow-up dan laporan kunjungan tanpa memberi Admin Sales kemampuan mengubah laporan Marketing.
- Work queue Admin Sales dengan halaman daftar, tambah, detail, SLA, PIC, status, dan riwayat audit terpisah.
- Permission khusus Admin Sales serta akses read-only Manager/Owner.
- Indeks untuk antrean verifikasi, assignment, review, tugas, dan audit.
- Lead perusahaan memiliki halaman daftar, input, detail, verifikasi, dan distribusi terpisah. Data awal tidak lagi dipaksa mengandung identitas fiktif.
- Assignment memiliki status `offered/accepted/rejected/responded/transferred`, deadline, waktu respons, dan catatan. Marketing menerima atau menolak melalui halaman Penugasan Lead Saya.
- Follow-up pertama mengubah assignment menjadi `responded` dan menyelesaikan tugas pemantauan SLA secara otomatis. Penolakan membuat tugas redistribusi untuk Admin Sales.
- Kalender Kegiatan Marketing menyatukan jadwal kunjungan, survey, rencana follow-up, reminder, dan action plan dalam tampilan bulan/daftar. Marketing dibatasi ke agendanya sendiri; Admin Sales dan role pengawas dapat memfilter seluruh tim.
- Sinkronisasi work queue berjalan idempoten setiap jam untuk follow-up terlambat, SLA respons lead, review kunjungan, dokumen belum lengkap, reservasi aktif, SPR belum final, KPR stagnan, dan pembayaran jatuh tempo. Tugas yang baru dibuat mengirim notifikasi kepada PIC.
- Consent komunikasi kini menjadi kontrol operasional, bukan sekadar informasi: Lead yang menolak komunikasi tidak muncul pada pilihan follow-up, tidak membentuk reminder/SLA kontak, dan tidak dapat direcycle. Perubahan consent wajib disertai catatan, kanal yang disetujui divalidasi ketika follow-up, dan seluruh perubahan masuk timeline audit Lead.
- Hasil follow-up tidak lagi dapat melompati proses kualifikasi. Status seperti siap reservasi tetap harus melewati checklist dan skor kualifikasi, pengajuan Qualified, serta verifikasi Admin Sales sebelum Lead dapat menjadi Customer.
- Input Lead langsung kini mempunyai pemeriksaan duplikat berdasarkan telepon, email, dan NIK. Kandidat menampilkan Lead lama, PIC, tahap, serta tautan detail; penyimpanan data serupa hanya diizinkan setelah pengguna memilih kandidat dan mencatat alasan bahwa data memang berbeda. Relasi kandidat, pemeriksa, waktu, alasan, dan event override disimpan sebagai audit trail sehingga duplikat tidak lagi hanya ditolak tanpa jalur keputusan.
- Lead kini memiliki halaman Edit terpisah. Perubahan profil dan minat dicatat dalam timeline; Lead yang sudah menjadi Customer tidak dapat diedit dari master Lead.
- Minat properti Lead memakai alur bertingkat Perumahan ke Tipe ke Unit tersedia, ditambah Campaign dan cabang turunan perumahan. Backend memvalidasi bahwa unit serta campaign memang berada pada perumahan terpilih. Saat konversi, campaign dan minat unit ikut dibawa ke Customer tanpa input ulang.
- Pusat Lead Duplikat Admin Sales kini menggabungkan antrean intake dengan keputusan data berbeda dari input langsung, termasuk kandidat lama, PIC, pemeriksa, waktu, dan alasan audit.
- Intake lead perusahaan menerima CSV/XLSX dan API kanal resmi. Setiap baris disimpan sebagai jejak intake; duplikat/tidak valid ditahan, keputusan data existing/data berbeda/spam wajib menyimpan alasan dan pemeriksa, sedangkan API mewajibkan secret serta `Idempotency-Key`.
- Laporan khusus Admin Sales menyediakan filter periode/PIC, ringkasan lead, SLA, tugas, produktivitas per Admin Sales, drill-down sumber, dan ekspor detail lead CSV.

## Gap lanjutan

- Adapter khusus vendor website/WhatsApp masih bergantung kontrak payload vendor; endpoint intake generik yang aman dan idempoten sudah tersedia untuk dihubungkan tanpa input ulang.
- Export CSV khusus Admin Sales sudah tersedia. Output PDF visual dapat menggunakan cetak browser dari halaman laporan; generator PDF server-side khusus belum diperlukan untuk menjaga angka tetap berasal dari query laporan yang sama.
- Workflow CRM dan migration baru telah diuji pada MySQL/MariaDB. SQLite proyek tetap tidak dipakai sebagai bukti perilaku karena migration lama masih memiliki statement MySQL-only.

## Matriks tanggung jawab

| Proses | Marketing | Admin Sales | Manager/Owner |
|---|---|---|---|
| Lead pribadi | input dan follow-up | monitor | laporan |
| Lead perusahaan | menerima dan follow-up setelah assignment | verifikasi, distribusi, monitor SLA | redistribusi/evaluasi sesuai permission |
| Follow-up/kunjungan | membuat aktivitas dan bukti | memberi catatan review | monitoring/verifikasi final sesuai permission |
| Reservasi/SPR | mengajukan | pemeriksaan administrasi | approval dari Setting Approval |
| Pembayaran/KPR/akad | mendampingi customer | monitoring administrasi | laporan/approval sesuai permission |

## Cara uji

1. Jalankan `php artisan migrate` pada database MySQL/MariaDB cadangan.
2. Login sebagai Admin Sales dan buka `/admin/admin-sales`.
3. Verifikasi lead perusahaan, periksa follow-up/kunjungan, lalu pastikan isi Marketing tidak berubah dan log bertambah.
4. Buat tugas melalui `/admin/admin-sales/tugas/create`, ubah status dari halaman detail, dan periksa audit.
5. Login sebagai Marketing: menu dan endpoint Admin Sales harus 403/tidak terlihat.
6. Login sebagai Manager/Owner: dashboard dan monitoring tersedia read-only sesuai permission.
7. Buka `/admin/marketing/kalender-kegiatan`, periksa pembatasan agenda Marketing dan filter seluruh tim untuk Admin Sales.
8. Jalankan `php artisan admin-sales:sync-work-queue`, lalu pastikan eksekusi ulang tidak membuat tugas otomatis duplikat.
