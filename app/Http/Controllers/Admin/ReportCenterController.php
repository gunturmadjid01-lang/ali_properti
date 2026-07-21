<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BarangMaterial;
use App\Models\Costumer;
use App\Models\CostumerFollowUp;
use App\Models\CustomerReceipt;
use App\Models\Gudang;
use App\Models\KprSubmission;
use App\Models\MaterialPurchase;
use App\Models\MaterialPurchaseDetail;
use App\Models\MaterialReturnDetail;
use App\Models\MaterialStockOpnameDetail;
use App\Models\MaterialUsageDetail;
use App\Models\OfficeAsset;
use App\Models\Perumahan;
use App\Models\SiteMaterialStock;
use App\Models\Spr;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportCenterController extends Controller
{
    public function index(): Response
    {
        $this->authorizeReport('view');

        return Inertia::render('Admin/Reports/Index', [
            'title' => 'Laporan',
            'description' => 'Pilih kelompok laporan, atur filter, preview data, lalu cetak laporan ke PDF.',
            'groups' => collect([
                [
                    'key' => 'progress-pembangunan',
                    'title' => 'Laporan Progress Pembangunan',
                    'description' => 'Progress harian, mingguan/rentang tanggal, atau bulanan untuk maksimal dua blok beserta pembayaran SPK.',
                    'href' => route('admin.construction-progress-report.index', absolute: false),
                    'permission' => 'laporan.view',
                ],
                [
                    'key' => 'pemakaian-barang',
                    'title' => 'Laporan Pemakaian Barang',
                    'description' => 'Pemakaian material pada progress pembangunan per perumahan, unit, harian, mingguan, atau bulanan.',
                    'href' => route('admin.material-usage-report.index', absolute: false),
                    'permission' => 'laporan-persediaan-material.view',
                ],
            ])->concat(collect($this->groups())->map(fn (array $group, string $key) => [
                'key' => $key,
                'title' => $group['title'],
                'description' => $group['description'],
                'href' => route('admin.reports.show', $key, absolute: false),
                'permission' => $group['permission'],
            ]))->values(),
        ]);
    }

    public function show(Request $request, string $group): Response
    {
        $config = $this->groupConfig($group);
        $this->authorizeReport('view', $config['permission']);

        $type = (string) $request->query('jenis_laporan', array_key_first($config['types']));
        if (! isset($config['types'][$type])) {
            $type = array_key_first($config['types']);
        }

        $result = $this->reportData($group, $type, $request);

        return Inertia::render('Admin/Reports/Show', [
            'title' => $config['title'],
            'description' => $config['description'],
            'baseUrl' => route('admin.reports.show', $group, absolute: false),
            'printUrl' => route('admin.reports.print', $group, absolute: false),
            'group' => $group,
            'selectedType' => $type,
            'types' => collect($config['types'])->map(fn (array $item, string $key) => [
                'value' => $key,
                'label' => $item['label'],
            ])->values(),
            'filters' => $this->filtersFor($group, $type),
            'filterValues' => $this->filterValues($request),
            'options' => $this->filterOptions(),
            'columns' => $result['columns'],
            'rows' => $result['rows'],
            'summary' => $result['summary'],
            'permissions' => [
                'canExport' => $this->canReport('export'),
            ],
        ]);
    }

    public function print(Request $request, string $group)
    {
        $config = $this->groupConfig($group);
        $this->authorizeReport('export', $config['permission']);

        $type = (string) $request->query('jenis_laporan', array_key_first($config['types']));
        if (! isset($config['types'][$type])) {
            $type = array_key_first($config['types']);
        }

        $result = $this->reportData($group, $type, $request, 5000);

        return response()
            ->view('reports.print', [
                'title' => $config['types'][$type]['label'],
                'groupTitle' => $config['title'],
                'printedAt' => now()->format('d/m/Y H:i'),
                'columns' => $result['columns'],
                'rows' => $result['rows'],
                'summary' => $result['summary'],
                'filters' => collect($this->filterValues($request))
                    ->filter(fn ($value) => filled($value))
                    ->all(),
            ]);
    }

    private function reportData(string $group, string $type, Request $request, int $limit = 200): array
    {
        return match ($group) {
            'master-data' => $this->masterReport($type, $request, $limit),
            'pembelian' => $this->purchaseReport($type, $request, $limit),
            'persediaan-material' => $this->inventoryReport($type, $request, $limit),
            'marketing' => $this->marketingReport($type, $request, $limit),
            'aset-perusahaan' => $this->companyAssetReport($type, $request, $limit),
            'alat-berat' => $this->heavyEquipmentReport($type, $request, $limit),
            default => abort(404),
        };
    }

    private function companyAssetReport(string $type, Request $request, int $limit): array
    {
        if ($type === 'daftar-aset') {
            $rows = DB::table('office_assets as assets')
                ->join('inventory_items as items', 'items.id', '=', 'assets.inventory_item_id')
                ->leftJoin('inventory_categories as categories', 'categories.id', '=', 'items.inventory_category_id')
                ->leftJoin('inventory_locations as locations', 'locations.id', '=', 'assets.inventory_location_id')
                ->whereNull('assets.deleted_at')
                ->when($request->query('location_id'), fn ($q, $v) => $q->where('assets.inventory_location_id', $v))
                ->when($request->query('status'), fn ($q, $v) => $q->where('assets.status', $v))
                ->tap(fn ($q) => $this->dateFilter($q, $request, 'assets.created_at'))
                ->orderBy('assets.kode_aset')->limit($limit)
                ->get(['assets.kode_aset', 'items.name as item', 'categories.name as kategori', 'locations.name as lokasi', 'assets.condition as kondisi', 'assets.status'])
                ->map(fn ($r) => (array) $r);

            return $this->result(['kode_aset' => 'Kode Aset', 'item' => 'Nama Aset', 'kategori' => 'Kategori', 'lokasi' => 'Lokasi', 'kondisi' => 'Kondisi', 'status' => 'Status'], $rows);
        }

        $definitions = [
            'peminjaman' => ['inventory_loans as source', 'source.date', ['source.transaction_no as nomor', 'source.date', 'source.borrower as penanggung_jawab', 'source.taken_by_name as pengambil', 'source.purpose as keterangan', 'source.status']],
            'mutasi' => ['inventory_transfers as source', 'source.date', ['source.transaction_no as nomor', 'source.date', 'items.name as item', 'origin.name as lokasi_asal', 'destination.name as lokasi_tujuan', 'source.quantity', 'source.reason as keterangan']],
            'kerusakan' => ['inventory_damage_reports as source', 'source.date', ['source.id as nomor', 'source.date', 'items.name as item', 'locations.name as lokasi', 'source.severity as tingkat', 'source.repair_status as status', 'source.damage as keterangan']],
            'kehilangan' => ['inventory_loss_reports as source', 'source.date', ['source.id as nomor', 'source.date', 'items.name as item', 'locations.name as lokasi', 'source.quantity', 'source.status', 'source.chronology as keterangan']],
        ];
        [$table, $dateColumn, $columns] = $definitions[$type] ?? abort(404);
        $query = DB::table($table)->whereNull('source.deleted_at');
        if ($type === 'mutasi') {
            $query->join('inventory_items as items', 'items.id', '=', 'source.inventory_item_id')->leftJoin('inventory_locations as origin', 'origin.id', '=', 'source.from_location_id')->leftJoin('inventory_locations as destination', 'destination.id', '=', 'source.to_location_id');
        } elseif (in_array($type, ['kerusakan', 'kehilangan'], true)) {
            $locationColumn = $type === 'kerusakan' ? 'inventory_location_id' : 'last_location_id';
            $query->join('inventory_items as items', 'items.id', '=', 'source.inventory_item_id')->leftJoin('inventory_locations as locations', 'locations.id', '=', "source.{$locationColumn}");
        }
        $query->when($request->query('status'), fn ($q, $v) => $q->where($type === 'kerusakan' ? 'source.repair_status' : 'source.status', $v))
            ->tap(fn ($q) => $this->dateFilter($q, $request, $dateColumn));
        $rows = $query->orderByDesc($dateColumn)->limit($limit)->get($columns)->map(fn ($r) => (array) $r);
        $labels = collect($rows->first() ?? [])->keys()->mapWithKeys(fn ($key) => [$key => str($key)->replace('_', ' ')->title()->toString()])->all();

        return $this->result($labels, $rows);
    }

    private function heavyEquipmentReport(string $type, Request $request, int $limit): array
    {
        if ($type === 'daftar-alat') {
            $rows = DB::table('heavy_equipments as equipment')->join('heavy_equipment_types as types', 'types.id', '=', 'equipment.heavy_equipment_type_id')
                ->whereNull('equipment.deleted_at')->when($request->query('equipment_id'), fn ($q, $v) => $q->where('equipment.id', $v))
                ->when($request->query('status'), fn ($q, $v) => $q->where('equipment.status', $v))->orderBy('equipment.code')->limit($limit)
                ->get(['equipment.code as kode', 'equipment.name as alat', 'types.name as jenis', 'equipment.brand as merk', 'equipment.model', 'equipment.current_hour_meter as hour_meter', 'equipment.ownership as kepemilikan', 'equipment.status'])->map(fn ($r) => (array) $r);

            return $this->result(['kode' => 'Kode', 'alat' => 'Alat Berat', 'jenis' => 'Jenis', 'merk' => 'Merk', 'model' => 'Model', 'hour_meter' => 'Hour Meter', 'kepemilikan' => 'Kepemilikan', 'status' => 'Status'], $rows);
        }
        $definitions = [
            'penggunaan' => ['heavy_equipment_usages', 'transaction_no as nomor', ['operator_id', 'project', 'hour_meter_start', 'hour_meter_end', 'duration_hours', 'status']],
            'maintenance' => ['heavy_equipment_maintenances', 'maintenance_no as nomor', ['maintenance_type', 'workshop', 'cost', 'next_schedule', 'status']],
            'bbm' => ['heavy_equipment_fuelings', 'id as nomor', ['fuel_type', 'liters', 'price_per_liter', 'total_cost', 'hour_meter']],
            'kerusakan' => ['heavy_equipment_damages', 'id as nomor', ['description', 'severity', 'repair_status as status', 'completed_date']],
        ];
        [$table,$number,$fields] = $definitions[$type] ?? abort(404);
        $rows = DB::table("{$table} as source")->join('heavy_equipments as equipment', 'equipment.id', '=', 'source.heavy_equipment_id')->whereNull('source.deleted_at')
            ->when($request->query('equipment_id'), fn ($q, $v) => $q->where('source.heavy_equipment_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where($type === 'kerusakan' ? 'source.repair_status' : 'source.status', $v))
            ->tap(fn ($q) => $this->dateFilter($q, $request, 'source.date'))->orderByDesc('source.date')->limit($limit)
            ->get(array_merge([DB::raw("source.{$number}"), 'source.date', 'equipment.code as kode_alat', 'equipment.name as alat'], array_map(fn ($f) => "source.{$f}", $fields)))
            ->map(fn ($r) => (array) $r);
        $labels = collect($rows->first() ?? [])->keys()->mapWithKeys(fn ($key) => [$key => str($key)->replace('_', ' ')->title()->toString()])->all();

        return $this->result($labels, $rows);
    }

    private function masterReport(string $type, Request $request, int $limit): array
    {
        if (in_array($type, ['daftar-item', 'daftar-item-harga-pokok'], true)) {
            $gudangId = $request->query('gudang_id');
            $stocks = SiteMaterialStock::query()
                ->select('barang_material_id', DB::raw('SUM(qty_available) as stok'))
                ->when($gudangId, fn (Builder $query) => $query->where('gudang_id', $gudangId))
                ->groupBy('barang_material_id');

            $rows = BarangMaterial::query()->finalized()
                ->leftJoinSub($stocks, 'stok', 'stok.barang_material_id', '=', 'barang_materials.id')
                ->select('barang_materials.*', DB::raw('COALESCE(stok.stok, 0) as stok_tersedia'))
                ->when($request->query('kategori'), fn (Builder $query, $value) => $query->where('jenis_material', $value))
                ->when($request->query('merk'), fn (Builder $query, $value) => $query->where('merk_material', $value))
                ->when($request->query('tanggal_mulai'), fn (Builder $query, $value) => $query->whereDate('barang_materials.created_at', '>=', $value))
                ->when($request->query('tanggal_selesai'), fn (Builder $query, $value) => $query->whereDate('barang_materials.created_at', '<=', $value))
                ->orderBy('kode_barang')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'kode' => $row->kode_barang,
                    'nama' => $row->nama_barang,
                    'jenis' => $row->jenis_material ?: '-',
                    'merk' => $row->merk_material ?: '-',
                    'satuan' => $row->satuan,
                    'stok' => $this->num($row->stok_tersedia),
                    'harga_pokok' => $this->money($row->harga_hpp),
                    'dibuat' => optional($row->created_at)->format('Y-m-d'),
                ]);

            $columns = ['kode' => 'Kode Item', 'nama' => 'Nama Item', 'jenis' => 'Jenis', 'merk' => 'Merk', 'satuan' => 'Satuan', 'stok' => 'Stok', 'harga_pokok' => 'Harga Pokok', 'dibuat' => 'Dibuat'];

            return $this->result($columns, $rows);
        }

        if (in_array($type, ['daftar-aset', 'daftar-stok-aset'], true)) {
            $rows = DB::table('office_assets as assets')
                ->join('inventory_items as items', 'items.id', '=', 'assets.inventory_item_id')
                ->leftJoin('inventory_categories as categories', 'categories.id', '=', 'items.inventory_category_id')
                ->leftJoin('inventory_locations as locations', 'locations.id', '=', 'assets.inventory_location_id')
                ->whereNull('assets.deleted_at')
                ->when($request->query('kategori'), fn ($query, $value) => $query->where('categories.name', $value))
                ->when($request->query('status'), fn ($query, $value) => $query->where('assets.status', $value))
                ->when($request->query('tanggal_mulai'), fn ($query, $value) => $query->whereDate('assets.created_at', '>=', $value))
                ->when($request->query('tanggal_selesai'), fn ($query, $value) => $query->whereDate('assets.created_at', '<=', $value))
                ->select('assets.*', 'items.name as item_name', 'categories.name as category_name', 'locations.name as location_name')
                ->orderBy('assets.kode_aset')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'kode' => $row->kode_aset,
                    'aset' => $row->item_name,
                    'kategori' => $row->category_name,
                    'kondisi' => $row->condition,
                    'status' => $row->status,
                    'lokasi' => $row->location_name ?: '-',
                    'nilai' => '-',
                ]);

            return $this->result(['kode' => 'Kode', 'aset' => 'Aset', 'kategori' => 'Kategori', 'kondisi' => 'Kondisi', 'status' => 'Status', 'lokasi' => 'Lokasi', 'nilai' => 'Nilai'], $rows);
        }

        if ($type === 'daftar-supplier') {
            $rows = Supplier::query()->finalized()
                ->when($request->query('tanggal_mulai'), fn (Builder $query, $value) => $query->whereDate('created_at', '>=', $value))
                ->when($request->query('tanggal_selesai'), fn (Builder $query, $value) => $query->whereDate('created_at', '<=', $value))
                ->orderBy('kode_supplier')
                ->limit($limit)
                ->get()
                ->map(fn (Supplier $row) => [
                    'kode' => $row->kode_supplier,
                    'nama' => $row->nama_supplier,
                    'pic' => $row->pic ?: '-',
                    'telepon' => $row->phone ?: '-',
                    'status' => $row->status,
                    'dibuat' => optional($row->created_at)->format('Y-m-d'),
                ]);

            return $this->result(['kode' => 'Kode', 'nama' => 'Supplier', 'pic' => 'PIC', 'telepon' => 'Telepon', 'status' => 'Status', 'dibuat' => 'Dibuat'], $rows);
        }

        if (in_array($type, ['daftar-pelanggan', 'daftar-pelanggan-per-supplier'], true)) {
            $rows = Costumer::query()->finalized()
                ->with(['perumahan:id,nama_perusahaan', 'creator:id,name'])
                ->when($request->query('tanggal_mulai'), fn (Builder $query, $value) => $query->whereDate('created_at', '>=', $value))
                ->when($request->query('tanggal_selesai'), fn (Builder $query, $value) => $query->whereDate('created_at', '<=', $value))
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(fn (Costumer $row) => [
                    'kode' => $row->kode_costumer,
                    'nama' => $row->nama,
                    'telepon' => $row->telepon,
                    'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                    'marketing' => $row->creator?->name ?? '-',
                    'status' => $row->status_lead ?: '-',
                    'dibuat' => optional($row->created_at)->format('Y-m-d'),
                ]);

            return $this->result(['kode' => 'Kode', 'nama' => 'Pelanggan', 'telepon' => 'Telepon', 'perumahan' => 'Perumahan', 'marketing' => 'Marketing', 'status' => 'Status', 'dibuat' => 'Dibuat'], $rows);
        }

        $rows = User::query()
            ->with('roles:id,name')
            ->when($request->query('tanggal_mulai'), fn (Builder $query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($request->query('tanggal_selesai'), fn (Builder $query, $value) => $query->whereDate('created_at', '<=', $value))
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (User $row) => [
                'nama' => $row->name,
                'email' => $row->email,
                'role' => $row->roles->pluck('name')->join(', ') ?: '-',
                'dibuat' => optional($row->created_at)->format('Y-m-d'),
            ]);

        return $this->result(['nama' => 'Nama', 'email' => 'Email', 'role' => 'Role', 'dibuat' => 'Dibuat'], $rows);
    }

    private function purchaseReport(string $type, Request $request, int $limit): array
    {
        if (str_starts_with($type, 'pemakaian-material')) {
            $rows = MaterialUsageDetail::query()
                ->with(['barangMaterial:id,kode_barang,nama_barang', 'detailRumahHppItem', 'siteMaterialStock.gudang:id,nama_gudang', 'siteMaterialStock.perumahan:id,nama_perusahaan', 'materialUsage.detailRumah:id,kode_nlok,nomor_rumah', 'materialUsage.creator:id,name'])
                ->whereHas('materialUsage', fn (Builder $query) => $this->dateFilter($query, $request, 'tanggal'))
                ->when($request->query('gudang_id'), fn (Builder $query, $value) => $query->whereHas('siteMaterialStock', fn (Builder $query) => $query->where('gudang_id', $value)))
                ->limit($limit)
                ->get()
                ->map(fn (MaterialUsageDetail $row) => [
                    'tanggal' => optional($row->materialUsage?->tanggal)->format('Y-m-d'),
                    'kode' => $row->materialUsage?->kode_pemakaian,
                    'material' => ($row->barangMaterial?->kode_barang ?? '-').' - '.($row->barangMaterial?->nama_barang ?? '-'),
                    'unit' => $row->materialUsage?->detailRumah ? trim(($row->materialUsage->detailRumah->kode_nlok ?? '').' '.($row->materialUsage->detailRumah->nomor_rumah ?? '')) : '-',
                    'gudang' => $row->siteMaterialStock?->gudang?->nama_gudang ?? '-',
                    'qty' => $this->num($row->qty).' '.$row->satuan,
                    'user' => $row->materialUsage?->creator?->name ?? '-',
                ]);

            return $this->result(['tanggal' => 'Tanggal', 'kode' => 'No Transaksi', 'material' => 'Material', 'unit' => 'Unit', 'gudang' => 'Gudang', 'qty' => 'Jumlah', 'user' => 'User'], $rows);
        }

        if (str_starts_with($type, 'pengembalian-material')) {
            $rows = MaterialReturnDetail::query()
                ->with(['barangMaterial:id,kode_barang,nama_barang', 'materialReturn.gudang:id,nama_gudang', 'materialReturn.detailRumah:id,kode_nlok,nomor_rumah', 'materialReturn.receivedBy:id,name'])
                ->whereHas('materialReturn', fn (Builder $query) => $this->dateFilter($query, $request, 'tanggal'))
                ->when($request->query('gudang_id'), fn (Builder $query, $value) => $query->whereHas('materialReturn', fn (Builder $query) => $query->where('gudang_id', $value)))
                ->limit($limit)
                ->get()
                ->map(fn (MaterialReturnDetail $row) => [
                    'tanggal' => optional($row->materialReturn?->tanggal)->format('Y-m-d'),
                    'kode' => $row->materialReturn?->kode_pengembalian,
                    'material' => ($row->barangMaterial?->kode_barang ?? '-').' - '.($row->barangMaterial?->nama_barang ?? '-'),
                    'unit' => $row->materialReturn?->detailRumah ? trim(($row->materialReturn->detailRumah->kode_nlok ?? '').' '.($row->materialReturn->detailRumah->nomor_rumah ?? '')) : '-',
                    'gudang' => $row->materialReturn?->gudang?->nama_gudang ?? '-',
                    'qty' => $this->num($row->qty).' '.$row->satuan,
                    'status' => $row->materialReturn?->status,
                ]);

            return $this->result(['tanggal' => 'Tanggal', 'kode' => 'No Transaksi', 'material' => 'Material', 'unit' => 'Unit', 'gudang' => 'Gudang', 'qty' => 'Jumlah', 'status' => 'Status'], $rows);
        }

        if ($type === 'pembelian-detail') {
            $rows = MaterialPurchaseDetail::query()
                ->with(['barangMaterial:id,kode_barang,nama_barang', 'materialPurchase.supplierData:id,kode_supplier,nama_supplier', 'materialPurchase.gudang:id,nama_gudang', 'materialPurchase.creator:id,name'])
                ->whereHas('materialPurchase', fn (Builder $query) => $this->purchaseFilter($query, $request))
                ->limit($limit)
                ->get()
                ->map(fn (MaterialPurchaseDetail $row) => [
                    'tanggal' => optional($row->materialPurchase?->tanggal)->format('Y-m-d'),
                    'kode' => $row->materialPurchase?->kode_pembelian,
                    'supplier' => $row->materialPurchase?->supplierData?->nama_supplier ?? $row->materialPurchase?->supplier ?? '-',
                    'gudang' => $row->materialPurchase?->gudang?->nama_gudang ?? '-',
                    'material' => ($row->barangMaterial?->kode_barang ?? '-').' - '.($row->barangMaterial?->nama_barang ?? '-'),
                    'qty' => $this->num($row->qty).' '.$row->satuan,
                    'harga' => $this->money($row->harga_satuan),
                    'total' => $this->money($row->subtotal),
                ]);

            return $this->result(['tanggal' => 'Tanggal', 'kode' => 'No Transaksi', 'supplier' => 'Supplier', 'gudang' => 'Gudang', 'material' => 'Material', 'qty' => 'Jumlah', 'harga' => 'Harga', 'total' => 'Total'], $rows);
        }

        $rows = MaterialPurchase::query()->finalized()
            ->with(['supplierData:id,kode_supplier,nama_supplier', 'gudang:id,nama_gudang', 'creator:id,name'])
            ->tap(fn (Builder $query) => $this->purchaseFilter($query, $request))
            ->orderByDesc('tanggal')
            ->limit($limit)
            ->get()
            ->map(fn (MaterialPurchase $row) => [
                'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                'kode' => $row->kode_pembelian,
                'supplier' => $row->supplierData?->nama_supplier ?? $row->supplier ?? '-',
                'gudang' => $row->gudang?->nama_gudang ?? '-',
                'metode' => $row->metode_pembayaran,
                'status' => $row->status,
                'total' => $this->money($row->total_nominal),
                'user' => $row->creator?->name ?? '-',
            ]);

        return $this->result(['tanggal' => 'Tanggal', 'kode' => 'No Transaksi', 'supplier' => 'Supplier', 'gudang' => 'Gudang', 'metode' => 'Metode', 'status' => 'Status', 'total' => 'Total', 'user' => 'User'], $rows);
    }

    private function inventoryReport(string $type, Request $request, int $limit): array
    {
        if ($type === 'item-opname') {
            $rows = MaterialStockOpnameDetail::query()
                ->with(['barangMaterial:id,kode_barang,nama_barang,satuan', 'opname.gudang:id,nama_gudang'])
                ->whereHas('opname', fn (Builder $query) => $this->dateFilter($query, $request, 'tanggal'))
                ->when($request->query('gudang_id'), fn (Builder $query, $value) => $query->whereHas('opname', fn (Builder $query) => $query->where('gudang_id', $value)))
                ->limit($limit)
                ->get()
                ->map(fn (MaterialStockOpnameDetail $row) => [
                    'tanggal' => optional($row->opname?->tanggal)->format('Y-m-d'),
                    'kode' => $row->opname?->kode_opname,
                    'gudang' => $row->opname?->gudang?->nama_gudang ?? '-',
                    'material' => ($row->barangMaterial?->kode_barang ?? '-').' - '.($row->barangMaterial?->nama_barang ?? '-'),
                    'buku' => $this->num($row->stok_sistem),
                    'fisik' => $this->num($row->fisik),
                    'selisih' => $this->num($row->selisih),
                ]);

            return $this->result(['tanggal' => 'Tanggal', 'kode' => 'No Opname', 'gudang' => 'Gudang', 'material' => 'Material', 'buku' => 'Buku', 'fisik' => 'Fisik', 'selisih' => 'Selisih'], $rows);
        }

        $isMasuk = $type === 'item-masuk';
        $rows = $isMasuk
            ? MaterialPurchaseDetail::query()
                ->with(['barangMaterial:id,kode_barang,nama_barang', 'materialPurchase.gudang:id,nama_gudang', 'materialPurchase.supplierData:id,nama_supplier'])
                ->whereHas('materialPurchase', fn (Builder $query) => $this->purchaseFilter($query, $request))
                ->limit($limit)
                ->get()
                ->map(fn (MaterialPurchaseDetail $row) => [
                    'tanggal' => optional($row->materialPurchase?->tanggal_barang_masuk ?? $row->materialPurchase?->tanggal)->format('Y-m-d'),
                    'kode' => $row->materialPurchase?->kode_pembelian,
                    'gudang' => $row->materialPurchase?->gudang?->nama_gudang ?? '-',
                    'material' => ($row->barangMaterial?->kode_barang ?? '-').' - '.($row->barangMaterial?->nama_barang ?? '-'),
                    'qty' => $this->num($row->qty_diterima ?: $row->qty).' '.$row->satuan,
                    'sumber' => $row->materialPurchase?->supplierData?->nama_supplier ?? $row->materialPurchase?->supplier ?? '-',
                ])
            : MaterialUsageDetail::query()
                ->with(['barangMaterial:id,kode_barang,nama_barang', 'siteMaterialStock.gudang:id,nama_gudang', 'materialUsage'])
                ->whereHas('materialUsage', fn (Builder $query) => $this->dateFilter($query, $request, 'tanggal'))
                ->when($request->query('gudang_id'), fn (Builder $query, $value) => $query->whereHas('siteMaterialStock', fn (Builder $query) => $query->where('gudang_id', $value)))
                ->limit($limit)
                ->get()
                ->map(fn (MaterialUsageDetail $row) => [
                    'tanggal' => optional($row->materialUsage?->tanggal)->format('Y-m-d'),
                    'kode' => $row->materialUsage?->kode_pemakaian,
                    'gudang' => $row->siteMaterialStock?->gudang?->nama_gudang ?? '-',
                    'material' => ($row->barangMaterial?->kode_barang ?? '-').' - '.($row->barangMaterial?->nama_barang ?? '-'),
                    'qty' => $this->num($row->qty).' '.$row->satuan,
                    'sumber' => 'Pemakaian proyek',
                ]);

        return $this->result(['tanggal' => 'Tanggal', 'kode' => 'No Transaksi', 'gudang' => 'Gudang', 'material' => 'Material', 'qty' => 'Jumlah', 'sumber' => 'Sumber/Tujuan'], $rows);
    }

    private function marketingReport(string $type, Request $request, int $limit): array
    {
        if ($type === 'follow-up-pelanggan') {
            $rows = CostumerFollowUp::query()
                ->with(['costumer:id,kode_costumer,nama,telepon', 'user:id,name'])
                ->tap(fn (Builder $query) => $this->dateFilter($query, $request, 'tanggal_follow_up'))
                ->when($request->query('media_follow_up'), fn (Builder $query, $value) => $query->where('metode_follow_up', $value))
                ->when(filled($request->query('status_serius')), fn (Builder $query) => $query->where('status_serius', (bool) $request->boolean('status_serius')))
                ->when($request->query('status_kemampuan'), fn (Builder $query, $value) => $query->where('progress_kemampuan', $value))
                ->orderByDesc('tanggal_follow_up')
                ->limit($limit)
                ->get()
                ->map(fn (CostumerFollowUp $row) => [
                    'tanggal' => optional($row->tanggal_follow_up)->format('Y-m-d'),
                    'pelanggan' => ($row->costumer?->kode_costumer ?? '-').' - '.($row->costumer?->nama ?? '-'),
                    'media' => $row->metode_follow_up,
                    'serius' => $row->status_serius ? 'Ya' : 'Tidak',
                    'kemampuan' => $row->progress_kemampuan,
                    'status' => $row->status,
                    'marketing' => $row->user?->name ?? '-',
                ]);

            return $this->result(['tanggal' => 'Tanggal', 'pelanggan' => 'Pelanggan', 'media' => 'Media', 'serius' => 'Serius', 'kemampuan' => 'Kemampuan', 'status' => 'Status', 'marketing' => 'Marketing'], $rows);
        }

        if (in_array($type, ['pembayaran-booking-fee', 'uang-muka'], true)) {
            $paymentType = $type === 'pembayaran-booking-fee' ? 'booking_fee' : 'uang_muka';
            $purpose = $paymentType === 'uang_muka' ? 'down_payment' : 'booking_fee';
            $rows = CustomerReceipt::query()->finalized()
                ->with(['salesTransaction.customer:id,nama', 'salesTransaction.housingUnit:id,kode_nlok,nomor_rumah', 'salesTransaction.spr:id,kode_spr', 'creator:id,name'])
                ->tap(fn (Builder $query) => $this->dateFilter($query, $request, 'payment_date'))
                ->where('receipt_purpose', $purpose)->where('status', 'posted')
                ->orderByDesc('payment_date')
                ->limit($limit)
                ->get()
                ->map(fn (CustomerReceipt $row) => [
                    'tanggal' => optional($row->payment_date)->format('Y-m-d'),
                    'spr' => $row->salesTransaction?->spr?->kode_spr ?? '-',
                    'pelanggan' => $row->salesTransaction?->customer?->nama ?? '-',
                    'unit' => $row->salesTransaction?->housingUnit ? trim(($row->salesTransaction->housingUnit->kode_nlok ?? '').' '.($row->salesTransaction->housingUnit->nomor_rumah ?? '')) : '-',
                    'nominal' => $this->money($row->amount),
                    'status' => $row->status,
                    'user' => $row->creator?->name ?? '-',
                ]);

            return $this->result(['tanggal' => 'Tanggal', 'spr' => 'SPR', 'pelanggan' => 'Pelanggan', 'unit' => 'Unit', 'nominal' => 'Nominal', 'status' => 'Status', 'user' => 'User'], $rows);
        }

        if (in_array($type, ['pengajuan-kpr', 'follow-up-kpr', 'akad', 'serah-terima'], true)) {
            $rows = KprSubmission::query()->finalized()
                ->with(['spr.costumer:id,nama', 'spr.detailRumah:id,kode_nlok,nomor_rumah', 'bank:id,nama_bank', 'handler:id,name'])
                ->tap(fn (Builder $query) => $this->dateFilter($query, $request, 'tanggal_pengajuan'))
                ->orderByDesc('tanggal_pengajuan')
                ->limit($limit)
                ->get()
                ->map(fn (KprSubmission $row) => [
                    'tanggal' => optional($row->tanggal_pengajuan)->format('Y-m-d'),
                    'kode' => $row->kode_kpr,
                    'spr' => $row->spr?->kode_spr ?? '-',
                    'pelanggan' => $row->spr?->costumer?->nama ?? '-',
                    'unit' => $row->spr?->detailRumah ? trim(($row->spr->detailRumah->kode_nlok ?? '').' '.($row->spr->detailRumah->nomor_rumah ?? '')) : '-',
                    'bank' => $row->bank?->nama_bank ?? '-',
                    'nilai' => $this->money($row->nilai_pengajuan),
                    'status' => $row->status,
                ]);

            return $this->result(['tanggal' => 'Tanggal', 'kode' => 'Kode KPR', 'spr' => 'SPR', 'pelanggan' => 'Pelanggan', 'unit' => 'Unit', 'bank' => 'Bank', 'nilai' => 'Nilai', 'status' => 'Status'], $rows);
        }

        $rows = Spr::query()->finalized()
            ->with(['costumer:id,nama', 'detailRumah:id,kode_nlok,nomor_rumah'])
            ->tap(fn (Builder $query) => $this->dateFilter($query, $request, 'tanggal_spr'))
            ->when($request->query('status'), fn (Builder $query, $value) => $query->where('status', $value))
            ->orderByDesc('tanggal_spr')
            ->limit($limit)
            ->get()
            ->map(fn (Spr $row) => [
                'tanggal' => optional($row->tanggal_spr)->format('Y-m-d'),
                'spr' => $row->kode_spr,
                'pelanggan' => $row->costumer?->nama ?? '-',
                'unit' => $row->detailRumah ? trim(($row->detailRumah->kode_nlok ?? '').' '.($row->detailRumah->nomor_rumah ?? '')) : '-',
                'metode' => $row->metode_pembayaran,
                'harga' => $this->money($row->harga_jual),
                'status' => $row->status,
            ]);

        return $this->result(['tanggal' => 'Tanggal', 'spr' => 'SPR', 'pelanggan' => 'Pelanggan', 'unit' => 'Unit', 'metode' => 'Metode', 'harga' => 'Harga', 'status' => 'Status'], $rows);
    }

    private function purchaseFilter(Builder $query, Request $request): void
    {
        $this->dateFilter($query, $request, 'tanggal');
        $query
            ->when($request->query('supplier_id'), fn (Builder $query, $value) => $query->where('supplier_id', $value))
            ->when($request->query('gudang_id'), fn (Builder $query, $value) => $query->where('gudang_id', $value))
            ->when($request->query('user_id'), fn (Builder $query, $value) => $query->where('created_by', $value));
    }

    private function dateFilter($query, Request $request, string $column): void
    {
        $query
            ->when($request->query('tanggal_mulai'), fn (Builder $query, $value) => $query->whereDate($column, '>=', $value))
            ->when($request->query('tanggal_selesai'), fn (Builder $query, $value) => $query->whereDate($column, '<=', $value));
    }

    private function result(array $columns, $rows): array
    {
        $rows = collect($rows)->values();

        return [
            'columns' => collect($columns)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'rows' => $rows,
            'summary' => [
                'total_rows' => $rows->count(),
            ],
        ];
    }

    private function filtersFor(string $group, string $type): array
    {
        $baseDate = [
            ['name' => 'tanggal_mulai', 'label' => 'Tanggal Mulai', 'type' => 'date'],
            ['name' => 'tanggal_selesai', 'label' => 'Tanggal Selesai', 'type' => 'date'],
        ];

        return match ($group) {
            'master-data' => match ($type) {
                'daftar-item', 'daftar-item-harga-pokok' => [
                    ['name' => 'gudang_id', 'label' => 'Gudang', 'type' => 'select', 'optionsKey' => 'gudangs'],
                    ['name' => 'kategori', 'label' => 'Jenis', 'type' => 'select', 'optionsKey' => 'materialJenis'],
                    ['name' => 'merk', 'label' => 'Merk', 'type' => 'select', 'optionsKey' => 'materialMerks'],
                    ...$baseDate,
                ],
                'daftar-aset', 'daftar-stok-aset' => [
                    ['name' => 'kategori', 'label' => 'Kategori Aset', 'type' => 'select', 'optionsKey' => 'assetCategories'],
                    ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'assetStatuses'],
                    ...$baseDate,
                ],
                default => $baseDate,
            },
            'pembelian' => [
                ['name' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'optionsKey' => 'suppliers'],
                ['name' => 'gudang_id', 'label' => 'Gudang', 'type' => 'select', 'optionsKey' => 'gudangs'],
                ['name' => 'user_id', 'label' => 'User', 'type' => 'select', 'optionsKey' => 'users'],
                ...$baseDate,
            ],
            'persediaan-material' => [
                ['name' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'optionsKey' => 'suppliers'],
                ['name' => 'gudang_id', 'label' => 'Gudang', 'type' => 'select', 'optionsKey' => 'gudangs'],
                ...$baseDate,
            ],
            'marketing' => match ($type) {
                'follow-up-pelanggan' => [
                    ['name' => 'media_follow_up', 'label' => 'Media Follow Up', 'type' => 'select', 'optionsKey' => 'followUpMedia'],
                    ['name' => 'status_serius', 'label' => 'Status Serius', 'type' => 'select', 'optionsKey' => 'yesNo'],
                    ['name' => 'status_kemampuan', 'label' => 'Status Kemampuan', 'type' => 'select', 'optionsKey' => 'kemampuan'],
                    ...$baseDate,
                ],
                default => [
                    ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'sprStatuses'],
                    ...$baseDate,
                ],
            },
            'aset-perusahaan' => [
                ['name' => 'location_id', 'label' => 'Lokasi Aset', 'type' => 'select', 'optionsKey' => 'inventoryLocations'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'text'],
                ...$baseDate,
            ],
            'alat-berat' => [
                ['name' => 'equipment_id', 'label' => 'Alat Berat', 'type' => 'select', 'optionsKey' => 'heavyEquipments'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'text'],
                ...$baseDate,
            ],
            default => $baseDate,
        };
    }

    private function filterOptions(): array
    {
        return [
            'gudangs' => $this->options(Gudang::query()->orderBy('nama_gudang')->get(['id', 'nama_gudang']), 'nama_gudang'),
            'suppliers' => $this->options(Supplier::query()->finalized()->orderBy('nama_supplier')->get(['id', 'nama_supplier']), 'nama_supplier'),
            'users' => $this->options(User::query()->orderBy('name')->get(['id', 'name']), 'name'),
            'perumahans' => $this->options(Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan']), 'nama_perusahaan'),
            'materialJenis' => BarangMaterial::query()->finalized()->whereNotNull('jenis_material')->distinct()->orderBy('jenis_material')->pluck('jenis_material')->map(fn ($value) => ['value' => $value, 'label' => $value])->values(),
            'materialMerks' => BarangMaterial::query()->finalized()->whereNotNull('merk_material')->distinct()->orderBy('merk_material')->pluck('merk_material')->map(fn ($value) => ['value' => $value, 'label' => $value])->values(),
            'assetCategories' => DB::table('inventory_categories')->whereNull('deleted_at')->orderBy('name')->pluck('name')->map(fn ($value) => ['value' => $value, 'label' => $value])->values(),
            'assetStatuses' => OfficeAsset::query()->whereNotNull('status')->distinct()->orderBy('status')->pluck('status')->map(fn ($value) => ['value' => $value, 'label' => $value])->values(),
            'followUpMedia' => CostumerFollowUp::query()->whereNotNull('metode_follow_up')->distinct()->orderBy('metode_follow_up')->pluck('metode_follow_up')->map(fn ($value) => ['value' => $value, 'label' => $value])->values(),
            'yesNo' => [['value' => '1', 'label' => 'Ya'], ['value' => '0', 'label' => 'Tidak']],
            'kemampuan' => CostumerFollowUp::query()->whereNotNull('progress_kemampuan')->distinct()->orderBy('progress_kemampuan')->pluck('progress_kemampuan')->map(fn ($value) => ['value' => $value, 'label' => $value])->values(),
            'sprStatuses' => [['value' => 'menunggu_manager', 'label' => 'Menunggu Manager'], ['value' => 'menunggu_owner', 'label' => 'Menunggu Owner'], ['value' => 'disetujui', 'label' => 'Disetujui'], ['value' => 'ditolak', 'label' => 'Ditolak']],
            'inventoryLocations' => $this->options(DB::table('inventory_locations')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']), 'name'),
            'heavyEquipments' => DB::table('heavy_equipments')->whereNull('deleted_at')->orderBy('name')->get(['id', 'code', 'name'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->code.' — '.$row->name])->values(),
        ];
    }

    private function filterValues(Request $request): array
    {
        return [
            'jenis_laporan' => $request->query('jenis_laporan'),
            'tanggal_mulai' => $request->query('tanggal_mulai', now()->startOfMonth()->toDateString()),
            'tanggal_selesai' => $request->query('tanggal_selesai', now()->toDateString()),
            'gudang_id' => $request->query('gudang_id', ''),
            'supplier_id' => $request->query('supplier_id', ''),
            'user_id' => $request->query('user_id', ''),
            'kategori' => $request->query('kategori', ''),
            'merk' => $request->query('merk', ''),
            'status' => $request->query('status', ''),
            'media_follow_up' => $request->query('media_follow_up', ''),
            'status_serius' => $request->query('status_serius', ''),
            'status_kemampuan' => $request->query('status_kemampuan', ''),
            'location_id' => $request->query('location_id', ''),
            'equipment_id' => $request->query('equipment_id', ''),
        ];
    }

    private function options($rows, string $labelKey)
    {
        return $rows->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->{$labelKey}])->values();
    }

    private function groups(): array
    {
        return [
            'master-data' => [
                'title' => 'Laporan Master Data',
                'description' => 'Daftar material, aset, supplier, pelanggan, dan marketing.',
                'permission' => 'laporan-master-data.view',
                'types' => [
                    'daftar-item' => ['label' => 'Laporan Daftar Item'],
                    'daftar-item-harga-pokok' => ['label' => 'Laporan Daftar Item Harga Pokok'],
                    'daftar-aset' => ['label' => 'Laporan Daftar Item Aset'],
                    'daftar-stok-aset' => ['label' => 'Laporan Daftar Item Stok Aset'],
                    'daftar-supplier' => ['label' => 'Laporan Daftar Supplier'],
                    'daftar-pelanggan' => ['label' => 'Laporan Pelanggan'],
                    'daftar-pelanggan-per-supplier' => ['label' => 'Laporan Pelanggan Per Supplier'],
                    'daftar-marketing' => ['label' => 'Laporan Daftar Marketing'],
                ],
            ],
            'pembelian' => [
                'title' => 'Laporan Pembelian',
                'description' => 'Rekap/detail pembelian, pemakaian, dan pengembalian material.',
                'permission' => 'laporan-pembelian.view',
                'types' => [
                    'pembelian-rekap' => ['label' => 'Laporan Pembelian Rekap'],
                    'pembelian-detail' => ['label' => 'Laporan Pembelian Detail'],
                    'pembelian-harian' => ['label' => 'Laporan Pembelian Harian'],
                    'pemakaian-material' => ['label' => 'Laporan Pemakaian Material'],
                    'pemakaian-material-perunit' => ['label' => 'Laporan Pemakaian Material Per Unit'],
                    'pemakaian-material-detail' => ['label' => 'Laporan Pemakaian Material Per Detail'],
                    'pengembalian-material' => ['label' => 'Laporan Pengembalian Material'],
                    'pengembalian-material-perunit' => ['label' => 'Laporan Pengembalian Material Per Unit'],
                    'pengembalian-material-detail' => ['label' => 'Laporan Pengembalian Material Per Unit Detail'],
                ],
            ],
            'persediaan-material' => [
                'title' => 'Laporan Persediaan Material',
                'description' => 'Item masuk, keluar, dan opname material.',
                'permission' => 'laporan-persediaan-material.view',
                'types' => [
                    'item-masuk' => ['label' => 'Laporan Item Masuk'],
                    'item-keluar' => ['label' => 'Laporan Item Keluar'],
                    'item-opname' => ['label' => 'Laporan Item Opname'],
                ],
            ],
            'marketing' => [
                'title' => 'Laporan Marketing',
                'description' => 'Follow up pelanggan, SPR, pembayaran, KPR, akad, dan serah terima.',
                'permission' => 'laporan-marketing.view',
                'types' => [
                    'follow-up-pelanggan' => ['label' => 'Laporan Follow Up Pelanggan'],
                    'spr' => ['label' => 'Laporan SPR'],
                    'pembayaran-booking-fee' => ['label' => 'Laporan Pembayaran Booking Fee'],
                    'uang-muka' => ['label' => 'Laporan Uang Muka'],
                    'pengajuan-kpr' => ['label' => 'Laporan Pengajuan KPR'],
                    'follow-up-kpr' => ['label' => 'Laporan Follow Up KPR'],
                    'akad' => ['label' => 'Laporan Akad'],
                    'serah-terima' => ['label' => 'Laporan Serah Terima'],
                ],
            ],
            'aset-perusahaan' => [
                'title' => 'Laporan Aset Perusahaan',
                'description' => 'Data aset, peminjaman, mutasi, kerusakan, dan kehilangan dengan jejak lokasi serta penanggung jawab.',
                'permission' => 'laporan-master-data.view',
                'types' => [
                    'daftar-aset' => ['label' => 'Daftar dan Status Aset'],
                    'peminjaman' => ['label' => 'Histori Peminjaman Aset'],
                    'mutasi' => ['label' => 'Histori Mutasi Aset'],
                    'kerusakan' => ['label' => 'Exception Aset Rusak'],
                    'kehilangan' => ['label' => 'Exception Aset Hilang'],
                ],
            ],
            'alat-berat' => [
                'title' => 'Laporan Alat Berat',
                'description' => 'Data alat, pemakaian, maintenance, konsumsi BBM, biaya, dan exception kerusakan.',
                'permission' => 'laporan-master-data.view',
                'types' => [
                    'daftar-alat' => ['label' => 'Daftar dan Status Alat Berat'],
                    'penggunaan' => ['label' => 'Penggunaan dan Jam Kerja'],
                    'maintenance' => ['label' => 'Maintenance dan Biaya'],
                    'bbm' => ['label' => 'Konsumsi dan Biaya BBM'],
                    'kerusakan' => ['label' => 'Kerusakan dan Status Perbaikan'],
                ],
            ],
        ];
    }

    private function groupConfig(string $group): array
    {
        $config = $this->groups()[$group] ?? null;
        abort_unless($config, 404);

        return $config;
    }

    private function canReport(string $action, ?string $specificPermission = null): bool
    {
        $user = auth()->user();

        return (bool) (
            $user?->hasRole('super_admin')
            || $user?->hasAnyRole(['owner', 'petugas'])
            || $user?->can("laporan.{$action}")
            || ($specificPermission && $user?->can($specificPermission))
        );
    }

    private function authorizeReport(string $action, ?string $specificPermission = null): void
    {
        abort_unless($this->canReport($action, $specificPermission), 403, 'Anda tidak memiliki permission laporan.');
    }

    private function money(float|int|null $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }

    private function num(float|int|null $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }
}
