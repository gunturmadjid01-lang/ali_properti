# Work Log

Catatan ini dipakai untuk menyimpan setiap langkah perbaikan di project `ali_properti`.

## Format

- Tanggal
- Target kerja
- Hasil
- File yang berubah
- Catatan tindak lanjut

## 2026-07-02

### 1. Audit awal ERP gap

- Target kerja: cek apakah sistem sudah matang sebagai ERP properti developer.
- Hasil: sudah ada fondasi kuat untuk marketing, proyek, keuangan, gudang, approval, dan role permission, tetapi masih ada gap di vendor, procurement end-to-end, fixed asset, after-sales, dan audit BI.
- File yang berubah:
  - `docs/erp-gap-matrix.md`
- Catatan tindak lanjut:
  - lanjut audit permission/role owner dan manager
  - rapikan menu yang masih campur role dan permission

### 2. Siapkan matriks akses owner/manager

- Target kerja: petakan halaman yang harus tampil, tersembunyi, atau approval-only untuk owner dan manager.
- Hasil: matriks awal sudah dibuat.
- File yang berubah:
  - `docs/owner-manager-permission-matrix.md`
- Catatan tindak lanjut:
  - isi daftar halaman per modul
  - sinkronkan dengan sidebar dan permission database

### 3. Pengetatan sidebar seluruh role

- Target kerja: jadikan visibility menu konsisten dengan permission, bukan hanya role.
- Hasil: sidebar utama untuk admin, marketing, pengawas, supervisor marketing, gudang, dan keuangan sudah diberi permission guard di item-item sensitif.
- File yang berubah:
  - `resources/js/Layouts/AdminLayout.jsx`
  - `resources/js/Sidebar/admin.jsx`
  - `resources/js/Sidebar/marketing.jsx`
  - `resources/js/Sidebar/pengawas.jsx`
  - `resources/js/Sidebar/supervisor_marketing.jsx`
  - `resources/js/Sidebar/gudang.jsx`
  - `resources/js/Sidebar/keuangan.jsx`
- Catatan tindak lanjut:
  - cek menu lain yang masih memakai role hardcoded
  - lanjut audit halaman create/edit yang masih terlalu longgar

### 4. Permission-aware master data overview

- Target kerja: mencegah form create/edit master data tampil tanpa permission.
- Hasil: halaman Management Overview sekarang membaca permission key per section.
- File yang berubah:
  - `resources/js/Pages/Admin/Management/Overview/Index.jsx`
- Catatan tindak lanjut:
  - cek section master data lain yang belum ikut permission key
  - pastikan role permission, users, cabang, perumahan, dan dokumen ikut terkunci sesuai hak akses

### 5. Verifikasi build

- Target kerja: pastikan perubahan UI tidak merusak build.
- Hasil: `npm run build` sukses.
- File yang berubah:
  - tidak ada file baru
- Catatan tindak lanjut:
  - ada warning ukuran bundle, bukan error

### 6. Permission-aware management overview

- Target kerja: cegah form master data muncul hanya karena halaman overview dibuka.
- Hasil: `Management Overview` sekarang mengecek permission per section sebelum menampilkan tombol create, form edit, dan aksi tabel.
- File yang berubah:
  - `resources/js/Pages/Admin/Management/Overview/Index.jsx`
- Catatan tindak lanjut:
  - lanjut audit controller yang masih memproses akses berdasarkan role langsung

### 7. Approval settings diperluas ke modul operasional

- Target kerja: membuat approval bisa diatur per halaman/create action, bukan hanya role statis di controller.
- Hasil: katalog approval diperluas ke `customer`, `marketing-lead-source`, `progress`, `site-report`, `quality-inspection`, `field-supervision`, `site-schedule`, `material-request`, `material-purchase`, `spk-kontraktor`, `spr`, dan `spr-payment`; default approval juga diisi supaya `customer` bisa menunggu supervisor marketing + manager, sementara beberapa modul lain bisa auto-approve bila setting dimatikan.
- File yang berubah:
  - `app/Support/ApprovalResources.php`
  - `app/Http/Controllers/Admin/Approval/ApprovalSettingController.php`
  - `app/Http/Controllers/Concerns/UsesApprovalSettings.php`
  - `app/Http/Controllers/Admin/Management/Perumahan/PerumahanController.php`
- Catatan tindak lanjut:
  - sambungkan modul lain yang masih hardcode approval ke setting yang sama
  - rapikan halaman approval settings agar mudah dibaca per modul

### 8. Approval workflow untuk customer, lead source, dan laporan lapangan

- Target kerja: uji jalur approval configurable pada halaman create/edit yang sering dipakai.
- Hasil: `customer` dan `sumber lead` sekarang lewat approval engine kalau setting memintanya, sedangkan `progress`, `site report`, `quality inspection`, dan `field supervision` bisa auto-approve kalau setting approval dimatikan.
- File yang berubah:
  - `app/Http/Controllers/Admin/Marketing/CostumerController.php`
  - `app/Http/Controllers/Admin/Marketing/LeadSourceController.php`
  - `app/Http/Controllers/Admin/ProgressPembangunanController.php`
  - `app/Http/Controllers/Admin/SiteReportController.php`
  - `app/Http/Controllers/Admin/QualityInspectionController.php`
  - `app/Http/Controllers/Admin/FieldSupervisionController.php`
