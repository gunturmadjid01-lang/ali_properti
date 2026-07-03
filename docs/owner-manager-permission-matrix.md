# Owner & Manager Permission Matrix

Dokumen ini dipakai untuk memetakan menu dan halaman yang sebaiknya tampil untuk role `owner` dan `manajer_pimpro` / `manager`.

Tujuan:
- mengurangi menu yang muncul tanpa hak yang sesuai,
- memisahkan akses lihat, create, edit, delete, dan approval,
- membuat perilaku sidebar, tombol, dan form konsisten.

## Prinsip Dasar

1. `owner` fokus ke:
   - dashboard
   - laporan
   - approval
   - monitoring lintas divisi
   - setting permission bila memang diizinkan

2. `manager` fokus ke:
   - approval tertentu
   - monitoring proyek
   - beberapa master data yang memang dibutuhkan
   - operasional yang memang ditugaskan

3. Halaman yang punya form create/edit harus benar-benar dicek oleh permission, bukan hanya role.

4. Jika sebuah halaman default-nya read-only, maka:
   - tombol tambah/edit/delete tidak ditampilkan
   - form create/edit tidak dirender

5. Jika role hanya approval-only, maka:
   - halaman detail masih boleh tampil
   - aksi submit/create harus hilang

## Matriks Awal

| Modul / Halaman | Owner | Manager | Catatan |
|---|---|---|---|
| Dashboard | Lihat | Lihat | Tetap tampil |
| Approval umum | Lihat + approve | Lihat + approve | Sesuai setting approval |
| Approval SPR | Approve only | Approve only | Tombol Buat SPR tidak muncul untuk owner |
| Approval Material | Approve only | Approve only | Form create hanya untuk pengawas |
| Approval SPK | Approve only | Approve only | Manager bisa approval, bukan create |
| Approval Refund SPR | Approve only | Approve only | Ikuti approval setting |
| Lead Source | Detail/setting jika diberi permission | Detail/setting jika diberi permission | Default tersembunyi jika permission create/edit/delete tidak ada |
| Lead Report | Lihat | Lihat | Read-only |
| Pipeline Report | Lihat | Lihat | Read-only |
| Campaign & Promosi | Lihat saja bila diizinkan | Umumnya tidak tampil | Create/edit/delete hanya role yang diberi permission |
| Validasi Berkas | Lihat | Jika diizinkan | Form hanya muncul jika ada create/update |
| Distribusi Lead | Lihat | Jika diizinkan | Default supervisor marketing |
| Monitoring Aktivitas | Lihat | Lihat | Read-only |
| Leaderboard Sales | Lihat | Lihat | Read-only |
| Tagihan & Kwitansi | Lihat + approve | Lihat + approve | Approval-only |
| Perumahan | Lihat | Lihat | CRUD tergantung permission |
| Unit Rumah / Kapling | Lihat | Lihat | HPP edit terpisah, bukan form create sembarang |
| HPP Perumahan | Lihat + edit bila diberi permission | Lihat + edit bila diberi permission | Harus konsisten dengan unit HPP |
| Progress Pembangunan | Lihat + approve | Lihat + approve | CRUD tergantung permission |
| Gudang / Material | Lihat | Lihat | Edit hanya bila diberi permission |
| Finance | Lihat | Lihat | Input jurnal / transaksi hanya jika permission aktif |
| KPR | Lihat | Lihat | Form sesuai role yang ditugaskan |
| Role Permission | Lihat + manage bila diizinkan | Umumnya tidak tampil | Khusus setting akses |

## Catatan Khusus

- Sidebar masih perlu audit karena ada item yang bisa muncul lewat `role` walau permission belum aktif.
- Beberapa controller masih mengandalkan `role` langsung untuk approval atau akses khusus.
- Untuk role `owner`, prinsipnya:
  - lihat laporan,
  - approval,
  - setting permission,
  - tanpa form create/edit yang tidak perlu.
- Untuk role `manager`, prinsipnya:
  - approval dan monitoring,
  - beberapa create/edit tertentu sesuai tugas,
  - tapi bukan semua form operasional.

## Langkah Berikutnya

1. Cocokkan matriks ini dengan sidebar yang ada.
2. Cocokkan matriks ini dengan permission seed di database.
3. Hapus menu/form yang tidak sesuai default role.
4. Tambahkan guard di controller dan frontend untuk item yang masih lolos.
