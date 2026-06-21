<?php

namespace App\Http\Controllers\Admin\Kpr;

use App\Http\Controllers\Controller;
use App\Models\KprFollowUp;
use App\Models\KprMilestone;
use App\Models\KprMilestoneDocument;
use App\Models\KprSubmission;
use App\Services\Marketing\MarketingLeadStatusService;
use App\Services\Marketing\MarketingOperationsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KprMilestoneController extends Controller
{
    public function index(Request $request, string $type): Response
    {
        $type = $this->validatedType($type);
        $search = trim((string) $request->query('search', ''));

        $rows = KprSubmission::query()
            ->with([
                'spr.costumer:id,nama,no_identitas,telepon',
                'spr.detailRumah.perumahan:id,nama_perusahaan',
                'bank:id,nama_bank',
                'milestones' => fn ($query) => $query->where('jenis', $type)->with(['documents', 'creator:id,name', 'updater:id,name', 'locker:id,name']),
            ])
            ->whereNotIn('status', ['ditolak'])
            ->when($type === KprMilestone::AKAD, fn (Builder $query) => $query->whereIn('status', ['sp3k_keluar', 'akad', 'menunggu_serah_terima', 'serah_terima_selesai']))
            ->when($type === KprMilestone::SERAH_TERIMA, fn (Builder $query) => $query->whereHas('milestones', fn (Builder $query) => $query->where('jenis', KprMilestone::AKAD)))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('kode_kpr', 'like', "%{$search}%")
                        ->orWhereHas('spr', fn (Builder $query) => $query->where('kode_spr', 'like', "%{$search}%"))
                        ->orWhereHas('spr.costumer', fn (Builder $query) => $query
                            ->where('nama', 'like', "%{$search}%")
                            ->orWhere('no_identitas', 'like', "%{$search}%"))
                        ->orWhereHas('spr.detailRumah', fn (Builder $query) => $query
                            ->where('kode_nlok', 'like', "%{$search}%")
                            ->orWhere('nomor_rumah', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (KprSubmission $submission) => $this->row($submission, $type));

        return Inertia::render('Admin/Kpr/Milestone/Index', [
            'title' => $type === KprMilestone::AKAD ? 'Akad KPR' : 'Serah Terima Unit',
            'description' => $type === KprMilestone::AKAD
                ? 'Catat jadwal dan dokumentasi akad kredit customer.'
                : 'Catat penyerahan unit, kunci, dan dokumentasi kepada customer.',
            'type' => $type,
            'baseUrl' => route('admin.kpr.milestone.index', $type, absolute: false),
            'filters' => ['search' => $search],
            'rows' => $rows,
        ]);
    }

    public function store(Request $request, string $type, string $submissionId): RedirectResponse
    {
        $type = $this->validatedType($type);
        $submission = KprSubmission::query()->with('spr.detailRumah')->findOrFail($submissionId);
        abort_if($type === KprMilestone::SERAH_TERIMA && ! $submission->milestones()->where('jenis', KprMilestone::AKAD)->exists(), 422, 'Akad harus dicatat sebelum serah terima.');
        abort_if($submission->milestones()->where('jenis', $type)->exists(), 422, 'Data proses ini sudah pernah dibuat.');

        $validated = $this->validated($request);

        DB::transaction(function () use ($request, $submission, $type, $validated): void {
            $milestone = KprMilestone::create([
                ...$validated,
                'kpr_submission_id' => $submission->id,
                'jenis' => $type,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);
            $this->storeDocuments($request, $milestone);
            $this->syncKpr($submission, $type, $validated['catatan'] ?? null, $request->user()?->id);
        });

        return back()->with('success', ($type === KprMilestone::AKAD ? 'Akad' : 'Serah terima').' berhasil dicatat.');
    }

    public function update(Request $request, string $type, string $id): RedirectResponse
    {
        $type = $this->validatedType($type);
        $milestone = KprMilestone::query()->where('jenis', $type)->findOrFail($id);
        abort_if($milestone->record_status === 'locked', 422, 'Data yang sudah di-lock tidak dapat diedit.');
        $validated = $this->validated($request);

        DB::transaction(function () use ($request, $milestone, $validated): void {
            $milestone->update([
                ...$validated,
                'updated_by' => $request->user()?->id,
            ]);
            $this->storeDocuments($request, $milestone);
        });

        return back()->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(string $type, string $id): RedirectResponse
    {
        $type = $this->validatedType($type);
        $milestone = KprMilestone::query()->with(['submission.spr.detailRumah', 'documents'])->where('jenis', $type)->findOrFail($id);
        abort_if($milestone->record_status === 'locked', 422, 'Data yang sudah di-lock tidak dapat dihapus.');
        abort_if(
            $type === KprMilestone::AKAD
            && $milestone->submission->milestones()->where('jenis', KprMilestone::SERAH_TERIMA)->exists(),
            422,
            'Akad tidak dapat dihapus karena serah terima sudah tercatat.',
        );

        DB::transaction(function () use ($milestone, $type): void {
            foreach ($milestone->documents as $document) {
                Storage::disk('public')->delete($document->path_file);
                $document->delete();
            }
            $submission = $milestone->submission;
            $milestone->forceDelete();

            if ($type === KprMilestone::SERAH_TERIMA) {
                $submission->update(['status' => 'akad']);
            } else {
                $submission->update(['status' => 'sp3k_keluar']);
                $submission->spr?->detailRumah?->update(['status_penjualan' => 'proses_penjualan']);
            }
        });

        return back()->with('success', 'Data berhasil dihapus.');
    }

    public function destroyDocument(string $type, string $id, string $documentId): RedirectResponse
    {
        $type = $this->validatedType($type);
        $milestone = KprMilestone::query()->where('jenis', $type)->findOrFail($id);
        abort_if($milestone->record_status === 'locked', 422, 'Dokumen pada data locked tidak dapat dihapus.');
        $document = KprMilestoneDocument::query()->where('kpr_milestone_id', $milestone->id)->findOrFail($documentId);
        Storage::disk('public')->delete($document->path_file);
        $document->delete();

        return back()->with('success', 'Dokumentasi berhasil dihapus.');
    }

    public function lock(string $type, string $id): RedirectResponse
    {
        $type = $this->validatedType($type);
        KprMilestone::query()->where('jenis', $type)->findOrFail($id)->update([
            'record_status' => 'locked',
            'locked_at' => now(),
            'locked_by' => auth()->id(),
        ]);

        return back()->with('success', 'Data berhasil di-lock.');
    }

    public function unlock(string $type, string $id): RedirectResponse
    {
        $type = $this->validatedType($type);
        $user = request()->user();
        abort_unless($user === null || $user->hasAnyRole(['owner', 'super_admin']), 403, 'Hanya owner yang dapat membuka lock.');
        KprMilestone::query()->where('jenis', $type)->findOrFail($id)->update([
            'record_status' => 'draft',
            'locked_at' => null,
            'locked_by' => null,
        ]);

        return back()->with('success', 'Lock berhasil dibuka.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'tanggal_proses' => ['required', 'date'],
            'lokasi' => ['required', 'string', 'max:255'],
            'nomor_dokumen' => ['nullable', 'string', 'max:255'],
            'pihak_terkait' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
            'dokumen' => ['nullable', 'array'],
            'dokumen.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
        ]);
    }

    protected function storeDocuments(Request $request, KprMilestone $milestone): void
    {
        foreach ($request->file('dokumen', []) as $file) {
            $path = $file->store('kpr/milestones/'.$milestone->jenis, 'public');
            KprMilestoneDocument::create([
                'kpr_milestone_id' => $milestone->id,
                'uploaded_by' => $request->user()?->id,
                'nama_file' => $file->getClientOriginalName(),
                'path_file' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    protected function syncKpr(KprSubmission $submission, string $type, ?string $note, ?int $userId): void
    {
        $status = $type === KprMilestone::AKAD ? 'akad' : 'serah_terima_selesai';
        $label = $type === KprMilestone::AKAD ? 'Akad kredit telah dilaksanakan.' : 'Serah terima unit telah dilaksanakan.';
        $submission->update(['status' => $status, 'handled_by' => $userId]);
        $submission->spr?->detailRumah?->update(['status_penjualan' => 'terjual']);

        KprFollowUp::create([
            'kpr_submission_id' => $submission->id,
            'user_id' => $userId,
            'tanggal_follow_up' => now()->toDateString(),
            'metode_follow_up' => 'proses_sistem',
            'status_kpr' => $status,
            'hasil_follow_up' => $label,
            'tindak_lanjut' => $type === KprMilestone::AKAD ? 'Persiapan serah terima unit.' : 'Proses KPR selesai.',
            'catatan' => $note,
        ]);

        app(MarketingOperationsService::class)->recordKprStage($submission, $status, $label.' '.($note ?? ''), $userId);
        app(MarketingLeadStatusService::class)->markSpr($submission->spr, MarketingLeadStatusService::CLOSING, $label);
    }

    protected function row(KprSubmission $submission, string $type): array
    {
        $milestone = $submission->milestones->first();
        $unit = $submission->spr?->detailRumah;

        return [
            'id' => $submission->id,
            'kode_kpr' => $submission->kode_kpr,
            'kode_spr' => $submission->spr?->kode_spr ?? '-',
            'customer' => $submission->spr?->costumer?->nama ?? '-',
            'no_identitas' => $submission->spr?->costumer?->no_identitas ?? '-',
            'telepon' => $submission->spr?->costumer?->telepon ?? '-',
            'unit' => $unit ? trim(($unit->kode_nlok ?? '').' '.($unit->nomor_rumah ?? '')) : '-',
            'perumahan' => $unit?->perumahan?->nama_perusahaan ?? '-',
            'bank' => $submission->bank?->nama_bank ?? '-',
            'status_kpr' => $submission->status,
            'milestone' => $milestone ? [
                'id' => $milestone->id,
                'tanggal_proses' => optional($milestone->tanggal_proses)->format('Y-m-d\TH:i'),
                'tanggal_label' => optional($milestone->tanggal_proses)->format('d/m/Y H:i'),
                'lokasi' => $milestone->lokasi,
                'nomor_dokumen' => $milestone->nomor_dokumen,
                'pihak_terkait' => $milestone->pihak_terkait,
                'catatan' => $milestone->catatan,
                'record_status' => $milestone->record_status,
                'created_by' => $milestone->creator?->name ?? '-',
                'updated_by' => $milestone->updater?->name ?? '-',
                'locked_by' => $milestone->locker?->name ?? '-',
                'documents' => $milestone->documents->map(fn (KprMilestoneDocument $document) => [
                    'id' => $document->id,
                    'nama_file' => $document->nama_file,
                    'url' => route('media', ['path' => $document->path_file], false),
                ])->values(),
            ] : null,
            'can_create' => $milestone === null,
            'type' => $type,
        ];
    }

    protected function validatedType(string $type): string
    {
        abort_unless(in_array($type, [KprMilestone::AKAD, KprMilestone::SERAH_TERIMA], true), 404);

        return $type;
    }
}
