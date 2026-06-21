<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Models\CashSale;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\DokumenCostumer;
use App\Models\KprSubmission;
use App\Models\Spr;
use App\Models\SprApproval;
use App\Models\SprBerkasCostumer;
use App\Services\Marketing\MarketingLeadStatusService;
use App\Services\Marketing\MarketingOperationsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SprController extends Controller
{
    use HandlesCrudLock;

    protected array $activeSprStatuses = [
        Spr::STATUS_MENUNGGU_MANAGER,
        Spr::STATUS_MENUNGGU_OWNER,
        Spr::STATUS_DISETUJUI,
    ];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = Spr::query()
            ->with([
                'costumer:id,nama,no_identitas,telepon',
                'detailRumah.perumahan:id,nama_perusahaan',
                'creator:id,name',
                'berkasCostumers.dokumen:id,kode_dokumen,nama_dokumen',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where('kode_spr', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('costumer', fn (Builder $query) => $query
                        ->where('nama', 'like', "%{$search}%")
                        ->orWhere('no_identitas', 'like', "%{$search}%"));
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Spr $spr) => $this->row($spr));

        return Inertia::render('Admin/Marketing/Spr/Index', [
            'title' => 'SPR',
            'description' => 'Buat Surat Pemesanan Rumah dan proses approval manager serta owner sebelum masuk KPR.',
            'baseUrl' => route('admin.marketing.spr.index', absolute: false),
            'rows' => $rows,
            'filters' => ['search' => $search],
            'customers' => $this->customerOptions(),
            'units' => $this->unitOptions(),
            'dokumenOptions' => $this->dokumenOptions(),
            'options' => [
                'paymentOptions' => $this->paymentOptions(),
                'statusOptions' => $this->statusOptions(),
            ],
            'permissions' => [
                'canApproveManager' => $request->user()?->hasAnyRole(['owner', 'manajer_pimpro']) ?? true,
                'canApproveOwner' => $request->user()?->hasRole('owner') ?? true,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Marketing/Spr/Form', [
            ...$this->formPayload(),
            'mode' => 'create',
            'title' => 'Buat SPR',
            'description' => 'Lengkapi data customer, unit rumah, penambahan, termin, dan berkas customer dalam satu halaman.',
            'submitUrl' => route('admin.marketing.spr.store', absolute: false),
            'method' => 'post',
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $spr = Spr::query()
            ->with([
                'costumer:id,nama,no_identitas,telepon',
                'detailRumah.perumahan:id,nama_perusahaan',
                'creator:id,name',
                'berkasCostumers.dokumen:id,kode_dokumen,nama_dokumen',
            ])
            ->findOrFail($id);

        return Inertia::render('Admin/Marketing/Spr/Form', [
            ...$this->formPayload($spr),
            'mode' => 'edit',
            'title' => 'Edit SPR '.$spr->kode_spr,
            'description' => 'Perbarui data SPR, penambahan biaya, termin, dan berkas customer yang terkait.',
            'submitUrl' => route('admin.marketing.spr.update', $spr->id, absolute: false),
            'method' => 'put',
        ]);
    }

    public function store(Request $request, MarketingLeadStatusService $leadStatus): RedirectResponse
    {
        $validated = $request->validate([
            'costumer_id' => ['required', 'exists:costumers,id'],
            'detail_rumah_id' => ['required', 'exists:detail_rumahs,id'],
            'tanggal_spr' => ['required', 'date'],
            'metode_pembayaran' => ['required', Rule::in(array_column($this->paymentOptions(), 'value'))],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'booking_fee' => ['nullable', 'numeric', 'min:0'],
            'booking_fee_includes_dp' => ['nullable', 'boolean'],
            'tanggal_pembayaran_booking_fee' => ['nullable', 'date'],
            'uang_muka' => ['nullable', 'numeric', 'min:0'],
            'uang_muka_jumlah_pembayaran' => ['nullable', 'integer', 'min:1'],
            'tanggal_jatuh_tempo_dp' => ['nullable', 'date'],
            'nilai_pengajuan_kpr' => ['nullable', 'numeric', 'min:0'],
            'penambahan_tanah' => ['nullable', 'numeric', 'min:0'],
            'harga_penambahan_tanah' => ['nullable', 'numeric', 'min:0'],
            'penambahan_lain_lain' => ['nullable', 'string'],
            'harga_penambahan_lain_lain' => ['nullable', 'numeric', 'min:0'],
            'jumlah_termin' => ['nullable', 'integer', 'min:1'],
            'tanggal_jatuh_tempo_angsuran' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string'],
            'berkas' => ['required', 'array', 'min:1'],
            'berkas.*.dokumen_costumer_id' => ['required', 'exists:dokumen_costumers,id'],
            'berkas.*.file_upload' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
            'berkas.*.keterangan' => ['nullable', 'string'],
        ]);

        $this->validateTerminRules($validated);

        DB::transaction(function () use ($request, $validated, $leadStatus) {
            $this->ensureUnitIsAvailable((int) $validated['detail_rumah_id']);
            $payload = $this->normalizeSprPayload($validated);

            $spr = Spr::create([
                ...$payload,
                'kode_spr' => $this->nextSprCode(),
                'created_by' => $request->user()?->id,
                'status' => Spr::STATUS_MENUNGGU_MANAGER,
            ]);

            $this->storeBerkas($request, $spr, $validated['berkas'] ?? []);
            $leadStatus->markSpr($spr, MarketingLeadStatusService::SPR);
        });

        return back()->with('success', 'SPR berhasil dibuat dan menunggu approval manager.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'costumer_id' => ['required', 'exists:costumers,id'],
            'detail_rumah_id' => ['required', 'exists:detail_rumahs,id'],
            'tanggal_spr' => ['required', 'date'],
            'metode_pembayaran' => ['required', Rule::in(array_column($this->paymentOptions(), 'value'))],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'booking_fee' => ['nullable', 'numeric', 'min:0'],
            'booking_fee_includes_dp' => ['nullable', 'boolean'],
            'tanggal_pembayaran_booking_fee' => ['nullable', 'date'],
            'uang_muka' => ['nullable', 'numeric', 'min:0'],
            'uang_muka_jumlah_pembayaran' => ['nullable', 'integer', 'min:1'],
            'tanggal_jatuh_tempo_dp' => ['nullable', 'date'],
            'nilai_pengajuan_kpr' => ['nullable', 'numeric', 'min:0'],
            'penambahan_tanah' => ['nullable', 'numeric', 'min:0'],
            'harga_penambahan_tanah' => ['nullable', 'numeric', 'min:0'],
            'penambahan_lain_lain' => ['nullable', 'string'],
            'harga_penambahan_lain_lain' => ['nullable', 'numeric', 'min:0'],
            'jumlah_termin' => ['nullable', 'integer', 'min:1'],
            'tanggal_jatuh_tempo_angsuran' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string'],
            'berkas' => ['nullable', 'array'],
            'berkas.*.dokumen_costumer_id' => ['required_with:berkas', 'exists:dokumen_costumers,id'],
            'berkas.*.file_upload' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
            'berkas.*.keterangan' => ['nullable', 'string'],
        ]);

        $this->validateTerminRules($validated);

        $spr = Spr::query()->findOrFail($id);
        $this->abortIfLocked($spr);
        if (! in_array($spr->status, [Spr::STATUS_MENUNGGU_MANAGER, Spr::STATUS_MENUNGGU_OWNER], true)) {
            throw ValidationException::withMessages([
                'status' => 'SPR yang sudah disetujui atau ditolak tidak bisa diubah.',
            ]);
        }
        $this->ensureUnitIsAvailable((int) $validated['detail_rumah_id'], $spr->id);

        DB::transaction(function () use ($request, $spr, $validated) {
            $payload = $this->normalizeSprPayload($validated);

            $spr->update([
                ...$payload,
            ]);

            if (array_key_exists('berkas', $validated)) {
                $this->updateBerkas($request, $spr, $validated['berkas'] ?? []);
            }
        });

        return back()->with('success', 'SPR berhasil diperbarui.');
    }

    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_column($this->statusOptions(), 'value'))],
        ]);

        $spr = Spr::query()->findOrFail($id);
        $this->abortIfLocked($spr);

        $spr->update(['status' => $validated['status']]);
        $this->syncUnitBookingState((int) $spr->detail_rumah_id, $spr->id);

        return back()->with('success', 'Status SPR berhasil diperbarui.');
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $leadStatus = app(MarketingLeadStatusService::class);
        $spr = Spr::query()->findOrFail($id);
        $this->abortIfLocked($spr);
        $user = $request->user();

        if ($spr->status === Spr::STATUS_MENUNGGU_MANAGER) {
            abort_unless($user === null || $user->hasAnyRole(['owner', 'manajer_pimpro']), 403);
            $this->approveLevel($spr, 'manager', Spr::STATUS_MENUNGGU_OWNER, $user?->id);

            return back()->with('success', 'SPR disetujui manager dan menunggu approval owner.');
        }

        if ($spr->status === Spr::STATUS_MENUNGGU_OWNER) {
            abort_unless($user === null || $user->hasRole('owner'), 403);

            DB::transaction(function () use ($spr, $user, $leadStatus) {
                $this->approveLevel($spr, 'owner', Spr::STATUS_DISETUJUI, $user?->id);
                $spr->update([
                    'booking_expires_at' => $spr->booking_expires_at ?? now()->addDays(7),
                ]);
                $this->syncUnitBookingState((int) $spr->detail_rumah_id, $spr->id);
                $leadStatus->markSpr($spr, MarketingLeadStatusService::SPR);
                app(MarketingOperationsService::class)->syncBillingSchedules($spr->fresh(['payments']));

                if ($spr->metode_pembayaran === 'kpr_bank') {
                    $submission = KprSubmission::query()->firstOrCreate(
                        ['spr_id' => $spr->id],
                        [
                            'kode_kpr' => $this->nextKprCode(),
                            'handled_by' => $user?->id,
                            'tanggal_pengajuan' => now()->toDateString(),
                            'nilai_pengajuan' => $spr->nilai_pengajuan_kpr,
                            'status' => 'pengumpulan_dokumen',
                            'catatan' => 'Otomatis dibuat dari SPR yang sudah disetujui owner.',
                        ],
                    );
                    if ($submission->wasRecentlyCreated) {
                        app(MarketingOperationsService::class)->recordKprStage(
                            $submission,
                            'pengumpulan_dokumen',
                            'Pengajuan KPR otomatis dibuat dari SPR yang disetujui owner.',
                            $user?->id,
                        );
                    }
                } elseif ($spr->metode_pembayaran === 'cash') {
                    CashSale::query()->firstOrCreate(
                        ['spr_id' => $spr->id],
                        [
                            'kode_cash' => $this->nextCashCode(),
                            'costumer_id' => $spr->costumer_id,
                            'detail_rumah_id' => $spr->detail_rumah_id,
                            'handled_by' => $user?->id,
                            'tanggal_transaksi' => now()->toDateString(),
                            'harga_rumah' => $spr->harga_jual,
                            'total_tagihan' => $spr->harga_jual,
                            'total_dibayar' => ((float) ($spr->booking_fee ?? 0)) + ((float) ($spr->uang_muka ?? 0)),
                            'sisa_tagihan' => max(0, (float) $spr->harga_jual - (((float) ($spr->booking_fee ?? 0)) + ((float) ($spr->uang_muka ?? 0)))),
                            'status_pembayaran' => (((float) ($spr->booking_fee ?? 0)) + ((float) ($spr->uang_muka ?? 0))) > 0
                                ? ((((float) $spr->harga_jual - (((float) ($spr->booking_fee ?? 0)) + ((float) ($spr->uang_muka ?? 0)))) <= 0)
                                    ? 'lunas'
                                    : 'dp_dibayar')
                                : 'menunggu_pembayaran',
                            'catatan' => 'Otomatis dibuat dari SPR cash yang sudah disetujui owner.',
                        ],
                    );
                }
            });

            return back()->with('success', 'SPR disetujui owner.');
        }

        return back()->with('error', 'SPR tidak berada pada status approval.');
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $leadStatus = app(MarketingLeadStatusService::class);
        $validated = $request->validate([
            'catatan' => ['nullable', 'string'],
        ]);

        $spr = Spr::query()->findOrFail($id);
        $this->abortIfLocked($spr);
        $user = $request->user();
        $level = $spr->status === Spr::STATUS_MENUNGGU_OWNER ? 'owner' : 'manager';

        abort_unless(
            $user === null
            || ($level === 'manager' && $user->hasAnyRole(['owner', 'manajer_pimpro']))
            || ($level === 'owner' && $user->hasRole('owner')),
            403,
        );

        DB::transaction(function () use ($spr, $user, $level, $validated, $leadStatus) {
            SprApproval::create([
                'spr_id' => $spr->id,
                'user_id' => $user?->id,
                'level' => $level,
                'status' => 'ditolak',
                'catatan' => $validated['catatan'] ?? null,
                'approved_at' => now(),
            ]);

            $spr->update(['status' => Spr::STATUS_DITOLAK]);
            $this->syncUnitBookingState((int) $spr->detail_rumah_id, $spr->id);
            $leadStatus->markSpr($spr, MarketingLeadStatusService::BATAL);
        });

        return back()->with('success', 'SPR berhasil ditolak.');
    }

    protected function approveLevel(Spr $spr, string $level, string $nextStatus, ?int $userId): void
    {
        DB::transaction(function () use ($spr, $level, $nextStatus, $userId) {
            SprApproval::create([
                'spr_id' => $spr->id,
                'user_id' => $userId,
                'level' => $level,
                'status' => 'disetujui',
                'approved_at' => now(),
            ]);

            $spr->update(['status' => $nextStatus]);
        });
    }

    protected function formPayload(?Spr $spr = null): array
    {
        $row = $spr ? $this->row($spr) : [
            'costumer_id' => '',
            'detail_rumah_id' => '',
            'tanggal_spr' => now()->toDateString(),
            'metode_key' => 'kpr_bank',
            'metode_pembayaran' => $this->labelFromOptions('kpr_bank', $this->paymentOptions()),
            'harga_jual' => 0,
            'booking_fee' => 0,
            'booking_fee_includes_dp' => false,
            'tanggal_pembayaran_booking_fee' => '',
            'uang_muka' => 0,
            'uang_muka_jumlah_pembayaran' => '',
            'tanggal_jatuh_tempo_dp' => '',
            'nilai_pengajuan_kpr' => 0,
            'penambahan_tanah' => '',
            'harga_penambahan_tanah' => 0,
            'penambahan_lain_lain' => '',
            'harga_penambahan_lain_lain' => 0,
            'total_penambahan_tanah' => 0,
            'total_penambahan_lain_lain' => 0,
            'total_penambahan' => 0,
            'nilai_pengajuan_akhir' => 0,
            'jumlah_termin' => '',
            'nominal_termin' => '',
            'tanggal_jatuh_tempo_angsuran' => '',
            'status' => Spr::STATUS_MENUNGGU_MANAGER,
            'status_label' => $this->labelFromOptions(Spr::STATUS_MENUNGGU_MANAGER, $this->statusOptions()),
            'catatan' => '',
            'record_status' => 'draft',
            'record_status_label' => 'Draft',
            'berkas' => [],
        ];

        return [
            'title' => 'SPR',
            'description' => 'Buat Surat Pemesanan Rumah dan proses approval manager serta owner sebelum masuk KPR.',
            'baseUrl' => route('admin.marketing.spr.index', absolute: false),
            'row' => $row,
            'customers' => $this->customerOptions(),
            'units' => $this->unitOptions(),
            'dokumenOptions' => $this->dokumenOptions(),
            'options' => [
                'paymentOptions' => $this->paymentOptions(),
                'statusOptions' => $this->statusOptions(),
            ],
            'permissions' => [
                'canApproveManager' => auth()->user()?->hasAnyRole(['owner', 'manajer_pimpro']) ?? true,
                'canApproveOwner' => auth()->user()?->hasRole('owner') ?? true,
            ],
        ];
    }

    protected function row(Spr $spr): array
    {
        $unit = $spr->detailRumah
            ? trim(($spr->detailRumah->kode_nlok ?? '').' '.($spr->detailRumah->nomor_rumah ?? ''))
            : '-';

        return [
            'id' => $spr->id,
            'kode_spr' => $spr->kode_spr,
            'costumer_id' => (string) $spr->costumer_id,
            'detail_rumah_id' => (string) $spr->detail_rumah_id,
            'tanggal_spr' => optional($spr->tanggal_spr)->format('Y-m-d'),
            'customer' => $spr->costumer?->nama ?? '-',
            'no_identitas' => $spr->costumer?->no_identitas ?? '-',
            'unit' => $unit,
            'perumahan' => $spr->detailRumah?->perumahan?->nama_perusahaan ?? '-',
            'metode_key' => $spr->metode_pembayaran,
            'metode_pembayaran' => $this->labelFromOptions($spr->metode_pembayaran, $this->paymentOptions()),
            'harga_jual' => $spr->harga_jual,
            'booking_fee' => $spr->booking_fee,
            'booking_fee_includes_dp' => (bool) ($spr->booking_fee_includes_dp ?? false),
            'tanggal_pembayaran_booking_fee' => optional($spr->tanggal_pembayaran_booking_fee)->format('Y-m-d'),
            'uang_muka' => $spr->uang_muka,
            'uang_muka_jumlah_pembayaran' => $spr->uang_muka_jumlah_pembayaran,
            'tanggal_jatuh_tempo_dp' => optional($spr->tanggal_jatuh_tempo_dp)->format('Y-m-d'),
            'nilai_pengajuan_kpr' => $spr->nilai_pengajuan_kpr,
            'penambahan_tanah' => $spr->penambahan_tanah,
            'harga_penambahan_tanah' => $spr->harga_penambahan_tanah,
            'penambahan_lain_lain' => $spr->penambahan_lain_lain,
            'harga_penambahan_lain_lain' => $spr->harga_penambahan_lain_lain,
            'total_penambahan_tanah' => $spr->total_penambahan_tanah,
            'total_penambahan_lain_lain' => $spr->total_penambahan_lain_lain,
            'total_penambahan' => $spr->total_penambahan,
            'nilai_pengajuan_akhir' => $spr->nilai_pengajuan_akhir,
            'jumlah_termin' => $spr->jumlah_termin,
            'nominal_termin' => $spr->nominal_termin,
            'tanggal_jatuh_tempo_angsuran' => optional($spr->tanggal_jatuh_tempo_angsuran)->format('Y-m-d'),
            'status' => $spr->status,
            'status_label' => $this->labelFromOptions($spr->status, $this->statusOptions()),
            'catatan' => $spr->catatan,
            'created_by' => $spr->creator?->name ?? '-',
            'record_status' => $spr->record_status ?? 'draft',
            'record_status_label' => ($spr->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
            'berkas_count' => $spr->berkasCostumers()->count(),
            'berkas' => $spr->berkasCostumers->map(fn (SprBerkasCostumer $berkas) => [
                'id' => $berkas->id,
                'dokumen_costumer_id' => (string) $berkas->dokumen_costumer_id,
                'dokumen_label' => $berkas->dokumen?->nama_dokumen
                    ? $berkas->dokumen->nama_dokumen.' ('.$berkas->dokumen->kode_dokumen.')'
                    : 'Dokumen',
                'nama_file' => $berkas->nama_file,
                'path_file' => $berkas->path_file,
                'keterangan' => $berkas->keterangan,
            ])->values(),
        ];
    }

    protected function dokumenOptions(): array
    {
        return DokumenCostumer::query()
            ->where('status', 'aktif')
            ->whereIn('kategori_pengajuan', ['spr', 'kpr', 'umum'])
            ->orderBy('nama_dokumen')
            ->get(['id', 'kode_dokumen', 'nama_dokumen', 'kategori_pengajuan'])
            ->map(fn (DokumenCostumer $dokumen) => [
                'value' => (string) $dokumen->id,
                'label' => $dokumen->nama_dokumen.' ('.$dokumen->kode_dokumen.')',
                'search' => strtolower(trim($dokumen->nama_dokumen.' '.$dokumen->kode_dokumen.' '.$dokumen->kategori_pengajuan)),
            ])
            ->all();
    }

    protected function storeBerkas(Request $request, Spr $spr, array $berkasRows): void
    {
        foreach ($berkasRows as $row) {
            $file = $row['file_upload'] ?? null;
            if (! $file) {
                continue;
            }

            $path = $file->store('spr/berkas', 'public');

            SprBerkasCostumer::create([
                'spr_id' => $spr->id,
                'dokumen_costumer_id' => $row['dokumen_costumer_id'],
                'uploaded_by' => $request->user()?->id,
                'nama_file' => $file->getClientOriginalName(),
                'path_file' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'keterangan' => $row['keterangan'] ?? null,
            ]);
        }
    }

    protected function updateBerkas(Request $request, Spr $spr, array $berkasRows): void
    {
        foreach ($berkasRows as $row) {
            $dokumenId = (int) ($row['dokumen_costumer_id'] ?? 0);
            if ($dokumenId <= 0) {
                continue;
            }

            $existing = $spr->berkasCostumers()->where('dokumen_costumer_id', $dokumenId)->first();
            $file = $row['file_upload'] ?? null;

            if ($file) {
                if ($existing?->path_file) {
                    Storage::disk('public')->delete($existing->path_file);
                }

                $path = $file->store('spr/berkas', 'public');

                if ($existing) {
                    $existing->update([
                        'uploaded_by' => $request->user()?->id,
                        'nama_file' => $file->getClientOriginalName(),
                        'path_file' => $path,
                        'mime_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'keterangan' => $row['keterangan'] ?? null,
                    ]);
                } else {
                    SprBerkasCostumer::create([
                        'spr_id' => $spr->id,
                        'dokumen_costumer_id' => $dokumenId,
                        'uploaded_by' => $request->user()?->id,
                        'nama_file' => $file->getClientOriginalName(),
                        'path_file' => $path,
                        'mime_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'keterangan' => $row['keterangan'] ?? null,
                    ]);
                }

                continue;
            }

            if ($existing) {
                $existing->update([
                    'keterangan' => $row['keterangan'] ?? $existing->keterangan,
                ]);
            }
        }
    }

    protected function customerOptions(): array
    {
        return Costumer::query()
            ->select(['id', 'nama', 'no_identitas', 'telepon'])
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (Costumer $costumer) => [
                'id' => $costumer->id,
                'label' => $costumer->nama.' - '.($costumer->no_identitas ?? '-'),
                'search' => strtolower($costumer->nama.' '.$costumer->no_identitas.' '.$costumer->telepon),
                'nama' => $costumer->nama,
                'no_identitas' => $costumer->no_identitas,
                'telepon' => $costumer->telepon,
            ])
            ->all();
    }

    protected function unitOptions(): array
    {
        return DetailRumah::query()
            ->with(['perumahan:id,nama_perusahaan'])
            ->withCount([
                'sprs as active_spr_count' => fn (Builder $query) => $query->whereIn('status', $this->activeSprStatuses),
            ])
            ->select(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah', 'tipe_rumah', 'harga_jual', 'status'])
            ->orderBy('kode_nlok')
            ->orderBy('nomor_rumah')
            ->limit(500)
            ->get()
            ->map(fn (DetailRumah $rumah) => [
                'id' => $rumah->id,
                'label' => trim(($rumah->kode_nlok ?? '').' '.($rumah->nomor_rumah ?? '')).' - '.($rumah->perumahan?->nama_perusahaan ?? '-'),
                'harga_jual' => (float) ($rumah->harga_jual ?? 0),
                'perumahan' => $rumah->perumahan?->nama_perusahaan,
                'kode_nlok' => $rumah->kode_nlok,
                'nomor_rumah' => $rumah->nomor_rumah,
                'tipe_rumah' => $rumah->tipe_rumah,
                'luas_tanah' => $rumah->luas_tanah,
                'luas_bangunan' => $rumah->luas_bangunan,
                'status_penjualan' => $rumah->status_penjualan ?? '-',
                'status_pembangunan' => $rumah->status_pembangunan ?? '-',
                'status' => $rumah->status,
                'is_available' => (int) ($rumah->active_spr_count ?? 0) === 0,
                'availability_label' => (int) ($rumah->active_spr_count ?? 0) === 0 ? 'Tersedia' : 'Sudah Ada SPR Aktif',
                'search' => strtolower(trim(($rumah->kode_nlok ?? '').' '.($rumah->nomor_rumah ?? '').' '.($rumah->perumahan?->nama_perusahaan ?? '').' '.($rumah->status ?? '').' '.(((int) ($rumah->active_spr_count ?? 0) === 0) ? 'tersedia' : 'spr aktif'))),
            ])
            ->all();
    }

    protected function paymentOptions(): array
    {
        return [
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'bertahap', 'label' => 'Bertahap'],
            ['value' => 'kpr_bank', 'label' => 'KPR Bank'],
        ];
    }

    protected function syncUnitBookingState(int $detailRumahId, ?int $bookingSprId = null): void
    {
        $unit = DetailRumah::query()->find($detailRumahId);

        if (! $unit) {
            return;
        }

        $approvedSpr = Spr::query()
            ->where('detail_rumah_id', $detailRumahId)
            ->where('status', Spr::STATUS_DISETUJUI)
            ->latest('id')
            ->first();

        if ($approvedSpr) {
            $unit->update([
                'status_penjualan' => 'booking',
                'booking_spr_id' => $bookingSprId ?? $approvedSpr->id,
                'booking_at' => $unit->booking_at ?? now(),
            ]);

            return;
        }

        if ($unit->status_penjualan === 'booking') {
            $unit->update([
                'status_penjualan' => 'tersedia',
                'booking_spr_id' => null,
                'booking_at' => null,
            ]);
        }
    }

    protected function syncUnitSaleState(int $detailRumahId, string $statusPenjualan): void
    {
        $unit = DetailRumah::query()->find($detailRumahId);

        if (! $unit) {
            return;
        }

        $unit->update([
            'status_penjualan' => $statusPenjualan,
        ]);
    }

    protected function validateTerminRules(array $validated): void
    {
        if (($validated['metode_pembayaran'] ?? null) !== 'bertahap') {
            return;
        }

        if (empty($validated['jumlah_termin']) || empty($validated['uang_muka_jumlah_pembayaran'])) {
            throw ValidationException::withMessages([
                'jumlah_termin' => 'Metode Bertahap wajib memiliki jumlah termin dan jumlah pembayaran uang muka.',
            ]);
        }

        if (empty($validated['tanggal_jatuh_tempo_angsuran'])) {
            throw ValidationException::withMessages([
                'tanggal_jatuh_tempo_angsuran' => 'Metode Bertahap wajib memiliki tanggal jatuh tempo angsuran.',
            ]);
        }

    }

    protected function normalizeSprPayload(array $validated): array
    {
        $bookingFee = (float) ($validated['booking_fee'] ?? 0);
        $bookingFeeIncludesDp = (bool) ($validated['booking_fee_includes_dp'] ?? false);
        $tanggalPembayaranBookingFee = filled($validated['tanggal_pembayaran_booking_fee'] ?? null) ? $validated['tanggal_pembayaran_booking_fee'] : null;
        $uangMuka = (float) ($validated['uang_muka'] ?? 0);
        $uangMukaJumlahPembayaran = isset($validated['uang_muka_jumlah_pembayaran']) && $validated['uang_muka_jumlah_pembayaran'] !== '' ? (int) $validated['uang_muka_jumlah_pembayaran'] : null;
        $tanggalJatuhTempoDp = filled($validated['tanggal_jatuh_tempo_dp'] ?? null) ? $validated['tanggal_jatuh_tempo_dp'] : null;
        $nilaiPengajuanKpr = (float) ($validated['nilai_pengajuan_kpr'] ?? 0);
        $luasPenambahanTanah = (float) ($validated['penambahan_tanah'] ?? 0);
        $hargaPenambahanTanah = (float) ($validated['harga_penambahan_tanah'] ?? 0);
        $hargaPenambahanLain = (float) ($validated['harga_penambahan_lain_lain'] ?? 0);
        $tanggalJatuhTempoAngsuran = filled($validated['tanggal_jatuh_tempo_angsuran'] ?? null) ? $validated['tanggal_jatuh_tempo_angsuran'] : null;
        $totalPenambahanTanah = $luasPenambahanTanah * $hargaPenambahanTanah;
        $totalPenambahanLain = $hargaPenambahanLain;
        $totalPenambahan = $totalPenambahanTanah + $totalPenambahanLain;
        $nilaiPengajuanAkhir = $nilaiPengajuanKpr + $totalPenambahan;
        $jumlahTermin = isset($validated['jumlah_termin']) && $validated['jumlah_termin'] !== '' ? max(1, (int) $validated['jumlah_termin']) : null;
        $nominalTermin = $jumlahTermin ? round($nilaiPengajuanAkhir / $jumlahTermin) : null;

        return [
            'costumer_id' => $validated['costumer_id'],
            'detail_rumah_id' => $validated['detail_rumah_id'],
            'tanggal_spr' => $validated['tanggal_spr'],
            'metode_pembayaran' => $validated['metode_pembayaran'],
            'harga_jual' => (float) ($validated['harga_jual'] ?? 0),
            'booking_fee' => $bookingFee,
            'booking_fee_includes_dp' => $bookingFeeIncludesDp,
            'tanggal_pembayaran_booking_fee' => $tanggalPembayaranBookingFee,
            'uang_muka' => $uangMuka,
            'uang_muka_jumlah_pembayaran' => $uangMukaJumlahPembayaran,
            'tanggal_jatuh_tempo_dp' => $tanggalJatuhTempoDp,
            'nilai_pengajuan_kpr' => $nilaiPengajuanKpr,
            'penambahan_tanah' => $luasPenambahanTanah,
            'harga_penambahan_tanah' => $hargaPenambahanTanah,
            'penambahan_lain_lain' => $validated['penambahan_lain_lain'] ?? null,
            'harga_penambahan_lain_lain' => $hargaPenambahanLain,
            'total_penambahan_tanah' => $totalPenambahanTanah,
            'total_penambahan_lain_lain' => $totalPenambahanLain,
            'total_penambahan' => $totalPenambahan,
            'nilai_pengajuan_akhir' => $nilaiPengajuanAkhir,
            'jumlah_termin' => $jumlahTermin,
            'nominal_termin' => $nominalTermin,
            'tanggal_jatuh_tempo_termin' => $tanggalJatuhTempoAngsuran,
            'tanggal_jatuh_tempo_angsuran' => $tanggalJatuhTempoAngsuran,
            'catatan' => $validated['catatan'] ?? null,
        ];
    }

    protected function statusOptions(): array
    {
        return [
            ['value' => Spr::STATUS_MENUNGGU_MANAGER, 'label' => 'Menunggu Manager'],
            ['value' => Spr::STATUS_MENUNGGU_OWNER, 'label' => 'Menunggu Owner'],
            ['value' => Spr::STATUS_DISETUJUI, 'label' => 'Disetujui'],
            ['value' => Spr::STATUS_DITOLAK, 'label' => 'Ditolak'],
        ];
    }

    protected function labelFromOptions(?string $value, array $options): string
    {
        foreach ($options as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value ?? '-';
    }

    protected function nextSprCode(): string
    {
        return 'SPR-'.now()->format('ymd').'-'.str_pad((string) ((Spr::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    protected function nextKprCode(): string
    {
        return 'KPR-'.now()->format('ymd').'-'.str_pad((string) ((KprSubmission::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    protected function modelClass(): string
    {
        return Spr::class;
    }

    protected function nextCashCode(): string
    {
        $next = (int) (CashSale::withTrashed()->max('id') ?? 0) + 1;

        return 'CASH-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    protected function ensureUnitIsAvailable(int $detailRumahId, ?int $ignoreSprId = null): void
    {
        $reserved = Spr::query()
            ->where('detail_rumah_id', $detailRumahId)
            ->whereIn('status', $this->activeSprStatuses)
            ->when($ignoreSprId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreSprId))
            ->exists();

        if ($reserved) {
            throw ValidationException::withMessages([
                'detail_rumah_id' => 'Unit ini sudah memiliki SPR aktif, silakan pilih unit lain.',
            ]);
        }
    }
}
