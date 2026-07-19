# Kontrak Integrasi Setting Approval untuk Modul Baru

Dokumen ini adalah definition of done wajib bagi manusia maupun AI yang menambah modul.

## Pola referensi: lifecycle SPR

Modul `SPR` adalah implementasi acuan untuk modul transaksi/pengajuan lain. Pola yang wajib ditiru:

1. `Create` hanya menyimpan `record_status = draft`; create tidak boleh memanggil `submitLocked()`.
2. Draft hanya boleh terlihat, dibuka, dan diubah oleh `created_by` melalui query backend, bukan sekadar menyembunyikan tombol.
3. Aksi `Lock` hanya boleh dijalankan pembuat draft dan wajib memvalidasi ulang data, master aktif, dokumen wajib, ownership, serta konflik resource secara transaksional.
4. Setelah valid, Lock mengisi `locked_at/locked_by`, mengubah status domain menjadi menunggu approval, lalu memanggil `ApprovalWorkflowService::submitLocked()`.
5. Data locked tidak boleh diedit oleh siapa pun. Manager/Owner tidak mendapat bypass edit; kewenangannya adalah `Unlock`.
6. `Unlock` Manager/Owner membatalkan approval pending dan mengembalikan data menjadi draft privat milik pembuat semula.
7. Data locked dapat dilihat pembuat, role tahap approval, Manager, dan Owner sesuai scope bisnis; draft tidak ikut terbuka kepada mereka.
8. Tombol approve/reject hanya berasal dari `ApprovalWorkflowService::canReview()`, bukan permission `*.approve` atau role hard-coded.
9. Side effect final baru berjalan setelah approval final/auto approve, bukan ketika draft dibuat.

Referensi implementasi: `SprController`, `ApprovalWorkflowEffectService`, dan `SprDraftLockLifecycleTest`.

## Backend

- Tentukan `module_key` yang stabil dan daftarkan di `ApprovalResources`.
- Pastikan model dan tabel mendukung finalisasi/lock.
- Buat atau pastikan baris `approval_settings` tersedia tanpa menimpa konfigurasi pengguna yang sudah ada.
- Kirim finalisasi melalui `ApprovalWorkflowService::submitLocked()`.
- Gunakan `ApprovalWorkflowService::canReview()` untuk otorisasi tahap aktif.
- Jangan memeriksa role approver secara hard-coded di controller.
- Letakkan side effect final pada effect/service terpusat agar approval dari tabel modul dan antrean pusat menghasilkan keluaran yang sama.
- Pastikan approve dan side effect idempotent.

## Tabel dan UI

- Tampilkan status lock dan status approval sebagai dua informasi berbeda.
- Tampilkan `Menunggu approval tahap X/Y`, `Disetujui`, atau `Ditolak`.
- Tampilkan tombol approve/reject hanya bagi role tahap aktif.
- Data locked tidak boleh diedit atau dihapus sebelum unlock/reject sesuai aturan domain.
- Jangan memberi bypass edit data locked kepada Manager/Owner. Jika ada kesalahan, gunakan Unlock lalu pembuat memperbaiki draft.
- Sediakan akses ke riwayat tahap dan catatan penolakan bila relevan.

## Pengujian wajib

- 0 tahap menghasilkan auto approve.
- Alur 1, 2, dan 3 tahap mengikuti urutan role.
- Role yang bukan tahap aktif menerima 403 dan tidak melihat tombol.
- Reject mengubah status domain dan memungkinkan perbaikan/resubmit bila diperbolehkan.
- Unlock membatalkan approval pending.
- Status dan tombol approval terlihat pada tabel modul.
- Approval dari antrean pusat dan halaman modul menjalankan side effect final yang sama persis.

## Larangan

- Jangan hanya menambahkan modul ke halaman Setting Approval tanpa menghubungkan workflow-nya.
- Jangan membuat alur approval kedua dengan tabel/status/role hard-coded jika workflow pusat dapat digunakan.
- Jangan menyatakan modul selesai bila tabel modul belum menampilkan status approval.