- Catatan tindak lanjut:
  - lanjut audit controller backend yang masih role-based
  - lanjut rapikan menu yang masih belum 100% konsisten

### 9. Sidebar/menu disejajarkan dengan permission approval baru

- Target kerja: mengurangi role gate yang redundant di sidebar dan menu approval.
- Hasil: menu approval dan marketing utama sekarang lebih banyak bergantung ke permission, bukan role hardcoded, sehingga role tambahan yang diberi permission bisa langsung melihat menu yang relevan.
- File yang berubah:
  - `resources/js/Sidebar/menuSections.js`
  - `resources/js/Sidebar/pengawas.jsx`
  - `resources/js/Sidebar/supervisor_marketing.jsx`
- Catatan tindak lanjut:
  - lanjut cek sidebar role lain yang masih campur role + permission
  - pertimbangkan menyamakan semua sidebar lewat satu sumber definisi menu

### 10. Verifikasi build setelah penataan menu

- Target kerja: memastikan perubahan permission/sidebar tidak merusak build.
- Hasil: `npm run build` berhasil.
- File yang berubah:
  - tidak ada file baru
- Catatan tindak lanjut:
  - warning ukuran bundle masih ada, tetapi build aman

### 11. Approval dipisah dari permission CRUD

- Target kerja: pastikan `create/update/delete` tetap permission, sementara approval berjalan sebagai workflow role-based setelah data dibuat.
- Hasil: permission approval action (`approve_manager`, `approve_owner`, `approve_finance`, `approve_admin`) dibuang dari matrix permission, approval workflow sekarang membaca role approver dari setting, dan beberapa controller operasional yang masih mengacu ke permission approval lama sudah dialihkan ke permission CRUD atau role approval yang lebih relevan.
- File yang berubah:
  - `database/seeders/RolePermissionSeeder.php`
  - `app/Services/ApprovalWorkflowService.php`
  - `app/Http/Controllers/Concerns/UsesApprovalSettings.php`
  - `app/Http/Controllers/Admin/Management/RolePermission/RolePermissionController.php`
  - `resources/js/Pages/Admin/Management/RolePermission/Index.jsx`
  - `app/Http/Controllers/Admin/FinanceController.php`
  - `app/Http/Controllers/Admin/AssetInventoryController.php`
  - `app/Http/Controllers/Admin/Marketing/SprPaymentController.php`
  - `app/Http/Controllers/Admin/SpkKontraktorController.php`
  - `app/Http/Controllers/Admin/MaterialRequestController.php`
  - `app/Http/Controllers/Admin/MaterialReturnController.php`
  - `app/Http/Controllers/Admin/ProgressPembangunanController.php`
- Catatan tindak lanjut:
  - lanjut audit halaman approval yang masih hardcoded role jika ingin dipindah total ke approval settings
  - lanjut cek menu/sidebar agar approval page tetap tampil sesuai permission akses halaman, bukan permission approval action

### 12. Sidebar role mapping dan menu longgar

- Target kerja: rapikan sidebar supaya sesuai role yang benar-benar ada dan menutup item menu yang masih terlalu longgar.
- Hasil: sidebar sekarang dipilih dengan prioritas role yang lebih jelas, alias `manager` dan `manajer_pimpro` dinormalisasi di helper akses marketing, dan beberapa item menu yang sebelumnya tanpa permission seperti `Jadwal Survey`, `Akad`, `Serah Terima`, serta `Progress Pembangunan` sudah dikunci permission.
- File yang berubah:
  - `resources/js/Layouts/AdminLayout.jsx`
  - `resources/js/Sidebar/marketing.jsx`
  - `resources/js/Sidebar/supervisor_marketing.jsx`
  - `app/Http/Controllers/Concerns/ChecksMarketingAccess.php`
- Catatan tindak lanjut:
  - lanjut audit menu lain yang masih punya item tanpa permission
  - cek lagi controller marketing/proyek yang masih pakai nama role lama agar konsisten dengan `manajer_pimpro`

### 13. Form operasional dibatasi izin + normalisasi role marketing

- Target kerja: sembunyikan form/tombol create/edit/delete pada halaman operasional yang masih terlalu longgar, dan kurangi sisa referensi `manager` yang bercampur dengan `manajer_pimpro`.
- Hasil: form di `site-report`, `field-supervision`, `material-purchase-request`, dan modul keuangan sekarang bergantung pada flag permission dari backend; beberapa controller marketing juga mulai dinormalisasi ke `manajer_pimpro` supaya akses lebih konsisten.
- File yang berubah:
  - `app/Http/Controllers/Admin/MaterialPurchaseRequestController.php`
  - `resources/js/Pages/Admin/MaterialPurchaseRequest/Index.jsx`
  - `app/Http/Controllers/Admin/SiteReportController.php`
  - `resources/js/Pages/Admin/SiteReport/Index.jsx`
  - `app/Http/Controllers/Admin/FieldSupervisionController.php`
  - `resources/js/Pages/Admin/FieldSupervision/Index.jsx`
  - `app/Http/Controllers/Admin/FinanceController.php`
  - `resources/js/Pages/Admin/Finance/Index.jsx`
  - `app/Http/Controllers/Admin/Marketing/LeadSourceController.php`
  - `app/Http/Controllers/Admin/Marketing/LeadReportController.php`
  - `app/Http/Controllers/Admin/Marketing/PipelineReportController.php`
  - `app/Http/Controllers/Admin/Marketing/MarketingOperationsController.php`
  - `app/Http/Controllers/Admin/Marketing/SprController.php`
