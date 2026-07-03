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
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $block = trim((string) $request->query('block', ''));
        $type = trim((string) $request->query('type', ''));
        $perPage = min(100, max(10, (int) $request->query('per_page', 10)));

        $kelompokHpps = $this->kelompokHppOptions();

        $rows = DetailRumah::query()
            ->with(['perumahan:id,nama_perusahaan', 'creator:id,name', 'updater:id,name', 'detailRumahHpps.items.kelompokHpp'])
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
            ->through(function (DetailRumah $row) use ($kelompokHpps) {
                $hppItems = $this->unitHppItems($row, $kelompokHpps);

                return [
                'id' => $row->id,
                'perumahan_id' => $row->perumahan_id,
                'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                'detail_url' => route('admin.management.perumahan.rumah.detail', [$row->perumahan_id, $row->id], false),
                'hpp_url' => route('admin.unit-rumah.hpp.detail', $row->id, false),
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
                'hpp_items' => $hppItems,
                'hpp_total_rab' => $hppItems->sum('jumlah_rab'),
                'can_edit' => ($row->record_status ?? 'draft') !== 'locked' || $this->currentUserCanManageLockedRecords(),
                'can_delete' => ($row->record_status ?? 'draft') !== 'locked' || $this->currentUserCanManageLockedRecords(),
                ];
            });

        return Inertia::render('Admin/UnitRumah/Index', [
            'title' => 'Management Proyek',
            'description' => 'Admin mengelola CRUD kapling dan unit rumah tanpa akses HPP unit. Data locked tidak bisa diedit atau dihapus oleh admin.',
            'baseUrl' => route('admin.unit-rumah.index', absolute: false),
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

    public function hpp(string $id): Response
    {
        $rumah = DetailRumah::query()
            ->with(['perumahan:id,nama_perusahaan', 'detailRumahHpps.items.kelompokHpp'])
            ->findOrFail($id);
        $kelompokHpps = $this->kelompokHppOptions();
        $rows = $this->unitHppRows($rumah, $kelompokHpps);

        return Inertia::render('Admin/Management/Perumahan/Hpp', [
            'title' => 'HPP Unit Rumah',
            'backLabel' => 'Daftar Unit',
            'perumahan' => [
                'id' => $rumah->id,
                'nama_perusahaan' => trim(($rumah->kode_nlok ? $rumah->kode_nlok.' ' : '').($rumah->nomor_rumah ?? '')),
                'cabang' => $rumah->perumahan?->nama_perusahaan,
                'alamat' => $rumah->perumahan?->alamat,
            ],
            'metaLine' => trim(($rumah->perumahan?->nama_perusahaan ?? '-').' | '.($rumah->perumahan?->alamat ?? '-')),
            'rows' => $rows,
            'summary' => [
                'jumlah_rab' => $rows->sum('jumlah_rab'),
                'jumlah_realisasi' => $rows->sum('jumlah_realisasi'),
                'sisa_anggaran' => $rows->sum('sisa_anggaran'),
            ],
            'options' => [
                'kelompokHpps' => $kelompokHpps,
            ],
            'baseUrl' => route('admin.unit-rumah.index', absolute: false),
            'detailUrl' => route('admin.management.perumahan.rumah.detail', [$rumah->perumahan_id, $rumah->id], false),
            'hppUrl' => route('admin.unit-rumah.hpp.update', $rumah->id, false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('detail-rumah.create') || auth()->user()?->can('detail-rumah.manage'), 403, 'Anda tidak memiliki permission untuk menambah unit rumah.');

        $payload = $this->normalizePayload($this->payload($request, bulk: true));
        $hppItems = $payload['hpp_items'] ?? [];
        unset($payload['hpp_items']);
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

        DB::transaction(function () use ($payload, $nomors, $hppItems) {
            foreach ($nomors as $nomorRumah) {
                $rumah = DetailRumah::query()->create([
                    ...$payload,
                    'nomor_rumah' => $nomorRumah,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                $this->syncUnitHpp($rumah, $hppItems);
            }
        });

        return back()->with('success', $jumlahUnit.' unit rumah berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth()->user()?->can('detail-rumah.update') || auth()->user()?->can('detail-rumah.manage'), 403, 'Anda tidak memiliki permission untuk mengubah unit rumah.');

        $row = DetailRumah::query()->findOrFail($id);
        $this->abortIfLocked($row);

        $payload = $this->normalizePayload($this->payload($request));
        $hppItems = $payload['hpp_items'] ?? [];
        unset($payload['hpp_items']);
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

        $this->syncUnitHpp($row, $hppItems);

        return back()->with('success', 'Unit rumah berhasil diperbarui.');
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
        abort_unless(auth()->user()?->can('detail-rumah.update') || auth()->user()?->can('detail-rumah.manage'), 403, 'Anda tidak memiliki permission untuk mengubah HPP unit rumah.');

        $rumah = DetailRumah::query()->findOrFail($id);
        $this->abortIfLocked($rumah);

        DB::transaction(function () use ($request, $rumah, $itemId) {
            $hpp = DetailRumahHpp::query()->firstOrCreate(
                ['detail_rumah_id' => $rumah->id],
                ['user_id' => auth()->id() ?? 1, 'tanggal_dibuat' => now()->toDateString()],
            );

            if ($itemId === null) {
                $hpp->items()->delete();
            }

            foreach ($request->validated('items') as $item) {
                $data = [
                    'kelompok_hpp_id' => $item['kelompok_hpp_id'],
                    'volume' => $item['volume'],
                    'satuan' => $item['satuan'] ?? '',
                    'harga_satuan' => $item['harga_satuan'],
                    'jumlah_rab' => (float) $item['volume'] * (float) $item['harga_satuan'],
                ];

                if ($itemId !== null) {
                    $existing = DetailRumahHppItem::query()
                        ->where('detail_rumah_hpp_id', $hpp->id)
                        ->where('id', $itemId)
                        ->first();

                    $existing ? $existing->update($data) : $hpp->items()->create($data);
                    continue;
                }

                $hpp->items()->create($data);
            }
        });

        return back()->with('success', 'HPP unit rumah berhasil diperbarui.');
    }

    protected function modelClass(): string
    {
        return DetailRumah::class;
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
            'hpp_items' => ['required', 'array', 'min:1'],
            'hpp_items.*.kelompok_hpp_id' => ['required', 'exists:kelompok_hpps,id'],
            'hpp_items.*.volume' => ['required', 'numeric', 'min:0'],
            'hpp_items.*.satuan' => ['nullable', 'string', 'max:255'],
            'hpp_items.*.harga_satuan' => ['required', 'numeric', 'min:0'],
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
            'hpp_items' => 'Data HPP',
            'hpp_items.*.kelompok_hpp_id' => 'Kelompok HPP',
            'hpp_items.*.volume' => 'Volume',
            'hpp_items.*.satuan' => 'Satuan',
            'hpp_items.*.harga_satuan' => 'Harga satuan',
        ];
    }

    protected function options(): array
    {
        return [
            'perumahans' => Perumahan::query()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn (Perumahan $row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])->values(),
            'blokOptions' => $this->blokOptions(),
            'filterBlokOptions' => [['value' => '', 'label' => 'Semua Blok'], ...$this->blokOptions()],
            'tipeRumahOptions' => $this->tipeRumahOptions(),
            'kelompokHpps' => $this->kelompokHppOptions(),
            'perPageOptions' => [
                ['value' => '10', 'label' => '10 data'],
                ['value' => '25', 'label' => '25 data'],
                ['value' => '50', 'label' => '50 data'],
                ['value' => '100', 'label' => '100 data'],
            ],
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

    protected function kelompokHppOptions(): array
    {
        return KelompokHpp::query()
            ->where('status', 'aktif')
            ->orderBy('kategori')
            ->orderBy('nama_hpp')
            ->get(['id', 'nama_hpp', 'kategori'])
            ->map(fn (KelompokHpp $row) => [
                'value' => (string) $row->id,
                'label' => $row->optionLabel(),
                'kategori' => $row->kategori_label,
            ])
            ->values()
            ->all();
    }

    protected function unitHppItems(DetailRumah $rumah, array $kelompokHpps): Collection
    {
        $hpp = $rumah->detailRumahHpps->first();
        $items = collect($hpp?->items ?? [])->keyBy('kelompok_hpp_id');

        return collect($kelompokHpps)->map(function (array $kelompokHpp) use ($items) {
            $item = $items->get((int) $kelompokHpp['value']);
            $volume = (float) ($item?->volume ?? 0);
            $hargaSatuan = (float) ($item?->harga_satuan ?? 0);

            return [
                'id' => $item?->id,
                'kelompok_hpp_id' => $kelompokHpp['value'],
                'kelompok_hpp_nama' => $kelompokHpp['label'],
                'kategori' => $kelompokHpp['kategori'] ?? '',
                'volume' => $volume,
                'satuan' => $item?->satuan ?? '',
                'harga_satuan' => $hargaSatuan,
                'jumlah_rab' => $volume * $hargaSatuan,
            ];
        })->values();
    }

    protected function unitHppRows(DetailRumah $rumah, array $kelompokHpps): Collection
    {
        $hpp = DetailRumahHpp::query()
            ->with('items.kelompokHpp')
            ->where('detail_rumah_id', $rumah->id)
            ->first();
        $items = collect($hpp?->items ?? [])->keyBy('kelompok_hpp_id');
        $realisasi = HppRealisasi::query()
            ->where('detail_rumah_id', $rumah->id)
            ->selectRaw('kelompok_hpp_id, SUM(nominal) as total')
            ->groupBy('kelompok_hpp_id')
            ->pluck('total', 'kelompok_hpp_id');

        return collect($kelompokHpps)->map(function (array $kelompokHpp) use ($hpp, $items, $realisasi) {
            $item = $items->get((int) $kelompokHpp['value']);
            $jumlahRab = (float) ($item?->jumlah_rab ?? 0);
            $jumlahRealisasi = (float) ($realisasi[(int) $kelompokHpp['value']] ?? 0);

            return [
                'id' => $item?->id,
                'tanggal' => $item ? optional($hpp?->tanggal_dibuat)->format('Y-m-d') : null,
                'kelompok_hpp_id' => $kelompokHpp['value'],
                'kelompok_hpp_nama' => $kelompokHpp['label'],
                'kategori' => $kelompokHpp['kategori'] ?? '',
                'volume' => (float) ($item?->volume ?? 0),
                'satuan' => $item?->satuan ?? '',
                'harga_satuan' => (float) ($item?->harga_satuan ?? 0),
                'jumlah_rab' => $jumlahRab,
                'jumlah_realisasi' => $jumlahRealisasi,
                'sisa_anggaran' => $jumlahRab - $jumlahRealisasi,
            ];
        })->values();
    }

    protected function syncUnitHpp(DetailRumah $rumah, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $hpp = DetailRumahHpp::query()->firstOrCreate(
            ['detail_rumah_id' => $rumah->id],
            ['user_id' => auth()->id() ?? 1, 'tanggal_dibuat' => now()->toDateString()],
        );

        $hpp->items()->delete();

        foreach ($items as $item) {
            $volume = (float) ($item['volume'] ?? 0);
            $hargaSatuan = (float) ($item['harga_satuan'] ?? 0);

            $hpp->items()->create([
                'kelompok_hpp_id' => $item['kelompok_hpp_id'],
                'volume' => $volume,
                'satuan' => $item['satuan'] ?? '',
                'harga_satuan' => $hargaSatuan,
                'jumlah_rab' => $volume * $hargaSatuan,
            ]);
        }
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
