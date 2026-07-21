<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\SalesProcessDocument;
use App\Models\SalesProcessStep;
use App\Services\ApprovalWorkflowService;
use App\Services\CustomerDocumentRequirementService;
use App\Services\FixedSalesDocumentService;
use App\Services\SalesProcessService;
use App\Support\SalesProcessDefinitions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesProcessController extends Controller
{
    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission) || $request->user()?->hasRole('super_admin'), 403);
    }

    public function workspace(Request $request, SalesProcessStep $step): Response
    {
        $this->allow($request, 'sales-process.view');
        $step->loadMissing('salesTransaction.housingUnit');
        app(SalesProcessService::class)->initialize($step->salesTransaction);
        $step->refresh();
        $step = app(SalesProcessService::class)->syncContext($step);
        $step->load(['salesTransaction.spr.berkasCostumers.dokumen', 'salesTransaction.spr.bankKredit', 'salesTransaction.spr.bankBranch', 'salesTransaction.spr.bankCreditProduct', 'salesTransaction.spr.cashInstallmentScheme', 'salesTransaction.spr.developerKprProduct', 'salesTransaction.customer', 'salesTransaction.housingProject', 'salesTransaction.housingUnit', 'checklistItems', 'documents', 'assignee', 'locker', 'completer']);
        $definition = SalesProcessDefinitions::get($step->code);
        $approval = ApprovalRequest::with(['requester', 'reviewer'])->where(['model_type' => $step::class, 'model_id' => $step->id])->latest()->first();
        $human = fn ($value) => str((string) $value)->replace('_', ' ')->title()->toString();
        $users = DB::table('users')->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['value' => (string) $u->id, 'label' => $u->name])->values();
        $userFields = ['validator', 'officer', 'inspector', 'site_manager', 'site_supervisor', 'developer_officer', 'developer_representative'];
        $fields = collect($definition['fields'])->map(function ($field) use ($userFields, $users) {
            if (in_array($field['name'], $userFields, true)) {
                return [...$field, 'type' => 'select', 'options' => $users->mapWithKeys(fn ($user) => [$user['label'] => $user['label']])->all()];
            }

            return $field;
        })->values();
        $documentRequirements = $step->salesTransaction?->spr
            ? app(CustomerDocumentRequirementService::class)->forStage($step)
            : collect();
        if ($documentRequirements->isNotEmpty()) {
            $legacyDocumentKeys = ['identity', 'family_card', 'marriage_document', 'tax_number', 'income_document', 'bank_statement', 'spouse_document', 'product_requirements'];
            $step->checklistItems()->whereIn('item_key', $legacyDocumentKeys)->delete();
            $activeRequirementKeys = $documentRequirements->pluck('requirement_item_id')->map(fn ($id) => 'document_requirement_'.$id);
            $step->checklistItems()->where('item_key', 'like', 'document_requirement_%')->whereNotIn('item_key', $activeRequirementKeys)->delete();
            foreach ($documentRequirements as $requirement) {
                $step->checklistItems()->updateOrCreate(
                    ['item_key' => 'document_requirement_'.$requirement['requirement_item_id']],
                    ['label' => $requirement['label'], 'is_required' => $requirement['required']]
                );
            }
            $step->load('checklistItems');
        }
        $printTypes = match ($step->code) {
            'contract_signing' => in_array($step->salesTransaction?->payment_method, ['cash_bertahap', 'kpr_developer'], true)
                ? ['ppjb', 'signing_minutes']
                : ['ppjb'],
            'customer_handover' => ['handover'],
            default => [],
        };
        $printableDocuments = collect(app(FixedSalesDocumentService::class)->catalog())
            ->whereIn('document_type', $printTypes)
            ->map(fn ($document) => [...$document, 'url' => route('admin.document-templates.spr.print', [$step->salesTransaction->spr_id, $document['id']], absolute: false)])
            ->values();
        $transaction = [
            'number' => $step->salesTransaction?->transaction_no,
            'spr' => $step->salesTransaction?->spr?->kode_spr,
            'customer' => $step->salesTransaction?->customer?->nama,
            'identity' => $step->salesTransaction?->customer?->no_identitas,
            'phone' => $step->salesTransaction?->customer?->telepon,
            'housing' => $step->salesTransaction?->housingProject?->nama_perusahaan,
            'unit' => $step->salesTransaction?->housingUnit?->display_label,
            'method' => $human($step->salesTransaction?->payment_method),
            'method_summary' => [
                'bank' => $step->salesTransaction?->spr?->bankKredit?->nama_bank,
                'branch' => $step->salesTransaction?->spr?->bankBranch?->branch_name,
                'product' => $step->salesTransaction?->spr?->bankCreditProduct?->product_name,
                'cash_scheme' => $step->salesTransaction?->spr?->cashInstallmentScheme?->name,
                'developer_product' => $step->salesTransaction?->spr?->developerKprProduct?->name,
                'tenor' => $step->salesTransaction?->spr?->kpr_tenor_bulan ?: $step->salesTransaction?->spr?->jumlah_termin,
                'financing' => 'Rp '.number_format((float) ($step->salesTransaction?->spr?->nilai_pengajuan_kpr ?: $step->salesTransaction?->sale_price_snapshot), 0, ',', '.'),
            ],
            'price' => 'Rp '.number_format((float) $step->salesTransaction?->sale_price_snapshot, 0, ',', '.'),
        ];
        $payload = [
            'id' => $step->id, 'sequence' => $step->sequence, 'label' => $step->label,
            'category' => $human($step->category), 'description' => $step->description,
            'assigned_to' => (string) ($step->assigned_to ?? ''), 'assignee' => $step->assignee?->name, 'assignees' => $users,
            'planned_date' => $step->planned_date?->format('Y-m-d'), 'actual_date' => $step->actual_date?->format('Y-m-d'),
            'status' => $step->status, 'status_label' => $human($step->status), 'notes' => $step->notes,
            'metadata' => $step->metadata['data'] ?? [], 'sources' => $step->metadata['sources'] ?? [], 'fields' => $fields,
            'document_types' => $definition['documents'],
            'checklist' => $step->checklistItems->map(fn ($item) => ['key' => $item->item_key, 'label' => $item->label, 'required' => $item->is_required, 'completed' => $item->is_completed]),
            'documents' => $step->documents->map(fn ($doc) => ['id' => $doc->id, 'label' => collect($definition['documents'])->firstWhere('type', $doc->document_type)['label'] ?? $doc->document_type, 'number' => $doc->document_number, 'date' => $doc->document_date?->format('d/m/Y'), 'expires' => $doc->expires_at?->format('d/m/Y'), 'name' => $doc->original_name, 'url' => route('admin.sales-process.document.show', [$step, $doc], absolute: false), 'delete_url' => route('admin.sales-process.document.destroy', [$step, $doc], absolute: false)]),
            'printable_documents' => $printableDocuments,
            'approval_stage' => $approval?->status === 'pending' ? "Tahap {$approval->current_step}/{$approval->total_steps}" : null,
            'record_status' => $step->record_status,
            'locked_at' => $step->locked_at?->format('d/m/Y H:i'),
            'locked_by' => $step->locker?->name,
            'completed_by' => $step->completer?->name,
            'can_skip' => $request->user()?->can('sales-process.lock') && $step->record_status === 'draft' && in_array($step->status, ['available', 'in_progress'], true) && in_array($step->code, ['construction_preparation', 'construction', 'quality_inspection'], true),
            'approval' => $approval ? [
                'status' => $human($approval->status),
                'current_step' => $approval->current_step,
                'total_steps' => $approval->total_steps,
                'requested_by' => $approval->requester?->name,
                'reviewed_by' => $approval->reviewer?->name,
                'reviewed_at' => $approval->reviewed_at?->format('d/m/Y H:i'),
                'note' => $approval->rejection_note ?: $approval->note,
                'history' => collect($approval->step_history ?? [])->map(fn ($history) => [
                    'step' => $history['step'] ?? null,
                    'decision' => $human($history['decision'] ?? '-'),
                    'user' => $history['user_name'] ?? null,
                    'date' => isset($history['decided_at']) ? date('d/m/Y H:i', strtotime($history['decided_at'])) : null,
                    'note' => $history['note'] ?? null,
                ])->values(),
            ] : null,
            'can_edit' => $request->user()?->can('sales-process.update') && $step->record_status === 'draft' && in_array($step->status, ['available', 'in_progress'], true),
            'can_lock' => $request->user()?->can('sales-process.lock') && $step->record_status === 'draft' && in_array($step->status, ['available', 'in_progress'], true),
            'can_unlock' => $request->user()?->can('sales-process.unlock') && $step->record_status === 'locked' && $step->status !== 'completed',
            'can_review' => $approval?->status === ApprovalRequest::STATUS_PENDING
                && app(ApprovalWorkflowService::class)->canReview($approval),
        ];

        return Inertia::render('Admin/IntegratedSales/ProcessForm', ['title' => $step->label, 'backUrl' => route('admin.integrated-sales.show', ['transactions', $step->sales_transaction_id], absolute: false), 'transaction' => $transaction, 'step' => $payload]);
    }

    public function update(Request $request, SalesProcessStep $step): RedirectResponse
    {
        $this->allow($request, 'sales-process.update');
        abort_unless($step->record_status === 'draft' && in_array($step->status, ['available', 'in_progress'], true), 422, 'Tahap belum tersedia atau sudah difinalisasi.');
        abort_unless(app(SalesProcessService::class)->dependenciesMet($step), 422, 'Prasyarat tahap belum selesai.');
        $data = $request->validate(['assigned_to' => 'nullable|exists:users,id', 'planned_date' => 'nullable|date', 'actual_date' => 'nullable|date', 'outcome' => 'nullable|string|max:100', 'notes' => 'nullable|string|max:5000', 'metadata' => 'nullable|array', 'checklist' => 'nullable|array', 'checklist.*' => 'boolean', 'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240', 'finalize' => 'nullable|boolean']);
        $finalize = (bool) ($data['finalize'] ?? false);
        if ($finalize) {
            $this->allow($request, 'sales-process.lock');
        }
        $step = app(SalesProcessService::class)->syncContext($step);
        $definition = SalesProcessDefinitions::get($step->code);
        $allowed = collect($definition['fields'])->pluck('name');
        $metadata = collect($data['metadata'] ?? [])->only($allowed)->all();
        foreach (($step->metadata['sources'] ?? []) as $key => $source) {
            if (array_key_exists($key, $step->metadata['data'] ?? [])) {
                $metadata[$key] = $step->metadata['data'][$key];
            }
        }
        $checklist = $data['checklist'] ?? null;
        unset($data['attachment'],$data['checklist'],$data['metadata'],$data['finalize']);
        $data['outcome'] = $metadata['decision'] ?? $metadata['result'] ?? $metadata['payment_condition'] ?? $data['outcome'] ?? null;
        $data['metadata'] = ['data' => $metadata, 'dependencies' => $step->metadata['dependencies'] ?? SalesProcessDefinitions::dependencies($step->code, $step->salesTransaction->payment_method), 'sources' => $step->metadata['sources'] ?? []];
        if (is_array($checklist)) {
            foreach ($step->checklistItems as $item) {
                $done = (bool) ($checklist[$item->item_key] ?? false);
                $item->update(['is_completed' => $done, 'completed_by' => $done ? $request->user()?->id : null, 'completed_at' => $done ? now() : null]);
            }
        }
        if ($request->hasFile('attachment')) {
            if ($step->attachment_path) {
                Storage::disk('public')->delete($step->attachment_path);
            }$data['attachment_path'] = $request->file('attachment')->store('sales-process/'.now()->format('Y/m'), 'public');
            $data['attachment_original_name'] = $request->file('attachment')->getClientOriginalName();
        }
        $step->update([...$data, 'started_at' => $step->started_at ?: now(), 'status' => 'in_progress', 'updated_by' => $request->user()?->id]);

        if ($finalize) {
            $approval = $this->finalizeStep($step, $request, app(ApprovalWorkflowService::class), is_array($checklist) ? $checklist : null);

            return back()->with('success', $approval->status === 'approved' ? 'Tahap disetujui dan tahap berikutnya dibuka.' : "Tahap diajukan ke approval {$approval->current_step}/{$approval->total_steps}.");
        }

        return back()->with('success', 'Data pelaksanaan tahap disimpan.');
    }

    public function updateChecklist(Request $request, SalesProcessStep $step): RedirectResponse
    {
        $this->allow($request, 'sales-process.update');
        abort_unless($step->record_status === 'draft' && in_array($step->status, ['available', 'in_progress'], true), 422, 'Tahap belum tersedia atau sudah difinalisasi.');
        abort_unless(app(SalesProcessService::class)->dependenciesMet($step), 422, 'Prasyarat tahap belum selesai.');
        $data = $request->validate(['checklist' => 'required|array', 'checklist.*' => 'boolean']);

        foreach ($step->checklistItems as $item) {
            $done = (bool) ($data['checklist'][$item->item_key] ?? false);
            $item->update([
                'is_completed' => $done,
                'completed_by' => $done ? $request->user()?->id : null,
                'completed_at' => $done ? now() : null,
            ]);
        }

        $step->update([
            'started_at' => $step->started_at ?: now(),
            'status' => 'in_progress',
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Checklist tahap disimpan.');
    }

    public function lock(Request $request, SalesProcessStep $step, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->allow($request, 'sales-process.lock');
        $approval = $this->finalizeStep($step, $request, $workflow);

        return back()->with('success', $approval->status === 'approved' ? 'Tahap disetujui dan tahap berikutnya dibuka.' : "Tahap diajukan ke approval {$approval->current_step}/{$approval->total_steps}.");
    }

    public function skip(Request $request, SalesProcessStep $step): RedirectResponse
    {
        $this->allow($request, 'sales-process.lock');
        abort_unless($step->record_status === 'draft' && in_array($step->status, ['available', 'in_progress'], true), 422, 'Tahap belum tersedia atau sudah difinalisasi.');
        abort_unless(in_array($step->code, ['construction_preparation', 'construction', 'quality_inspection'], true), 422, 'Tahap ini tidak bisa dilewati.');
        $step = app(SalesProcessService::class)->syncContext($step)->load(['salesTransaction.housingUnit', 'checklistItems', 'documents']);
        $reason = match ($step->code) {
            'construction_preparation', 'construction' => $step->metadata['skip_reason'] ?? 'Unit sudah siap sehingga tahap pembangunan dilewati.',
            'quality_inspection' => $step->metadata['skip_reason'] ?? 'Inspeksi mutu sudah final sehingga tahap ini dilewati.',
            default => 'Tahap dilewati.',
        };
        abort_if($step->code !== 'quality_inspection' && empty($step->metadata['skip_reason']) && ! ($step->salesTransaction?->housingUnit?->status_pembangunan === 'selesai' || (float) $step->salesTransaction?->housingUnit?->progress_terakhir >= 100), 422, 'Tahap ini belum memenuhi syarat untuk dilewati.');
        abort_if($step->code === 'quality_inspection' && empty($step->metadata['skip_reason']), 422, 'Inspeksi mutu belum final sehingga belum bisa dilewati.');

        DB::transaction(function () use ($step, $request, $reason) {
            $locked = SalesProcessStep::with(['salesTransaction.processSteps'])->lockForUpdate()->findOrFail($step->id);
            abort_if($locked->record_status !== 'draft' || ! in_array($locked->status, ['available', 'in_progress'], true), 422, 'Tahap belum tersedia atau sudah difinalisasi.');
            $locked->update([
                'status' => 'skipped',
                'actual_date' => $locked->actual_date ?: now(),
                'updated_by' => $request->user()?->id,
                'metadata' => [
                    ...($locked->metadata ?? []),
                    'skip_reason' => $reason,
                    'data' => [
                        ...($locked->metadata['data'] ?? []),
                        'skip_reason' => $reason,
                    ],
                ],
            ]);

            app(SalesProcessService::class)->syncUnitState($locked->salesTransaction->fresh());
            $locked->salesTransaction->fresh(['processSteps'])->processSteps
                ->where('status', 'waiting')
                ->each(fn ($candidate) => app(SalesProcessService::class)->dependenciesMet($candidate) && $candidate->update(['status' => 'available']));
        });

        return back()->with('success', 'Tahap dilewati dan proses berikutnya dibuka.');
    }

    public function updateDocumentChecklist(Request $request, SalesProcessStep $step): RedirectResponse
    {
        $this->allow($request, 'sales-process.update');
        abort_unless($step->record_status === 'draft', 422, 'Tahap sudah dikunci.');
        $data = $request->validate(['requirement_item_id' => 'required|exists:document_requirement_set_items,id', 'is_complete' => 'required|boolean', 'notes' => 'nullable|string|max:1000']);
        DB::table('sales_stage_document_checklists')->updateOrInsert(
            ['sales_process_step_id' => $step->id, 'document_requirement_set_item_id' => $data['requirement_item_id']],
            ['is_complete' => $data['is_complete'], 'notes' => $data['notes'] ?? null, 'checked_by' => $request->user()->id, 'checked_at' => $data['is_complete'] ? now() : null, 'created_at' => now(), 'updated_at' => now()]
        );

        return back()->with('success', 'Checklist kelengkapan dokumen diperbarui.');
    }

    public function unlock(Request $request, SalesProcessStep $step, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->allow($request, 'sales-process.unlock');
        abort_if($step->status === 'completed', 422, 'Tahap sudah disetujui final.');
        $workflow->cancelPendingLock($step);
        $step->update(['record_status' => 'draft', 'status' => 'in_progress', 'locked_at' => null, 'locked_by' => null]);

        return back()->with('success', 'Tahap kembali menjadi draf.');
    }

    public function review(Request $request, SalesProcessStep $step, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $approval = ApprovalRequest::where(['model_type' => SalesProcessStep::class, 'model_id' => $step->id, 'status' => 'pending'])->latest()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403);
        $decision === 'approve' ? $workflow->approve($approval) : $workflow->reject($approval, $request->validate(['note' => 'required|string|max:1000'])['note']);

        return back()->with('success', 'Approval tahapan berhasil diproses.');
    }

    public function attachment(Request $request, SalesProcessStep $step): StreamedResponse
    {
        $this->allow($request, 'sales-process.view');
        abort_unless($step->attachment_path && Storage::disk('public')->exists($step->attachment_path), 404);

        return Storage::disk('public')->download($step->attachment_path, $step->attachment_original_name);
    }

    public function storeDocument(Request $request, SalesProcessStep $step): RedirectResponse
    {
        $this->allow($request, 'sales-process.update');
        abort_unless($step->record_status === 'draft', 422);
        $allowed = collect(SalesProcessDefinitions::get($step->code)['documents'])->pluck('type')->all();
        $data = $request->validate(['document_type' => 'required|string', 'document_number' => 'nullable|string|max:150', 'document_date' => 'nullable|date', 'expires_at' => 'nullable|date|after_or_equal:document_date', 'notes' => 'nullable|string|max:2000', 'file' => 'required|file|extensions:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:15360']);
        abort_unless(in_array($data['document_type'], $allowed, true), 422, 'Jenis dokumen tidak sesuai tahap.');
        $path = $request->file('file')->store('sales-process-documents/'.now()->format('Y/m'), 'public');
        $step->documents()->create([...collect($data)->except('file')->all(), 'file_path' => $path, 'original_name' => $request->file('file')->getClientOriginalName(), 'uploaded_by' => $request->user()?->id]);

        return back()->with('success', 'Dokumen proses ditambahkan.');
    }

    public function destroyDocument(Request $request, SalesProcessStep $step, SalesProcessDocument $document): RedirectResponse
    {
        $this->allow($request, 'sales-process.update');
        abort_unless($document->sales_process_step_id === $step->id && $step->record_status === 'draft', 403);
        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Dokumen dihapus.');
    }

    public function document(Request $request, SalesProcessStep $step, SalesProcessDocument $document): StreamedResponse
    {
        $this->allow($request, 'sales-process.view');
        abort_unless($document->sales_process_step_id === $step->id && Storage::disk('public')->exists($document->file_path), 404);

        return Storage::disk('public')->download($document->file_path, $document->original_name);
    }

    private function validateCompletion(SalesProcessStep $step, ?array $checklistOverride = null): void
    {
        $checklist = $checklistOverride ?? $step->checklistItems
            ->mapWithKeys(fn ($item) => [$item->item_key => (bool) $item->is_completed])
            ->all();

        $this->validateCompletionData(
            $step,
            $step->metadata['data'] ?? [],
            $checklist,
            $step->actual_date,
            $step->notes,
        );
    }

    private function validateCompletionData(SalesProcessStep $step, array $data, array $checklist, mixed $actualDate, mixed $notes): void
    {
        $definition = SalesProcessDefinitions::get($step->code);
        $errors = [];
        foreach ($definition['fields'] as $field) {
            $value = $data[$field['name']] ?? null;
            if ($field['required'] && $this->completionValueMissing($value)) {
                $errors['metadata.'.$field['name']] = $field['label'].' wajib diisi.';
                continue;
            }
            if ($this->completionValueMissing($value)) {
                continue;
            }
            if ($field['type'] === 'date' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
                $errors['metadata.'.$field['name']] = $field['label'].' harus berupa tanggal yang valid.';
            }
            if ($field['type'] === 'datetime' && ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/', (string) $value)) {
                $errors['metadata.'.$field['name']] = $field['label'].' harus berupa tanggal dan jam yang valid.';
            }
            if (in_array($field['type'], ['number', 'currency'], true) && ! is_numeric($value)) {
                $errors['metadata.'.$field['name']] = $field['label'].' harus berupa angka yang valid.';
            }
        }
        foreach ($step->checklistItems as $item) {
            if ($item->is_required && ! (bool) ($checklist[$item->item_key] ?? false)) {
                $errors['checklist.'.$item->item_key] = 'Checklist "'.$item->label.'" wajib dicentang.';
            }
        }
        $present = $step->documents->pluck('document_type');
        // Tahap pemeriksaan kelengkapan hanya memvalidasi checklist dari master
        // persyaratan. Scan dokumen customer tetap berada di repository dan tidak
        // boleh menjadi syarat upload ulang ketika tahap difinalisasi.
        $missingDocs = in_array($step->code, ['document_validation', 'document_collection'], true)
            ? []
            : collect($definition['documents'])->filter(fn ($doc) => $doc['required'] && ! $present->contains($doc['type']));
        foreach ($missingDocs as $document) {
            $errors['documents.'.$document['type']] = 'Dokumen "'.$document['label'].'" wajib diunggah.';
        }
        if ($this->completionValueMissing($actualDate)) {
            $errors['actual_date'] = 'Tanggal realisasi tahap wajib diisi.';
        }
        if ($this->completionValueMissing($notes)) {
            $errors['notes'] = 'Catatan pelaksanaan tahap wajib diisi.';
        }
        if ($errors) {
            $errors = ['completion' => 'Data belum dapat disimpan karena bagian wajib masih belum lengkap.', ...$errors];
            throw ValidationException::withMessages($errors);
        }
    }

    private function completionValueMissing(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function finalizeStep(SalesProcessStep $step, Request $request, ApprovalWorkflowService $workflow, ?array $checklistOverride = null): ApprovalRequest
    {
        abort_unless($step->record_status === 'draft' && in_array($step->status, ['available', 'in_progress'], true), 422);
        $step = $step->fresh(['checklistItems', 'documents', 'salesTransaction.paymentSchedules', 'salesTransaction.housingUnit']);
        $this->validateCompletion($step, $checklistOverride);
        $this->validateDomain($step);
        $step->update(['record_status' => 'locked', 'status' => 'pending_approval', 'locked_at' => now(), 'locked_by' => $request->user()?->id]);

        return $workflow->submitLocked($step, 'sales-process-step');
    }

    private function validateDomain(SalesProcessStep $step): void
    {
        $data = $step->metadata['data'] ?? [];
        if (in_array($step->outcome, ['rejected', 'failed', 'not_eligible', 'invalid'], true)) {
            throw ValidationException::withMessages(['outcome' => 'Hasil proses belum memungkinkan tahap diteruskan. Gunakan penolakan/revisi atau perubahan skema/bank.']);
        }if ($step->code === 'sp3k' && ! empty($data['expires_at']) && now()->startOfDay()->gt($data['expires_at'])) {
            throw ValidationException::withMessages(['expires_at' => 'SP3K sudah kedaluwarsa.']);
        }if (in_array($step->code, ['quality_inspection', 'internal_handover', 'customer_handover'], true)) {
            $open = DB::table('field_defects')->where('detail_rumah_id', $step->salesTransaction->detail_rumah_id)->whereNull('deleted_at')->whereIn('prioritas', ['high', 'urgent'])->whereNotIn('status', ['selesai', 'closed', 'resolved'])->count();
            if ($open) {
                throw ValidationException::withMessages(['defects' => "Masih ada {$open} defect mayor/kritis yang belum selesai."]);
            }
        }if ($step->code === 'quality_inspection' && (float) DB::table('detail_rumahs')->where('id', $step->salesTransaction->detail_rumah_id)->value('progress_terakhir') < 100) {
            throw ValidationException::withMessages(['construction' => 'Progress pembangunan unit belum 100%.']);
        }if ($step->code === 'completed') {
            $remaining = $step->salesTransaction->paymentSchedules->sum(fn ($row) => max(0, (float) $row->amount - (float) $row->paid_amount));
            if ($remaining > 0) {
                throw ValidationException::withMessages(['receivable' => 'Masih ada piutang customer Rp '.number_format($remaining, 0, ',', '.').'.']);
            }
        }
    }
}