- Catatan tindak lanjut:
  - lanjut audit `SPK`, `SPR payment`, dan `AssetInventory` supaya form/tombol juga mengikuti permission yang sama
  - lanjut rapikan sidebar/menu untuk menu marketing/proyek yang masih memakai label role lama di teks atau default akses

### 14. Matrix role-permission diperluas + sidebar admin diisi ulang

- Target kerja: supaya role-permission tidak hanya menampilkan master data, tetapi juga modul proyek, gudang, approval, dan keuangan; lalu sidebar admin menampilkan menu yang lebih lengkap sesuai role dan permission.
- Hasil: `RolePermissionController` sekarang punya grup approval, proyek, gudang, dan keuangan tambahan; role admin di seeder diberi view-permission yang lebih luas; sidebar admin diperluas agar menu proyek, approval, gudang, dan keuangan muncul saat permission tersedia; dan `AdminAreaPages` ikut disesuaikan agar shortcut area admin lebih lengkap.
- File yang berubah:
  - `app/Http/Controllers/Admin/Management/RolePermission/RolePermissionController.php`
  - `database/seeders/RolePermissionSeeder.php`
  - `resources/js/Sidebar/admin.jsx`
  - `app/Support/AdminAreaPages.php`
  - `app/Http/Controllers/Admin/SpkKontraktorController.php`
  - `app/Http/Controllers/Admin/Marketing/SprPaymentController.php`
  - `app/Http/Controllers/Admin/AssetInventoryController.php`
  - `resources/js/Pages/Admin/SpkKontraktor/Index.jsx`
  - `resources/js/Pages/Admin/Marketing/SprPayment/Refund.jsx`
- Catatan tindak lanjut:
  - lanjut audit `SpkKontraktor` dan `SprPayment` untuk sisa label `manager` yang masih dipakai di nilai internal
  - lanjut cek role admin di database lama, lalu sync ulang permission kalau menu masih belum tampil seperti yang diharapkan

### 15. Sidebar admin ditambah menu sales dan akuntansi detail

- Target kerja: mengurangi kesan menu admin terlalu sedikit dengan menambah blok penjualan, marketing, dan akuntansi yang memang sudah punya route dan permission.
- Hasil: sidebar admin sekarang memuat `Penjualan & Customer`, `Marketing & Analitik`, dan `Keuangan & Akuntansi`; role admin di seeder ikut diberi permission baru seperti `booking.view`, `spr-payment.view`, `kpr.view`, `marketing.pipeline-report.view`, `bank-account-ledger.view`, dan beberapa permission laporan keuangan detail; matriks role-permission juga ditambah grup `sales` dan `finance`.
- File yang berubah:
  - `resources/js/Sidebar/admin.jsx`
  - `database/seeders/RolePermissionSeeder.php`
  - `app/Http/Controllers/Admin/Management/RolePermission/RolePermissionController.php`
- Catatan tindak lanjut:
  - lanjut audit controller marketing yang masih role-based kalau ada menu baru yang masih 403
  - bila database lama belum sinkron, jalankan ulang seeder permission/role

### 16. Sidebar role lama dirapikan + form operasional yang longgar ditutup

- Target kerja: membersihkan label sidebar yang masih menyebut `Owner/Manager` secara langsung dan menutup form operasional yang masih tampil untuk role tanpa izin.
- Hasil: prioritas sidebar di layout sekarang memprioritaskan `admin` sebelum `manager`, judul sidebar `Menu Owner` dan `Menu Manager` diubah menjadi lebih netral, tab dan form `Asset Inventory` sekarang disaring sesuai izin pemakaian aset, dan beberapa label approval di SPK / SPR / refund diselaraskan ke istilah `Manajer`.
- File yang berubah:
  - `resources/js/Layouts/AdminLayout.jsx`
  - `resources/js/Sidebar/menuSections.js`
  - `resources/js/Pages/Admin/AssetInventory/Index.jsx`
  - `app/Http/Controllers/Admin/SpkKontraktorController.php`
  - `app/Http/Controllers/Admin/Marketing/SprController.php`
  - `app/Http/Controllers/Admin/Marketing/SprPaymentController.php`
  - `app/Http/Controllers/Admin/AssetInventoryController.php`
- Catatan tindak lanjut:
  - lanjut audit sisa controller operasional lain kalau masih ada label `Manager` yang muncul ke user
  - kalau ingin, berikutnya bisa saya audit `MaterialRequest`, `MaterialPurchaseRequest`, dan `FieldSupervision` satu per satu untuk memastikan form longgar sudah benar-benar tertutup

