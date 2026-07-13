<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Requests\Admin\PerumahanHpp\UpdatePerumahanHppRequest;
use App\Models\DetailRumah;
use App\Models\DetailRumahHpp;
use App\Models\DetailRumahHppItem;
use App\Models\HppRealisasi;
use App\Models\KelompokHpp;
use App\Models\Perumahan;
use App\Models\TahapanPembangunan;
use App\Services\HppTemplateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UnitRumahController extends Controller
{
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
        $block = trim((string) $request->query('block', ''));
        $type = trim((string) $request->query('type', ''));
        $perPage = min(100, max(10, (int) $request->query('per_page', 10)));

        $rows = DetailRumah::query()
            ->with(['perumahan:id,nama_perusahaan', 'creator:id,name', 'updater:id,name'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode_nlok', 'like', "%{$search}%")
                        ->orWhere('nomor_rumah', 'like', "%{$search}%")
                        ->orWhere('tipe_rumah', 'like', "%{$search}%")
                        ->orWhereHas('perumahan', fn (Builder $query) => $query->where('nama_perusahaan', 'like', "%{$search}%"));
                });
            })
            ->when($block !== '', fn (Builder $query) => $query->where('kode_nlok', $block))
            ->when($type !== '', fn (Builder $query) => $query->where('tipe_rumah', $type))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (DetailRumah $row) {
                return [
                'id' => $row->id,
                'perumahan_id' => $row->perumahan_id,
                'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                'detail_url' => route('admin.management.perumahan.rumah.detail', [$row->perumahan_id, $row->id], false),
                'edit_url' => route('admin.unit-rumah.edit', $row->id, false),
                'kode_nlok' => $row->kode_nlok,
                'blok_label' => $row->kode_nlok,
                'nomor_rumah' => $row->nomor_rumah,
                'tipe_rumah' => $row->tipe_rumah,
                'model_unit' => $row->model_unit,
                'luas_bangunan' => $row->luas_bangunan,
                'luas_tanah' => $row->luas_tanah,
                'jumlah_lantai' => $row->jumlah_lantai,
                'kamar_tidur' => $row->kamar_tidur,
                'kamar_mandi' => $row->kamar_mandi,
                'daya_listrik' => $row->daya_listrik,
                'sumber_air' => $row->sumber_air,
                'carport' => $row->carport,
                'arah_hadap' => $row->arah_hadap,
                'posisi_unit' => $row->posisi_unit,
                'harga_jual' => $row->harga_jual,
                'status_penjualan' => $row->status_penjualan,
                'status_pembangunan' => $row->status_pembangunan,
                'progress_terakhir' => $row->progress_terakhir,
                'tanggal_mulai_bangun' => optional($row->tanggal_mulai_bangun)->format('Y-m-d'),
                'tanggal_selesai_bangun' => optional($row->tanggal_selesai_bangun)->format('Y-m-d'),
                'spesifikasi' => $row->spesifikasi,
                'catatan' => $row->catatan,
                'status' => $row->status,
                'record_status' => $row->record_status ?? 'draft',
                'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
                'created_by' => $row->creator?->name ?? '-',
                'updated_by' => $row->updater?->name ?? '-',
                'can_edit' => ($row->record_status ?? 'draft') !== 'locked' || $this->currentUserCanManageLockedRecords(),
                'can_delete' => ($row->record_status ?? 'draft') !== 'locked' || $this->currentUserCanManageLockedRecords(),
                ];
            });

        return Inertia::render('Admin/UnitRumah/Index', [
            'title' => 'Kapling / Unit',
            'description' => 'Kelola data kapling dan unit rumah, spesifikasi bangunan, harga jual, serta status pembangunannya.',
            'baseUrl' => route('admin.unit-rumah.index', absolute: false),
            'createUrl' => route('admin.unit-rumah.create', absolute: false),
            'rows' => $rows,
            'filters' => [
                'search' => $search,
                'block' => $block,
                'type' => $type,
                'per_page' => (string) $perPage,
            ],
            'options' => $this->options(),
            'permissions' => [
                'canManageLocked' => $this->currentUserCanManageLockedRecords(),
            ],
        ]);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()?->can('detail-rumah.create') || auth()->user()?->can('detail-rumah.manage'), 403, 'Anda tidak memiliki permission untuk menambah unit rumah.');

        return $this->formPage();
    }

    public function edit(string $id): Response
    {
        abort_unless(auth()->user()?->can('detail-rumah.update') || auth()->user()?->can('detail-rumah.manage'), 403, 'Anda tidak memiliki permission untuk mengubah unit rumah.');
        $row = DetailRumah::query()->findOrFail($id);
        $this->abortIfLocked($row);

        return $this->formPage($row);
    }

    public function hppIndex(Request $request): Response
    {
        abort_unless(
            ! auth()->user() || auth()->user()->hasRole('super_admin') || auth()->user()->can('rab-unit.view'),
            403,
            'Anda tidak memiliki permission untuk melihat HPP unit rumah.',
        );

        $search = trim((string) $request->query('search', ''));
        $block = trim((string) $request->query('block', ''));
        $type = trim((string) $request->query('type', ''));
        $perPage = min(100, max(10, (int) $request->query('per_page', 10)));

        $rows = DetailRumah::query()
            ->with(['perumahan:id,nama_perusahaan', 'detailRumahHpps.items'])
            ->where(fn (Builder $query) => $query
                ->whereNull('status_pembangunan')
                ->orWhere('status_pembangunan', '!=', 'selesai'))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode_nlok', 'like', "%{$search}%")
                        ->orWhere('nomor_rumah', 'like', "%{$search}%")
                        ->orWhere('tipe_rumah', 'like', "%{$search}%")
                        ->orWhereHas('perumahan', fn (Builder $query) => $query->where('nama_perusahaan', 'like', "%{$search}%"));
                });
            })
            ->when($block !== '', fn (Builder $query) => $query->where('kode_nlok', $block))
            ->when($type !== '', fn (Builder $query) => $query->where('tipe_rumah', $type))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (DetailRumah $row) {
                $items = $row->detailRumahHpps->flatMap->items;
                $totalRab = (float) $items->sum('jumlah_rab');
                $totalRealisasi = (float) HppRealisasi::query()
                    ->where('detail_rumah_id', $row->id)
                    ->sum('nominal');

                return [
                    'id' => $row->id,
                    'perumahan_id' => $row->perumahan_id,
                    'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                    'hpp_url' => route('admin.unit-rumah.hpp.detail', $row->id, false),
                    'has_hpp' => $items->isNotEmpty(),
                    'total_rab' => $totalRab,
                    'total_realisasi' => $totalRealisasi,
                    'sisa_anggaran' => $totalRab - $totalRealisasi,
                    'persentase_anggaran' => $totalRab > 0 ? min(100, ($totalRealisasi / $totalRab) * 100) : 0,
                    'blok_label' => $row->kode_nlok,
                    'nomor_rumah' => $row->nomor_rumah,
                    'tipe_rumah' => $row->tipe_rumah,
                    'status_pembangunan' => $row->status_pembangunan,
                ];
            });

        return Inertia::render('Admin/UnitRumah/HppIndex', [
            'title' => 'HPP Unit Rumah',
            'description' => 'Kelola RAB dan pantau realisasi, sisa anggaran, serta pemakaian anggaran setiap unit rumah.',
            'baseUrl' => route('admin.hpp-unit-rumah.index', absolute: false),
            'rows' => $rows,
            'filters' => [
                'search' => $search,
                'block' => $block,
                'type' => $type,
                'per_page' => (string) $perPage,
            ],
            'options' => $this->options(),
        ]);
    }

    public function hpp(Request $request, string $id): Response
    {
        abort_unless(
            ! auth()->user() || auth()->user()->hasRole('super_admin') || auth()->user()->can('rab-unit.view'),
            403,
            'Anda tidak memiliki permission untuk melihat RAB unit rumah.',
        );

        $rumah = DetailRumah::query()
            ->with(['perumahan:id,nama_perusahaan,alamat', 'detailRumahHpps.items.kelompokHpp', 'detailRumahHpps.items.tahapanPembangunan'])
            ->findOrFail($id);
        $this->ensureUnitStages($rumah);
        app(HppTemplateService::class)->initializeUnit($rumah);
        $rumah->load(['detailRumahHpps.items.kelompokHpp', 'detailRumahHpps.items.tahapanPembangunan']);
        $rows = $this->unitHppRows($rumah);
        $requestedTargetIds = collect($request->query('targets', []))
            ->map(fn ($targetId) => (int) $targetId)
            ->filter()
            ->push((int) $rumah->id)
            ->unique();
        $initialTargetIds = DetailRumah::query()
            ->where('perumahan_id', $rumah->perumahan_id)
            ->whereIn('id', $requestedTargetIds)
            ->pluck('id')
            ->map(fn ($targetId) => (string) $targetId)
            ->values();

        return Inertia::render('Admin/UnitRumah/Rab', [
            'title' => 'RAB Unit Rumah',
            'backLabel' => 'Daftar Unit',
            'unit' => [
                'id' => $rumah->id,
                'label' => trim(($rumah->kode_nlok ? $rumah->kode_nlok.' ' : '').($rumah->nomor_rumah ?? '')),
                'kode_nlok' => $rumah->kode_nlok,
                'nomor_rumah' => $rumah->nomor_rumah,
                'tipe_rumah' => $rumah->tipe_rumah,
                'luas_tanah' => $rumah->luas_tanah,
                'luas_bangunan' => $rumah->luas_bangunan,
            ],
            'perumahan' => [
                'id' => $rumah->perumahan_id,
                'nama_perusahaan' => $rumah->perumahan?->nama_perusahaan,
                'alamat' => $rumah->perumahan?->alamat,
            ],
            'metaLine' => trim(($rumah->perumahan?->nama_perusahaan ?? '-').' | '.($rumah->perumahan?->alamat ?? '-')),
            'rows' => $rows,
            'initialTargetIds' => $initialTargetIds,
            'summary' => [
                'jumlah_rab' => $rows->sum('jumlah_rab'),
                'jumlah_realisasi' => $rows->sum('jumlah_realisasi'),
                'sisa_anggaran' => $rows->sum('sisa_anggaran'),
            ],
            'options' => [
                'tahapanHpps' => $this->tahapanHppOptions('unit', $rumah->perumahan_id, $rumah->id),
                'unitTargets' => DetailRumah::query()
                    ->where('perumahan_id', $rumah->perumahan_id)
                    ->where(fn (Builder $query) => $query
                        ->whereNull('status_pembangunan')
                        ->orWhere('status_pembangunan', '!=', 'selesai'))
                    ->orderBy('kode_nlok')
                    ->orderBy('nomor_rumah')
                    ->get(['id', 'kode_nlok', 'nomor_rumah', 'tipe_rumah'])
                    ->map(fn (DetailRumah $unit) => [
                        'value' => (string) $unit->id,
                        'label' => trim(($unit->kode_nlok ? $unit->kode_nlok.' ' : '').($unit->nomor_rumah ?? '')).($unit->tipe_rumah ? ' - '.$unit->tipe_rumah : ''),
                    ])
                    ->values(),
            ],
            'baseUrl' => route('admin.hpp-unit-rumah.index', absolute: false),
            'detailUrl' => route('admin.management.perumahan.rumah.detail', [$rumah->perumahan_id, $rumah->id], false),
            'hppUrl' => route('admin.unit-rumah.hpp.update', $rumah->id, false),
            'pdfUrl' => route('admin.unit-rumah.hpp.pdf', $rumah->id, false),
            'stageUrl' => route('admin.management.tahapan-hpp.store', absolute: false),
            'stageBaseUrl' => url('/admin/management/tahapan-hpp'),
            'hppContext' => 'unit',
            'hppOwner' => ['perumahan_id' => (string) $rumah->perumahan_id, 'detail_rumah_id' => (string) $rumah->id],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('detail-rumah.create') || auth()->user()?->can('detail-rumah.manage'), 403, 'Anda tidak memiliki permission untuk menambah unit rumah.');

        $payload = $this->normalizePayload($this->payload($request, bulk: true));
        $jumlahUnit = max(1, (int) ($payload['jumlah_unit'] ?? 1));
        $nomorMulai = (string) $payload['nomor_rumah'];
        $nomors = $this->nomorRange($nomorMulai, $jumlahUnit);
        $this->validateNextNomor($payload['perumahan_id'], $payload['kode_nlok'], $nomorMulai);

        $duplicates = DetailRumah::query()
            ->where('perumahan_id', $payload['perumahan_id'])
            ->where('kode_nlok', $payload['kode_nlok'])
            ->whereIn('nomor_rumah', $nomors)
            ->pluck('nomor_rumah')
            ->all();

        if (count($duplicates) > 0) {
            throw ValidationException::withMessages([
                'nomor_rumah' => 'Nomor rumah sudah ada pada blok ini: '.implode(', ', $duplicates),
            ]);
        }

        unset($payload['jumlah_unit']);

        DB::transaction(function () use ($payload, $nomors) {
            foreach ($nomors as $nomorRumah) {
                $rumah = DetailRumah::query()->create([
                    ...$payload,
                    'nomor_rumah' => $nomorRumah,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
            }
        });

        return redirect()->route('admin.unit-rumah.index')->with('success', $jumlahUnit.' unit rumah berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth()->user()?->can('detail-rumah.update') || auth()->user()?->can('detail-rumah.manage'), 403, 'Anda tidak memiliki permission untuk mengubah unit rumah.');

        $row = DetailRumah::query()->findOrFail($id);
        $this->abortIfLocked($row);

        $payload = $this->normalizePayload($this->payload($request));
        $duplicate = DetailRumah::query()
            ->where('perumahan_id', $payload['perumahan_id'])
            ->where('kode_nlok', $payload['kode_nlok'])
            ->where('nomor_rumah', $payload['nomor_rumah'])
            ->whereKeyNot($row->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'nomor_rumah' => 'Nomor rumah sudah ada pada blok ini.',
            ]);
        }

        $row->update([
            ...$payload,
            'updated_by' => auth()->id(),
        ]);
        return redirect()->route('admin.unit-rumah.index')->with('success', 'Unit rumah berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        abort_unless(auth()->user()?->can('detail-rumah.delete') || auth()->user()?->can('detail-rumah.manage'), 403, 'Anda tidak memiliki permission untuk menghapus unit rumah.');

        $row = DetailRumah::query()->findOrFail($id);
        $this->abortIfLocked($row);

        $row->delete();

        return back()->with('success', 'Unit rumah berhasil dihapus.');
    }

    public function updateHpp(UpdatePerumahanHppRequest $request, string $id, ?string $itemId = null): RedirectResponse
    {
        abort_unless(
            ! auth()->user()
            || auth()->user()->hasRole('super_admin')
            || auth()->user()->can('rab-unit.manage'),
            403,
            'Anda tidak memiliki permission manage untuk mengedit isi RAB unit rumah.',
        );

        $rumah = DetailRumah::query()->findOrFail($id);
        $this->abortIfLocked($rumah);
        $items = $request->validated('items');
        $targetIds = collect($request->validated('target_detail_rumah_ids') ?? [])
            ->map(fn ($targetId) => (int) $targetId)
            ->push((int) $rumah->id)
            ->unique()
            ->values();

        $targets = DetailRumah::query()
            ->whereIn('id', $targetIds)
            ->get();

        abort_unless($targets->count() === $targetIds->count(), 422, 'Unit target tidak valid.');
        abort_unless($targets->every(fn (DetailRumah $target) => (int) $target->perumahan_id === (int) $rumah->perumahan_id), 422, 'Semua unit target harus berada pada perumahan yang sama.');

        DB::transaction(function () use ($items, $targets, $itemId, $rumah) {
            $sourceStages = TahapanPembangunan::query()
                ->where('konteks', 'unit')
                ->where('perumahan_id', $rumah->perumahan_id)
                ->where('detail_rumah_id', $rumah->id)
                ->where('status', 'aktif')
                ->get(['id', 'nama_tahapan', 'urutan', 'bobot_persen'])
                ->keyBy('id');

            foreach ($targets as $target) {
                $this->abortIfLocked($target);
                $this->ensureUnitStages($target);

                foreach ($sourceStages as $sourceStage) {
                    TahapanPembangunan::query()->updateOrCreate(
                        [
                            'nama_tahapan' => $sourceStage->nama_tahapan,
                            'konteks' => 'unit',
                            'perumahan_id' => $target->perumahan_id,
                            'detail_rumah_id' => $target->id,
                        ],
                        [
                            'urutan' => $sourceStage->urutan,
                            'bobot_persen' => $sourceStage->bobot_persen,
                            'status' => 'aktif',
                        ],
                    );
                }

                $targetStages = TahapanPembangunan::query()
                    ->where('konteks', 'unit')
                    ->where('perumahan_id', $target->perumahan_id)
                    ->where('detail_rumah_id', $target->id)
                    ->pluck('id', 'nama_tahapan');

                $hpp = DetailRumahHpp::query()->firstOrCreate(
                    ['detail_rumah_id' => $target->id],
                    ['user_id' => auth()->id() ?? 1, 'tanggal_dibuat' => now()->toDateString()],
                );

                if ($itemId === null) {
                    $hpp->items()->delete();
                }

                foreach ($items as $item) {
                    $stageName = $sourceStages->get((int) ($item['tahapan_pembangunan_id'] ?? 0))?->nama_tahapan;
                    $targetStageId = $stageName ? ($targetStages[$stageName] ?? null) : null;

                    $existing = $itemId !== null && $target->is($targets->first())
                        ? DetailRumahHppItem::query()
                            ->where('detail_rumah_hpp_id', $hpp->id)
                            ->where('id', $itemId)
                            ->first()
                        : null;

                    $data = [
                        'kelompok_hpp_id' => $this->resolveKelompokHppId($item['kelompok_hpp_id'] ?? $existing?->kelompok_hpp_id),
                        'tahapan_pembangunan_id' => $targetStageId,
                        'nama_pekerjaan' => $item['nama_pekerjaan'] ?? null,
                        'volume' => $item['volume'],
                        'satuan' => $item['satuan'] ?? '',
                        'harga_satuan' => $item['harga_satuan'],
                        'jumlah_rab' => $this->calculateHppAmount($item),
                        'urutan' => $item['urutan'] ?? 0,
                    ];

                    if ($itemId !== null && $existing) {
                        $existing->update($data);
                        continue;
                    }

                    $hpp->items()->create($data);
                }
            }

            app(HppTemplateService::class)->syncBuildingTypeSummary((int) $rumah->perumahan_id);
        });

        return back()->with('success', 'RAB unit rumah berhasil diperbarui untuk '.$targets->count().' unit.');
    }

    public function exportHppPdf(string $id)
    {
        abort_unless(
            ! auth()->user() || auth()->user()->hasRole('super_admin') || auth()->user()->can('rab-unit.view'),
            403,
            'Anda tidak memiliki permission untuk export RAB unit rumah.',
        );

        $rumah = DetailRumah::query()
            ->with(['perumahan:id,nama_perusahaan,alamat', 'detailRumahHpps.items.kelompokHpp', 'detailRumahHpps.items.tahapanPembangunan'])
            ->findOrFail($id);
        $rows = $this->unitHppRows($rumah);
        $pdf = $this->buildRabPdf($rumah, $rows);
        $filename = 'RAB-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', trim(($rumah->kode_nlok ?? '').'-'.($rumah->nomor_rumah ?? 'unit'))).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function ensureUnitStages(DetailRumah $rumah): void
    {
        foreach (self::UNIT_HPP_STAGES as $index => $stageName) {
            TahapanPembangunan::query()->firstOrCreate(
                [
                    'nama_tahapan' => $stageName,
                    'konteks' => 'unit',
                    'perumahan_id' => $rumah->perumahan_id,
                    'detail_rumah_id' => $rumah->id,
                ],
                [
                    'urutan' => $index + 1,
                    'bobot_persen' => $this->unitStageWeight($index),
                    'status' => 'aktif',
                ],
            );
        }
    }

    protected function unitStageWeight(int $index): float
    {
        return [7.48, 26.30, 14.44, 1.66, 10.81, 14.96, 6.38, 7.42, 7.42, 3.13][$index] ?? 0;
    }

    protected function buildRabPdf(DetailRumah $rumah, Collection $rows): string
    {
        $width = 842;
        $height = 595;
        $margin = 35;
        $columns = [35, 370, 55, 75, 120, 117];
        $headers = ['NO', 'ITEM PEKERJAAN', 'Satuan', 'Jumlah', 'Harga satuan', 'Total harga'];
        $pages = [];
        $content = '';
        $y = $height - 38;
        $line = fn (float $x1, float $y1, float $x2, float $y2) => sprintf("%.2F %.2F m %.2F %.2F l S\n", $x1, $y1, $x2, $y2);
        $rect = fn (float $x, float $yy, float $w, float $h, bool $fill = false) => sprintf("%.2F %.2F %.2F %.2F re %s\n", $x, $yy, $w, $h, $fill ? 'f' : 'S');
        $text = fn (float $x, float $yy, string $value, int $size = 8, string $font = 'F1') => sprintf("BT /%s %d Tf %.2F %.2F Td (%s) Tj ET\n", $font, $size, $x, $yy, $this->pdfEscape($value));
        $money = fn (float $value) => 'Rp '.number_format($value, 0, ',', '.');
        $number = fn (float $value) => rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');

        $drawHeader = function () use (&$content, &$y, $width, $height, $margin, $columns, $headers, $text, $rect, $line, $rumah): void {
            $y = $height - 38;
            $content .= $text($margin, $y, 'RAB PER UNIT RUMAH '.($rumah->perumahan?->nama_perusahaan ?? ''), 15, 'F2');
            $y -= 18;
            $content .= $text($margin, $y, 'UNIT RUMAH '.$this->unitLabel($rumah), 11, 'F2');
            $y -= 24;

            $info = [
                ['OWNER', ': PT. ALI PROPERTY INDONESIA'],
                ['PEKERJAAN', ': '.($rumah->perumahan?->nama_perusahaan ?? '-')],
                ['LOKASI', ': '.($rumah->perumahan?->alamat ?? '-')],
                ['TAHUN ANGGARAN', ': '.now()->format('Y')],
                ['TIPE / LUAS', ': '.($rumah->tipe_rumah ?: '-').' / LT '.$rumah->luas_tanah.' / LB '.($rumah->luas_bangunan ?: '-')],
            ];

            foreach ($info as [$label, $value]) {
                $content .= $text($margin, $y, $label, 8, 'F2');
                $content .= $text($margin + 95, $y, $value, 8);
                $y -= 13;
            }

            $y -= 8;
            $x = $margin;
            $headerTop = $y;
            $content .= "0.90 0.90 0.90 rg\n";
            $content .= $rect($margin, $y - 18, array_sum($columns), 18, true);
            $content .= "0 0 0 RG 0 0 0 rg\n";

            foreach ($headers as $index => $header) {
                $w = $columns[$index];
                $content .= $rect($x, $y - 18, $w, 18);
                $content .= $text($x + 4, $y - 12, $header, 7, 'F2');
                $x += $w;
            }

            $y = $headerTop - 18;
            $x = $margin;
            foreach (['1', '2', '3', '4', '5', '6'] as $index => $label) {
                $w = $columns[$index];
                $content .= $rect($x, $y - 15, $w, 15);
                $content .= $text($x + ($w / 2) - 3, $y - 10, $label, 7, 'F2');
                $x += $w;
            }
            $y -= 15;
            $content .= $line($margin, $y, $margin + array_sum($columns), $y);
        };

        $newPage = function () use (&$pages, &$content, &$drawHeader): void {
            if ($content !== '') {
                $pages[] = $content;
            }
            $content = '';
            $drawHeader();
        };

        $drawHeader();
        $grouped = $rows->groupBy('tahapan_nama');
        $grandTotal = 0;
        $sectionNo = 1;

        foreach (self::UNIT_HPP_STAGES as $stageName) {
            $items = $grouped->get($stageName, collect());
            $sectionTotal = (float) $items->sum('jumlah_rab');
            $grandTotal += $sectionTotal;

            if ($y < 92) {
                $newPage();
            }

            $content .= "0.94 0.94 0.94 rg\n";
            $content .= $rect($margin, $y - 17, array_sum($columns), 17, true);
            $content .= "0 0 0 RG 0 0 0 rg\n";
            $content .= $rect($margin, $y - 17, $columns[0], 17);
            $content .= $rect($margin + $columns[0], $y - 17, array_sum($columns) - $columns[0], 17);
            $content .= $text($margin + 12, $y - 11, (string) $sectionNo, 8, 'F2');
            $content .= $text($margin + $columns[0] + 5, $y - 11, strtoupper($stageName), 8, 'F2');
            $y -= 17;
            $itemNo = 1;

            foreach ($items as $item) {
                if ($y < 72) {
                    $newPage();
                }

                $cells = [
                    (string) $itemNo,
                    (string) $item['nama_pekerjaan'],
                    (string) ($item['satuan'] ?: '-'),
                    $number((float) $item['volume']),
                    $money((float) $item['harga_satuan']),
                    $money((float) $item['jumlah_rab']),
                ];
                $x = $margin;
                foreach ($cells as $index => $cell) {
                    $w = $columns[$index];
                    $content .= $rect($x, $y - 16, $w, 16);
                    $content .= $text($x + 4, $y - 11, $this->pdfTrim($cell, $index === 1 ? 72 : 22), 7);
                    $x += $w;
                }
                $y -= 16;
                $itemNo++;
            }

            $x = $margin;
            $content .= $rect($x, $y - 17, array_sum(array_slice($columns, 0, 5)), 17);
            $content .= "0.57 0.82 0.31 rg\n";
            $content .= $rect($x + array_sum(array_slice($columns, 0, 5)), $y - 17, $columns[5], 17, true);
            $content .= "0 0 0 RG 0 0 0 rg\n";
            $content .= $rect($x + array_sum(array_slice($columns, 0, 5)), $y - 17, $columns[5], 17);
            $content .= $text($margin + 205, $y - 11, 'TOTAL', 8, 'F2');
            $content .= $text($margin + array_sum(array_slice($columns, 0, 5)) + 4, $y - 11, $money($sectionTotal), 8, 'F2');
            $y -= 21;
            $sectionNo++;
        }

        if ($y < 92) {
            $newPage();
        }

        $content .= "0.88 0.88 0.88 rg\n";
        $content .= $rect($margin, $y - 20, array_sum($columns), 20, true);
        $content .= "0 0 0 RG 0 0 0 rg\n";
        $content .= $rect($margin, $y - 20, array_sum(array_slice($columns, 0, 5)), 20);
        $content .= $rect($margin + array_sum(array_slice($columns, 0, 5)), $y - 20, $columns[5], 20);
        $content .= $text($margin + 8, $y - 13, 'TOTAL', 10, 'F2');
        $content .= $text($margin + array_sum(array_slice($columns, 0, 5)) + 4, $y - 13, $money($grandTotal), 10, 'F2');
        $y -= 45;
        $content .= $text($width - 235, $y, 'PT. ALI PROPERTY INDONESIA', 8, 'F2');
        $y -= 15;
        $content .= $text($width - 195, $y, 'DIREKTUR', 8, 'F2');
        $y -= 60;
        $content .= $text($width - 245, $y, 'MUHAMMAD ALI BESTARI SH, MH', 8, 'F2');

        $pages[] = $content;

        return $this->assemblePdf($pages, $width, $height);
    }

    public function destroyHppItem(string $id, string $itemId): RedirectResponse
    {
        abort_unless(
            ! auth()->user()
            || auth()->user()->hasRole('super_admin')
            || auth()->user()->can('rab-unit.delete'),
            403,
            'Anda tidak memiliki permission untuk menghapus uraian RAB.',
        );

        $hppId = DetailRumahHpp::query()->where('detail_rumah_id', $id)->value('id');
        DetailRumahHppItem::query()->where('detail_rumah_hpp_id', $hppId)->findOrFail($itemId)->delete();
        $perumahanId = DetailRumah::query()->whereKey($id)->value('perumahan_id');

        if ($perumahanId) {
            app(HppTemplateService::class)->syncBuildingTypeSummary((int) $perumahanId);
        }

        return back()->with('success', 'Uraian pekerjaan berhasil dihapus.');
    }

    protected function modelClass(): string
    {
        return DetailRumah::class;
    }

    protected function unitLabel(DetailRumah $rumah): string
    {
        return trim(($rumah->kode_nlok ? $rumah->kode_nlok.' ' : '').($rumah->nomor_rumah ?? ''));
    }

    protected function pdfTrim(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return strlen($value) > $limit ? substr($value, 0, $limit - 3).'...' : $value;
    }

    protected function pdfEscape(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;

        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $value);
    }

    protected function assemblePdf(array $contents, int $width, int $height): string
    {
        $pageCount = count($contents);
        $fontRegularId = 3 + ($pageCount * 2);
        $fontBoldId = $fontRegularId + 1;
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
        ];
        $kids = [];

        foreach ($contents as $index => $content) {
            $pageId = 3 + ($index * 2);
            $contentId = $pageId + 1;
            $kids[] = "{$pageId} 0 R";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$width} {$height}] /Resources << /Font << /F1 {$fontRegularId} 0 R /F2 {$fontBoldId} 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[$contentId] = "<< /Length ".strlen($content)." >>\nstream\n{$content}\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pageCount.' >>';
        $objects[$fontRegularId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= count($objects); $id++) {
            $pdf .= str_pad((string) ($offsets[$id] ?? 0), 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    protected function payload(Request $request, bool $bulk = false): array
    {
        return $request->validate([
            'perumahan_id' => ['required', 'exists:perumahans,id'],
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
        ], $this->validationMessages(), $this->validationAttributes());
    }

    protected function validationMessages(): array
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

    protected function validationAttributes(): array
    {
        return [
            'perumahan_id' => 'Perumahan',
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

    protected function options(): array
    {
        return [
            'perumahans' => Perumahan::query()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn (Perumahan $row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])->values(),
            'blokOptions' => $this->blokOptions(),
            'filterBlokOptions' => [['value' => '', 'label' => 'Semua Blok'], ...$this->blokOptions()],
            'tipeRumahOptions' => $this->tipeRumahOptions(),
            'perPageOptions' => [
                ['value' => '10', 'label' => '10 data'],
                ['value' => '25', 'label' => '25 data'],
                ['value' => '50', 'label' => '50 data'],
                ['value' => '100', 'label' => '100 data'],
            ],
            'hppUnitTargets' => DetailRumah::query()
                ->with('perumahan:id,nama_perusahaan')
                ->where(fn (Builder $query) => $query
                    ->whereNull('status_pembangunan')
                    ->orWhere('status_pembangunan', '!=', 'selesai'))
                ->orderBy('perumahan_id')
                ->orderBy('kode_nlok')
                ->orderBy('nomor_rumah')
                ->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah', 'tipe_rumah'])
                ->map(fn (DetailRumah $row) => [
                    'value' => (string) $row->id,
                    'perumahan_id' => (string) $row->perumahan_id,
                    'label' => trim(($row->perumahan?->nama_perusahaan ?? '-').' - '.($row->kode_nlok ?? '').' '.($row->nomor_rumah ?? '')).($row->tipe_rumah ? ' - '.$row->tipe_rumah : ''),
                    'url' => route('admin.unit-rumah.hpp.detail', $row->id, false),
                ])
                ->values(),
            'tahapanPembangunans' => TahapanPembangunan::query()->where('status', 'aktif')->where('konteks', 'unit')->orderBy('urutan')->get(['id', 'nama_tahapan'])->map(fn (TahapanPembangunan $row) => ['value' => (string) $row->id, 'label' => $row->nama_tahapan])->values(),
            'statusPembangunan' => [
                ['value' => 'kapling', 'label' => 'Kapling'],
                ['value' => 'sedang_dibangun', 'label' => 'Sedang Dibangun'],
                ['value' => 'selesai', 'label' => 'Selesai / Ready Stock'],
            ],
            'statusPenjualan' => [
                ['value' => 'tersedia', 'label' => 'Tersedia'],
                ['value' => 'booking', 'label' => 'Booking'],
                ['value' => 'dp', 'label' => 'DP'],
                ['value' => 'dp_lunas', 'label' => 'DP Lunas'],
                ['value' => 'proses_penjualan', 'label' => 'Proses Penjualan'],
                ['value' => 'terjual', 'label' => 'Terjual'],
                ['value' => 'hold', 'label' => 'Hold'],
                ['value' => 'batal', 'label' => 'Batal'],
            ],
            'arahHadap' => [
                ['value' => 'utara', 'label' => 'Utara'],
                ['value' => 'timur', 'label' => 'Timur'],
                ['value' => 'selatan', 'label' => 'Selatan'],
                ['value' => 'barat', 'label' => 'Barat'],
                ['value' => 'timur_laut', 'label' => 'Timur Laut'],
                ['value' => 'tenggara', 'label' => 'Tenggara'],
                ['value' => 'barat_daya', 'label' => 'Barat Daya'],
                ['value' => 'barat_laut', 'label' => 'Barat Laut'],
            ],
            'posisiUnit' => [
                ['value' => 'standar', 'label' => 'Standar'],
                ['value' => 'hook', 'label' => 'Hook'],
                ['value' => 'kuldesak', 'label' => 'Kuldesak'],
                ['value' => 'boulevard', 'label' => 'Boulevard'],
            ],
        ];
    }

    protected function formPage(?DetailRumah $row = null): Response
    {
        $initialData = [
            'perumahan_id' => (string) ($row?->perumahan_id ?? ''),
            'kode_nlok' => $row?->kode_nlok ?? '',
            'nomor_rumah' => $row?->nomor_rumah ?? '',
            'jumlah_unit' => '1',
            'tipe_rumah' => $row?->tipe_rumah ?? '',
            'model_unit' => $row?->model_unit ?? '',
            'luas_bangunan' => $row?->luas_bangunan ?? '',
            'luas_tanah' => $row?->luas_tanah ?? '',
            'jumlah_lantai' => (string) ($row?->jumlah_lantai ?? 1),
            'kamar_tidur' => (string) ($row?->kamar_tidur ?? 0),
            'kamar_mandi' => (string) ($row?->kamar_mandi ?? 0),
            'daya_listrik' => $row?->daya_listrik ?? '',
            'sumber_air' => $row?->sumber_air ?? '',
            'carport' => $row?->carport ?? '',
            'arah_hadap' => $row?->arah_hadap ?? '',
            'posisi_unit' => $row?->posisi_unit ?? 'standar',
            'harga_jual' => $row?->harga_jual ?? '',
            'status_penjualan' => $row?->status_penjualan ?? 'tersedia',
            'status_pembangunan' => $row?->status_pembangunan ?? 'kapling',
            'progress_terakhir' => (string) ($row?->progress_terakhir ?? 0),
            'tanggal_mulai_bangun' => optional($row?->tanggal_mulai_bangun)->format('Y-m-d') ?? '',
            'tanggal_selesai_bangun' => optional($row?->tanggal_selesai_bangun)->format('Y-m-d') ?? '',
            'spesifikasi' => $row?->spesifikasi ?? '',
            'catatan' => $row?->catatan ?? '',
            'status' => $row?->status ?? 'aktif',
        ];

        return Inertia::render('Admin/UnitRumah/Form', [
            'title' => $row ? 'Edit Kapling / Unit' : 'Tambah Kapling / Unit',
            'description' => $row
                ? 'Perbarui data unit rumah pada form terpisah.'
                : 'Tambahkan satu atau beberapa unit berurutan pada proyek perumahan.',
            'baseUrl' => route('admin.unit-rumah.index', absolute: false),
            'actionUrl' => $row
                ? route('admin.unit-rumah.update', $row->id, false)
                : route('admin.unit-rumah.store', absolute: false),
            'method' => $row ? 'put' : 'post',
            'editing' => (bool) $row,
            'initialData' => $initialData,
            'options' => $this->options(),
        ]);
    }

    protected function blokOptions(): array
    {
        return collect(range('A', 'Z'))
            ->map(fn (string $blok) => ['value' => $blok, 'label' => 'Blok '.$blok])
            ->values()
            ->all();
    }

    protected function tipeRumahOptions(): array
    {
        $types = DetailRumah::query()
            ->whereNotNull('tipe_rumah')
            ->where('tipe_rumah', '!=', '')
            ->select('tipe_rumah')
            ->distinct()
            ->orderBy('tipe_rumah')
            ->pluck('tipe_rumah')
            ->map(fn (string $type) => ['value' => $type, 'label' => $type])
            ->values()
            ->all();

        return [['value' => '', 'label' => 'Semua Tipe'], ...$types];
    }

    protected function unitHppRows(DetailRumah $rumah): Collection
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

    protected function formatHppRow(DetailRumahHppItem $item, mixed $tanggal, float $jumlahRealisasi): array
    {
        $jumlahRab = (float) $item->jumlah_rab;

        return [
            'id' => $item->id,
            'tanggal' => optional($tanggal)->format('Y-m-d'),
            'tahapan_pembangunan_id' => (string) ($item->tahapan_pembangunan_id ?? ''),
            'tahapan_nama' => $item->tahapanPembangunan?->nama_tahapan ?? 'Tanpa Tahap',
            'kelompok_hpp_id' => (string) $item->kelompok_hpp_id,
            'kelompok_hpp_nama' => $item->kelompokHpp?->nama_hpp ?? '-',
            'kategori' => $item->kelompokHpp?->kategori_label ?? '',
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

    protected function tahapanHppOptions(string $konteks, int|string $perumahanId, int|string|null $detailRumahId = null): array
    {
        return TahapanPembangunan::query()
            ->where('status', 'aktif')
            ->where('konteks', $konteks)
            ->where('perumahan_id', $perumahanId)
            ->where('detail_rumah_id', $detailRumahId)
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

    protected function normalizePayload(array $payload): array
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

    protected function nomorRange(string $nomorMulai, int $jumlahUnit): array
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

    protected function validateNextNomor(int|string $perumahanId, string $blok, string $nomorMulai): void
    {
        $start = $this->nomorToInteger($nomorMulai);

        if ($start === null) {
            throw ValidationException::withMessages([
                'nomor_rumah' => 'Nomor mulai harus berakhir dengan angka, contoh: 1 atau 001.',
            ]);
        }

        $maxExisting = DetailRumah::query()
            ->where('perumahan_id', $perumahanId)
            ->where('kode_nlok', $blok)
            ->pluck('nomor_rumah')
            ->map(fn ($nomor) => $this->nomorToInteger((string) $nomor))
            ->filter(fn ($nomor) => $nomor !== null)
            ->max();

        $expected = $maxExisting ? $maxExisting + 1 : 1;

        if ($start !== $expected) {
            throw ValidationException::withMessages([
                'nomor_rumah' => "Nomor berikutnya untuk Blok {$blok} harus mulai dari {$expected}.",
            ]);
        }
    }

    protected function nomorToInteger(string $nomor): ?int
    {
        preg_match('/(\d+)$/', $nomor, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }
}
