# Audit Kesiapan Produk CRM Marketing & Admin Sales

Tanggal audit: 2026-08-16  
Ruang lingkup: Marketing CRM, Admin Sales workspace, lead intake, customer conversion, reservasi, SPR, dan handoff ke Integrated Sales.

## Kesimpulan

Fondasi aplikasi sudah mencakup lifecycle internal developer properti: lead masuk, deduplikasi, consent, assignment, SLA respons, kualifikasi, konversi Customer, minat unit, survey/kunjungan, reservasi, Booking Fee, SPR, pembiayaan, tahapan penjualan, readiness administrasi, kalender milestone, work queue Admin Sales, laporan, dan approval.

Aplikasi belum dapat disebut setara produk CRM komersial sebelum empat kelompok gap berikut selesai:

1. komunikasi dan nurture multi-kanal yang benar-benar terintegrasi;
2. otomatisasi workflow dan next-best-action yang dapat dikonfigurasi;
3. 360-degree customer/property/service view sampai pasca-penjualan;
4. pembuktian runtime, keamanan tenancy, observability, backup, dan mobile/field operation.

## Inventaris fitur yang sudah tersedia

### Marketing

- Dashboard meja kerja harian, quick actions, aktivitas, reminder, SLA, pipeline, dan monitoring.
- Lead terpisah dari Customer, dengan source, campaign, kanal, perumahan, unit, anggaran, minat, metode pembayaran, timeline, dan status kualifikasi.
- Pemeriksaan duplikat berdasarkan telepon, email, dan identitas; merge/override wajib menyimpan alasan.
- Consent status, consent channel, dan do-not-contact.
- Assignment lead perusahaan, respons assignment, distribusi ulang, dan riwayat assignment.
- Follow-up, kunjungan/canvassing, GPS check-in/check-out, foto bukti, survey, action plan, dan reminder.
- Konversi Lead Qualified ke Customer; reservasi hanya dari Customer hasil konversi.
- Campaign, source, template, target, komisi, evaluasi, master pilihan, kalender, pipeline report, owner report, dan export.
- Checklist dokumen customer yang terhubung ke repository dokumen.

### Admin Sales

- Dashboard exception queue.
- Lead qualified belum diverifikasi.
- Lead perusahaan belum dibagikan.
- SLA respons pertama terlambat.
- Review follow-up dan kunjungan tanpa menimpa catatan Marketing.
- Readiness Customer: profil, unit, metode pembayaran, dan dokumen.
- Work item dengan PIC, prioritas, due date, status, resolution note, notifikasi, dan activity log.
- Import CSV/XLSX, export, duplicate resolution, dan idempotent lead intake API.
- Kalender milestone appraisal, akad, pencairan, serah terima internal, dan serah terima customer.
- Laporan Admin Sales.

### Transaksi yang sudah tersambung

- Reservasi dan Booking Fee.
- SPR draft/lock/approval.
- Cash, cash bertahap, KPR developer, dan KPR bank.
- Payment schedule, receipt, piutang, dokumen, dan proses penjualan sampai serah terima.
- Setting Approval sebagai sumber tahap dan reviewer.

## Gap terhadap CRM properti komersial

| Prioritas | Gap | Dampak produk | Rekomendasi |
|---|---|---|---|
| P0 | Email/WhatsApp/SMS belum menjadi inbox dan activity stream terintegrasi | Riwayat komunikasi masih tersebar; SLA dan consent sulit dibuktikan | Buat Communication Hub: provider abstraction, inbound/outbound message, template, opt-in channel, delivery status, retry, webhook, dan timeline immutable |
| P0 | Nurture/sequence belum configurable | Follow-up masih bergantung pada kedisiplinan user | Fondasi sequence lead baru sudah ditambahkan: langkah bertahap, jeda, stop rule, dan pengingat otomatis; berikutnya perlu layar konfigurasi berbasis stage/channel |
| P0 | Runtime MySQL, browser, queue, scheduler, storage, dan provider belum terbukti | Tidak layak menjanjikan reliabilitas produksi | Buat smoke suite MySQL, browser E2E role matrix, scheduler/queue health check, storage check, dan deployment checklist |
| P0 | Hak akses per cabang/perumahan belum dibuktikan menyeluruh | Risiko kebocoran lead, customer, dan unit antar wilayah | Terapkan policy/scope terpusat untuk list, detail, export, dashboard aggregate, import, dan API; tambah negative authorization tests |
| P1 | Objek Property/Listing belum menjadi workspace CRM terpadu | User harus berpindah antara lead, unit, dan master perumahan | Buat Property Workspace: proyek, unit, status availability, harga/version, promo, media, lead interest, reservation, dan history perubahan |
| P1 | Forecast dan pipeline coverage masih laporan, belum forecast operasional | Manajemen belum dapat memprediksi closing dan cash-in secara konsisten | Tambahkan forecast by stage/probability, weighted pipeline, aging, target gap, expected close, dan snapshot periodik |
| P1 | Service pasca-penjualan belum terlihat sebagai CRM case | Hubungan berhenti di akad/serah terima | Tambahkan case/ticket customer, complaint, warranty/maintenance, SLA, assignment, escalation, dan customer timeline pasca-huni |
| P1 | Referral, repeat buyer, household/contact relationship belum lengkap | Nilai customer jangka panjang tidak terukur | Tambahkan household/contact roles, referral source, referral reward, repeat purchase, dan consent history |
| P1 | Mobile/field mode dan offline belum terbukti | Marketing lapangan bergantung koneksi stabil | Sediakan PWA/mobile field shell, offline draft queue, photo compression, GPS permission state, retry, dan sync conflict log |
| P1 | Observability dan audit operasional belum menjadi produk | Sulit mencari kegagalan webhook, reminder, upload, atau approval | Tambahkan correlation ID, failed jobs dashboard, provider delivery log, audit export, health metrics, dan alert |
| P2 | AI/lead scoring belum sampai rekomendasi tindakan | Prioritas masih rule-based dan manual | Setelah data cukup, tambahkan explainable score, next-best-action, summary, dan human approval; jangan mulai dari AI black box |
| P2 | Landing page/form website dan attribution end-to-end belum terbukti | Lead digital masih memerlukan integrasi luar/manual import | Tambahkan form API, UTM/campaign attribution, consent capture, spam protection, webhook signature, dan lead source reconciliation |
| P2 | Product catalog, proposal, quotation, dan e-sign belum menjadi workflow terpadu | Sales masih banyak membuat dokumen/proses di luar aplikasi | Tambahkan price book/version, proposal template, approval pricing/discount, document generation, e-sign provider, dan expiry |