### 17. Progress pembangunan dan pembelian material dikunci permission

- Target kerja: menutup form dan tombol workflow yang masih muncul hanya karena status data cocok, lalu menyesuaikan label approval ke `Manajer`.
- Hasil: halaman `Progress Pembangunan` sekarang menerima `permissions` dari backend, form create disembunyikan jika tidak punya izin, dan tombol edit/hapus/lock/approve ikut dipagari permission; `MaterialPurchaseRequest` dan `MaterialPurchase` juga diberi permission workflow supaya tombol approve, cairkan dana, dan tandai sudah dibeli hanya muncul untuk role yang sesuai; label approval yang masih memakai `manager` di jalur pembelian diselaraskan ke `manajer`.
- File yang berubah:
  - `app/Http/Controllers/Admin/ProgressPembangunanController.php`
  - `resources/js/Pages/Admin/ProgressPembangunan/Index.jsx`
  - `app/Http/Controllers/Admin/MaterialPurchaseRequestController.php`
  - `resources/js/Pages/Admin/MaterialPurchaseRequest/FinanceIndex.jsx`
  - `app/Http/Controllers/Admin/MaterialPurchaseController.php`
  - `resources/js/Pages/Admin/MaterialPurchase/Index.jsx`
  - `app/Services/MaterialPurchaseService.php`
- Catatan tindak lanjut:
  - lanjut audit `MaterialRequest` dan `FieldSupervision` kalau masih ada tombol yang muncul dari status saja
  - lanjut cek sidebar `marketing` dan `gudang` bila masih ada label lama yang belum ikut tersapu

### 18. MaterialRequest, FieldSupervision, dan operasional stok diseragamkan

- Target kerja: menutup sisa halaman operasional yang masih terlalu bergantung pada `status`, lalu merapikan sidebar admin/keuangan/proyek supaya lebih konsisten.
- Hasil: `MaterialRequest` kini mengirim permission lengkap untuk create/update/delete/approve/issue/lock/unlock dan tombolnya di frontend ikut dipagari permission; `FieldSupervision` juga diseragamkan dengan permission create/update/delete/approve/lock/unlock yang jelas; `MaterialReturn` dan `MaterialUsage` sekarang punya permission prop untuk form dan tombol aksi; sidebar admin dan keuangan dirapikan lagi ke judul yang lebih netral dan konsisten.
- File yang berubah:
  - `app/Http/Controllers/Admin/MaterialRequestController.php`
  - `resources/js/Pages/Admin/MaterialRequest/Index.jsx`
  - `app/Http/Controllers/Admin/FieldSupervisionController.php`
  - `resources/js/Pages/Admin/FieldSupervision/Index.jsx`
  - `app/Http/Controllers/Admin/MaterialReturnController.php`
  - `resources/js/Pages/Admin/MaterialReturn/Index.jsx`
  - `app/Http/Controllers/Admin/MaterialUsageController.php`
  - `resources/js/Pages/Admin/MaterialUsage/Index.jsx`
  - `resources/js/Sidebar/admin.jsx`
  - `resources/js/Sidebar/keuangan.jsx`
- Catatan tindak lanjut:
  - lanjut audit sisa halaman operasional lain seperti `SPK`, `SiteReport`, dan `AssetInventory` kalau masih ada action yang muncul hanya karena status row
  - kalau perlu, berikutnya saya bisa fokus ke sidebar `marketing` dan `proyek` detail satu per satu untuk menyamakan istilah menu

### 19. SPK, SiteReport, dan Asset Inventory disambungkan ke sidebar role

- Target kerja: menutup sisa action berbasis status pada `SPK` dan `SiteReport`, lalu memastikan `Asset Inventory` muncul di sidebar `admin`, `manager`, dan `owner`.
- Hasil: `SiteReport` sekarang memisahkan permission lock dan unlock, pesan review diselaraskan ke `manajer`, dan row action edit/hapus ikut permission-aware; `SPK` tetap mempertahankan workflow approval tetapi label sidebar/menu dan pesan yang tampil diselaraskan; `Asset Inventory` ditambahkan ke sidebar role-based di `menuSections` dan permission `asset-inventory.view` diberikan ke role manager supaya menu itu muncul di `manager` dan tetap aman di `owner` serta `admin`.
- File yang berubah:
  - `app/Http/Controllers/Admin/SiteReportController.php`
  - `resources/js/Pages/Admin/SiteReport/Index.jsx`
  - `app/Http/Controllers/Admin/SpkKontraktorController.php`
  - `resources/js/Sidebar/menuSections.js`
  - `database/seeders/RolePermissionSeeder.php`
- Catatan tindak lanjut:
  - lanjut audit `SPK` jika kamu mau label internal status `manager` juga dinormalisasi penuh
  - lanjut sidebar `marketing` dan `proyek` lagi kalau ada istilah yang masih ingin kamu samakan satu per satu

### 20. SPK dan sidebar label lanjutan dinormalisasi

