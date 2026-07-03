# ERP Gap Matrix Sistem Properti

Dokumen ini merangkum area ERP yang sudah ada di sistem `ali_properti`, mana yang masih **partial**, dan mana yang **belum terlihat** dari codebase saat ini.

Catatan:
- Status disusun dari hasil inspeksi route, controller, model, seeder, service, dan sidebar/menu.
- Ini fokus ke kebutuhan ERP untuk developer properti rumah.
- Beberapa area bisa saja sudah ada secara tersebar, tetapi belum menjadi workflow end-to-end yang utuh.

## Ringkasan Cepat

| Area | Status | Catatan Singkat |
|---|---|---|
| Marketing / CRM | Sudah ada | Lead, follow up, SPR, laporan, pipeline, campaign, survey, reminder |
| Proyek / Unit Rumah | Sudah ada | Perumahan, detail rumah, progress, HPP unit, detail unit, lock workflow |
| Approval | Sudah ada | Approval request, approval setting, approval SPR, material, SPK, refund |
| Gudang / Logistik | Sudah ada | Material, stok, permintaan, penggunaan, pengembalian, transaksi logistik |
| Keuangan / HPP | Sudah ada | Transaksi kas/bank, jurnal, rekening bank, HPP, realisasi, laporan |
| KPR / Pembiayaan | Partial | Ada pengajuan dan milestone, tapi belum tampak lengkap seperti pipeline bank end-to-end |
| Vendor / Supplier management | Partial | Ada field `supplier`, pembayaran hutang supplier, tapi belum terlihat master vendor penuh |
| Procurement end-to-end | Partial | Ada request, purchase, inspection, dan accounting, tapi belum terlihat alur PO-Receipt-Invoice terpisah penuh |
| Budget vs actual per proyek / cost center | Partial | HPP dan realisasi ada, tetapi belum tampak cost center/budget control yang konsisten lintas modul |
| Fixed asset lifecycle / depreciation | Missing | Ada inventaris aset, tetapi belum terlihat depresiasi dan siklus aset tetap penuh |
| After-sales / warranty / complaint | Missing | Belum terlihat modul keluhan, garansi, atau service after-sales unit rumah |
| Audit trail / BI reporting | Partial | Ada created_by/updated_by, lock, notification, laporan; belum terlihat audit log dan BI layer yang kuat |

## Analisis Per Area

### 1) Vendor / Supplier Management

**Status: Partial**

Yang sudah ada:
- `supplier` muncul di material purchase dan harga material.
- Ada accounting service yang mengenali `supplier_bill` dan `supplier_payment`.
- Ada hutang supplier yang sudah masuk ke laporan keuangan.

Yang belum terlihat:
- Master vendor/supplier khusus.
- Profil vendor, kontak, NPWP, termin pembayaran, rating, histori performa.
- Workflow vendor onboarding dan evaluasi.
- Relasi vendor ke PO, invoice, pembayaran, dan retensi.

Kesimpulan:
- Sistem sudah menyentuh vendor dari sisi transaksi, tetapi belum menjadi modul vendor management yang utuh.

### 2) Procurement End-to-End

**Status: Partial**

Yang sudah ada:
- Permintaan material.
- Pembelian material.
- Pemeriksaan barang masuk.
- Pemakaian dan pengembalian material.
- Posting ke accounting / hutang supplier.

Yang belum terlihat:
- Pemisahan dokumen bisnis yang tegas antara:
  - purchase request
  - purchase order
  - goods receipt
  - supplier invoice
  - payment
- Approval procurement bertingkat yang konsisten dari request sampai payment.
- Kontrol status dokumen procurement yang benar-benar satu jalur.

Kesimpulan:
- Fungsinya sudah ada, tetapi belum rapi sebagai rantai procurement ERP standar.

### 3) Budget vs Actual per Proyek / Cost Center

**Status: Partial**

Yang sudah ada:
- HPP per perumahan dan HPP unit rumah.
- Realisasi HPP.
- Ringkasan `jumlah_rab`, `jumlah_realisasi`, dan `sisa_anggaran`.
- Beberapa modul menyimpan anggaran dan realisasi biaya.

Yang belum terlihat:
- Cost center / project budget yang formal dan seragam.
- Struktur budget tahunan/bulanan per proyek.
- Variance analysis per akun biaya, per tahap, per blok, atau per unit.
- Budget kontrol lintas approval.

Kesimpulan:
- Secara konsep sudah masuk, tetapi belum jadi modul budget control ERP yang konsisten.

### 4) Fixed Asset Lifecycle / Depreciation

**Status: Missing**

Yang sudah ada:
- `asset inventory` dan `office_asset` ada.
- Ada request, usage, maintenance, dan approval untuk aset.

Yang belum terlihat:
- Kartu aset tetap yang utuh.
- Depresiasi / penyusutan.
- Umur manfaat.
- Disposal / write-off / transfer aset.
- Rekonsiliasi aset ke jurnal.

Kesimpulan:
- Ini masih level inventaris aset operasional, belum fixed asset accounting penuh.

### 5) After-Sales / Warranty / Complaint Handling

**Status: Missing**

Yang sudah ada:
- Progress pembangunan, quality inspection, site report, dan internal handover.
- Dasar untuk serah terima dan kontrol kualitas lapangan.

Yang belum terlihat:
- Modul komplain customer setelah serah terima.
- Retur/garansi per unit.
- Ticketing after-sales.
- SLA penyelesaian complaint.
- Riwayat service per unit rumah.

Kesimpulan:
- Ini salah satu gap paling jelas untuk ERP properti developer.

### 6) Audit Trail / Reporting BI

**Status: Partial**

Yang sudah ada:
- `created_by`, `updated_by`, dan lock/unlock fields di banyak tabel.
- Notifikasi.
- Laporan di marketing, finance, dan proyek.
- Approval history di beberapa area.

Yang belum terlihat:
- Audit trail terpusat yang menyimpan siapa mengubah apa, kapan, dan dari nilai apa ke apa.
- Filter histori perubahan per field.
- Report builder / BI dashboard lintas divisi.
- Dashboard analitik yang menyatukan marketing, finance, proyek, dan logistik.

Kesimpulan:
- Ada fondasi audit dan reporting, tetapi belum cukup kuat untuk ERP skala produksi yang butuh traceability tinggi.

## Area Yang Sudah Cukup Kuat

- Marketing / CRM
- Approval workflow dasar
- Manajemen proyek dan unit rumah
- HPP per perumahan dan unit
- Material dan logistik
- Keuangan dasar dan laporan
- Role / permission

## Prioritas Perbaikan Yang Paling Masuk Akal

1. Rapikan permission dan visibility menu supaya owner, manager, supervisor, dan role lain konsisten.
2. Satukan procurement flow: request -> approval -> purchase -> receipt -> invoice -> payment.
3. Tambahkan master vendor/supplier yang benar-benar terpisah.
4. Formalisasi budget vs actual per proyek / cost center.
5. Tambahkan audit trail terpusat.
6. Tambahkan modul after-sales / complaint / warranty unit rumah.
7. Lengkapi aset tetap dengan lifecycle dan depresiasi.

## Kesimpulan

Sistem ini sudah punya fondasi ERP properti yang cukup luas dan relevan.
Namun, untuk disebut ERP standar yang matang, masih perlu penguatan di area:
- vendor management,
- procurement end-to-end,
- budget control,
- fixed asset accounting,
- after-sales,
- dan audit/BI.

Kalau nanti mau dilanjutkan, dokumen ini bisa dipakai sebagai roadmap implementasi modul berikutnya.
