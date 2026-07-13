<?php

namespace App\Http\Controllers\Admin\Kpr;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Models\BankKredit;
use App\Models\BerkasCostumer;
use App\Models\DokumenCostumer;
use App\Models\KprFollowUp;
use App\Models\KprSubmission;
use App\Services\Marketing\MarketingLeadStatusService;
use App\Services\Marketing\MarketingOperationsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KprSubmissionController extends Controller
{
    use HandlesCrudLock, ScopesActivePerumahan;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = KprSubmission::query()
            ->with(['spr.costumer:id,nama,no_identitas,telepon', 'spr.detailRumah.perumahan:id,nama_perusahaan', 'bank:id,nama_bank', 'handler:id,name'])
            ->withCount(['followUps', 'berkasCostumers'])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('spr', fn (Builder $query) => $query->where('created_by', $request->user()?->id)))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode_kpr', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('spr.costumer', fn (Builder $query) => $query
                            ->where('nama', 'like', "%{$search}%")
                            ->orWhere('no_identitas', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (KprSubmission $submission) => $this->row($submission));

        return Inertia::render('Admin/Kpr/Index', [
            'title' => 'Pengajuan KPR',
            'description' => 'Proses data KPR Bank dari SPR yang sudah disetujui owner.',
            'baseUrl' => route('admin.kpr.index', absolute: false),
            'rows' => $rows,
            'filters' => ['search' => $search],
            'banks' => $this->bankOptions(),
            'options' => [
                'statusOptions' => $this->statusOptions(),
                'methodOptions' => $this->methodOptions(),
            ],
        ]);
    }

    public function show(string $id): Response
    {
        $submission = KprSubmission::query()
            ->with([
                'spr.costumer',
                'spr.detailRumah.perumahan',
                'spr.berkasCostumers.dokumen:id,nama_dokumen,kode_dokumen',
                'spr.berkasCostumers.uploader:id,name',
                'bank:id,nama_bank,kode_bank',
                'handler:id,name',
                'followUps.user:id,name',
                'followUps.lockedBy:id,name',
                'berkasCostumers.dokumen:id,nama_dokumen,kode_dokumen',
                'berkasCostumers.uploader:id,name',
                'stageHistories.user:id,name',
                'milestones.documents',
                'milestones.creator:id,name',
            ])
            ->withCount(['followUps', 'berkasCostumers'])
            ->when($this->shouldScopeToCurrentMarketing(request()), fn (Builder $query) => $query->whereHas('spr', fn (Builder $query) => $query->where('created_by', request()->user()?->id)))
            ->when($this->shouldScopeToActivePerumahan(request()), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, request())))
            ->findOrFail($id);

        if ($submission->stageHistories->isEmpty()) {
            app(MarketingOperationsService::class)->recordKprStage(
                $submission,
                $submission->status,
                'Status awal KPR sebelum timeline diaktifkan.',
                $submission->handled_by,
            );
            $submission->load('stageHistories.user:id,name');
        }

        return Inertia::render('Admin/Kpr/Detail', [
            'title' => 'Detail KPR',
            'description' => 'Pantau follow up KPR dan upload berkas customer pada halaman ini.',
            'baseUrl' => route('admin.kpr.index', absolute: false),
            'submission' => $this->detailPayload($submission),
            'options' => [
                'statusOptions' => $this->statusOptions(),
                'methodOptions' => $this->methodOptions(),
            ],
            'dokumenOptions' => $this->dokumenOptions(),
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $submission = $this->submissionQueryFor($request)->findOrFail($id);
        $this->abortIfLocked($submission);

        return Inertia::render('Admin/Kpr/FormPage', [
            'title' => 'Edit Pengajuan KPR '.$submission->kode_kpr,
            'baseUrl' => route('admin.kpr.index', absolute: false),
            'actionUrl' => route('admin.kpr.update', $submission->id, false),
            'row' => [
                'bank_kredit_id' => (string) ($submission->bank_kredit_id ?? ''),
                'tanggal_pengajuan' => optional($submission->tanggal_pengajuan)->format('Y-m-d'),
                'nilai_pengajuan' => (string) ($submission->nilai_pengajuan ?? ''),
                'status' => $submission->status,
                'catatan' => $submission->catatan ?? '',
            ],
            'banks' => $this->bankOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'bank_kredit_id' => ['required', 'exists:bank_kredits,id'],
            'tanggal_pengajuan' => ['nullable', 'date'],
            'nilai_pengajuan' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_column($this->statusOptions(), 'value'))],
            'catatan' => ['nullable', 'string'],
        ]);

        $row = $this->submissionQueryFor($request)->findOrFail($id);
        $this->abortIfLocked($row);
        $row->update([
            ...$validated,
            'handled_by' => $request->user()?->id,
        ]);

        $this->syncUnitSaleState($row, $validated['status']);
        app(MarketingOperationsService::class)->recordKprStage(
            $row,
            $validated['status'],
            $validated['catatan'] ?? null,
            $request->user()?->id,
        );

        return to_route('admin.kpr.index')->with('success', 'Data KPR berhasil diperbarui.');
    }

    public function storeFollowUp(Request $request, string $id): RedirectResponse
    {
        $submission = $this->submissionQueryFor($request)->findOrFail($id);

        $validated = $request->validate([
            'tanggal_follow_up' => ['required', 'date'],
            'metode_follow_up' => ['required', Rule::in(array_column($this->methodOptions(), 'value'))],
            'status_kpr' => ['required', Rule::in(array_column($this->statusOptions(), 'value'))],
            'hasil_follow_up' => ['nullable', 'string'],
            'kendala' => ['nullable', 'string'],
            'tindak_lanjut' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'rencana_follow_up_at' => ['nullable', 'date'],
        ]);

        KprFollowUp::create([
            ...$validated,
            'kpr_submission_id' => $submission->id,
            'user_id' => $request->user()?->id,
        ]);

        $submission->update([
            'status' => $validated['status_kpr'],
            'handled_by' => $request->user()?->id,
        ]);

        $this->syncUnitSaleState($submission, $validated['status_kpr']);
        app(MarketingOperationsService::class)->recordKprStage(
            $submission,
            $validated['status_kpr'],
            $validated['hasil_follow_up'] ?? $validated['catatan'] ?? null,
            $request->user()?->id,
        );

        return back()->with('success', 'Follow up KPR berhasil ditambahkan.');
    }

    public function updateFollowUp(Request $request, string $id, string $followUpId): RedirectResponse
    {
        $submission = $this->submissionQueryFor($request)->findOrFail($id);
        $followUp = KprFollowUp::query()
            ->where('kpr_submission_id', $submission->id)
            ->findOrFail($followUpId);

        $this->abortIfLocked($followUp);

        $validated = $request->validate([
            'tanggal_follow_up' => ['required', 'date'],
            'metode_follow_up' => ['required', Rule::in(array_column($this->methodOptions(), 'value'))],
            'status_kpr' => ['required', Rule::in(array_column($this->statusOptions(), 'value'))],
            'hasil_follow_up' => ['nullable', 'string'],
            'kendala' => ['nullable', 'string'],
            'tindak_lanjut' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'rencana_follow_up_at' => ['nullable', 'date'],
        ]);

        $followUp->update([
            ...$validated,
            'user_id' => $request->user()?->id,
        ]);

        $submission->update([
            'status' => $validated['status_kpr'],
            'handled_by' => $request->user()?->id,
        ]);

        $this->syncUnitSaleState($submission, $validated['status_kpr']);
        app(MarketingOperationsService::class)->recordKprStage(
            $submission,
            $validated['status_kpr'],
            $validated['hasil_follow_up'] ?? $validated['catatan'] ?? null,
            $request->user()?->id,
        );

        return back()->with('success', 'Follow up KPR berhasil diperbarui.');
    }

    public function destroyFollowUp(Request $request, string $id, string $followUpId): RedirectResponse
    {
        $submission = $this->submissionQueryFor($request)->findOrFail($id);
        $followUp = KprFollowUp::query()
            ->where('kpr_submission_id', $submission->id)
            ->findOrFail($followUpId);

        $this->abortIfLocked($followUp);
        $followUp->delete();

        return back()->with('success', 'Follow up KPR berhasil dihapus.');
    }

    public function lockFollowUp(Request $request, string $id, string $followUpId): RedirectResponse
    {
        $submission = $this->submissionQueryFor($request)->findOrFail($id);
        $followUp = KprFollowUp::query()
            ->where('kpr_submission_id', $submission->id)
            ->findOrFail($followUpId);

        $followUp->forceFill([
            'record_status' => 'locked',
            'locked_at' => now(),
            'locked_by' => auth()->id(),
        ])->save();

        return back()->with('success', 'Follow up berhasil di-lock.');
    }

    public function unlockFollowUp(Request $request, string $id, string $followUpId): RedirectResponse
    {
        abort_unless($this->currentUserCanManageLockedRecords(), 403, 'Hanya user yang diberi akses yang dapat membuka lock data.');

        $submission = $this->submissionQueryFor($request)->findOrFail($id);
        $followUp = KprFollowUp::query()
            ->where('kpr_submission_id', $submission->id)
            ->findOrFail($followUpId);

        $followUp->forceFill([
            'record_status' => 'draft',
            'locked_at' => null,
            'locked_by' => null,
        ])->save();

        return back()->with('success', 'Lock follow up berhasil dibuka.');
    }

    public function storeBerkas(Request $request, string $id): RedirectResponse
    {
        $submission = $this->submissionQueryFor($request)->findOrFail($id);
        $this->abortIfLocked($submission);

        $validated = $request->validate([
            'berkas' => ['required', 'array', 'min:1'],
            'berkas.*.dokumen_costumer_id' => ['required', 'exists:dokumen_costumers,id'],
            'berkas.*.file_upload' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
            'berkas.*.keterangan' => ['nullable', 'string'],
        ]);

        foreach ($validated['berkas'] as $item) {
            $file = $item['file_upload'];
            $path = $file->store('kpr/berkas', 'public');

            BerkasCostumer::create([
                'kpr_submission_id' => $submission->id,
                'dokumen_costumer_id' => $item['dokumen_costumer_id'],
                'uploaded_by' => $request->user()?->id,
                'nama_file' => $file->getClientOriginalName(),
                'path_file' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'keterangan' => $item['keterangan'] ?? null,
            ]);
        }

        return back()->with('success', 'Berkas customer berhasil diupload.');
    }

    public function destroyBerkas(Request $request, string $id, string $berkasId): RedirectResponse
    {
        $submission = $this->submissionQueryFor($request)->findOrFail($id);
        $this->abortIfLocked($submission);

        $berkas = BerkasCostumer::query()
            ->where('kpr_submission_id', $submission->id)
            ->findOrFail($berkasId);

        $this->abortIfLocked($berkas);
        Storage::disk('public')->delete($berkas->path_file);
        $berkas->delete();

        return back()->with('success', 'Berkas customer berhasil dihapus.');
    }

    protected function row(KprSubmission $submission): array
    {
        $unit = $submission->spr?->detailRumah
            ? trim(($submission->spr->detailRumah->kode_nlok ?? '').' '.($submission->spr->detailRumah->nomor_rumah ?? ''))
            : '-';

        return [
            'id' => $submission->id,
            'kode_kpr' => $submission->kode_kpr,
            'kode_spr' => $submission->spr?->kode_spr ?? '-',
            'customer' => $submission->spr?->costumer?->nama ?? '-',
            'no_identitas' => $submission->spr?->costumer?->no_identitas ?? '-',
            'telepon' => $submission->spr?->costumer?->telepon ?? '-',
            'unit' => $unit,
            'perumahan' => $submission->spr?->detailRumah?->perumahan?->nama_perusahaan ?? '-',
            'detail_url' => route('admin.kpr.show', $submission->id, absolute: false),
            'edit_url' => route('admin.kpr.edit', $submission->id, false),
            'bank_kredit_id' => $submission->bank_kredit_id,
            'bank' => $submission->bank?->nama_bank ?? '-',
            'tanggal_pengajuan' => optional($submission->tanggal_pengajuan)->format('Y-m-d'),
            'nilai_pengajuan' => $submission->nilai_pengajuan,
            'status' => $submission->status,
            'status_label' => $this->labelFromOptions($submission->status, $this->statusOptions()),
            'catatan' => $submission->catatan,
            'handled_by' => $submission->handler?->name ?? '-',
            'created_at' => optional($submission->created_at)->format('d/m/Y H:i'),
            'updated_at' => optional($submission->updated_at)->format('d/m/Y H:i'),
            'follow_ups_count' => $submission->follow_ups_count,
            'berkas_costumers_count' => $submission->berkas_costumers_count,
            'record_status' => $submission->record_status ?? 'draft',
            'record_status_label' => ($submission->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
        ];
    }

    protected function detailPayload(KprSubmission $submission): array
    {
        $sprBerkas = collect($submission->spr?->berkasCostumers ?? [])->sortByDesc('id')->values();

        return [
            ...$this->row($submission),
            'nilai_pengajuan' => $submission->nilai_pengajuan,
            'catatan' => $submission->catatan,
            'follow_ups' => $submission->followUps->sortByDesc('tanggal_follow_up')->values()->map(fn (KprFollowUp $followUp) => [
                'id' => $followUp->id,
                'tanggal_follow_up' => optional($followUp->tanggal_follow_up)->format('Y-m-d'),
                'metode_follow_up_key' => $followUp->metode_follow_up,
                'metode_follow_up' => $this->labelFromOptions($followUp->metode_follow_up, $this->methodOptions()),
                'status_kpr' => $followUp->status_kpr,
                'status_label' => $this->labelFromOptions($followUp->status_kpr, $this->statusOptions()),
                'hasil_follow_up' => $followUp->hasil_follow_up,
                'kendala' => $followUp->kendala,
                'tindak_lanjut' => $followUp->tindak_lanjut,
                'catatan' => $followUp->catatan,
                'rencana_follow_up_at' => optional($followUp->rencana_follow_up_at)->format('Y-m-d'),
                'user' => $followUp->user?->name ?? '-',
                'record_status' => $followUp->record_status ?? 'draft',
                'record_status_label' => ($followUp->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
                'locked_at' => optional($followUp->locked_at)->format('d/m/Y H:i'),
                'locked_by' => $followUp->lockedBy?->name ?? '-',
            ]),
            'berkas_costumers' => $submission->berkasCostumers->sortByDesc('id')->values()->map(fn (BerkasCostumer $berkas) => [
                'id' => $berkas->id,
                'dokumen_costumer_id' => $berkas->dokumen_costumer_id,
                'nama_dokumen' => $berkas->dokumen?->nama_dokumen ?? '-',
                'kode_dokumen' => $berkas->dokumen?->kode_dokumen ?? '-',
                'nama_file' => $berkas->nama_file,
                'path_file' => Storage::disk('public')->url($berkas->path_file),
                'mime_type' => $berkas->mime_type,
                'file_size' => $berkas->file_size,
                'keterangan' => $berkas->keterangan,
                'record_status' => $berkas->record_status ?? 'draft',
                'record_status_label' => ($berkas->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
                'uploaded_by' => $berkas->uploader?->name ?? '-',
                'created_at' => optional($berkas->created_at)->format('d/m/Y H:i'),
            ]),
            'spr_berkas_costumers' => $sprBerkas->map(fn ($berkas) => [
                'id' => $berkas->id,
                'dokumen_costumer_id' => $berkas->dokumen_costumer_id,
                'nama_dokumen' => $berkas->dokumen?->nama_dokumen ?? '-',
                'kode_dokumen' => $berkas->dokumen?->kode_dokumen ?? '-',
                'nama_file' => $berkas->nama_file,
                'path_file' => Storage::disk('public')->url($berkas->path_file),
                'mime_type' => $berkas->mime_type,
                'file_size' => $berkas->file_size,
                'keterangan' => $berkas->keterangan,
                'uploaded_by' => $berkas->uploader?->name ?? '-',
                'created_at' => optional($berkas->created_at)->format('d/m/Y H:i'),
            ])->values(),
            'stage_histories' => $submission->stageHistories->sortByDesc('tanggal_status')->values()->map(fn ($history) => [
                'id' => $history->id,
                'tahapan' => $history->tahapan,
                'status' => $history->status,
                'status_label' => $this->labelFromOptions($history->status, $this->statusOptions()),
                'tanggal_status' => optional($history->tanggal_status)->format('d/m/Y H:i'),
                'catatan' => $history->catatan,
                'user' => $history->user?->name ?? '-',
            ]),
            'milestones' => $submission->milestones->sortBy('tanggal_proses')->values()->map(fn ($milestone) => [
                'id' => $milestone->id,
                'jenis' => $milestone->jenis,
                'jenis_label' => $milestone->jenis === 'akad' ? 'Akad KPR' : 'Serah Terima Unit',
                'tanggal' => optional($milestone->tanggal_proses)->format('d/m/Y H:i'),
                'lokasi' => $milestone->lokasi,
                'nomor_dokumen' => $milestone->nomor_dokumen,
                'pihak_terkait' => $milestone->pihak_terkait,
                'catatan' => $milestone->catatan,
                'created_by' => $milestone->creator?->name ?? '-',
                'documents' => $milestone->documents->map(fn ($document) => [
                    'id' => $document->id,
                    'nama_file' => $document->nama_file,
                    'url' => route('media', ['path' => $document->path_file], false),
                ])->values(),
            ]),
        ];
    }

    protected function bankOptions(): array
    {
        return BankKredit::query()
            ->where('status', 'aktif')
            ->orderBy('nama_bank')
            ->get(['id', 'nama_bank', 'kode_bank'])
            ->map(fn (BankKredit $bank) => [
                'value' => (string) $bank->id,
                'label' => $bank->nama_bank.' ('.$bank->kode_bank.')',
            ])
            ->all();
    }

    protected function dokumenOptions(): array
    {
        return DokumenCostumer::query()
            ->where('status', 'aktif')
            ->whereIn('kategori_pengajuan', ['umum', 'spr', 'kpr'])
            ->orderBy('nama_dokumen')
            ->get(['id', 'kode_dokumen', 'nama_dokumen', 'kategori_pengajuan', 'wajib'])
            ->map(fn (DokumenCostumer $dokumen) => [
                'value' => (string) $dokumen->id,
                'label' => $dokumen->nama_dokumen.' ('.$dokumen->kode_dokumen.')',
                'search' => strtolower(trim($dokumen->nama_dokumen.' '.$dokumen->kode_dokumen.' '.$dokumen->kategori_pengajuan)),
            ])
            ->all();
    }

    protected function statusOptions(): array
    {
        return [
            ['value' => 'pengumpulan_dokumen', 'label' => 'Pengumpulan Dokumen'],
            ['value' => 'submit_bank', 'label' => 'Submit ke Bank'],
            ['value' => 'survey_bank', 'label' => 'Survey Bank'],
            ['value' => 'analisa_bank', 'label' => 'Analisa Bank'],
            ['value' => 'sp3k_keluar', 'label' => 'SP3K Keluar'],
            ['value' => 'akad', 'label' => 'Akad'],
            ['value' => 'menunggu_serah_terima', 'label' => 'Menunggu Serah Terima'],
            ['value' => 'serah_terima_selesai', 'label' => 'Serah Terima Selesai'],
            ['value' => 'ditolak', 'label' => 'Ditolak'],
            ['value' => 'revisi_dokumen', 'label' => 'Revisi Dokumen'],
        ];
    }

    protected function methodOptions(): array
    {
        return [
            ['value' => 'chat', 'label' => 'Chat'],
            ['value' => 'telephone', 'label' => 'Telephone'],
            ['value' => 'kunjungan_bank', 'label' => 'Kunjungan Bank'],
            ['value' => 'kunjungan_customer', 'label' => 'Kunjungan Customer'],
            ['value' => 'proses_sistem', 'label' => 'Proses Sistem'],
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

    protected function modelClass(): string
    {
        return KprSubmission::class;
    }

    protected function shouldScopeToCurrentMarketing(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing'])
            && ! $user->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin']);
    }

    protected function submissionQueryFor(Request $request): Builder
    {
        return KprSubmission::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('spr', fn (Builder $query) => $query->where('created_by', $request->user()?->id)))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)));
    }

    protected function syncUnitSaleState(KprSubmission $submission, ?string $status): void
    {
        $unit = $submission->spr?->detailRumah;
        app(MarketingLeadStatusService::class)->markSpr(
            $submission->spr,
            $status === 'ditolak' ? MarketingLeadStatusService::BATAL : MarketingLeadStatusService::CLOSING
        );

        if (! $unit) {
            return;
        }

        if ($status === 'serah_terima_selesai') {
            $unit->update([
                'status_penjualan' => 'terjual',
            ]);

            return;
        }

        if ($status && $status !== 'ditolak' && $status !== 'serah_terima_selesai') {
            $unit->update([
                'status_penjualan' => 'proses_penjualan',
            ]);
        }
    }
}