- Target kerja: menghabiskan sisa teks `manager` yang masih muncul di SPK, lalu menyelesaikan label sidebar marketing dan proyek yang masih campur istilah lama.
- Hasil: label sidebar `Lead & Report`, `Analitik & Billing`, `Unit & Perumahan`, dan `Mitra & SPK` sudah dinormalisasi; SPK sekarang membaca approval role dengan label `Manajer` untuk data baru dan tetap kompatibel dengan data lama; status pembayaran SPK juga dibuat kompatibel untuk nama lama dan baru supaya alur approval tidak putus.
- File yang berubah:
  - `resources/js/Sidebar/menuSections.js`
  - `app/Http/Controllers/Admin/SpkKontraktorController.php`
  - `resources/js/Pages/Admin/SpkKontraktor/Index.jsx`
  - `resources/js/Pages/Admin/SpkKontraktor/Disbursement.jsx`
- Catatan tindak lanjut:
  - cek apakah ada tabel atau halaman lain yang masih menyimpan nilai role lama dan perlu migrasi data
  - lanjut audit `Asset Inventory` bila ingin menu ini ditampilkan juga di role selain pengawas/admin/owner

### 21. Sidebar marketing dan proyek diseragamkan lagi

- Target kerja: menyamakan istilah sidebar agar tidak ada lagi campur kata Indonesia dan Inggris, sekaligus merapikan sisa validasi SPK yang masih menerima nilai lama.
- Hasil: sidebar admin, marketing, pengawas, dan definisi menu bersama sudah diselaraskan ke istilah yang lebih konsisten seperti `Pemasaran`, `Manajemen`, `Pelanggan`, `Unit Tersedia`, dan `Daftar Harga Aktif`; validasi SPK juga sekarang hanya menerima `manajer` dan `admin` untuk approval role baru.
- File yang berubah:
  - `resources/js/Sidebar/admin.jsx`
  - `resources/js/Sidebar/marketing.jsx`
  - `resources/js/Sidebar/pengawas.jsx`
  - `resources/js/Sidebar/menuSections.js`
  - `app/Http/Controllers/Admin/SpkKontraktorController.php`
- Catatan tindak lanjut:
  - kalau masih ada istilah lama di sidebar lain, kita bisa terus sapu satu role per role
  - bila diperlukan, berikutnya kita audit lagi halaman SPK untuk memastikan tidak ada label lama di modal/detail view

### 22. Sidebar admin dan marketing disapu sampai seragam

- Target kerja: menyapu sisa istilah lama di sidebar `admin`, `marketing`, `pengawas`, dan menu bersama sampai bahasanya konsisten.
- Hasil: label seperti `Master Document Customer`, `Penjualan & Customer`, `Reminder Follow Up`, dan `Piutang Customer` sudah diganti ke istilah `Dokumen Pelanggan`, `Pemasaran & Pelanggan`, `Pengingat Tindak Lanjut`, dan `Piutang Pelanggan`; sidebar pengawas juga diseragamkan ke `Manajemen Proyek` dan `Manajemen Logistik`.
- File yang berubah:
  - `resources/js/Sidebar/admin.jsx`
  - `resources/js/Sidebar/marketing.jsx`
  - `resources/js/Sidebar/pengawas.jsx`
  - `resources/js/Sidebar/menuSections.js`
- Catatan tindak lanjut:
  - kalau mau, berikutnya kita bisa audit detail SPK/modal layar lain untuk label yang masih tersisa
  - setelah itu kita lanjut role/menu lain yang masih belum benar-benar seragam

### 23. View SPK dibersihkan dari artefak encoding

- Target kerja: membersihkan layar SPK dari sisa karakter tipografi/encoding yang masih terlihat aneh di detail list dan modal.
- Hasil: tampilan SPK pada daftar, disbursement, dan payment sekarang memakai separator biasa yang konsisten; artefak seperti `â€”`, `Â·`, dan `×` yang muncul di browser sudah diganti dengan pemisah yang aman.
- File yang berubah:
  - `resources/js/Pages/Admin/SpkKontraktor/Index.jsx`
  - `resources/js/Pages/Admin/SpkKontraktor/Payment.jsx`
  - `resources/js/Pages/Admin/SpkKontraktor/Disbursement.jsx`
- Catatan tindak lanjut:
  - jika masih ada tampilan aneh di halaman lain, kita bisa lakukan audit encoding UI per modul
  - setelah itu kita bisa lanjut ke halaman operasional lain yang masih menyisakan istilah lama

### 24. MaterialUsage yang bikin error permissions dipindah kembali ke dalam komponen

- Target kerja: memperbaiki blank page akibat `permissions is not defined` yang muncul dari `MaterialUsage`.
- Hasil: konstanta permission `canCreate`, `canUpdate`, `canDelete`, `canLock`, dan `canUnlock` dikembalikan ke dalam komponen `MaterialUsage` dan baris nyasar setelah `Index.layout` dihapus.
- File yang berubah:
  - `resources/js/Pages/Admin/MaterialUsage/Index.jsx`
- Catatan tindak lanjut:
  - kalau browser masih menampilkan error lama, lakukan hard refresh karena asset lama bisa saja masih ter-cache
  - lanjut cek halaman lain hanya bila error serupa muncul lagi

