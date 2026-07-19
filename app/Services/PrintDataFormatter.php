<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrintDataFormatter
{
    private const HIDDEN = ['id', 'deleted_at', 'updated_at', 'created_at', 'locked_at', 'locked_by'];

    private const LABELS = [
        'transaction_no' => 'Nomor Transaksi', 'date' => 'Tanggal', 'borrower' => 'Peminjam', 'division' => 'Divisi',
        'block' => 'Blok', 'unit_house' => 'Unit Rumah', 'planned_return_date' => 'Rencana Tanggal Kembali',
        'purpose' => 'Keperluan', 'notes' => 'Catatan', 'status' => 'Status', 'transaction_type' => 'Jenis Transaksi',
        'taken_by_name' => 'Nama Pengambil', 'taken_by_phone' => 'Telepon Pengambil', 'handed_over_by' => 'Diserahkan Oleh',
        'received_by' => 'Diterima Oleh', 'created_by' => 'Dibuat Oleh', 'updated_by' => 'Diperbarui Oleh',
        'verified_by' => 'Diverifikasi Oleh', 'approved_by' => 'Disetujui Oleh', 'inventory_location_id' => 'Lokasi Inventaris',
        'perumahan_id' => 'Perumahan', 'inventory_item_id' => 'Barang', 'office_asset_id' => 'Aset', 'heavy_equipment_id' => 'Alat Berat',
        'operator_id' => 'Operator', 'component_id' => 'Komponen', 'supplier_id' => 'Supplier', 'gudang_id' => 'Gudang',
        'description' => 'Deskripsi', 'quantity' => 'Jumlah', 'condition' => 'Kondisi', 'return_date' => 'Tanggal Kembali',
    ];

    private const VALUES = [
        'draft' => 'Draf', 'submitted' => 'Diajukan', 'pending' => 'Menunggu Persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak',
        'locked' => 'Final', 'active' => 'Aktif', 'inactive' => 'Tidak Aktif', 'partially_returned' => 'Dikembalikan Sebagian',
        'returned' => 'Sudah Dikembalikan', 'borrowed' => 'Dipinjam', 'loan' => 'Peminjaman', 'return' => 'Pengembalian',
        'transfer' => 'Mutasi', 'damage' => 'Kerusakan', 'loss' => 'Kehilangan', 'maintenance' => 'Perawatan', 'fuel' => 'Pengisian BBM',
        'good' => 'Baik', 'damaged' => 'Rusak', 'lost' => 'Hilang', 'open' => 'Terbuka', 'closed' => 'Selesai',
    ];

    private const RELATIONS = [
        'created_by' => ['users', 'name'], 'updated_by' => ['users', 'name'], 'handed_over_by' => ['users', 'name'],
        'received_by' => ['users', 'name'], 'verified_by' => ['users', 'name'], 'approved_by' => ['users', 'name'],
        'inventory_location_id' => ['inventory_locations', 'name'], 'perumahan_id' => ['perumahans', 'nama_perumahan'],
        'inventory_item_id' => ['inventory_items', 'name'], 'office_asset_id' => ['office_assets', 'asset_code'],
        'heavy_equipment_id' => ['heavy_equipments', 'name'], 'operator_id' => ['heavy_equipment_operators', 'name'],
        'component_id' => ['heavy_equipment_components', 'name'], 'supplier_id' => ['suppliers', 'nama_supplier'],
        'gudang_id' => ['gudangs', 'nama_gudang'],
    ];

    public function rows(array|object $row): array
    {
        return collect((array) $row)->reject(fn ($value, $key) => in_array($key, self::HIDDEN, true))
            ->map(function ($value, $key) {
                if (isset(self::RELATIONS[$key]) && filled($value)) {
                    [$table,$column] = self::RELATIONS[$key];
                    if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                        $value = DB::table($table)->where('id', $value)->value($column) ?? '-';
                    }
                }

                return ['label' => self::LABELS[$key] ?? str($key)->replace('_', ' ')->title()->toString(), 'value' => $this->value($value)];
            })->values()->all();
    }

    public function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }
        if (is_scalar($value)) {
            return self::VALUES[strtolower((string) $value)] ?? (string) $value;
        }

        return collect($value)->flatten()->implode(', ');
    }
}
