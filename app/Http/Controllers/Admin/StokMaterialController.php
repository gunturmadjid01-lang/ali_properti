<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BarangMaterial;
use App\Models\Gudang;
use App\Models\MaterialOpeningBalance;
use App\Models\MaterialPurchase;
use App\Models\MaterialRequest;
use App\Models\MaterialReturn;
use App\Models\MaterialStockOpname;
use App\Models\StokMaterial;
use App\Models\TransaksiLogistik;
use App\Models\TransaksiLogistikDetail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StokMaterialController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Logistik/StokMaterial', $this->indexProps($request));
    }

    public function indexData(Request $request): JsonResponse
    {
        return response()->json($this->indexProps($request));
    }

    protected function indexProps(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $gudangId = trim((string) $request->query('gudang_id', ''));
        $materialId = trim((string) $request->query('material_id', ''));
        $kategori = trim((string) $request->query('kategori', ''));
        $perPageInput = (string) $request->query('per_page', '10');
        $perPage = in_array($perPageInput, ['10', '25', '50', 'all'], true) ? $perPageInput : '10';
        $gudangs = $this->accessibleGudangs();
        $allowedGudangIds = $gudangs->pluck('value')->all();
        if ($gudangId === '' || ! in_array((string) $gudangId, $allowedGudangIds, true)) {
            $gudangId = (string) ($allowedGudangIds[0] ?? '');
        }

        $selectedGudang = filled($gudangId) ? Gudang::query()->find($gudangId) : null;

        $materials = BarangMaterial::query()
            ->where('status', 'aktif')
            ->orderBy('kode_barang')
            ->get(['id', 'kode_barang', 'nama_barang', 'satuan', 'jenis_material', 'merk_material'])
            ->map(fn (BarangMaterial $row) => [
                'value' => (string) $row->id,
                'label' => "{$row->kode_barang} - {$row->nama_barang}",
                'satuan' => $row->satuan,
                'jenis' => $row->jenis_material,
                'merk' => $row->merk_material,
            ])
            ->values();

        if (filled($gudangId)) {
            $rowsQuery = BarangMaterial::query()
                ->leftJoin('stok_materials as stok', function ($join) use ($gudangId) {
                    $join->on('stok.barang_material_id', '=', 'barang_materials.id')
                        ->where('stok.gudang_id', '=', $gudangId);
                })
                ->where('barang_materials.status', 'aktif')
                ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                    $query->where('barang_materials.kode_barang', 'like', "%{$search}%")
                        ->orWhere('barang_materials.nama_barang', 'like', "%{$search}%")
                        ->orWhere('barang_materials.jenis_material', 'like', "%{$search}%")
                        ->orWhere('barang_materials.merk_material', 'like', "%{$search}%")
                        ->orWhere('barang_materials.kategori_material', 'like', "%{$search}%");
                }))
                ->when($kategori !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($kategori) {
                    $query->where('barang_materials.jenis_material', $kategori)
                        ->orWhere('barang_materials.kategori_material', $kategori);
                }))
                ->orderBy('barang_materials.kode_barang')
                ->select([
                    'barang_materials.id',
                    'barang_materials.kode_barang',
                    'barang_materials.nama_barang',
                    'barang_materials.satuan',
                    'barang_materials.harga_hpp',
                    'barang_materials.stok_minimum',
                    'barang_materials.jenis_material',
                    'barang_materials.merk_material',
                    'barang_materials.kategori_material',
                    'stok.id as stok_id',
                    'stok.qty as stok_qty',
                ]);

            $rows = $rowsQuery
                ->paginate($perPage === 'all' ? max(1, (clone $rowsQuery)->toBase()->getCountForPagination()) : (int) $perPage)
                ->withQueryString()
                ->through(fn (BarangMaterial $row) => [
                    'id' => $row->id,
                    'stok_id' => $row->stok_id,
                    'gudang' => $selectedGudang?->nama_gudang ?? 'Gudang Umum',
                    'kode_barang' => $row->kode_barang,
                    'nama_barang' => $row->nama_barang,
                    'jenis_material' => $row->jenis_material ?: $row->kategori_material,
                    'merk_material' => $row->merk_material,
                    'qty' => (float) ($row->stok_qty ?? 0),
                    'satuan' => $row->satuan,
                    'harga_hpp' => (float) $row->harga_hpp,
                    'stok_minimum' => (float) $row->stok_minimum,
                    'status_stok' => ((float) ($row->stok_qty ?? 0)) <= (float) ($row->stok_minimum ?? 0) ? 'Minimum' : 'Aman',
                ]);
        } else {
            $rows = StokMaterial::query()->whereRaw('1 = 0')->paginate($perPage === 'all' ? 1 : (int) $perPage)->withQueryString();
        }

        return [
            'title' => 'Stok Material',
            'baseUrl' => route('admin.stok-material.index', absolute: false),
            'dataUrl' => route('admin.stok-material.data', absolute: false),
            'cardUrl' => route('admin.kartu-stok.index', absolute: false),
            'masterMaterialUrl' => route('admin.master-material.index', absolute: false),
            'rows' => $rows,
            'filters' => [
                'search' => $search,
                'gudang_id' => $gudangId,
                'material_id' => $materialId,
                'kategori' => $kategori,
                'per_page' => $perPage,
                'total_found' => $rows->total(),
            ],
            'options' => [
                'gudangs' => $gudangs,
                'materials' => $materials,
                'kategoriMaterials' => $this->kategoriMaterialOptions(),
            ],
            'selectedGudang' => $selectedGudang ? [
                'id' => (string) $selectedGudang->id,
                'nama_gudang' => $selectedGudang->nama_gudang,
            ] : null,
            'assignmentWarning' => $this->assignmentWarning(),
        ];
    }

    public function kartuStok(Request $request): Response
    {
        return Inertia::render('Admin/Logistik/KartuStok', $this->kartuStokProps($request));
    }

    public function kartuStokData(Request $request): JsonResponse
    {
        return response()->json($this->kartuStokProps($request));
    }

    protected function kartuStokProps(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $gudangId = trim((string) $request->query('gudang_id', ''));
        $materialId = trim((string) $request->query('material_id', ''));
        $kodeItem = trim((string) $request->query('kode_item', ''));
        $periodeInput = (string) $request->query('periode', 'tahunan');
        $periode = $periodeInput === 'tahunan' ? 'tahunan' : 'bulanan';
        $year = (int) $request->query('year', now()->year);
        $month = $periode === 'bulanan' ? max(1, min(12, (int) $periodeInput)) : now()->month;

        $gudangs = $this->accessibleGudangs();
        $allowedGudangIds = $gudangs->pluck('value')->all();
        if ($gudangId === '' || ! in_array((string) $gudangId, $allowedGudangIds, true)) {
            $gudangId = (string) ($allowedGudangIds[0] ?? '');
        }

        $selectedMaterial = null;
        if ($materialId !== '') {
            $selectedMaterial = BarangMaterial::query()->finalized()->where('status', 'aktif')->find($materialId);
        }

        if (! $selectedMaterial && $kodeItem !== '') {
            $selectedMaterial = BarangMaterial::query()
                ->where('status', 'aktif')
                ->whereRaw('LOWER(kode_barang) = ?', [strtolower($kodeItem)])
                ->first();
        }

        if ($selectedMaterial) {
            $materialId = (string) $selectedMaterial->id;
            $kodeItem = (string) $selectedMaterial->kode_barang;
        } else {
            $materialId = '';
        }

        return [
            'title' => 'Kartu Stok',
            'baseUrl' => route('admin.kartu-stok.index', absolute: false),
            'dataUrl' => route('admin.kartu-stok.data', absolute: false),
            'filters' => [
                'search' => $search,
                'gudang_id' => $gudangId,
                'material_id' => $materialId,
                'kode_item' => $kodeItem,
                'periode' => $periode === 'tahunan' ? 'tahunan' : (string) $month,
                'year' => $year,
                'month' => $month,
            ],
            'options' => [
                'gudangs' => $gudangs,
                'periods' => [
                    ...$this->monthOptions(),
                    ['value' => 'tahunan', 'label' => 'Selama Setahun'],
                ],
                'years' => $this->yearOptions(),
            ],
            'assignmentWarning' => $this->assignmentWarning(),
            'materialNotFound' => $kodeItem !== '' && ! $selectedMaterial,
            'selectedMaterial' => $selectedMaterial ? [
                'id' => (string) $selectedMaterial->id,
                'kode_barang' => $selectedMaterial->kode_barang,
                'nama_barang' => $selectedMaterial->nama_barang,
                'satuan' => $selectedMaterial->satuan,
            ] : null,
            'card' => $this->cardData($gudangId, $materialId, $periode, $year, $month),
        ];
    }

    protected function cardData(string $gudangId, string $materialId, string $periode, int $year, int $month): array
    {
        if ($gudangId === '' || $materialId === '') {
            return [
                'rows' => [],
                'summary' => ['saldo_awal' => 0, 'total_masuk' => 0, 'total_keluar' => 0, 'saldo_akhir' => 0],
                'material_label' => null,
                'gudang_label' => null,
            ];
        }

        $material = BarangMaterial::query()->find($materialId);
        $gudang = Gudang::query()->find($gudangId);

        if (! $material) {
            return [
                'rows' => [],
                'summary' => ['saldo_awal' => 0, 'total_masuk' => 0, 'total_keluar' => 0, 'saldo_akhir' => 0],
                'material_label' => null,
                'gudang_label' => $gudang?->nama_gudang ?? null,
            ];
        }

        $start = $periode === 'tahunan'
            ? Carbon::create($year, 1, 1)->startOfYear()
            : Carbon::create($year, max(1, min(12, $month)), 1)->startOfMonth();
        $end = $periode === 'tahunan'
            ? Carbon::create($year, 12, 31)->endOfYear()
            : Carbon::create($year, max(1, min(12, $month)), 1)->endOfMonth();

        $openingBalance = MaterialOpeningBalance::query()
            ->where('gudang_id', $gudangId)
            ->where('barang_material_id', $materialId)
            ->first();
        $openingDate = $openingBalance?->tanggal_saldo ? Carbon::parse($openingBalance->tanggal_saldo) : null;
        $openingQty = (float) ($openingBalance?->qty ?? 0);
        $openingBeforePeriod = $openingDate && $openingDate->lt($start);
        $openingInPeriod = $openingDate && $openingDate->gte($start) && $openingDate->lte($end) && $openingQty > 0;

        $priorNet = (float) TransaksiLogistikDetail::query()
            ->join('transaksi_logistiks as t', 'transaksi_logistik_details.transaksi_logistik_id', '=', 't.id')
            ->where('t.gudang_id', $gudangId)
            ->where('transaksi_logistik_details.barang_material_id', $materialId)
            ->whereDate('t.tanggal', '<', $start->toDateString())
            ->selectRaw('COALESCE(SUM(CASE WHEN t.jenis = ? THEN transaksi_logistik_details.qty ELSE -transaksi_logistik_details.qty END), 0) as net', [TransaksiLogistik::JENIS_MASUK])
            ->value('net');

        $saldoAwal = ($openingBeforePeriod ? $openingQty : 0) + $priorNet;

        $entries = TransaksiLogistikDetail::query()
            ->join('transaksi_logistiks as t', 'transaksi_logistik_details.transaksi_logistik_id', '=', 't.id')
            ->where('t.gudang_id', $gudangId)
            ->where('transaksi_logistik_details.barang_material_id', $materialId)
            ->whereBetween('t.tanggal', [$start->toDateString(), $end->toDateString()])
            ->orderBy('t.tanggal')
            ->orderBy('t.id')
            ->orderBy('transaksi_logistik_details.id')
            ->get([
                'transaksi_logistik_details.id as detail_id',
                'transaksi_logistik_details.qty as detail_qty',
                'transaksi_logistik_details.satuan as detail_satuan',
                'transaksi_logistik_details.input_qty',
                'transaksi_logistik_details.input_satuan',
                't.id as transaksi_id',
                't.kode_transaksi',
                't.tanggal as transaksi_tanggal',
                't.jenis',
                't.keterangan',
                't.source_type',
            ]);

        $rows = [];

        $monthCursor = $start->copy()->startOfMonth();
        while ($monthCursor->lte($end)) {
            $rows[] = [
                'kode_transaksi' => 'SA',
                'tanggal' => $monthCursor->toDateString(),
                'jenis' => 'SA',
                'keterangan' => 'Saldo Awal',
                'masuk' => 0,
                'keluar' => 0,
                'saldo' => 0,
                'sumber' => 'Saldo Awal',
                '_order' => 0,
            ];

            $monthCursor->addMonth();
        }

        if ($openingInPeriod) {
            $rows[] = [
                'kode_transaksi' => 'SA-INPUT',
                'tanggal' => $openingDate->toDateString(),
                'jenis' => 'SA',
                'keterangan' => $openingBalance?->catatan ?: 'Saldo Awal',
                'masuk' => $openingQty,
                'keluar' => 0,
                'saldo' => 0,
                'sumber' => 'Saldo Awal',
                '_order' => 1,
            ];
        }

        foreach ($entries as $entry) {
            $masuk = $entry->jenis === TransaksiLogistik::JENIS_MASUK ? (float) $entry->detail_qty : 0;
            $keluar = $entry->jenis === TransaksiLogistik::JENIS_KELUAR ? (float) $entry->detail_qty : 0;

            $rows[] = [
                'kode_transaksi' => $entry->kode_transaksi,
                'tanggal' => optional(Carbon::parse($entry->transaksi_tanggal))->format('Y-m-d'),
                'jenis' => $this->transactionTypeCode($entry->source_type, $entry->jenis),
                'keterangan' => $entry->keterangan ?: $this->sourceLabel($entry->source_type),
                'masuk' => $masuk,
                'keluar' => $keluar,
                'saldo' => 0,
                'sumber' => $this->sourceLabel($entry->source_type),
                'input' => filled($entry->input_qty) ? ((float) $entry->input_qty).' '.($entry->input_satuan ?: $entry->detail_satuan) : null,
                '_order' => 2,
            ];
        }

        $rows = collect($rows)
            ->sortBy([
                ['tanggal', 'asc'],
                ['_order', 'asc'],
            ])
            ->values()
            ->all();

        $runningSaldo = $saldoAwal;
        foreach ($rows as $index => $row) {
            if ($row['kode_transaksi'] === 'SA') {
                $rows[$index]['masuk'] = 0;
                $rows[$index]['keluar'] = 0;
                $rows[$index]['saldo'] = $runningSaldo;

                continue;
            }

            $runningSaldo += (float) $row['masuk'] - (float) $row['keluar'];
            $rows[$index]['saldo'] = $runningSaldo;
        }

        $rows = array_map(function (array $row): array {
            unset($row['_order']);

            return $row;
        }, $rows);

        return [
            'rows' => $rows,
            'summary' => [
                'saldo_awal' => $saldoAwal,
                'total_masuk' => collect($rows)->reject(fn (array $row) => $row['jenis'] === 'SA')->sum('masuk'),
                'total_keluar' => collect($rows)->reject(fn (array $row) => $row['jenis'] === 'SA')->sum('keluar'),
                'saldo_akhir' => $runningSaldo,
            ],
            'material_label' => $material ? "{$material->kode_barang} - {$material->nama_barang}" : null,
            'gudang_label' => $gudang?->nama_gudang ?? null,
        ];
    }

    protected function sourceLabel(?string $sourceType): string
    {
        return match ($sourceType) {
            MaterialRequest::class => 'Permintaan material disetujui',
            MaterialReturn::class => 'Pengembalian dari lokasi',
            MaterialPurchase::class => 'Penerimaan pembelian',
            MaterialStockOpname::class => 'Stock Opname',
            null => 'Transaksi lama/manual',
            default => class_basename($sourceType),
        };
    }

    protected function transactionTypeCode(?string $sourceType, ?string $jenis): string
    {
        return match ($sourceType) {
            MaterialPurchase::class => 'PL',
            MaterialStockOpname::class => 'SO',
            MaterialRequest::class => 'PT',
            MaterialReturn::class => 'PT',
            default => match ($jenis) {
                TransaksiLogistik::JENIS_MASUK => 'PL',
                TransaksiLogistik::JENIS_KELUAR => 'PT',
                default => 'SO',
            },
        };
    }

    protected function assignmentWarning(): ?string
    {
        $user = auth()->user();

        if (! $user?->hasRole('user_area_gudang')) {
            return null;
        }

        return $this->assignedGudangIds($user)->isNotEmpty()
            ? null
            : 'Akun gudang ini belum ditugaskan ke gudang tertentu. Minta admin menetapkan gudang pada data user.';
    }

    protected function accessibleGudangs()
    {
        $user = auth()->user();

        $query = Gudang::query()->where('status', 'aktif');

        if ($user?->hasRole('user_area_gudang')) {
            $assignedIds = $this->assignedGudangIds($user);
            if ($assignedIds->isEmpty()) {
                return collect();
            }

            $query->whereIn('id', $assignedIds);
        }

        return $query->orderBy('nama_gudang')
            ->get(['id', 'nama_gudang'])
            ->map(fn (Gudang $row) => [
                'value' => (string) $row->id,
                'label' => $row->nama_gudang,
            ])
            ->values();
    }

    protected function kategoriMaterialOptions()
    {
        return BarangMaterial::query()
            ->where('status', 'aktif')
            ->selectRaw('COALESCE(NULLIF(jenis_material, \'\'), NULLIF(kategori_material, \'\')) as kategori')
            ->whereRaw('COALESCE(NULLIF(jenis_material, \'\'), NULLIF(kategori_material, \'\')) IS NOT NULL')
            ->distinct()
            ->orderBy('kategori')
            ->pluck('kategori')
            ->map(fn (string $kategori) => ['value' => $kategori, 'label' => $kategori])
            ->values();
    }

    protected function assignedGudangIds($user)
    {
        if (! $user) {
            return collect();
        }

        $ids = $user->gudangs()->pluck('gudangs.id')->map(fn ($id) => (int) $id);

        if ($ids->isEmpty() && filled($user->gudang_id)) {
            $ids = collect([(int) $user->gudang_id]);
        }

        return $ids->filter()->unique()->values();
    }

    protected function monthOptions(): array
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return collect($months)->map(fn (string $label, int $value) => ['value' => (string) $value, 'label' => $label])->values()->all();
    }

    protected function yearOptions(): array
    {
        $current = now()->year;

        return collect(range($current - 5, $current + 1))
            ->reverse()
            ->map(fn (int $year) => ['value' => (string) $year, 'label' => (string) $year])
            ->values()
            ->all();
    }
}