### 25. Catatan progress client disiapkan

- Target kerja: menyiapkan catatan progress yang bisa dipakai untuk presentasi client malam ini.
- Hasil: dibuat ringkasan progress berbasis modul, urutan prioritas presentasi, status `Done / Partial / Missing`, dan estimasi awal progress ERP properti.
- File yang berubah:
  - `docs/client-progress-summary.md`
- Catatan tindak lanjut:
  - angka progress bisa disesuaikan lagi setelah audit halaman per halaman selesai
  - jika nanti client minta detail, dokumen ini bisa dipakai sebagai narasi pembuka sebelum masuk ke matriks modul

### 26. Approval dikaitkan dengan lock dan owner bisa edit permission per user

- Target kerja: menyamakan logika approval supaya tombol approval hanya muncul setelah data di-lock, sekaligus memberi owner kemampuan mengatur permission khusus per user.
- Hasil: approval di progress, laporan lapangan, quality inspection, field supervision, material request, material purchase request, SPK refund, dan sebagian approval lain sekarang dicek bersama status `locked`; halaman user management juga sekarang mendukung `permission_ids` per user selain role.
- File yang berubah:
  - `app/Http/Controllers/Admin/Management/User/UserController.php`
  - `app/Http/Controllers/Admin/Management/User/Logic/UserPayload.php`
  - `app/Http/Requests/Admin/User/StoreUserRequest.php`
  - `app/Http/Requests/Admin/User/UpdateUserRequest.php`
  - `resources/js/Pages/Admin/Management/User/Form.jsx`
  - `app/Http/Controllers/Admin/ProgressPembangunanController.php`
  - `app/Http/Controllers/Admin/SiteReportController.php`
  - `app/Http/Controllers/Admin/QualityInspectionController.php`
  - `app/Http/Controllers/Admin/FieldSupervisionController.php`
  - `app/Http/Controllers/Admin/MaterialRequestController.php`
  - `app/Http/Controllers/Admin/MaterialPurchaseRequestController.php`
  - `app/Http/Controllers/Admin/Marketing/SprPaymentController.php`
  - `app/Http/Controllers/Admin/Management/Perumahan/PerumahanController.php`
- Catatan tindak lanjut:
  - kalau masih ada modul approval yang belum ikut rule lock, tinggal kita sapu dari daftar `can_approve`
  - next step yang paling aman adalah audit halaman user management dan approval page di browser supaya label permission khusus sudah muncul normal

### 27. Error save role permission karena syncPermissions menerima ID

- Target kerja: memperbaiki error `There is no permission named '12' for guard 'web'` saat role permission disimpan.
- Hasil: `RolePermissionController` sekarang men-submit model `Permission` ke `syncPermissions()` lewat helper `syncRolePermissions()` sehingga nilai permission ID tidak lagi dianggap nama permission.
- File yang berubah:
  - `app/Http/Controllers/Admin/Management/RolePermission/RolePermissionController.php`
- Catatan tindak lanjut:
  - setelah refresh halaman role permission, simpan ulang role yang tadi gagal agar sync permission kembali normal

### 28. Permission khusus di user management dihapus

- Target kerja: menghapus override permission per user karena ternyata membuat form jadi terlalu rumit.
- Hasil: field `Permission Khusus` di management user, validasi, payload, sync, dan deskripsi form sudah dihapus; user sekarang kembali hanya memakai role + penugasan properti.
- File yang berubah:
  - `app/Http/Controllers/Admin/Management/User/UserController.php`
  - `app/Http/Controllers/Admin/Management/User/Logic/UserPayload.php`
  - `app/Http/Requests/Admin/User/StoreUserRequest.php`
  - `app/Http/Requests/Admin/User/UpdateUserRequest.php`
  - `resources/js/Pages/Admin/Management/User/Form.jsx`
- Catatan tindak lanjut:
  - kalau nanti butuh pengecualian permission per user, kita bisa desain ulang dengan pendekatan yang lebih kecil dan spesifik

### 29. Lock bisa semua user login, unlock ikut permission

- Target kerja: menyamakan perilaku lock/unlock supaya semua user yang login bisa mengunci data, sedangkan unlock hanya muncul dan berhasil untuk user yang memang diberi akses.
- Hasil: trait lock global sekarang mengizinkan semua user login untuk lock; unlock berpindah ke pengecekan permission `unlock` atau `manage` yang relevan, dan beberapa controller operasional/marketing/KPR yang masih hardcode role lama sudah disesuaikan.
- File yang berubah:
  - `app/Http/Controllers/Concerns/HandlesCrudLock.php`
  - `app/Http/Controllers/Admin/SpkKontraktorController.php`
  - `app/Http/Controllers/Admin/SiteReportController.php`
  - `app/Http/Controllers/Admin/QualityInspectionController.php`
  - `app/Http/Controllers/Admin/SiteScheduleController.php`
  - `app/Http/Controllers/Admin/Marketing/CashSaleController.php`
  - `app/Http/Controllers/Admin/Marketing/LeadSourceController.php`
  - `app/Http/Controllers/Admin/Marketing/SurveyScheduleController.php`
  - `app/Http/Controllers/Admin/Marketing/SprController.php`
  - `app/Http/Controllers/Admin/Marketing/FollowUpController.php`
  - `app/Http/Controllers/Admin/Marketing/MarketingOperationsController.php`
  - `app/Http/Controllers/Admin/FieldSupervisionController.php`
  - `app/Http/Controllers/Admin/Kpr/KprMilestoneController.php`
  - `app/Http/Controllers/Admin/Kpr/KprSubmissionController.php`
  - `app/Http/Controllers/Admin/MaterialRequestController.php`
  - `app/Http/Controllers/Admin/MaterialUsageController.php`
  - `app/Http/Controllers/Admin/MaterialReturnController.php`
  - `app/Http/Controllers/Admin/ProgressPembangunanController.php`
