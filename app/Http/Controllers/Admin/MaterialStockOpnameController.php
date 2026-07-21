<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BarangMaterial;
use App\Models\Gudang;
use App\Models\MaterialStockOpname;
use App\Models\StokMaterial;
use App\Models\TransaksiLogistik;
use App\Services\MaterialUnitConversionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MaterialStockOpnameController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Logistik/StockOpname/Index', $this->indexProps($request));
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Logistik/StockOpname/Create', $this->formProps($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $this->assertGudangAccess($validated['gudang_id']);

        $materialIds = collect($validated['items'])->pluck('barang_material_id')->map(fn ($id) => (int) $id)->unique()->values();
        $materials = BarangMaterial::query()->finalized()
            ->where('status', 'aktif')
            ->whereIn('id', $materialIds)
            ->get()
            ->keyBy('id');

        abort_if($materials->count() !== $materialIds->count(), 422, 'Ada material yang tidak valid pada daftar opname.');

        $stocks = StokMaterial::query()
            ->where('gudang_id', $validated['gudang_id'])
            ->whereIn('barang_material_id', $materialIds)
            ->get()
            ->keyBy('barang_material_id');

        DB::transaction(function () use ($validated, $materials, $stocks): void {
            $opname = MaterialStockOpname::query()->create([
                'kode_opname' => $validated['kode_opname'] ?: null,
                'gudang_id' => $validated['gudang_id'],
                'tanggal' => $validated['tanggal'],
                'keterangan' => $validated['keterangan'] ?? null,
                'record_status' => 'locked',
                'locked_at' => now(),
                'locked_by' => auth()->id(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $details = [];
            $inRows = [];
            $outRows = [];

            foreach ($validated['items'] as $item) {
                $materialId = (int) $item['barang_material_id'];
                $material = $materials->get($materialId);
                $stokSistem = (float) ($stocks->get($materialId)?->qty ?? 0);
                $counts = collect($item['unit_counts'] ?? []);
                $fisik = $counts->isNotEmpty()
                    ? $counts->sum(function (array $count) use ($material): float {
                        $normalized = app(MaterialUnitConversionService::class)->normalize($material, $count['unit_id'] ?? null, (float) ($count['qty'] ?? 0));

                        return $normalized['quantity_base'];
                    })
                    : (float) ($item['fisik'] ?? 0);
                $selisih = round($fisik - $stokSistem, 6);
                $masuk = $selisih > 0 ? $selisih : 0;
                $keluar = $selisih < 0 ? abs($selisih) : 0;

                $details[] = [
                    'barang_material_id' => $materialId,
                    'stok_sistem' => $stokSistem,
                    'fisik' => $fisik,
                    'physical_unit_counts' => $counts->values()->all(),
                    'masuk' => $masuk,
                    'keluar' => $keluar,
                    'selisih' => $selisih,
                    'catatan' => $item['catatan'] ?? null,
                ];

                if ($masuk > 0) {
                    $inRows[] = [
                        'barang_material_id' => $materialId,
                        'qty' => $masuk,
                        'satuan' => $material?->satuan ?? '',
                    ];
                } elseif ($keluar > 0) {
                    $outRows[] = [
                        'barang_material_id' => $materialId,
                        'qty' => $keluar,
                        'satuan' => $material?->satuan ?? '',
                    ];
                }

                $this->adjustStock((int) $validated['gudang_id'], $materialId, $selisih);
            }

            $opname->details()->createMany($details);

            if ($inRows !== []) {
                $this->createLogistikAdjustment($opname, $validated['tanggal'], TransaksiLogistik::JENIS_MASUK, $inRows);
            }

            if ($outRows !== []) {
                $this->createLogistikAdjustment($opname, $validated['tanggal'], TransaksiLogistik::JENIS_KELUAR, $outRows);
            }
        });

        return redirect()->route('admin.material-stock-opname.index')->with('success', 'Stock opname berhasil disimpan.');
    }

    protected function indexProps(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $gudangId = trim((string) $request->query('gudang_id', ''));
        $sort = (string) $request->query('sort', 'tanggal');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $gudangs = $this->accessibleGudangs();
        $allowedIds = $gudangs->pluck('value')->all();

        if ($gudangId !== '' && ! in_array($gudangId, $allowedIds, true)) {
            $gudangId = '';
        }

        $query = MaterialStockOpname::query()
            ->with(['gudang:id,nama_gudang', 'creator:id,name', 'updater:id,name', 'details.barangMaterial:id,kode_barang,nama_barang,satuan,jenis_material,merk_material,kategori_material'])
            ->when(! $this->canViewAllRecords(), function (Builder $builder): void {
                $builder->where(function (Builder $builder): void {
                    $builder->where('record_status', 'locked')
                        ->orWhere('created_by', auth()->id());
                });
            })
            ->when($gudangId !== '', fn (Builder $builder) => $builder->where('gudang_id', $gudangId))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $builder) use ($search): void {
                    $builder->where('kode_opname', 'like', "%{$search}%")
                        ->orWhere('keterangan', 'like', "%{$search}%")
                        ->orWhereHas('gudang', fn (Builder $gudang) => $gudang->where('nama_gudang', 'like', "%{$search}%"));
                });
            });

        $sortable = [
            'tanggal' => 'tanggal',
            'kode_opname' => 'kode_opname',
            'gudang' => 'gudang_id',
            'status' => 'record_status',
        ];

        $sortColumn = $sortable[$sort] ?? 'tanggal';

        $rows = $query
            ->orderBy($sortColumn, $direction)
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (MaterialStockOpname $row) => $this->formatIndexRow($row));

        return [
            'title' => 'Stock Opname Material',
            'baseUrl' => route('admin.material-stock-opname.index', absolute: false),
            'createUrl' => route('admin.material-stock-opname.create', absolute: false),
            'rows' => $rows,
            'filters' => [
                'search' => $search,
                'gudang_id' => $gudangId,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'options' => [
                'gudangs' => $gudangs,
                'sortOptions' => [
                    ['value' => 'tanggal', 'label' => 'Tanggal'],
                    ['value' => 'kode_opname', 'label' => 'No Opname'],
                    ['value' => 'gudang', 'label' => 'Gudang'],
                    ['value' => 'status', 'label' => 'Status'],
                ],
            ],
            'canCreate' => $this->canCreate(),
        ];
    }

    protected function formProps(Request $request): array
    {
        $gudangId = trim((string) $request->query('gudang_id', ''));
        $gudangs = $this->accessibleGudangs();
        $allowedIds = $gudangs->pluck('value')->all();

        if ($gudangId !== '' && ! in_array($gudangId, $allowedIds, true)) {
            $gudangId = '';
        }

        $selectedGudang = filled($gudangId) ? Gudang::query()->find($gudangId) : null;
        $materials = filled($gudangId)
            ? $this->stockRowsForGudang($gudangId)
            : collect();

        return [
            'title' => 'Stock Opname Material',
            'baseUrl' => route('admin.material-stock-opname.create', absolute: false),
            'storeUrl' => route('admin.material-stock-opname.store', absolute: false),
            'indexUrl' => route('admin.material-stock-opname.index', absolute: false),
            'nextCode' => MaterialStockOpname::nextKodeOpname(),
            'selectedGudang' => $selectedGudang ? [
                'id' => (string) $selectedGudang->id,
                'nama_gudang' => $selectedGudang->nama_gudang,
            ] : null,
            'rows' => $materials,
            'filters' => [
                'gudang_id' => $gudangId,
            ],
            'options' => [
                'gudangs' => $gudangs,
            ],
            'assignmentWarning' => $this->assignmentWarning(),
            'canCreate' => $this->canCreate(),
        ];
    }

    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'kode_opname' => ['nullable', 'string', 'max:50'],
            'gudang_id' => ['required', 'exists:gudangs,id'],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_material_id' => ['required', 'exists:barang_materials,id'],
            'items.*.fisik' => ['required', 'numeric', 'min:0'],
            'items.*.unit_counts' => ['nullable', 'array'],
            'items.*.unit_counts.*.unit_id' => ['required', 'exists:material_units,id'],
            'items.*.unit_counts.*.qty' => ['required', 'numeric', 'min:0'],
            'items.*.catatan' => ['nullable', 'string'],
        ]);
    }

    protected function stockRowsForGudang(string $gudangId)
    {
        $stocks = StokMaterial::query()
            ->where('gudang_id', $gudangId)
            ->get()
            ->keyBy('barang_material_id');

        return BarangMaterial::query()
            ->with(['baseUnit:id,name,symbol', 'unitConversions.childUnit:id,name,symbol'])
            ->where('status', 'aktif')
            ->orderBy('kode_barang')
            ->get(['id', 'kode_barang', 'nama_barang', 'satuan', 'jenis_material', 'merk_material', 'kategori_material', 'base_unit_id', 'harga_hpp'])
            ->map(function (BarangMaterial $material) use ($stocks) {
                $qty = (float) ($stocks->get($material->id)?->qty ?? 0);
                $jenis = trim((string) ($material->jenis_material ?: $material->kategori_material));
                $merk = trim((string) ($material->merk_material ?: '-'));

                return [
                    'barang_material_id' => (string) $material->id,
                    'kode_barang' => $material->kode_barang,
                    'nama_barang' => $material->nama_barang,
                    'jenis_merk' => trim($jenis.' / '.$merk, ' /'),
                    'satuan' => $material->satuan,
                    'stok_sistem' => $qty,
                    'fisik' => $qty,
                    'masuk' => 0,
                    'keluar' => 0,
                    'selisih' => 0,
                    'unit_options' => app(MaterialUnitConversionService::class)->options($material),
                ];
            })
            ->values();
    }

    protected function formatIndexRow(MaterialStockOpname $row): array
    {
        $details = $row->details;
        $totalSelisih = (float) $details->sum('selisih');

        return [
            'id' => (string) $row->id,
            'kode_opname' => $row->kode_opname,
            'tanggal' => optional($row->tanggal)->format('d/m/Y'),
            'gudang' => $row->gudang?->nama_gudang ?? '-',
            'keterangan' => $row->keterangan ?? '-',
            'status' => 'locked',
            'status_label' => 'Selesai',
            'total_item' => $details->count(),
            'total_selisih' => $totalSelisih,
            'created_by_name' => $row->creator?->name ?? '-',
            'updated_by_name' => $row->updater?->name ?? '-',
            'locked_at' => optional($row->locked_at)->format('d/m/Y H:i') ?? '-',
            'items' => $details->map(function ($detail) {
                $material = $detail->barangMaterial;

                return [
                    'kode_barang' => $material?->kode_barang ?? '-',
                    'nama_barang' => $material?->nama_barang ?? '-',
                    'jenis_merk' => trim((string) (($material?->jenis_material ?: $material?->kategori_material) ?: '-').' / '.(($material?->merk_material ?: '-') ?: '-'), ' /'),
                    'satuan' => $material?->satuan ?? '-',
                    'stok_sistem' => (float) $detail->stok_sistem,
                    'fisik' => (float) $detail->fisik,
                    'masuk' => (float) ($detail->masuk ?? 0),
                    'keluar' => (float) ($detail->keluar ?? 0),
                    'selisih' => (float) $detail->selisih,
                    'catatan' => $detail->catatan ?? '-',
                ];
            })->values(),
        ];
    }

    protected function createLogistikAdjustment(MaterialStockOpname $opname, string $tanggal, string $jenis, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $transaction = TransaksiLogistik::query()->create([
            'kode_transaksi' => $this->nextKodeTransaksi($jenis),
            'gudang_id' => $opname->gudang_id,
            'tanggal' => Carbon::parse($tanggal)->toDateString(),
            'jenis' => $jenis,
            'keterangan' => 'Stock Opname '.$opname->kode_opname,
            'source_type' => MaterialStockOpname::class,
            'source_id' => $opname->id,
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'total_nominal' => 0,
        ]);

        foreach ($rows as $row) {
            $transaction->details()->create([
                'barang_material_id' => $row['barang_material_id'],
                'qty' => (float) $row['qty'],
                'satuan' => $row['satuan'],
                'harga_satuan' => 0,
                'subtotal' => 0,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        }
    }

    protected function nextKodeTransaksi(string $jenis): string
    {
        $prefix = 'SO-'.now()->format('Ymd').'-'.($jenis === TransaksiLogistik::JENIS_MASUK ? 'IN' : 'OUT').'-';
        $count = TransaksiLogistik::query()->where('kode_transaksi', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    protected function adjustStock(int $gudangId, int $materialId, float $delta): void
    {
        $stock = StokMaterial::query()->firstOrCreate(
            ['gudang_id' => $gudangId, 'barang_material_id' => $materialId],
            ['cabang_id' => null, 'qty' => 0],
        );

        $nextQty = round(((float) $stock->qty) + $delta, 3);
        abort_if($nextQty < 0, 422, 'Stok material tidak boleh menjadi minus.');

        $stock->update(['qty' => $nextQty]);
    }

    protected function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('material-stock-opname.create')
            || $user?->can('material-stock-opname.manage')
            || $user?->hasRole('super_admin'));
    }

    protected function canViewAllRecords(): bool
    {
        return (bool) (auth()->user()?->can('material-stock-opname.view-all')
            || auth()->user()?->can('material-stock-opname.manage')
            || auth()->user()?->hasRole('super_admin'));
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
}
