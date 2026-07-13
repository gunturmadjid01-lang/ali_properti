<?php

namespace App\Http\Controllers\Admin\Management\Perumahan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Admin\Management\Perumahan\Logic\PerumahanPayload;
use App\Http\Requests\Admin\Perumahan\StorePerumahanRequest;
use App\Http\Requests\Admin\Perumahan\UpdatePerumahanRequest;
use App\Http\Requests\Admin\PerumahanHpp\UpdatePerumahanHppRequest;
use App\Models\CabangPerusahaan;
use App\Models\DetailPerumahanHpp;
use App\Models\DetailRumah;
use App\Models\DetailRumahHpp;
use App\Models\DetailRumahHppItem;
use App\Models\HppRealisasi;
use App\Models\KelompokHpp;
use App\Models\MaterialRequest;
use App\Models\Perumahan;
use App\Models\PerumahanHpp;
use App\Models\ProgressPembangunan;
use App\Models\TransaksiLogistik;
use App\Models\TahapanPembangunan;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\HppTemplateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PerumahanController extends Controller
{
    private const REQUIRED_KAWASAN_HPP_STAGES = [
        'IV RAB BANGUNAN',
    ];

    private const UNIT_HPP_STAGES = [
        'PEK. PERSIAPAN & PONDASI',
        'PEK. DINDING',
        'PEK. FINISHING AWAL',
        'PEK. PIPA AIR BERSIH & KOTOR',
        'PEK. KERAMIK, SANITASI, PINTU & KAMAR MANDI',
        'PEK. PAGAR & CAR PORT',
        'PEK. TAMAN, PROFIL DAN PENGECATAN',
        'PEK. PEMASANGAN ATAP',
        'PEK. PEMASANGAN PLAFON',
        'PEK. INSTALASI LISTRIK',
    ];

    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = Perumahan::query()
            ->with($this->relations())
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    foreach ($this->searchableColumns() as $column) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Perumahan $row) => $this->formatRow($row));

        return Inertia::render($this->component(), [
            'title' => $this->title(),
            'description' => $this->description(),
            'baseUrl' => route($this->routeName().'.index', absolute: false),
            'createUrl' => route($this->routeName().'.create', absolute: false),
            'routeName' => $this->routeName(),
            'filters' => ['search' => $search],
            'rows' => $rows,
            'columns' => $this->columns(),
            'fields' => $this->fields(),
            'options' => $this->options(),
        ]);
    }

    public function create(): Response
    {
        return $this->formPage();
    }

    public function edit(string $id): Response
    {
        $row = Perumahan::query()->findOrFail($id);
        $this->abortIfLocked($row);

        return $this->formPage($row);
    }

    public function detail(string $id): Response
    {
        $perumahan = Perumahan::query()
            ->with('cabang')
            ->findOrFail($id);
        $kelompokHpps = KelompokHpp::query()->orderBy('nama_hpp')->get(['id', 'nama_hpp']);

        $rumahs = DetailRumah::query()
            ->with(['detailRumahHpps.items.kelompokHpp', 'detailRumahHpps.items.tahapanPembangunan', 'creator:id,name', 'updater:id,name'])
            ->where('perumahan_id', $perumahan->id)
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(function (DetailRumah $rumah) use ($kelompokHpps, $perumahan) {
                $hppItems = $this->rumahHppItems($rumah, $kelompokHpps);

                return [
                    'id' => $rumah->id,
                    'kode_nlok' => $rumah->kode_nlok,
                    'blok_label' => $rumah->kode_nlok,
                    'nomor_rumah' => $rumah->nomor_rumah,
                    'tipe_rumah' => $rumah->tipe_rumah,
                    'model_unit' => $rumah->model_unit,
                    'luas_bangunan' => $rumah->luas_bangunan,
                    'luas_tanah' => $rumah->luas_tanah,
                    'jumlah_lantai' => $rumah->jumlah_lantai,
                    'kamar_tidur' => $rumah->kamar_tidur,
                    'kamar_mandi' => $rumah->kamar_mandi,
                    'daya_listrik' => $rumah->daya_listrik,
                    'sumber_air' => $rumah->sumber_air,
                    'carport' => $rumah->carport,
                    'arah_hadap' => $rumah->arah_hadap,
                    'posisi_unit' => $rumah->posisi_unit,
                    'harga_jual' => $rumah->harga_jual,
                    'status_penjualan' => $rumah->status_penjualan,
                    'status' => $rumah->status,
                    'status_pembangunan' => $rumah->status_pembangunan,
                    'progress_terakhir' => $rumah->progress_terakhir,
                    'tanggal_mulai_bangun' => optional($rumah->tanggal_mulai_bangun)->format('Y-m-d'),
                    'tanggal_selesai_bangun' => optional($rumah->tanggal_selesai_bangun)->format('Y-m-d'),
                    'spesifikasi' => $rumah->spesifikasi,
                    'catatan' => $rumah->catatan,
                    'created_by' => $rumah->creator?->name ?? '-',
                    'updated_by' => $rumah->updater?->name ?? '-',
                    'hpp_items' => $hppItems,
                    'total_rab' => $hppItems->sum('jumlah_rab'),
                    'total_realisasi' => $hppItems->sum('jumlah_realisasi'),
                    'detail_url' => route('admin.management.perumahan.rumah.detail', [$perumahan->id, $rumah->id], false),
                ];
            });

        return Inertia::render('Admin/Management/Perumahan/Detail', [
            'title' => 'Detail Perumahan',
            'perumahan' => [
                'id' => $perumahan->id,
                'nama_perusahaan' => $perumahan->nama_perusahaan,
                'cabang' => $perumahan->cabang?->nama_cabang,
                'alamat' => $perumahan->alamat,
                'jumlah_unit' => $perumahan->jumlah_unit,
                'status' => $perumahan->status,
                'total_hpp_perumahan' => $perumahan->perumahanHpps()->with('detailPerumahanHpps')->get()->flatMap->detailPerumahanHpps->sum('jumlah_rab'),
                'total_realisasi_perumahan' => HppRealisasi::query()->where('perumahan_id', $perumahan->id)->whereNull('detail_rumah_id')->sum('nominal'),
            ],
            'rows' => $rumahs,
            'baseUrl' => route('admin.management.perumahan.index', absolute: false),
        ]);
    }

    public function unitDetail(Request $request, string $id, string $rumahId): Response
    {
        $search = trim((string) $request->query('search', ''));

        $perumahan = Perumahan::query()
            ->with('cabang')
            ->findOrFail($id);

        $rumah = DetailRumah::query()
            ->with(['creator:id,name', 'updater:id,name', 'currentOwnership.costumer:id,kode_costumer,nama'])
            ->where('perumahan_id', $perumahan->id)
            ->findOrFail($rumahId);

        $progress = ProgressPembangunan::query()
            ->with(['tahapanPembangunan:id,nama_tahapan,bobot_persen', 'user:id,name', 'approvedBy:id,name', 'lockedBy:id,name'])
            ->where('detail_rumah_id', $rumah->id)
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->whereHas('tahapanPembangunan', fn (Builder $query) => $query->where('nama_tahapan', 'like', "%{$search}%"))
                        ->orWhere('keterangan', 'like', "%{$search}%");
                });
            })
            ->latest('tanggal')
            ->latest('id')
            ->get()
            ->map(fn (ProgressPembangunan $row) => [
                'id' => $row->id,
                'tahapan_id' => (string) ($row->tahapan_pembangunan_id ?? ''),
                'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                'tahapan' => $row->tahapanPembangunan?->nama_tahapan ?? '-',
                'bobot_tahapan' => (float) ($row->tahapanPembangunan?->bobot_persen ?? 0),
                'persentase' => (float) $row->persentase,
                'persentase_total' => (float) $row->persentase_total,
                'keterangan' => $row->keterangan,
                'foto_url' => $row->foto ? route('media', ['path' => $row->foto], false) : null,
                'status' => $row->approval_status,
                'status_label' => match ($row->approval_status) {
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                    default => 'Menunggu Approval',
                },
                'record_status' => $row->record_status ?? 'draft',
                'locked_at' => optional($row->locked_at)->format('Y-m-d H:i'),
                'locked_by' => $row->lockedBy?->name ?? '-',
                'input_oleh' => $row->user?->name ?? '-',
                'approved_by' => $row->approvedBy?->name ?? '-',
            ])
            ->values();

        $materialRequests = MaterialRequest::query()
            ->with(['details.barangMaterial:id,nama_barang', 'gudang:id,nama_gudang', 'approvedByGudang:id,name', 'tahapanPembangunan:id,nama_tahapan'])
            ->where('detail_rumah_id', $rumah->id)
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode_request', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('details.barangMaterial', fn (Builder $query) => $query->where('nama_barang', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->get()
            ->map(fn (MaterialRequest $row) => [
                'id' => $row->id,
                'kode_request' => $row->kode_request,
                'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                'gudang' => $row->gudang?->nama_gudang ?? 'Gudang Umum',
                'tahapan' => $row->tahapanPembangunan?->nama_tahapan ?? '-',
                'status' => $row->status,
                'status_label' => match ($row->status) {
                    MaterialRequest::STATUS_DIAJUKAN => 'Diajukan',
                    MaterialRequest::STATUS_DIPROSES => 'Disetujui / Diproses',
                    MaterialRequest::STATUS_SELESAI => 'Selesai',
                    MaterialRequest::STATUS_DITOLAK => 'Ditolak',
                    default => ucfirst($row->status),
                },
                'items_text' => $row->details->map(fn ($detail) => trim(($detail->barangMaterial?->nama_barang ?? '-').' x '.($detail->qty ?? 0).' '.($detail->satuan ?? '')))->join(', '),
                'keterangan' => $row->keterangan,
                'approved_by' => $row->approvedByGudang?->name ?? '-',
                'can_approve' => ($row->record_status ?? 'draft') === 'locked' && (bool) auth()->user()?->hasAnyRole(['owner', 'super_admin', 'user_area_gudang']),
                'approve_url' => route('admin.material-request.approve', $row->id, false),
            ])
            ->values();

        $logistik = TransaksiLogistik::query()
            ->with(['gudang:id,nama_gudang', 'details.barangMaterial:id,nama_barang'])
            ->where('detail_rumah_id', $rumah->id)
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode_transaksi', 'like', "%{$search}%")
                        ->orWhere('keterangan', 'like', "%{$search}%")
                        ->orWhereHas('details.barangMaterial', fn (Builder $query) => $query->where('nama_barang', 'like', "%{$search}%"));
                });
            })
            ->latest('tanggal')
            ->latest('id')
            ->get()
            ->map(fn (TransaksiLogistik $row) => [
                'id' => $row->id,
                'kode_transaksi' => $row->kode_transaksi,
                'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                'jenis' => $row->jenis,
                'gudang' => $row->gudang?->nama_gudang ?? '-',
                'keterangan' => $row->keterangan,
                'items_text' => $row->details->map(fn ($detail) => trim(($detail->barangMaterial?->nama_barang ?? '-').' x '.($detail->qty ?? 0).' '.($detail->satuan ?? '')))->join(', '),
            ])
            ->values();

        return Inertia::render('Admin/Management/Perumahan/UnitDetail', [
            'title' => 'Detail Unit Rumah',
            'baseUrl' => route('admin.management.perumahan.detail', $perumahan->id, false),
            'perumahan' => [
                'id' => $perumahan->id,
                'nama_perusahaan' => $perumahan->nama_perusahaan,
                'cabang' => $perumahan->cabang?->nama_cabang,
                'alamat' => $perumahan->alamat,
            ],
            'rumah' => [
                'id' => $rumah->id,
                'kode_nlok' => $rumah->kode_nlok,
                'nomor_rumah' => $rumah->nomor_rumah,
                'tipe_rumah' => $rumah->tipe_rumah,
                'model_unit' => $rumah->model_unit,
                'luas_bangunan' => $rumah->luas_bangunan,
                'luas_tanah' => $rumah->luas_tanah,
                'jumlah_lantai' => $rumah->jumlah_lantai,
                'kamar_tidur' => $rumah->kamar_tidur,
                'kamar_mandi' => $rumah->kamar_mandi,
                'daya_listrik' => $rumah->daya_listrik,
                'sumber_air' => $rumah->sumber_air,
                'carport' => $rumah->carport,
                'arah_hadap' => $rumah->arah_hadap,
                'posisi_unit' => $rumah->posisi_unit,
                'harga_jual' => (float) $rumah->harga_jual,
                'status_penjualan' => $rumah->status_penjualan,
                'pemilik' => $rumah->currentOwnership ? [
                    'nama' => $rumah->currentOwnership->owner_name,
                    'jenis_identitas' => $rumah->currentOwnership->identity_type,
                    'nomor_identitas' => $rumah->currentOwnership->identity_number,
                    'telepon' => $rumah->currentOwnership->phone,
                    'email' => $rumah->currentOwnership->email,
                    'alamat' => $rumah->currentOwnership->address,
                    'tanggal_mulai' => optional($rumah->currentOwnership->acquired_at)->format('Y-m-d'),
                    'sumber' => match ($rumah->currentOwnership->source_type) {
                        'kpr_akad' => 'Akad KPR', 'cash_handover' => 'Serah Terima Cash', default => 'Data Lama / Manual',
                    },
                    'nomor_dokumen' => $rumah->currentOwnership->document_number,
                ] : null,
                'status_pembangunan' => $rumah->status_pembangunan,
                'progress_terakhir' => (float) $rumah->progress_terakhir,
                'tanggal_mulai_bangun' => optional($rumah->tanggal_mulai_bangun)->format('Y-m-d'),
                'tanggal_selesai_bangun' => optional($rumah->tanggal_selesai_bangun)->format('Y-m-d'),
                'spesifikasi' => $rumah->spesifikasi,
                'catatan' => $rumah->catatan,
                'created_by' => $rumah->creator?->name ?? '-',
                'updated_by' => $rumah->updater?->name ?? '-',
            ],
            'progressRows' => $progress,
            'materialRows' => $materialRequests,
            'logistikRows' => $logistik,
            'filters' => ['search' => $search],
        ]);
    }

    public function storeRumah(Request $request, string $id): RedirectResponse
    {
        $this->authorizeOwnerOrManager();
        $perumahan = Perumahan::query()->findOrFail($id);
        $validated = $this->normalizeRumahPayload($this->rumahPayload($request, bulk: true));
        $jumlahUnit = max(1, (int) ($validated['jumlah_unit'] ?? 1));
        $nomors = $this->nomorRumahRange((string) $validated['nomor_rumah'], $jumlahUnit);
        $this->validateNextNomorRumah($perumahan->id, $validated['kode_nlok'], (string) $validated['nomor_rumah']);

        $duplicates = DetailRumah::query()
            ->where('perumahan_id', $perumahan->id)
            ->where('kode_nlok', $validated['kode_nlok'])
            ->whereIn('nomor_rumah', $nomors)
            ->pluck('nomor_rumah')
            ->all();

        if (count($duplicates) > 0) {
            throw ValidationException::withMessages([
                'nomor_rumah' => 'Nomor rumah sudah ada pada blok ini: '.implode(', ', $duplicates),
            ]);
        }

        unset($validated['jumlah_unit']);

        DB::transaction(function () use ($perumahan, $validated, $nomors) {
            foreach ($nomors as $nomorRumah) {
                $perumahan->detailRumahs()->create([
                    ...$validated,
                    'nomor_rumah' => $nomorRumah,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
            }
        });

        return back()->with('success', $jumlahUnit.' unit rumah berhasil ditambahkan.');
    }

    public function updateRumah(Request $request, string $id, string $rumahId): RedirectResponse
    {
        $this->authorizeOwnerOrManager();
        $rumah = DetailRumah::query()->where('perumahan_id', $id)->findOrFail($rumahId);
        $payload = $this->normalizeRumahPayload($this->rumahPayload($request));
        $duplicate = DetailRumah::query()
            ->where('perumahan_id', $id)
            ->where('kode_nlok', $payload['kode_nlok'])
            ->where('nomor_rumah', $payload['nomor_rumah'])
            ->whereKeyNot($rumah->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'nomor_rumah' => 'Nomor rumah sudah ada pada blok ini.',
            ]);
        }

        $rumah->update([
            ...$payload,
            'updated_by' => auth()->id(),
        ]);
        return back()->with('success', 'Unit rumah berhasil diperbarui.');
    }

    public function storeProgress(Request $request, string $id, string $rumahId): RedirectResponse
    {
        $rumah = DetailRumah::query()->where('perumahan_id', $id)->findOrFail($rumahId);
        $validated = $request->validate([
            'tahapan_pembangunan_id' => ['required', 'exists:tahapan_pembangunans,id'],
            'tanggal' => ['required', 'date'],
            'persentase' => ['required', 'numeric', 'min:0', 'max:100'],
            'keterangan' => ['required', 'string'],
            'foto' => ['nullable', 'file', 'image', 'max:4096'],
        ]);

        $tahapan = TahapanPembangunan::query()->findOrFail($validated['tahapan_pembangunan_id']);
        $persentaseTotal = ((float) $validated['persentase'] / 100) * (float) $tahapan->bobot_persen;
        $fotoPath = $request->file('foto')?->store('progress-pembangunan', 'public');

        ProgressPembangunan::query()->create([
            ...$validated,
            'detail_rumah_id' => $rumah->id,
            'tahapan' => $tahapan->bobot_persen,
            'persentase_total' => $persentaseTotal,
            'foto' => $fotoPath,
            'approval_status' => 'menunggu_approval_manager',
            'users_id' => auth()->id() ?? User::query()->value('id'),
        ]);

        return back()->with('success', 'Progress pembangunan berhasil ditambahkan.');
    }

    public function hpp(string $id): Response
    {
        $this->authorizeRab('kawasan', 'view');
        $perumahan = Perumahan::query()
            ->with('cabang')
            ->findOrFail($id);

        $hpp = PerumahanHpp::query()
            ->with(['detailPerumahanHpps.kelompokHpp', 'detailPerumahanHpps.tahapanPembangunan'])
            ->where('perumahan_id', $perumahan->id)
            ->first();
        $kelompokHpps = KelompokHpp::query()->orderBy('nama_hpp')->get(['id', 'nama_hpp']);
        $rows = $this->perumahanHppRows($perumahan, $hpp, $kelompokHpps);

        return Inertia::render('Admin/Management/Perumahan/Hpp', [
            'title' => 'HPP Perumahan',
            'perumahan' => [
                'id' => $perumahan->id,
                'nama_perusahaan' => $perumahan->nama_perusahaan,
                'cabang' => $perumahan->cabang?->nama_cabang,
                'alamat' => $perumahan->alamat,
            ],
            'rows' => $rows,
            'summary' => [
                'jumlah_rab' => $rows->sum('jumlah_rab'),
                'jumlah_realisasi' => $rows->sum('jumlah_realisasi'),
                'sisa_anggaran' => $rows->sum('sisa_anggaran'),
            ],
            'options' => [
                'kelompokHpps' => $this->kelompokHppOptions(),
                'tahapanHpps' => $this->tahapanHppOptions('kawasan', $perumahan->id),
            ],
            'baseUrl' => route('admin.management.perumahan.index', absolute: false),
            'detailUrl' => route('admin.management.perumahan.detail', $perumahan->id, false),
            'hppUrl' => route('admin.management.perumahan.hpp.update', $perumahan->id, false),
            'stageUrl' => route('admin.management.tahapan-hpp.store', absolute: false),
            'stageBaseUrl' => url('/admin/management/tahapan-hpp'),
            'hppContext' => 'kawasan',
            'hppOwner' => ['perumahan_id' => (string) $perumahan->id, 'detail_rumah_id' => null],
        ]);
    }

    public function rumahHpp(string $id, string $rumahId): RedirectResponse
    {
        $this->authorizeRab('unit', 'view');
        $rumah = DetailRumah::query()
            ->where('perumahan_id', $id)
            ->findOrFail($rumahId);

        return redirect()->route('admin.unit-rumah.hpp.detail', $rumah->id);
    }

    public function store(StorePerumahanRequest $request, ApprovalWorkflowService $approvalWorkflow): RedirectResponse
    {
        $payload = app(PerumahanPayload::class)->fromRequest($request);

        $approvalWorkflow->create('perumahan', $payload, function (array $payload): void {
            Perumahan::create($payload);
        });

        return redirect()->route($this->routeName().'.index')->with('success', session('success'));
    }

    public function update(UpdatePerumahanRequest $request, string $id, ApprovalWorkflowService $approvalWorkflow): RedirectResponse
    {
        $row = Perumahan::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $payload = app(PerumahanPayload::class)->fromRequest($request);

        $approvalWorkflow->update('perumahan', $row, $payload, function (Perumahan $row, array $payload): void {
            $row->update($payload);
        });

        return redirect()->route($this->routeName().'.index')->with('success', session('success'));
    }

    public function destroy(string $id, ApprovalWorkflowService $approvalWorkflow): RedirectResponse
    {
        $row = Perumahan::query()->findOrFail($id);
        $this->abortIfLocked($row);

        return $approvalWorkflow->delete('perumahan', $row, function (Perumahan $row): void {
            $row->delete();
        });
    }

    public function updateHpp(UpdatePerumahanHppRequest $request, string $id): RedirectResponse
    {
        $this->authorizeRabEditor('kawasan');
        $perumahan = Perumahan::query()->findOrFail($id);

        DB::transaction(function () use ($request, $perumahan) {
            $hpp = PerumahanHpp::query()->firstOrCreate(
                ['perumahan_id' => $perumahan->id],
                ['user_id' => $this->userId(), 'tanggal_dibuat' => now()->toDateString()],
            );

            $hpp->detailPerumahanHpps()->delete();

            foreach ($request->validated('items') as $item) {
                $hpp->detailPerumahanHpps()->create([
                    'kelompok_hpp_id' => $this->resolveKelompokHppId($item['kelompok_hpp_id'] ?? null),
                    'tahapan_pembangunan_id' => $item['tahapan_pembangunan_id'] ?? null,
                    'nama_pekerjaan' => $item['nama_pekerjaan'] ?? null,
                    'volume' => $item['volume'],
                    'satuan' => $item['satuan'] ?? '',
                    'harga_satuan' => $item['harga_satuan'],
                    'jumlah_rab' => $this->calculateHppAmount($item),
                    'urutan' => $item['urutan'] ?? 0,
                ]);
            }

            app(HppTemplateService::class)->syncBuildingTypeSummary((int) $perumahan->id);
        });

        return back()->with('success', 'HPP perumahan berhasil diperbarui.');
    }

    public function updateHppItem(UpdatePerumahanHppRequest $request, string $id, string $itemId): RedirectResponse
    {
        $this->authorizeRabEditor('kawasan');
        $perumahan = Perumahan::query()->findOrFail($id);
        $payload = $request->validated('items.0');

        DB::transaction(function () use ($perumahan, $itemId, $payload) {
            $hpp = PerumahanHpp::query()->firstOrCreate(
                ['perumahan_id' => $perumahan->id],
                ['user_id' => $this->userId(), 'tanggal_dibuat' => now()->toDateString()],
            );

            $item = DetailPerumahanHpp::query()
                ->whereHas('perumahanHpp', fn (Builder $query) => $query->where('perumahan_id', $perumahan->id))
                ->where('id', $itemId)
                ->first();

            $data = [
                'kelompok_hpp_id' => $this->resolveKelompokHppId($payload['kelompok_hpp_id'] ?? $item?->kelompok_hpp_id),
                'tahapan_pembangunan_id' => $payload['tahapan_pembangunan_id'] ?? null,
                'nama_pekerjaan' => $payload['nama_pekerjaan'] ?? null,
                'volume' => $payload['volume'],
                'satuan' => $payload['satuan'] ?? '',
                'harga_satuan' => $payload['harga_satuan'],
                'jumlah_rab' => $this->calculateHppAmount($payload),
                'urutan' => $payload['urutan'] ?? 0,
            ];

            $item ? $item->update($data) : $hpp->detailPerumahanHpps()->create($data);

            app(HppTemplateService::class)->syncBuildingTypeSummary((int) $perumahan->id);
        });

        return back()->with('success', 'Item HPP perumahan berhasil diperbarui.');
    }

    public function updateRumahHpp(UpdatePerumahanHppRequest $request, string $id, string $rumahId, ?string $itemId = null): RedirectResponse
    {
        $this->authorizeRabEditor('unit');
        $rumah = DetailRumah::query()
            ->where('perumahan_id', $id)
            ->findOrFail($rumahId);

        DB::transaction(function () use ($request, $rumah, $itemId) {
            $hpp = DetailRumahHpp::query()->firstOrCreate(
                ['detail_rumah_id' => $rumah->id],
                ['user_id' => auth()->id(), 'tanggal_dibuat' => now()->toDateString()],
            );

            if ($itemId === null) {
                $hpp->items()->delete();
            }

            foreach ($request->validated('items') as $item) {
                $existing = $itemId !== null
                    ? DetailRumahHppItem::query()
                        ->where('detail_rumah_hpp_id', $hpp->id)
                        ->where('id', $itemId)
                        ->first()
                    : null;

                $data = [
                    'kelompok_hpp_id' => $this->resolveKelompokHppId($item['kelompok_hpp_id'] ?? $existing?->kelompok_hpp_id),
                    'tahapan_pembangunan_id' => $item['tahapan_pembangunan_id'] ?? null,
                    'nama_pekerjaan' => $item['nama_pekerjaan'] ?? null,
                    'volume' => $item['volume'],
                    'satuan' => $item['satuan'] ?? '',
                    'harga_satuan' => $item['harga_satuan'],
                    'jumlah_rab' => $this->calculateHppAmount($item),
                    'urutan' => $item['urutan'] ?? 0,
                ];

                if ($itemId !== null) {
                    $existing ? $existing->update($data) : $hpp->items()->create($data);
                    continue;
                }

                $hpp->items()->create($data);
            }

            app(HppTemplateService::class)->syncBuildingTypeSummary((int) $rumah->perumahan_id);
        });

        return back()->with('success', 'HPP rumah berhasil diperbarui.');
    }

    public function storeHppStage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'konteks' => ['required', 'in:kawasan,unit'],
            'nama_tahapan' => ['required', 'string', 'max:255'],
            'urutan' => ['required', 'integer', 'min:1'],
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'target_detail_rumah_ids' => ['nullable', 'array'],
            'target_detail_rumah_ids.*' => ['required', 'exists:detail_rumahs,id'],
            'items' => ['nullable', 'array'],
            'items.*.kelompok_hpp_id' => ['nullable', 'exists:kelompok_hpps,id'],
            'items.*.nama_pekerjaan' => ['required_with:items', 'string', 'max:255'],
            'items.*.volume' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.satuan' => ['nullable', 'string', 'max:50'],
            'items.*.harga_satuan' => ['required_with:items', 'numeric', 'min:0'],
        ]);

        $this->authorizeRab($validated['konteks'], 'create');
        $this->validateHppStageOwner($validated);
        $detailRumahIds = $validated['konteks'] === 'unit'
            ? collect($validated['target_detail_rumah_ids'] ?? [])
                ->push($validated['detail_rumah_id'] ?? null)
                ->filter()
                ->unique()
                ->values()
            : collect([null]);

        if ($validated['konteks'] === 'unit') {
            $validTargetCount = DetailRumah::query()
                ->where('perumahan_id', $validated['perumahan_id'])
                ->whereIn('id', $detailRumahIds)
                ->count();
            abort_unless($validTargetCount === $detailRumahIds->count(), 422, 'Semua unit target harus berada pada perumahan yang sama.');
        }

        DB::transaction(function () use ($validated, $detailRumahIds): void {
            foreach ($detailRumahIds as $detailRumahId) {
                $this->hppStageScope($validated['perumahan_id'], $detailRumahId)
                    ->where('urutan', '>=', $validated['urutan'])
                    ->increment('urutan');

                $stage = TahapanPembangunan::query()->create([
                    'konteks' => $validated['konteks'],
                    'nama_tahapan' => $validated['nama_tahapan'],
                    'urutan' => $validated['urutan'],
                    'perumahan_id' => $validated['perumahan_id'],
                    'detail_rumah_id' => $detailRumahId,
                    'bobot_persen' => 0,
                    'status' => 'aktif',
                ]);

                $items = collect($validated['items'] ?? [])
                    ->values()
                    ->filter(fn (array $item) => filled($item['nama_pekerjaan'] ?? null))
                    ->map(function (array $item, int $index) use ($stage): array {
                        $volume = (float) ($item['volume'] ?? 0);
                        $hargaSatuan = (float) ($item['harga_satuan'] ?? 0);

                        return [
                            'kelompok_hpp_id' => $this->resolveKelompokHppId($item['kelompok_hpp_id'] ?? null),
                            'tahapan_pembangunan_id' => $stage->id,
                            'nama_pekerjaan' => $item['nama_pekerjaan'],
                            'volume' => $volume,
                            'satuan' => $item['satuan'] ?? '',
                            'harga_satuan' => $hargaSatuan,
                            'jumlah_rab' => $this->calculateHppAmount($item),
                            'urutan' => $item['urutan'] ?? (($index + 1) * 100),
                        ];
                    })
                    ->all();

                if (empty($items)) {
                    continue;
                }

                if ($validated['konteks'] === 'kawasan') {
                    $hpp = PerumahanHpp::query()->firstOrCreate(
                        ['perumahan_id' => $validated['perumahan_id']],
                        ['user_id' => $this->userId(), 'tanggal_dibuat' => now()->toDateString()],
                    );

                    foreach ($items as $item) {
                        $hpp->detailPerumahanHpps()->create($item);
                    }
                    continue;
                }

                $rumah = DetailRumah::query()->find($detailRumahId);
                if (! $rumah) {
                    continue;
                }

                $hpp = DetailRumahHpp::query()->firstOrCreate(
                    ['detail_rumah_id' => $rumah->id],
                    ['user_id' => $this->userId(), 'tanggal_dibuat' => now()->toDateString()],
                );

                foreach ($items as $item) {
                    $hpp->items()->create($item);
                }
            }
        });

        return back()->with('success', 'Tahapan HPP berhasil ditambahkan.');
    }

    public function updateHppStage(Request $request, TahapanPembangunan $stage): RedirectResponse
    {
        $this->authorizeRab($stage->konteks, 'update');
        abort_if(
            $this->isRequiredHppStage($stage),
            422,
            'Tahap IV RAB BANGUNAN wajib otomatis dari total RAB unit dan tidak dapat diubah manual.',
        );

        $validated = $request->validate([
            'nama_tahapan' => ['required', 'string', 'max:255'],
            'urutan' => ['required', 'integer', 'min:1'],
            'target_detail_rumah_ids' => ['nullable', 'array'],
            'target_detail_rumah_ids.*' => ['required', 'exists:detail_rumahs,id'],
        ]);

        abort_unless($stage->perumahan_id || $stage->detail_rumah_id, 422, 'Tahap master tidak dapat diubah dari halaman HPP.');
        $targetIds = $stage->konteks === 'unit'
            ? collect($validated['target_detail_rumah_ids'] ?? [])
                ->push($stage->detail_rumah_id)
                ->filter()
                ->unique()
                ->values()
            : collect([$stage->detail_rumah_id]);
        $stages = $stage->konteks === 'unit'
            ? TahapanPembangunan::query()
                ->where('konteks', 'unit')
                ->where('perumahan_id', $stage->perumahan_id)
                ->whereIn('detail_rumah_id', $targetIds)
                ->where('nama_tahapan', $stage->nama_tahapan)
                ->get()
            : collect([$stage]);

        DB::transaction(function () use ($stages, $validated): void {
            foreach ($stages as $targetStage) {
                $oldOrder = (int) $targetStage->urutan;
                $newOrder = (int) $validated['urutan'];
                $scope = $this->hppStageScope($targetStage->perumahan_id, $targetStage->detail_rumah_id)->where('id', '!=', $targetStage->id);

                if ($newOrder < $oldOrder) {
                    $scope->whereBetween('urutan', [$newOrder, $oldOrder - 1])->increment('urutan');
                } elseif ($newOrder > $oldOrder) {
                    $scope->whereBetween('urutan', [$oldOrder + 1, $newOrder])->decrement('urutan');
                }

                $targetStage->update([
                    'nama_tahapan' => $validated['nama_tahapan'],
                    'urutan' => $validated['urutan'],
                ]);
            }
        });

        return back()->with('success', 'Tahap HPP berhasil diperbarui.');
    }

    public function destroyHppStage(TahapanPembangunan $stage): RedirectResponse
    {
        $this->authorizeRab($stage->konteks, 'delete');
        abort_unless($stage->perumahan_id || $stage->detail_rumah_id, 422, 'Tahap master tidak dapat dihapus dari halaman HPP.');
        abort_if(
            $this->isRequiredHppStage($stage),
            422,
            'Tahap IV RAB BANGUNAN wajib ada karena menjadi sinkronisasi total RAB per unit.',
        );

        DB::transaction(function () use ($stage): void {
            $order = (int) $stage->urutan;
            $perumahanId = $stage->perumahan_id;
            $detailRumahId = $stage->detail_rumah_id;
            $stage->detailPerumahanHpps()->get()->each->delete();
            $stage->detailRumahHppItems()->get()->each->delete();
            $stage->delete();
            $this->hppStageScope($perumahanId, $detailRumahId)
                ->where('urutan', '>', $order)
                ->decrement('urutan');
        });

        return back()->with('success', 'Tahap dan seluruh uraian pekerjaannya berhasil dihapus.');
    }

    public function destroyHppItem(string $id, string $itemId): RedirectResponse
    {
        $this->authorizeRab('kawasan', 'delete');

        $item = DetailPerumahanHpp::query()
            ->whereHas('perumahanHpp', fn (Builder $query) => $query->where('perumahan_id', $id))
            ->findOrFail($itemId);
        $item->delete();
        app(HppTemplateService::class)->syncBuildingTypeSummary((int) $id);

        return back()->with('success', 'Uraian pekerjaan berhasil dihapus.');
    }

    public function destroyRumahHppItem(string $id, string $rumahId, string $itemId): RedirectResponse
    {
        $this->authorizeRab('unit', 'delete');

        $item = DetailRumahHppItem::query()
            ->whereHas('detailRumahHpp.detailRumah', fn (Builder $query) => $query
                ->where('perumahan_id', $id)
                ->where('id', $rumahId))
            ->findOrFail($itemId);
        $item->delete();
        app(HppTemplateService::class)->syncBuildingTypeSummary((int) $id);

        return back()->with('success', 'Uraian pekerjaan berhasil dihapus.');
    }

    protected function modelClass(): string
    {
        return Perumahan::class;
    }

    protected function component(): string
    {
        return 'Admin/Management/Perumahan/Index';
    }

    protected function routeName(): string
    {
        return 'admin.management.perumahan';
    }

    protected function title(): string
    {
        return 'Management Perumahan';
    }

    protected function relations(): array
    {
        return ['cabang'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'cabang_nama', 'label' => 'Cabang'],
            ['key' => 'kode_proyek', 'label' => 'Kode Proyek'],
            ['key' => 'nama_perusahaan', 'label' => 'Nama Perumahan'],
            ['key' => 'luas_lahan', 'label' => 'Luas Lahan'],
            ['key' => 'jumlah_unit', 'label' => 'Unit'],
            ['key' => 'harga_mulai_label', 'label' => 'Harga Mulai'],
            ['key' => 'tanggal_mulai', 'label' => 'Tanggal Mulai'],
            ['key' => 'record_status', 'label' => 'Lock'],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'cabang_id', 'label' => 'Cabang Perusahaan', 'type' => 'select', 'optionsKey' => 'cabang', 'required' => true],
            ['name' => 'nama_perusahaan', 'label' => 'Nama Perumahan', 'type' => 'text', 'required' => true],
            ['name' => 'developer_name', 'label' => 'Nama Developer', 'type' => 'text'],
            ['name' => 'luas_lahan', 'label' => 'Luas Lahan', 'type' => 'text', 'required' => true],
            ['name' => 'luas_komersial', 'label' => 'Luas Komersial', 'type' => 'text'],
            ['name' => 'luas_fasos_fasum', 'label' => 'Luas Fasos/Fasum', 'type' => 'text'],
            ['name' => 'jumlah_unit', 'label' => 'Jumlah Unit', 'type' => 'number', 'required' => true],
            ['name' => 'total_blok', 'label' => 'Total Blok', 'type' => 'number'],
            ['name' => 'harga_mulai', 'label' => 'Harga Mulai', 'type' => 'currency'],
            ['name' => 'tanggal_mulai', 'label' => 'Tanggal Mulai', 'type' => 'date', 'required' => true],
            ['name' => 'tanggal_target_selesai', 'label' => 'Target Selesai', 'type' => 'date'],
            ['name' => 'jenis_sertifikat', 'label' => 'Jenis Sertifikat', 'type' => 'select', 'optionsKey' => 'jenisSertifikat'],
            ['name' => 'nomor_sertifikat_induk', 'label' => 'Nomor Sertifikat Induk', 'type' => 'text'],
            ['name' => 'nama_marketing', 'label' => 'PIC Marketing', 'type' => 'text'],
            ['name' => 'phone_marketing', 'label' => 'No. Marketing', 'type' => 'text'],
            ['name' => 'email_marketing', 'label' => 'Email Marketing', 'type' => 'text'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'status', 'required' => true],
            ['name' => 'latitude', 'label' => 'Latitude', 'type' => 'text'],
            ['name' => 'longtitude', 'label' => 'Longitude', 'type' => 'text'],
            ['name' => 'logo', 'label' => 'Logo', 'type' => 'image'],
            ['name' => 'alamat', 'label' => 'Alamat', 'type' => 'textarea', 'full' => true, 'required' => true],
            ['name' => 'deskripsi', 'label' => 'Deskripsi Proyek', 'type' => 'textarea', 'full' => true],
        ];
    }

    protected function searchableColumns(): array
    {
        return ['kode_proyek', 'nama_perusahaan', 'developer_name', 'alamat', 'luas_lahan', 'status'];
    }

    protected function formatRow(Model $row): array
    {
        $hppItems = collect(PerumahanHpp::query()
            ->with('detailPerumahanHpps.kelompokHpp')
            ->where('perumahan_id', $row->id)
            ->first()
            ?->detailPerumahanHpps
            ?->map(fn (DetailPerumahanHpp $item) => [
                'kelompok_hpp_id' => (string) $item->kelompok_hpp_id,
                'kelompok_hpp_nama' => $item->kelompokHpp?->nama_hpp,
                'volume' => $item->volume,
                'satuan' => $item->satuan,
                'harga_satuan' => $item->harga_satuan,
                'jumlah_rab' => $item->jumlah_rab,
            ])
            ->values() ?? []);

        return array_merge($row->toArray(), [
            'cabang_nama' => $row->cabang?->nama_cabang,
            'tanggal_mulai' => optional($row->tanggal_mulai)->format('Y-m-d'),
            'tanggal_target_selesai' => optional($row->tanggal_target_selesai)->format('Y-m-d'),
            'harga_mulai_label' => 'Rp '.number_format((float) $row->harga_mulai, 0, ',', '.'),
            'hpp_items' => $hppItems,
            'total_hpp' => $hppItems->sum('jumlah_rab'),
            'detail_url' => route('admin.management.perumahan.detail', $row->id, false),
            'hpp_url' => route('admin.management.perumahan.hpp.detail', $row->id, false),
            'edit_url' => route('admin.management.perumahan.edit', $row->id, false),
        ]);
    }

    protected function formPage(?Perumahan $row = null): Response
    {
        $fields = $this->fields();
        $data = collect($fields)->mapWithKeys(function (array $field) use ($row): array {
            $value = $row?->{$field['name']} ?? ($field['defaultValue'] ?? '');
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d');
            }

            return [$field['name'] => $value];
        })->all();

        return Inertia::render('Admin/Management/Perumahan/FormPage', [
            'title' => $row ? 'Edit Perumahan' : 'Tambah Perumahan',
            'description' => $row
                ? 'Perbarui informasi proyek perumahan. Kode proyek tetap dan tidak dapat diubah.'
                : 'Isi informasi proyek. Kode proyek akan dibuat otomatis oleh sistem.',
            'baseUrl' => route($this->routeName().'.index', absolute: false),
            'actionUrl' => $row
                ? route($this->routeName().'.update', $row->id, false)
                : route($this->routeName().'.store', absolute: false),
            'method' => $row ? 'put' : 'post',
            'projectCode' => $row?->kode_proyek,
            'fields' => $fields,
            'options' => $this->options(),
            'initialData' => $data,
        ]);
    }

    protected function perumahanHppRows(Perumahan $perumahan, ?PerumahanHpp $hpp, $kelompokHpps)
    {
        $realisasi = HppRealisasi::query()
            ->where('perumahan_id', $perumahan->id)
            ->whereNull('detail_rumah_id')
            ->selectRaw('COALESCE(tahapan_pembangunan_id, 0) as tahap_id, COALESCE(kelompok_hpp_id, 0) as kelompok_id, SUM(nominal) as total')
            ->groupBy('tahap_id', 'kelompok_id')
            ->get()
            ->keyBy(fn (HppRealisasi $row) => $row->tahap_id.'-'.$row->kelompok_id);

        return collect($hpp?->detailPerumahanHpps ?? [])
            ->sortBy([['tahapanPembangunan.urutan', 'asc'], ['urutan', 'asc'], ['id', 'asc']])
            ->map(function (DetailPerumahanHpp $item) use ($hpp, $realisasi) {
                $jumlahRab = (float) $item->jumlah_rab;
                $realisasiKey = ((int) ($item->tahapan_pembangunan_id ?? 0)).'-'.((int) ($item->kelompok_hpp_id ?? 0));
                $jumlahRealisasi = (float) ($realisasi->get($realisasiKey)?->total ?? 0);

                return $this->formatHppRow($item, $hpp?->tanggal_dibuat, $jumlahRealisasi);
            })
            ->values();
    }

    protected function userId(): int
    {
        $userId = auth()->id() ?? User::query()->value('id');

        abort_if(!$userId, 422, 'User belum tersedia untuk membuat HPP.');

        return (int) $userId;
    }

    protected function tahapanHppOptions(string $konteks, int|string $perumahanId, int|string|null $detailRumahId = null): array
    {
        $query = TahapanPembangunan::query()
            ->where('status', 'aktif')
            ->where('konteks', $konteks);

        if ($konteks === 'unit') {
            $query->where('perumahan_id', $perumahanId)
                ->where('detail_rumah_id', $detailRumahId);
        } else {
            $query->where('perumahan_id', $perumahanId)
                ->whereNull('detail_rumah_id');
        }

        return $query
            ->orderBy('urutan')
            ->orderBy('nama_tahapan')
            ->get(['id', 'nama_tahapan', 'bobot_persen', 'urutan'])
            ->unique('nama_tahapan')
            ->map(fn (TahapanPembangunan $row) => [
                'value' => (string) $row->id,
                'nama_tahapan' => $row->nama_tahapan,
                'label' => $row->nama_tahapan.($row->bobot_persen > 0 ? ' ('.$row->bobot_persen.'%)' : ''),
                'urutan' => $row->urutan,
            ])
            ->values()
            ->all();
    }

    protected function validateHppStageOwner(array $validated): void
    {
        if ($validated['konteks'] === 'kawasan') {
            return;
        }

        $validUnit = DetailRumah::query()
            ->where('id', $validated['detail_rumah_id'] ?? null)
            ->where('perumahan_id', $validated['perumahan_id'])
            ->exists();

        abort_unless($validUnit, 422, 'Unit rumah tidak sesuai dengan perumahan yang dipilih.');
    }

    protected function hppStageScope(int|string $perumahanId, int|string|null $detailRumahId): Builder
    {
        return TahapanPembangunan::query()
            ->where('perumahan_id', $perumahanId)
            ->when(
                $detailRumahId,
                fn (Builder $query) => $query->where('detail_rumah_id', $detailRumahId),
                fn (Builder $query) => $query->whereNull('detail_rumah_id'),
            );
    }

    protected function isRequiredHppStage(TahapanPembangunan $stage): bool
    {
        return $stage->konteks === 'kawasan'
            && in_array($stage->nama_tahapan, self::REQUIRED_KAWASAN_HPP_STAGES, true);
    }

    protected function resolveKelompokHppId(mixed $preferred = null): int
    {
        if ($preferred && KelompokHpp::query()->whereKey($preferred)->exists()) {
            return (int) $preferred;
        }

        $id = KelompokHpp::query()->where('status', 'aktif')->orderBy('id')->value('id')
            ?? KelompokHpp::query()->orderBy('id')->value('id');

        abort_if(! $id, 422, 'Data internal HPP belum tersedia.');

        return (int) $id;
    }

    protected function rumahHppItems(DetailRumah $rumah, $kelompokHpps)
    {
        $hpp = $rumah->detailRumahHpps->first();
        $realisasi = HppRealisasi::query()
            ->where('detail_rumah_id', $rumah->id)
            ->selectRaw('COALESCE(tahapan_pembangunan_id, 0) as tahap_id, COALESCE(kelompok_hpp_id, 0) as kelompok_id, SUM(nominal) as total')
            ->groupBy('tahap_id', 'kelompok_id')
            ->get()
            ->keyBy(fn (HppRealisasi $row) => $row->tahap_id.'-'.$row->kelompok_id);

        return collect($hpp?->items ?? [])->map(function (DetailRumahHppItem $item) use ($hpp, $realisasi) {
            $realisasiKey = ((int) ($item->tahapan_pembangunan_id ?? 0)).'-'.((int) ($item->kelompok_hpp_id ?? 0));

            return $this->formatHppRow($item, $hpp?->tanggal_dibuat, (float) ($realisasi->get($realisasiKey)?->total ?? 0));
        })->values();
    }

    protected function rumahHppRows(DetailRumah $rumah, $kelompokHpps)
    {
        $hpp = DetailRumahHpp::query()
            ->with(['items.kelompokHpp', 'items.tahapanPembangunan'])
            ->where('detail_rumah_id', $rumah->id)
            ->first();
        $realisasi = HppRealisasi::query()
            ->where('detail_rumah_id', $rumah->id)
            ->selectRaw('COALESCE(tahapan_pembangunan_id, 0) as tahap_id, COALESCE(kelompok_hpp_id, 0) as kelompok_id, SUM(nominal) as total')
            ->groupBy('tahap_id', 'kelompok_id')
            ->get()
            ->keyBy(fn (HppRealisasi $row) => $row->tahap_id.'-'.$row->kelompok_id);

        return collect($hpp?->items ?? [])
            ->sortBy([['tahapanPembangunan.urutan', 'asc'], ['urutan', 'asc'], ['id', 'asc']])
            ->map(function (DetailRumahHppItem $item) use ($hpp, $realisasi) {
                $realisasiKey = ((int) ($item->tahapan_pembangunan_id ?? 0)).'-'.((int) ($item->kelompok_hpp_id ?? 0));

                return $this->formatHppRow($item, $hpp?->tanggal_dibuat, (float) ($realisasi->get($realisasiKey)?->total ?? 0));
            })
            ->values();
    }

    protected function formatHppRow(DetailPerumahanHpp|DetailRumahHppItem $item, mixed $tanggal, float $jumlahRealisasi): array
    {
        $jumlahRab = (float) $item->jumlah_rab;

        return [
            'id' => $item->id,
            'tanggal' => optional($tanggal)->format('Y-m-d'),
            'tahapan_pembangunan_id' => (string) ($item->tahapan_pembangunan_id ?? ''),
            'tahapan_nama' => $item->tahapanPembangunan?->nama_tahapan ?? 'Tanpa Tahap',
            'kelompok_hpp_id' => (string) $item->kelompok_hpp_id,
            'kelompok_hpp_nama' => $item->kelompokHpp?->nama_hpp ?? '-',
            'nama_pekerjaan' => $item->nama_pekerjaan ?: ($item->kelompokHpp?->nama_hpp ?? '-'),
            'volume' => (float) $item->volume,
            'satuan' => $item->satuan ?? '',
            'harga_satuan' => (float) $item->harga_satuan,
            'jumlah_rab' => $jumlahRab,
            'jumlah_realisasi' => $jumlahRealisasi,
            'sisa_anggaran' => $jumlahRab - $jumlahRealisasi,
            'urutan' => (int) ($item->urutan ?? 0),
        ];
    }

    protected function calculateHppAmount(array $item): float
    {
        $amount = (float) ($item['volume'] ?? 0) * (float) ($item['harga_satuan'] ?? 0);

        return trim((string) ($item['satuan'] ?? '')) === '%' ? $amount / 100 : $amount;
    }

    protected function authorizeOwnerOrManager(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        abort_unless($user->hasAnyRole(['owner', 'super_admin', 'manajer_pimpro']), 403, 'Hanya owner atau manager yang dapat mengelola unit/HPP dari halaman ini.');
    }

    protected function authorizeRabEditor(string $context): void
    {
        $user = auth()->user();
        $key = $context === 'unit' ? 'rab-unit' : 'rab-perumahan';

        abort_unless(
            ! $user || $user->hasRole('super_admin') || $user->can("{$key}.manage"),
            403,
            'Anda tidak memiliki permission manage untuk mengedit isi RAB.',
        );
    }

    protected function authorizeRab(string $context, string $action): void
    {
        $user = auth()->user();
        $key = $context === 'unit' ? 'rab-unit' : 'rab-perumahan';

        abort_unless(
            ! $user || $user->hasRole('super_admin') || $user->can("{$key}.{$action}"),
            403,
            'Anda tidak memiliki permission untuk mengelola RAB.',
        );
    }

    protected function options(): array
    {
        return [
            'cabang' => CabangPerusahaan::query()
                ->orderBy('nama_cabang')
                ->get(['id', 'nama_cabang'])
                ->map(fn (CabangPerusahaan $cabang) => ['value' => (string) $cabang->id, 'label' => $cabang->nama_cabang])
                ->values(),
            'status' => [['value' => 'aktif', 'label' => 'Aktif'], ['value' => 'nonaktif', 'label' => 'Nonaktif']],
            'jenisSertifikat' => [
                ['value' => 'shm', 'label' => 'SHM'],
                ['value' => 'shgb', 'label' => 'SHGB'],
                ['value' => 'girik', 'label' => 'Girik'],
                ['value' => 'lainnya', 'label' => 'Lainnya'],
            ],
            'kelompokHpps' => $this->kelompokHppOptions(),
        ];
    }

    protected function tahapanOptions()
    {
        return TahapanPembangunan::query()
            ->where('status', 'aktif')
            ->where('konteks', 'unit')
            ->orderBy('urutan')
            ->get(['id', 'nama_tahapan', 'bobot_persen'])
            ->map(fn (TahapanPembangunan $tahapan) => [
                'value' => (string) $tahapan->id,
                'label' => $tahapan->nama_tahapan.' ('.$tahapan->bobot_persen.'%)',
                'bobot_persen' => $tahapan->bobot_persen,
            ])
            ->values();
    }

    protected function rumahPayload(Request $request, bool $bulk = false): array
    {
        return $request->validate([
            'kode_nlok' => ['required', 'string', 'max:5'],
            'jumlah_unit' => [$bulk ? 'required' : 'nullable', 'integer', 'min:1', 'max:500'],
            'nomor_rumah' => ['required', 'string', 'max:255'],
            'tipe_rumah' => ['required_unless:status_pembangunan,kapling', 'nullable', 'string', 'max:255'],
            'model_unit' => ['nullable', 'string', 'max:255'],
            'luas_bangunan' => ['nullable', 'string', 'max:255'],
            'luas_tanah' => ['required', 'string', 'max:255'],
            'jumlah_lantai' => ['nullable', 'integer', 'min:0', 'max:20'],
            'kamar_tidur' => ['nullable', 'integer', 'min:0', 'max:50'],
            'kamar_mandi' => ['nullable', 'integer', 'min:0', 'max:50'],
            'daya_listrik' => ['nullable', 'string', 'max:255'],
            'sumber_air' => ['nullable', 'string', 'max:255'],
            'carport' => ['nullable', 'string', 'max:255'],
            'arah_hadap' => ['nullable', 'string', 'max:255'],
            'posisi_unit' => ['nullable', 'string', 'max:255'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'status_penjualan' => ['required', 'in:tersedia,booking,dp,dp_lunas,proses_penjualan,terjual,hold,batal'],
            'status_pembangunan' => ['required', 'in:kapling,sedang_dibangun,selesai'],
            'progress_terakhir' => ['required', 'numeric', 'min:0', 'max:100'],
            'tanggal_mulai_bangun' => ['nullable', 'date'],
            'tanggal_selesai_bangun' => ['nullable', 'date'],
            'spesifikasi' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:255'],
        ], $this->rumahValidationMessages(), $this->rumahValidationAttributes());
    }

    protected function rumahValidationMessages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_unless' => ':attribute wajib diisi jika status pembangunan bukan Kapling.',
            'exists' => ':attribute yang dipilih tidak valid.',
            'in' => ':attribute yang dipilih tidak valid.',
            'string' => ':attribute harus berupa teks.',
            'integer' => ':attribute harus berupa angka bulat.',
            'numeric' => ':attribute harus berupa angka.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            'min.numeric' => ':attribute minimal :min.',
            'min.integer' => ':attribute minimal :min.',
            'max.numeric' => ':attribute maksimal :max.',
            'max.integer' => ':attribute maksimal :max.',
            'max.string' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function rumahValidationAttributes(): array
    {
        return [
            'kode_nlok' => 'Blok',
            'jumlah_unit' => 'Jumlah unit dibuat',
            'nomor_rumah' => 'Nomor rumah',
            'tipe_rumah' => 'Tipe rumah',
            'model_unit' => 'Model unit',
            'luas_bangunan' => 'Luas bangunan',
            'luas_tanah' => 'Luas tanah',
            'jumlah_lantai' => 'Jumlah lantai',
            'kamar_tidur' => 'Kamar tidur',
            'kamar_mandi' => 'Kamar mandi',
            'daya_listrik' => 'Daya listrik',
            'sumber_air' => 'Sumber air',
            'carport' => 'Carport',
            'arah_hadap' => 'Arah hadap',
            'posisi_unit' => 'Posisi unit',
            'harga_jual' => 'Harga jual dasar',
            'status_penjualan' => 'Status penjualan',
            'status_pembangunan' => 'Status pembangunan',
            'progress_terakhir' => 'Progress awal',
            'tanggal_mulai_bangun' => 'Tanggal mulai',
            'tanggal_selesai_bangun' => 'Tanggal selesai',
            'spesifikasi' => 'Spesifikasi bangunan',
            'catatan' => 'Catatan unit',
            'status' => 'Status',
        ];
    }

    protected function statusPenjualanOptions(): array
    {
        return [
            ['value' => 'tersedia', 'label' => 'Tersedia'],
            ['value' => 'booking', 'label' => 'Booking'],
            ['value' => 'dp', 'label' => 'DP'],
            ['value' => 'dp_lunas', 'label' => 'DP Lunas'],
            ['value' => 'proses_penjualan', 'label' => 'Proses Penjualan'],
            ['value' => 'terjual', 'label' => 'Terjual'],
            ['value' => 'hold', 'label' => 'Hold'],
            ['value' => 'batal', 'label' => 'Batal'],
        ];
    }

    protected function statusPembangunanOptions(): array
    {
        return [
            ['value' => 'kapling', 'label' => 'Kapling'],
            ['value' => 'sedang_dibangun', 'label' => 'Sedang Dibangun'],
            ['value' => 'selesai', 'label' => 'Selesai / Ready Stock'],
        ];
    }

    protected function arahHadapOptions(): array
    {
        return [
            ['value' => 'utara', 'label' => 'Utara'],
            ['value' => 'timur', 'label' => 'Timur'],
            ['value' => 'selatan', 'label' => 'Selatan'],
            ['value' => 'barat', 'label' => 'Barat'],
            ['value' => 'timur_laut', 'label' => 'Timur Laut'],
            ['value' => 'tenggara', 'label' => 'Tenggara'],
            ['value' => 'barat_daya', 'label' => 'Barat Daya'],
            ['value' => 'barat_laut', 'label' => 'Barat Laut'],
        ];
    }

    protected function posisiUnitOptions(): array
    {
        return [
            ['value' => 'standar', 'label' => 'Standar'],
            ['value' => 'hook', 'label' => 'Hook'],
            ['value' => 'kuldesak', 'label' => 'Kuldesak'],
            ['value' => 'boulevard', 'label' => 'Boulevard'],
        ];
    }

    protected function blokOptions(): array
    {
        return collect(range('A', 'Z'))
            ->map(fn (string $blok) => ['value' => $blok, 'label' => 'Blok '.$blok])
            ->values()
            ->all();
    }

    protected function nomorRumahRange(string $nomorMulai, int $jumlahUnit): array
    {
        preg_match('/^(.*?)(\d+)$/', $nomorMulai, $matches);

        if (! $matches) {
            return array_map(fn (int $index) => $index === 0 ? $nomorMulai : $nomorMulai.'-'.($index + 1), range(0, $jumlahUnit - 1));
        }

        $prefix = $matches[1];
        $start = (int) $matches[2];
        $padding = strlen($matches[2]);

        return array_map(
            fn (int $index) => $prefix.str_pad((string) ($start + $index), $padding, '0', STR_PAD_LEFT),
            range(0, $jumlahUnit - 1)
        );
    }

    protected function normalizeRumahPayload(array $payload): array
    {
        if (($payload['status_pembangunan'] ?? null) !== 'kapling') {
            return $payload;
        }

        foreach ([
            'tipe_rumah',
            'model_unit',
            'luas_bangunan',
            'jumlah_lantai',
            'kamar_tidur',
            'kamar_mandi',
            'daya_listrik',
            'sumber_air',
            'carport',
            'arah_hadap',
            'posisi_unit',
            'spesifikasi',
        ] as $key) {
            $payload[$key] = null;
        }

        return $payload;
    }

    protected function validateNextNomorRumah(int|string $perumahanId, string $blok, string $nomorMulai): void
    {
        $start = $this->nomorRumahToInteger($nomorMulai);

        if ($start === null) {
            throw ValidationException::withMessages([
                'nomor_rumah' => 'Nomor mulai harus berakhir dengan angka, contoh: 1 atau 001.',
            ]);
        }

        $maxExisting = DetailRumah::query()
            ->where('perumahan_id', $perumahanId)
            ->where('kode_nlok', $blok)
            ->pluck('nomor_rumah')
            ->map(fn ($nomor) => $this->nomorRumahToInteger((string) $nomor))
            ->filter(fn ($nomor) => $nomor !== null)
            ->max();

        $expected = $maxExisting ? $maxExisting + 1 : 1;

        if ($start !== $expected) {
            throw ValidationException::withMessages([
                'nomor_rumah' => "Nomor berikutnya untuk Blok {$blok} harus mulai dari {$expected}.",
            ]);
        }
    }

    protected function nomorRumahToInteger(string $nomor): ?int
    {
        preg_match('/(\d+)$/', $nomor, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    protected function updateProgressRumah(DetailRumah $rumah): void
    {
        $latestByTahapan = ProgressPembangunan::query()
            ->where('detail_rumah_id', $rumah->id)
            ->where('approval_status', 'approved')
            ->latest('tanggal')
            ->latest('id')
            ->get()
            ->unique('tahapan_pembangunan_id');

        $progressTotal = $latestByTahapan->sum('persentase_total');
        $rumah->update([
            'progress_terakhir' => min(100, $progressTotal),
            'status_pembangunan' => $progressTotal >= 100 ? 'selesai' : 'sedang_dibangun',
            'updated_by' => auth()->id(),
        ]);
    }

    protected function kelompokHppOptions()
    {
        return KelompokHpp::query()
            ->orderBy('nama_hpp')
            ->get(['id', 'nama_hpp'])
            ->map(fn (KelompokHpp $hpp) => ['value' => (string) $hpp->id, 'label' => $hpp->nama_hpp])
            ->values();
    }

    protected function description(): string
    {
        return 'Kelola data, cari cepat, edit, dan hapus dari satu halaman.';
    }
}