- Verifikasi:
  - `php -l` lolos untuk file-file yang diubah
  - `npm run build` berhasil

### 30. Tahapan proyek dibuat context-aware setelah fresh migrate

- Target kerja: mengembalikan data tahapan ke kondisi bersih lalu memperbaiki dropdown tahapan yang kosong di rencana kerja jangka pendek, progress pembangunan, dan laporan lapangan.
- Hasil: database sudah di-reset ulang dengan `migrate:fresh --seed` supaya tabel tahapan punya konteks `unit` dan `kawasan`; opsi backend sekarang mengirim dua daftar tahapan; form `Progress Pembangunan` dan `Laporan Lapangan` sekarang otomatis berganti antara tahapan kawasan dan tahapan rumah sesuai pilihan unit.
- File yang berubah:
  - `app/Http/Controllers/Concerns/BuildsFieldOptions.php`
  - `app/Http/Controllers/Admin/ProgressPembangunanController.php`
  - `app/Http/Controllers/Admin/SiteReportController.php`
  - `resources/js/Pages/Admin/ProgressPembangunan/Index.jsx`
  - `resources/js/Pages/Admin/SiteReport/Index.jsx`
- Catatan tindak lanjut:
  - kalau mau, tahap berikutnya bisa kita sinkronkan `Tenaga Kerja & Alat` agar ikut jadwal dan tahapan yang sama
  - form lain yang masih memakai tahapan unit-only bisa disapu satu per satu kalau user menemukan dropdown kosong lagi

### 31. Tenaga Kerja & Alat disinkronkan dengan jadwal dan progress

- Target kerja: menyambungkan log tenaga kerja & alat ke alur jadwal kerja dan progress pembangunan supaya data pengawasan lapangan tidak berdiri sendiri.
- Hasil: tabel `site_manpower_logs` sekarang punya relasi ke `tahapan_pembangunan`, `site_schedule`, dan `progress_pembangunan`; form `Tenaga Kerja & Alat` sekarang menampilkan pilihan jadwal kerja dan progress terkait; saat progress atau jadwal dipilih, perumahan, unit, dan tahapan ikut terisi otomatis.
- File yang berubah:
  - `database/migrations/2026_07_02_000001_add_schedule_and_progress_to_site_manpower_logs_table.php`
  - `app/Models/SiteManpowerLog.php`
  - `app/Http/Controllers/Admin/FieldSupervisionController.php`
  - `resources/js/Pages/Admin/FieldSupervision/Index.jsx`
- Catatan tindak lanjut:
  - jika nanti mau, kita bisa bikin `Tenaga Kerja & Alat` lebih ketat lagi dengan mewajibkan pilihan jadwal/progress
  - modul `MaterialUsage` dan `SiteReport` juga bisa disamakan pola keterkaitannya kalau ingin seluruh pengawasan lapangan seragam

### 32. Progress pembangunan support kawasan tanpa unit

- Target kerja: memperbaiki progress pembangunan supaya `Nama Progress` tetap bisa dipilih saat progress kawasan dibuat, tanpa memaksa pilih unit rumah.
- Hasil: `detail_rumah_id` di progress dibuka jadi nullable, opsi `site_schedule` ikut memuat jadwal kawasan dan jadwal unit, dropdown `Nama Progress` tidak lagi dikunci oleh pilihan unit, serta query list/search progress ikut membaca jadwal kawasan.
- File yang berubah:
  - `app/Http/Controllers/Admin/ProgressPembangunanController.php`
  - `resources/js/Pages/Admin/ProgressPembangunan/Index.jsx`
  - `database/migrations/2026_07_02_000002_make_detail_rumah_nullable_on_progress_pembangunans_table.php`
- Catatan tindak lanjut:
  - kalau mau lebih ketat, kita bisa paksa `site_schedule_id` wajib dipilih agar setiap progress pasti nempel ke jadwal
  - bila modul report masih ada yang belum membaca progress kawasan, lanjutkan audit `SiteReport` dan `QualityInspection` berikutnya

### 33. Laporan lapangan ikut menampilkan jadwal kawasan