## Perbandingan praktik CRM eksternal

CRM real-estate modern menekankan satu profil terpadu untuk kontak/prospek, riwayat interaksi, deal lifecycle, task, campaign, dan akses lintas perangkat. Salesforce juga menempatkan lead scoring, forecast, otomatisasi pesan/follow-up, property data, serta integrasi pihak ketiga sebagai kapabilitas utama. HubSpot menekankan pipeline automation, workflow lead handoff, sales sequences, inbox komunikasi terpusat, task harian, dan reporting. Propertybase menekankan alur lead-to-close, contacts/activity, listings/properties, offers/closings, dashboards, dan integrasi korespondensi.

Implikasinya untuk aplikasi ini:

- Lifecycle transaksi internal sudah menjadi keunggulan lokal.
- Communication Hub dan nurture automation adalah kekurangan paling terasa dibanding CRM komersial.
- Property/listing workspace dan post-sale service perlu ditambahkan agar produk tidak berhenti sebagai CRM penjualan developer.
- Forecast, audit operasional, dan mobile field proof wajib ada sebelum menjual SLA produk.

## Roadmap implementasi yang disarankan

### Fase 1 — Production trust

- MySQL/browser smoke tests untuk role Marketing, Admin Sales, Supervisor, Finance, Owner.
- Policy scope cabang/perumahan untuk semua query, export, aggregate, API, dan import.
- Queue/scheduler/storage/provider health checks.
- Standardized modal rejection, error state, empty state, loading state, dan retry.
- Pisahkan komponen halaman SPR, Operations, dan Integrated Sales tanpa mengubah route/payload.

### Fase 2 — Communication and automation

- Communication Hub WhatsApp/email/SMS (fondasi inbox, pesan masuk/keluar, consent guard, dan webhook sudah tersedia; adapter provider nyata masih perlu dipasang).
- Template dengan variable aman, consent guard, opt-out, delivery status, inbound webhook, dan retry.
- Sequence nurture dan automatic tasks (fondasi tabel, langkah default lead baru, sinkronisasi terjadwal, dan pengingat idempotent sudah tersedia).
- SLA escalation dan Admin Sales handoff otomatis.

### Fase 3 — Property and management intelligence

- Property Workspace dan price/promo versioning.
- Forecast, weighted pipeline, aging, conversion cohort, campaign ROI, dan commission forecast.
- Proposal/quotation/document generation dan e-sign.

### Fase 4 — Customer lifetime value

- Case/complaint/warranty pasca-serah-terima.
- Referral, repeat buyer, household relationship.
- Portal/customer communication history.

### Fase 5 — Field/mobile and scale

- Offline-first field activity.
- Media/GPS sync queue.
- Multi-company/branch tenancy hardening.
- Observability, audit retention, backup/restore drill, dan disaster recovery.

## Kriteria layak jual

Aplikasi baru layak ditawarkan ke perusahaan properti jika seluruh P0 lulus, tidak ada negative authorization test yang gagal, lead communication dapat ditelusuri dari inbound sampai conversion, sequence tidak menghubungi lead yang opt-out, approval side effect idempotent, dan alur utama sudah lulus browser E2E pada database MySQL yang menyerupai produksi.
