# Aturan Wajib Pengembangan Modul

Instruksi ini berlaku untuk semua AI/agent yang mengubah repository ini.

## Kontrak Setting Approval

Setiap penambahan modul bisnis, transaksi, master yang dapat difinalisasi, atau proses pengajuan WAJIB diintegrasikan dengan Setting Approval. Modul belum dianggap selesai sebelum seluruh kontrak berikut terpenuhi:

1. Daftarkan `module_key`, label, dan model pada `App\Support\ApprovalResources`.
2. Sediakan status `draft/locked`, `locked_at`, dan `locked_by`, atau dokumentasikan alasan teknis jika modul memakai status finalisasi domain yang setara.
3. Finalisasi wajib memanggil `ApprovalWorkflowService::submitLocked()`; unlock wajib membatalkan request pending.
4. Jumlah tahap dan role approver hanya boleh dibaca dari `approval_settings`. Dilarang hard-code role, nama jabatan, atau jumlah tahap di controller/UI.
5. Tabel modul wajib menampilkan kolom status approval, tahap aktif, serta tombol approve/reject hanya ketika `ApprovalWorkflowService::canReview()` bernilai benar.
6. Halaman Setting Approval wajib menampilkan modul tersebut. Menambahkan entri registry tanpa menghubungkan controller dan tabel tidak dianggap integrasi.
7. Side effect setelah approval final (posting transaksi, reservasi unit, pembuatan jadwal, dan sebagainya) harus idempotent dan berjalan baik dari halaman modul maupun halaman Approval pusat.
8. Tambahkan feature test untuk auto approve (0 tahap), 1–3 tahap, role tiap tahap, reject, unlock/resubmit, visibilitas approval pada tabel, dan side effect final.
9. Perbarui `docs/approval-lock-integration-audit.md` ketika modul ditambah atau status integrasinya berubah.

Modul read-only, laporan tanpa mutasi, chat, profil, dan Setting Approval sendiri boleh dikecualikan. Pengecualian harus ditulis pada audit beserta alasannya.

Gunakan checklist lengkap di `docs/ai-module-approval-contract.md` sebelum menyerahkan perubahan.