- Target kerja: membuat dropdown `Jadwal Lapangan Terkait` di laporan harian/mingguan menampilkan jadwal kawasan juga, bukan hanya jadwal yang punya unit.
- Hasil: query opsi jadwal di `SiteReport` sudah tidak memfilter `detail_rumah_id`, sehingga jadwal kawasan ikut muncul; label jadwal juga dibuat lebih informatif dengan prefix `Kawasan` atau kode unit.
- File yang berubah:
  - `app/Http/Controllers/Admin/SiteReportController.php`
- Catatan tindak lanjut:
  - kalau masih kosong di browser, lakukan refresh penuh karena kemungkinan asset lama masih cache
  - kalau mau, kita bisa lanjut samakan format pilihan di `QualityInspection` dan `FieldSupervision` juga

### 34. Laporan lapangan tidak lagi me-reset perumahan saat progress dipilih

- Target kerja: memperbaiki bug di form laporan perumahan agar saat memilih `Progress Terkait`, field `Perumahan` tidak ikut ter-reset dan form lain tetap aman.
- Hasil: opsi progress sekarang membawa fallback `perumahan_id` dari `detail_rumah` atau `site_schedule`, lalu handler frontend hanya mengganti field dengan nilai yang benar-benar ada. Dengan begitu, memilih progress kawasan tidak lagi mengosongkan `Perumahan`.
- File yang berubah:
  - `app/Http/Controllers/Admin/SiteReportController.php`
  - `resources/js/Pages/Admin/SiteReport/Index.jsx`
- Catatan tindak lanjut:
  - kalau masih ada dropdown yang terasa loncat sendiri, audit pola `onChange` yang masih memakai nilai kosong tanpa fallback
  - setelah ini, lanjut cek `SiteSupervision` dan `QualityInspection` untuk pola field yang sama

### 35. Tahapan kawasan/unit disamakan di QualityInspection dan MaterialUsage

- Target kerja: menyesuaikan modul kontrol kualitas dan pemakaian material agar pilihan tahapan mengikuti pola laporan lapangan, yaitu ada konteks kawasan dan konteks unit.
- Hasil: `QualityInspection` dan `MaterialUsage` sekarang memakai opsi tahapan terpisah untuk unit dan kawasan, opsi jadwal/progress ikut membawa fallback perumahan dari schedule/progress, dan handler form tidak lagi menimpa perumahan/unit dengan nilai kosong.
- File yang berubah:
  - `app/Http/Controllers/Admin/QualityInspectionController.php`
  - `resources/js/Pages/Admin/QualityInspection/Index.jsx`
  - `app/Http/Controllers/Admin/MaterialUsageController.php`
  - `resources/js/Pages/Admin/MaterialUsage/Index.jsx`
- Catatan tindak lanjut:
  - audit modul lain yang masih memakai `tahapan_pembangunan_id` supaya logika kawasan/unit seragam
  - cek `SPK` dan modul perubahan pekerjaan kalau nanti perlu disambungkan ke progress/tahapan yang sama

### 36. Opname kontraktor diberi sumber tenaga kerja dan tahapan context-aware

- Target kerja: membuat input opname kontraktor bisa memilih sumber tenaga kerja secara langsung, lalu tahapan mengikuti pola kawasan/unit seperti laporan lapangan.
- Hasil: `opname-kontraktor` sekarang punya field `Sumber Tenaga Kerja` sendiri, `SPK Kontraktor` tetap mengikuti sumber kontraktor, dan field tahapan di pengawasan lapangan memakai opsi kawasan atau unit sesuai lokasi yang dipilih.
- File yang berubah:
  - `app/Http/Controllers/Admin/FieldSupervisionController.php`
  - `resources/js/Pages/Admin/FieldSupervision/Index.jsx`
- Catatan tindak lanjut:
  - kalau dropdown SPK masih terasa susah dipilih di browser, cek apakah masalahnya di z-index atau focus overlay komponen dropdown
  - lanjut audit `perubahan-pekerjaan` dan modul pengawasan lain agar pola field-nya konsisten

### 37. Perubahan pekerjaan mengikuti pola SPK dan defect bisa menempel ke progress

- Target kerja: menyamakan `perubahan-pekerjaan` dengan pola `opname-kontraktor` untuk sumber tenaga kerja, lalu memberi `defect / punch list` relasi progress opsional agar temuan QC bisa menempel ke progres bila perlu.
- Hasil: `perubahan-pekerjaan` sekarang punya field `Sumber Tenaga Kerja` dan SPK hanya tampil saat sumbernya kontraktor, sementara `defect` kini mendukung `progress_pembangunan_id` baik di form maupun sinkronisasi dari inspeksi QC.
- File yang berubah:
  - `app/Http/Controllers/Admin/FieldSupervisionController.php`
  - `resources/js/Pages/Admin/FieldSupervision/Index.jsx`
  - `app/Http/Controllers/Admin/QualityInspectionController.php`
  - `app/Models/FieldDefect.php`
  - `database/migrations/2026_07_02_000003_add_progress_pembangunan_id_to_field_defects_table.php`
- Catatan tindak lanjut:
  - jalankan migrate fresh/refresh kalau mau kolom defect progress ikut aktif di database lokal
  - kalau nanti ingin semua defect wajib menempel ke progress, tinggal ubah rule `nullable` jadi `required`
