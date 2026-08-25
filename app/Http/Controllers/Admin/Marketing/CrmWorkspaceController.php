<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\Costumer;
use App\Models\CustomerDocument;
use App\Models\CustomerDocumentChecklist;
use App\Models\DokumenCostumer;
use App\Models\MarketingActionPlan;
use App\Models\MarketingReferenceOption;
use App\Models\MarketingReminder;
use App\Models\MarketingVisit;
use App\Models\Perumahan;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\CustomerDocumentRequirementService;
use App\Services\Marketing\MarketingActivityService;
use App\Services\Marketing\MarketingLeadStatusService;
use App\Support\CodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class CrmWorkspaceController extends Controller
{
    public function index(Request $request, string $resource): Response
    {
        $config = $this->config($resource);
        $this->authorizeAction($request, $config, 'view');
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $from = $request->query('date_from');
        $to = $request->query('date_to');
        $modelClass = $config['model'];

        $rows = $this->scopeForUser($modelClass::query(), $request, $config)
            ->with($config['with'])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search, $config): void {
                foreach ($config['search'] as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
                $query->orWhereHas('costumer', fn (Builder $customer) => $customer->where('nama', 'like', "%{$search}%")->orWhere('kode_costumer', 'like', "%{$search}%"));
            }))
            ->when($status !== '', fn (Builder $query) => $query->where($config['status_column'], $status))
            ->when($from, fn (Builder $query) => $query->whereDate($config['date_column'], '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate($config['date_column'], '<=', $to))
            ->latest($config['date_column'])
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Model $row) => $this->row($row, $request, $config));

        return Inertia::render('Admin/Marketing/CrmWorkspace/Index', [
            'title' => $config['title'],
            'description' => $config['description'],
            'resource' => $resource,
            'baseUrl' => route('admin.marketing.crm.index', $resource, absolute: false),
            'rows' => $rows,
            'filters' => compact('search', 'status', 'from', 'to'),
            'statusOptions' => $config['status_options'],
            'permissions' => $this->permissionMap($request, $config),
        ]);
    }

    public function create(Request $request, string $resource): Response
    {
        $config = $this->config($resource);
        $this->authorizeAction($request, $config, 'create');

        return $this->form($request, $resource, $config, null);
    }

    public function edit(Request $request, string $resource, string $id): Response
    {
        $config = $this->config($resource);
        $this->authorizeAction($request, $config, 'update');
        $row = $this->findForUser($request, $config, $id);
        abort_if($row->record_status === 'locked', 422, 'Data locked harus di-unlock sebelum diedit.');

        return $this->form($request, $resource, $config, $row);
    }

    public function show(Request $request, string $resource, string $id): Response
    {
        $config = $this->config($resource);
        $this->authorizeAction($request, $config, 'view');
        $row = $this->findForUser($request, $config, $id);

        return Inertia::render('Admin/Marketing/CrmWorkspace/Show', [
            'title' => 'Detail '.$config['title'],
            'resource' => $resource,
            'baseUrl' => route('admin.marketing.crm.index', $resource, absolute: false),
            'row' => $this->row($row->load($config['with']), $request, $config),
            'fields' => $config['detail_fields'],
            'contacts' => $resource === 'visits' ? $row->contacts()->with('lead:id,lead_no,name,stage')->latest()->get() : [],
            'contactStoreUrl' => $resource === 'visits' ? route('admin.marketing.field-activities.contacts.store', $row, false) : null,
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $config = $this->config($resource);
        $this->authorizeAction($request, $config, 'create');
        $payload = $this->validated($request, $resource, $config);
        $modelClass = $config['model'];

        DB::transaction(function () use ($request, $resource, $config, $payload, $modelClass): void {
            $row = $modelClass::query()->create([
                ...$payload,
                $config['number_column'] => CodeGenerator::next($modelClass, $config['number_column'], $config['prefix']),
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
                'record_status' => 'draft',
            ]);
            $this->syncCustomerTimeline($row, $resource);
            if ($resource === 'visits') {
                $this->syncVisitNextActionReminder($row);
                $activityType = $row->status === 'completed' ? 'visit_completed' : 'visit_scheduled';
                $activityTitle = $row->status === 'completed' ? 'Aktivitas prospek selesai' : 'Jadwal kunjungan dibuat';
                app(MarketingActivityService::class)->record($row->costumer_id, $activityType, $activityTitle, $row, $row->result ?: $row->objective, ['planned_at' => $row->planned_at, 'location' => $row->location, 'interest_level' => $row->interest_level]);
                if ($row->costumer_id) {
                    app(MarketingLeadStatusService::class)->markCustomer($row->costumer_id, $row->status === 'completed' ? MarketingLeadStatusService::SUDAH_DIKUNJUNGI : MarketingLeadStatusService::JADWAL_KUNJUNGAN, $row::class, $row->id, $activityTitle.'.');
                }
            }
        });

        return redirect()->route('admin.marketing.crm.index', $resource)->with('success', 'Data berhasil dibuat sebagai draft.');
    }

    public function update(Request $request, string $resource, string $id): RedirectResponse
    {
        $config = $this->config($resource);
        $this->authorizeAction($request, $config, 'update');
        $row = $this->findForUser($request, $config, $id);
        abort_if($row->record_status === 'locked', 422, 'Data locked harus di-unlock sebelum diedit.');
        $payload = $this->validated($request, $resource, $config);
        $row->update([...$payload, 'updated_by' => $request->user()?->id]);
        $this->syncCustomerTimeline($row, $resource);
        if ($resource === 'visits') {
            $this->syncVisitNextActionReminder($row);
            app(MarketingActivityService::class)->record($row->costumer_id, 'visit_schedule_updated', 'Jadwal kunjungan diperbarui', $row, $row->objective, ['planned_at' => $row->planned_at, 'status' => $row->status]);
        }

        return redirect()->route('admin.marketing.crm.index', $resource)->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(Request $request, string $resource, string $id): RedirectResponse
    {
        $config = $this->config($resource);
        $this->authorizeAction($request, $config, 'delete');
        $row = $this->findForUser($request, $config, $id);
        abort_if($row->record_status === 'locked', 422, 'Data locked tidak dapat dihapus.');
        if ($resource === 'visits') {
            abort_if(in_array($row->status, ['in_progress', 'completed'], true), 422, 'Kunjungan yang sudah dijalankan tidak dapat dihapus. Batalkan jadwal sebelum check-in bila memang tidak jadi.');
            abort_if(($row->verification_status ?? 'draft') !== 'draft', 422, 'Laporan kunjungan yang sudah diajukan atau diverifikasi tidak dapat dihapus.');
        }
        $row->delete();

        return back()->with('success', 'Data draft berhasil dihapus.');
    }

    public function lock(Request $request, string $resource, string $id, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $config = $this->config($resource);
        $this->authorizeAction($request, $config, 'lock');
        $row = $this->findForUser($request, $config, $id);
        abort_unless($row->record_status === 'draft', 422, 'Data sudah dikunci.');
        abort_unless((int) $row->created_by === (int) $request->user()?->id || $request->user()?->hasRole('super_admin'), 403);
        if ($resource === 'visits') {
            abort_unless($row->status === 'completed' && $row->started_at && $row->finished_at, 422, 'Laporan kunjungan hanya dapat difinalisasi setelah check-in dan check-out selesai.');
            abort_unless($row->check_in_latitude && $row->check_out_latitude && $row->check_in_photo_path && $row->check_out_photo_path, 422, 'GPS dan foto check-in/check-out wajib lengkap sebelum finalisasi.');
        }

        $row->forceFill(['record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $request->user()?->id])->save();
        $approval = $workflow->submitLocked($row, $config['module_key']);

        return back()->with('success', $approval->status === 'approved' ? 'Data dikunci dan disetujui otomatis.' : "Data masuk approval tahap {$approval->current_step}/{$approval->total_steps}.");
    }

    public function unlock(Request $request, string $resource, string $id, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $config = $this->config($resource);
        $this->authorizeAction($request, $config, 'unlock');
        $row = $this->findForUser($request, $config, $id);
        abort_unless($row->record_status === 'locked', 422, 'Data tidak sedang dikunci.');
        $workflow->reverseLockApproval($row);
        $row->forceFill(['record_status' => 'draft', 'locked_at' => null, 'locked_by' => null, 'updated_by' => $request->user()?->id])->save();

        return back()->with('success', 'Data dibuka kembali menjadi draft.');
    }

    public function review(Request $request, string $resource, string $id, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $config = $this->config($resource);
        $row = $this->findForUser($request, $config, $id, false);
        $approval = ApprovalRequest::query()->where('model_type', $row::class)->where('model_id', $row->id)->where('status', 'pending')->latest()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $decision === 'approve' ? $workflow->approve($approval) : $workflow->reject($approval, $validated['note'] ?? null);

        return back()->with('success', $decision === 'approve' ? 'Tahap approval disetujui.' : 'Pengajuan ditolak.');
    }

    public function visitExecutionForm(Request $request, string $id, string $phase): Response
    {
        abort_unless(in_array($phase, ['check-in', 'check-out'], true), 404);
        $config = $this->config('visits');
        $this->authorizeAction($request, $config, 'update');
        $visit = $this->findForUser($request, $config, $id);
        abort_if($visit->record_status === 'locked', 422, 'Kunjungan sudah dikunci.');
        abort_if($phase === 'check-in' && ! in_array($visit->status, ['planned', 'rescheduled'], true), 422, 'Check-in hanya untuk kunjungan yang direncanakan.');
        abort_if($phase === 'check-out' && $visit->status !== 'in_progress', 422, 'Check-out hanya dapat dilakukan setelah check-in.');

        return Inertia::render('Admin/Marketing/CrmWorkspace/VisitExecution', [
            'title' => $phase === 'check-in' ? 'Mulai Kunjungan' : 'Selesaikan Kunjungan',
            'phase' => $phase,
            'visit' => $this->row($visit->load($config['with']), $request, $config),
            'options' => [
                'interestOptions' => $this->referenceOptions('interest_level', ['cold' => 'Dingin', 'warm' => 'Hangat', 'hot' => 'Panas']),
            ],
            'actionUrl' => route('admin.marketing.visit-execution.store', [$visit->id, $phase], absolute: false),
            'backUrl' => route('admin.marketing.crm.show', ['visits', $visit->id], absolute: false),
        ]);
    }

    public function executeVisit(Request $request, string $id, string $phase, MarketingActivityService $activities): RedirectResponse
    {
        abort_unless(in_array($phase, ['check-in', 'check-out'], true), 404);
        $config = $this->config('visits');
        $this->authorizeAction($request, $config, 'update');
        $visit = $this->findForUser($request, $config, $id);
        abort_if($visit->record_status === 'locked', 422, 'Kunjungan sudah dikunci.');
        $location = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:250'],
            'photo' => ['required', 'image', 'max:8192'],
            'device_info' => ['nullable', 'string', 'max:1000'],
            'result' => [Rule::requiredIf($phase === 'check-out'), 'nullable', 'string', 'min:5'],
            'customer_response' => ['nullable', 'string'],
            'objections' => ['nullable', 'string'],
            'interest_level' => ['nullable', Rule::in(array_column($this->referenceOptions('interest_level', ['cold' => 'Dingin', 'warm' => 'Hangat', 'hot' => 'Panas']), 'value'))],
            'next_action' => [Rule::requiredIf($phase === 'check-out'), 'nullable', 'string', 'min:3'],
            'next_action_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        DB::transaction(function () use ($request, $visit, $phase, $location, $activities): void {
            $photo = $request->file('photo')->store('marketing/visit-'.$phase, 'public');
            if ($phase === 'check-in') {
                abort_unless(in_array($visit->status, ['planned', 'rescheduled'], true), 422, 'Kunjungan sudah dimulai atau tidak dapat dimulai.');
                $visit->forceFill(['started_at' => now(), 'status' => 'in_progress', 'check_in_latitude' => $location['latitude'], 'check_in_longitude' => $location['longitude'], 'check_in_accuracy_m' => (int) round($location['accuracy']), 'check_in_photo_path' => $photo, 'device_info' => $location['device_info'] ?? $request->userAgent(), 'updated_by' => $request->user()?->id])->save();
                $activities->record($visit->costumer_id, 'visit_check_in', 'Check-in kunjungan', $visit, 'Marketing tiba di lokasi kunjungan.', ['latitude' => $location['latitude'], 'longitude' => $location['longitude'], 'accuracy_m' => (int) round($location['accuracy']), 'photo' => $photo]);
            } else {
                abort_unless($visit->status === 'in_progress' && $visit->started_at, 422, 'Kunjungan belum check-in.');
                $visit->forceFill(['finished_at' => now(), 'status' => 'completed', 'verification_status' => 'pending_review', 'check_out_latitude' => $location['latitude'], 'check_out_longitude' => $location['longitude'], 'check_out_accuracy_m' => (int) round($location['accuracy']), 'check_out_photo_path' => $photo, 'result' => $location['result'], 'customer_response' => $location['customer_response'] ?? null, 'objections' => $location['objections'] ?? null, 'interest_level' => $location['interest_level'] ?? null, 'next_action' => $location['next_action'], 'next_action_at' => $location['next_action_at'] ?? null, 'updated_by' => $request->user()?->id])->save();
                if ($visit->costumer_id) {
                    Costumer::query()->whereKey($visit->costumer_id)->update(['last_activity_at' => now(), 'next_action_at' => $location['next_action_at'] ?? null]);
                }
                $activities->record($visit->costumer_id, 'visit_check_out', 'Laporan hasil kunjungan', $visit, $location['result'], ['duration_minutes' => $visit->started_at->diffInMinutes(now()), 'next_action' => $location['next_action'], 'photo' => $photo]);
                if ($visit->costumer_id) {
                    app(MarketingLeadStatusService::class)->markCustomer($visit->costumer_id, MarketingLeadStatusService::SUDAH_DIKUNJUNGI, $visit::class, $visit->id, 'Kunjungan selesai dan laporan dibuat.');
                }
            }
        });

        return redirect()->route('admin.marketing.crm.show', ['visits', $visit->id])->with('success', $phase === 'check-in' ? 'Check-in tercatat dengan waktu server dan GPS.' : 'Check-out dan laporan kunjungan berhasil disimpan. Silakan finalisasi untuk pemeriksaan.');
    }

    public function visitEvidence(Request $request, string $id, string $phase): BinaryFileResponse
    {
        abort_unless(in_array($phase, ['check-in', 'check-out'], true), 404);
        $config = $this->config('visits');
        $this->authorizeAction($request, $config, 'view');
        $visit = $this->findForUser($request, $config, $id, false);
        $path = $phase === 'check-in' ? $visit->check_in_photo_path : $visit->check_out_photo_path;
        abort_unless($path && is_file(storage_path('app/public/'.$path)), 404);

        return response()->file(storage_path('app/public/'.$path), ['Cache-Control' => 'private, max-age=300']);
    }

    public function checklistDocument(Request $request, string $id, string $document): BinaryFileResponse
    {
        $config = $this->config('document-checklists');
        $this->authorizeAction($request, $config, 'view');
        /** @var CustomerDocumentChecklist $checklist */
        $checklist = $this->findForUser($request, $config, $id);
        $item = collect($checklist->items ?? [])->first(fn (array $item) => (string) ($item['customer_document_id'] ?? '') === (string) $document);
        abort_unless($item, 404);

        $repository = CustomerDocument::query()
            ->whereKey($document)
            ->where('costumer_id', $checklist->costumer_id)
            ->where('status', 'active')
            ->firstOrFail();

        abort_unless($repository->path_file && is_file(storage_path('app/public/'.$repository->path_file)), 404);

        return response()->file(storage_path('app/public/'.$repository->path_file), [
            'Cache-Control' => 'private, max-age=300',
            'Content-Disposition' => 'inline; filename="'.addslashes($repository->nama_file ?: 'dokumen-customer').'"',
        ]);
    }

    private function form(Request $request, string $resource, array $config, ?Model $row): Response
    {
        $options = $this->options($request, $row, $resource);
        $formRow = $row?->toArray() ?? array_filter([
            'costumer_id' => $request->query('costumer_id'),
            'marketing_id' => $this->isIndividualMarketing($request) ? $request->user()?->id : null,
            'perumahan_id' => $this->isIndividualMarketing($request) ? $this->assignedPerumahanId($request) : null,
            // Aktivitas lapangan dicatat pada waktu pengisian, bukan dijadwalkan
            // manual oleh marketing. Backend tetap menjadi sumber waktu final.
            'planned_at' => $resource === 'visits' ? now()->format('Y-m-d\\TH:i') : null,
        ], fn ($value) => $value !== null && $value !== '');

        if ($resource === 'document-checklists' && $row) {
            $formRow['items'] = $this->mergeChecklistItems($row->items ?? [], $options['documentDefaults'] ?? []);
        }

        return Inertia::render('Admin/Marketing/CrmWorkspace/FormPage', [
            'title' => ($row ? 'Edit ' : 'Tambah ').$config['title'],
            'resource' => $resource,
            'baseUrl' => route('admin.marketing.crm.index', $resource, absolute: false),
            'actionUrl' => $row ? route('admin.marketing.crm.update', [$resource, $row->id], absolute: false) : route('admin.marketing.crm.store', $resource, absolute: false),
            'method' => $row ? 'put' : 'post',
            'row' => $formRow,
            'fields' => $config['form_fields'],
            'options' => $options,
        ]);
    }

    private function validated(Request $request, string $resource, array $config): array
    {
        $payload = $request->validate($config['rules']);
        if ($resource === 'visits') {
            $isDailyActivity = $request->isMethod('post') && ! $request->route('id');
            if ($isDailyActivity) {
                $request->validate([
                    'latitude' => ['required', 'numeric', 'between:-90,90'],
                    'longitude' => ['required', 'numeric', 'between:-180,180'],
                    'evidence_path' => ['required', 'image', 'max:8192'],
                    'result' => ['required', 'string', 'min:3'],
                    'interest_level' => ['required', Rule::in(array_column($this->referenceOptions('interest_level', ['cold' => 'Dingin', 'warm' => 'Hangat', 'hot' => 'Panas']), 'value'))],
                ]);
                $photo = $request->file('evidence_path')->store('marketing/visit-evidence', 'public');
                $payload['status'] = 'completed';
                // Jangan percaya nilai datetime dari browser. Waktu aktivitas
                // harus mencerminkan saat data benar-benar disimpan di server.
                $payload['planned_at'] = now();
                $payload['started_at'] = now();
                $payload['finished_at'] = now();
                $payload['location_captured_at'] = now();
                $payload['evidence_path'] = $photo;
                $payload['check_in_latitude'] = $payload['latitude'];
                $payload['check_in_longitude'] = $payload['longitude'];
                $payload['check_in_accuracy_m'] = $payload['location_accuracy_m'] ?? null;
                $payload['check_in_photo_path'] = $photo;
                $payload['check_out_latitude'] = $payload['latitude'];
                $payload['check_out_longitude'] = $payload['longitude'];
                $payload['check_out_accuracy_m'] = $payload['location_accuracy_m'] ?? null;
                $payload['check_out_photo_path'] = $photo;
                $payload['verification_status'] = 'pending_review';
            }
        }
        $this->assertPayloadWithinMarketingScope($request, $payload);

        if ($this->isIndividualMarketing($request)) {
            if (array_key_exists('marketing_id', $payload)) {
                $payload['marketing_id'] = $request->user()?->id;
            }
            if (array_key_exists('perumahan_id', $payload)) {
                $payload['perumahan_id'] = $this->assignedPerumahanId($request);
            }
        }
        if ($resource === 'document-checklists') {
            $defaults = $this->documentDefaults((int) $payload['costumer_id'])['items'];
            $payload['items'] = $this->mergeChecklistItems($payload['items'], $defaults);
            $items = collect($payload['items'])->map(function (array $item) use ($request, $payload) {
                $repository = null;
                $file = $item['file_upload'] ?? null;
                $documentId = $item['document_id'] ?? null;
                if ($file && ! empty($payload['costumer_id'])) {
                    $repository = $this->storeCustomerDocumentFromChecklist(
                        $request,
                        (int) $payload['costumer_id'],
                        $documentId ? (int) $documentId : null,
                        (string) ($item['name'] ?? 'Dokumen Customer'),
                        (string) ($item['party_scope'] ?? 'customer'),
                        $file,
                        $item['expires_at'] ?? null,
                        $item['note'] ?? null
                    );
                }
                if (! $repository && ! empty($payload['costumer_id']) && ! empty($item['customer_document_id'])) {
                    $repository = CustomerDocument::query()
                        ->where('costumer_id', $payload['costumer_id'])
                        ->where('status', 'active')
                        ->find($item['customer_document_id']);
                }

                $status = $item['status'] ?? 'missing';
                if ($repository && $status === 'missing') {
                    $status = 'received';
                }

                return [
                    'document_id' => $item['document_id'] ?? null,
                    'requirement_item_id' => $item['requirement_item_id'] ?? null,
                    'customer_document_id' => $repository?->id,
                    'party_scope' => $item['party_scope'] ?? 'customer',
                    'source' => $item['source'] ?? null,
                    'file_name' => $repository?->nama_file ?? ($item['file_name'] ?? null),
                    'file_path' => $repository?->path_file ?? ($item['file_path'] ?? null),
                    'name' => trim((string) ($item['name'] ?? '')),
                    'required' => (bool) ($item['required'] ?? false),
                    'status' => $status,
                    'expires_at' => $item['expires_at'] ?? null,
                    'note' => $item['note'] ?? null,
                ];
            })->values();
            $completed = $items->whereIn('status', ['valid', 'received'])->count();
            $payload['items'] = $items->all();
            $payload['completion_percentage'] = $items->count() ? (int) round($completed / $items->count() * 100) : 0;
            $payload['validation_status'] = $items->contains(fn ($item) => $item['required'] && ! in_array($item['status'], ['valid', 'received'], true)) ? 'incomplete' : 'complete';
        }
        if (array_key_exists('marketing_id', $payload) && ! $payload['marketing_id']) {
            $payload['marketing_id'] = $request->user()?->id;
        }

        return $payload;
    }

    private function storeCustomerDocumentFromChecklist(Request $request, int $customerId, ?int $documentId, string $label, string $partyScope, UploadedFile $file, ?string $expiresAt, ?string $note): CustomerDocument
    {
        $path = null;

        try {
            $path = $file->store('customer-repository/'.$customerId, 'public');

            return CustomerDocument::query()->create([
                'costumer_id' => $customerId,
                'dokumen_costumer_id' => $documentId,
                'label' => $documentId ? null : $label,
                'party_scope' => $partyScope,
                'nama_file' => $file->getClientOriginalName(),
                'path_file' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'expires_at' => $expiresAt,
                'status' => 'active',
                'keterangan' => $note,
                'uploaded_by' => $request->user()?->id,
            ]);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }
    }

    private function options(Request $request, ?Model $row = null, string $resource = ''): array
    {
        $individualMarketing = $this->isIndividualMarketing($request);
        $perumahanId = $individualMarketing ? $this->assignedPerumahanId($request) : null;
        $documents = $resource === 'document-checklists'
            ? $this->documentDefaults((int) ($row?->costumer_id ?: $request->query('costumer_id')))
            : ['items' => [], 'context' => null];

        return [
            'customers' => Costumer::query()->where('record_status', 'locked')
                ->when($individualMarketing, fn (Builder $query) => $query->where('assigned_marketing_id', $request->user()?->id)->where('perumahan_id', $perumahanId))
                ->latest('id')->limit(200)->get(['id', 'kode_costumer', 'nama', 'telepon'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => "{$row->nama} - {$row->kode_costumer} - ".($row->telepon ?: '-')]),
            'marketings' => User::query()
                ->when($individualMarketing, fn (Builder $query) => $query->whereKey($request->user()?->id))
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['marketing', 'area_marketing']))
                ->orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name]),
            'perumahans' => Perumahan::query()->finalized()->when($individualMarketing, fn (Builder $query) => $query->whereKey($perumahanId))->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan]),
            'hiddenFields' => $individualMarketing
                ? ['marketing_id', 'perumahan_id', ...($resource === 'action-plans' ? ['supervisor_note'] : [])]
                : [],
            'autoIdentity' => $individualMarketing ? [
                'marketing' => $request->user()?->name,
                'perumahan' => Perumahan::query()->whereKey($perumahanId)->value('nama_perusahaan'),
            ] : null,
            'documentDefaults' => $documents['items'],
            'documentContext' => $documents['context'],
            'visit_type' => $this->referenceOptions('visit_type', ['customer_location' => 'Lokasi Customer', 'office' => 'Kantor', 'housing_site' => 'Lokasi Perumahan', 'online' => 'Online', 'canvassing' => 'Canvassing', 'event' => 'Pameran/Event', 'agency' => 'Instansi/Partner']),
            'visit_status' => $this->referenceOptions('visit_status', ['planned' => 'Direncanakan', 'rescheduled' => 'Dijadwalkan Ulang', 'cancelled' => 'Dibatalkan']),
            'interest' => $this->referenceOptions('interest_level', ['cold' => 'Dingin', 'warm' => 'Hangat', 'hot' => 'Panas']),
            'activity_source' => $this->referenceOptions('activity_source', ['door_to_door' => 'Canvassing door-to-door', 'brochure' => 'Pembagian brosur', 'event' => 'Event / pameran', 'community' => 'Komunitas / instansi', 'partner' => 'Partner / agen', 'referral' => 'Referensi', 'other' => 'Lainnya']),
        ];
    }

    private function assertPayloadWithinMarketingScope(Request $request, array $payload): void
    {
        if (! $this->isIndividualMarketing($request)) {
            return;
        }

        $perumahanId = $this->assignedPerumahanId($request);
        if (! empty($payload['costumer_id'])) {
            abort_unless(Costumer::query()->whereKey($payload['costumer_id'])->where('assigned_marketing_id', $request->user()?->id)->where('perumahan_id', $perumahanId)->exists(), 403, 'Customer tidak berada dalam penugasan Marketing Anda.');
        }
    }

    private function isIndividualMarketing(Request $request): bool
    {
        return (bool) $request->user()?->hasAnyRole(['marketing', 'area_marketing'])
            && ! $request->user()?->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin']);
    }

    private function assignedPerumahanId(Request $request): int
    {
        $perumahanId = (int) ($request->session()->get('active_perumahan_id') ?: $request->user()?->perumahans()->value('perumahans.id'));
        abort_if($perumahanId <= 0, 422, 'Pilih perumahan aktif sebelum mengelola CRM Marketing.');

        return $perumahanId;
    }

    private function row(Model $row, Request $request, array $config): array
    {
        $approval = ApprovalRequest::query()->where('model_type', $row::class)->where('model_id', $row->id)->latest()->first();
        $workflow = app(ApprovalWorkflowService::class);
        $data = $row->toArray();
        $data['customer'] = $row->costumer?->nama ?? ($row->contact_name ?: '-');
        $data['marketing'] = (method_exists($row, 'marketing') ? $row->marketing?->name : null) ?? $row->costumer?->assignedMarketing?->name ?? '-';
        $data['perumahan'] = $row->perumahan?->nama_perusahaan ?? '-';
        $data['approval_status'] = $approval?->status ?? 'not_submitted';
        $data['approval_stage'] = $approval?->status === 'pending' ? "Tahap {$approval->current_step}/{$approval->total_steps}" : null;
        $data['can_review'] = $approval?->status === 'pending' && $workflow->canReview($approval);
        $data['can_edit'] = $row->record_status === 'draft' && $this->can($request, $config, 'update');
        $data['can_delete'] = $row->record_status === 'draft' && $this->can($request, $config, 'delete');
        $data['can_lock'] = $row->record_status === 'draft' && $this->can($request, $config, 'lock');
        $data['can_unlock'] = $row->record_status === 'locked' && $this->can($request, $config, 'unlock');
        if ($row instanceof MarketingVisit) {
            $data['check_in_url'] = in_array($row->status, ['planned', 'rescheduled'], true) && $data['can_edit'] ? route('admin.marketing.visit-execution.form', [$row->id, 'check-in'], absolute: false) : null;
            $data['check_out_url'] = $row->status === 'in_progress' && $data['can_edit'] ? route('admin.marketing.visit-execution.form', [$row->id, 'check-out'], absolute: false) : null;
            $data['check_in_evidence_url'] = $row->check_in_photo_path ? route('admin.marketing.visit-evidence', [$row->id, 'check-in'], absolute: false) : null;
            $data['check_out_evidence_url'] = $row->check_out_photo_path ? route('admin.marketing.visit-evidence', [$row->id, 'check-out'], absolute: false) : null;
            $data['duration_minutes'] = $row->started_at && $row->finished_at ? $row->started_at->diffInMinutes($row->finished_at) : null;
            $latitude = $row->check_in_latitude ?: $row->check_out_latitude;
            $longitude = $row->check_in_longitude ?: $row->check_out_longitude;
            $data['map_url'] = $latitude && $longitude ? "https://www.google.com/maps?q={$latitude},{$longitude}" : null;
            $data['convert_customer_url'] = null;
        }
        if ($row instanceof CustomerDocumentChecklist) {
            $data['items'] = collect($row->items ?? [])->map(function (array $item) use ($row): array {
                $documentId = $item['customer_document_id'] ?? null;
                if ($documentId) {
                    $item['document_url'] = route('admin.marketing.checklist-document', [$row->id, $documentId], absolute: false);
                }

                return $item;
            })->values()->all();
        }
        foreach ($config['date_fields'] as $field) {
            $data[$field] = $row->{$field}?->format(str_contains($field, '_at') ? 'Y-m-d\TH:i' : 'Y-m-d');
        }

        return $data;
    }

    private function findForUser(Request $request, array $config, string $id, bool $scope = true): Model
    {
        $modelClass = $config['model'];
        $query = $modelClass::query();

        return ($scope ? $this->scopeForUser($query, $request, $config) : $query)->findOrFail($id);
    }

    private function scopeForUser(Builder $query, Request $request, array $config): Builder
    {
        if ($request->user()?->hasAnyRole(['super_admin', 'owner', 'manager', 'supervisor_marketing', 'admin_sales'])) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($request, $config): void {
            if ($config['marketing_column']) {
                $query->where($config['marketing_column'], $request->user()?->id);
            } else {
                $query->whereHas('costumer', fn (Builder $customer) => $customer->where('assigned_marketing_id', $request->user()?->id));
            }
        });
    }

    private function syncCustomerTimeline(Model $row, string $resource): void
    {
        if (! $row->costumer_id) {
            return;
        }
        $updates = ['last_activity_at' => now()];
        if ($resource === 'visits') {
            $updates['next_action_at'] = $row->next_action_at;
        }
        if ($resource === 'action-plans') {
            $updates['next_action_at'] = $row->due_at;
        }
        Costumer::query()->whereKey($row->costumer_id)->update($updates);
    }

    private function syncVisitNextActionReminder(MarketingVisit $visit): void
    {
        $sourceType = MarketingVisit::class.':next-action';
        if (! $visit->next_action_at || ! $visit->next_action) {
            MarketingReminder::query()->where('source_type', $sourceType)->where('source_id', $visit->id)->delete();

            return;
        }

        MarketingReminder::query()->updateOrCreate(['source_type' => $sourceType, 'source_id' => $visit->id], [
            'costumer_id' => $visit->costumer_id,
            'user_id' => $visit->marketing_id ?: auth()->id(),
            'jenis' => 'tindak_lanjut_kunjungan',
            'judul' => 'Tindak lanjut: '.mb_strimwidth($visit->next_action, 0, 120, '...'),
            'remind_at' => $visit->next_action_at,
            'status' => 'menunggu',
            'catatan' => $visit->result ?: $visit->objective,
        ]);
    }

    private function permissionMap(Request $request, array $config): array
    {
        return collect(['view', 'create', 'update', 'delete', 'lock', 'unlock'])->mapWithKeys(fn ($action) => ['can'.ucfirst($action) => $this->can($request, $config, $action)])->all();
    }

    private function authorizeAction(Request $request, array $config, string $action): void
    {
        abort_unless($this->can($request, $config, $action), 403);
    }

    private function can(Request $request, array $config, string $action): bool
    {
        return (bool) ($request->user()?->hasRole('super_admin') || $request->user()?->can($config['permission'].'.'.$action));
    }

    private function config(string $resource): array
    {
        $configs = [
            'visits' => [
                'model' => MarketingVisit::class, 'module_key' => 'marketing-visit', 'permission' => 'marketing-visit', 'title' => 'Aktivitas Lapangan / Canvassing', 'description' => 'Pintu awal kerja Marketing: canvassing, pembagian brosur, event, instansi, komunitas, atau kunjungan prospek. Aktivitas boleh tanpa Lead; kontak yang bersedia ditindaklanjuti baru dikonversi menjadi Lead.',
                'number_column' => 'visit_no', 'prefix' => 'VISIT', 'with' => ['costumer.assignedMarketing:id,name', 'marketing:id,name', 'perumahan:id,nama_perusahaan'], 'search' => ['visit_no', 'objective', 'result', 'location'], 'status_column' => 'status', 'date_column' => 'planned_at', 'marketing_column' => 'marketing_id',
                'status_options' => $this->referenceOptions('visit_status', ['planned' => 'Direncanakan', 'in_progress' => 'Berlangsung', 'completed' => 'Selesai', 'rescheduled' => 'Dijadwalkan Ulang', 'cancelled' => 'Dibatalkan']),
                'form_fields' => ['costumer_id:customer:Customer Terdaftar (opsional)', 'contact_name:text:Nama Prospek / Kontak Lapangan', 'contact_phone:text:Telepon Prospek / Kontak', 'organization_name:text:Instansi / Komunitas / Event', 'lead_source_note:text:Sumber Aktivitas / Kanal Prospek', 'marketing_id:marketing:Marketing', 'perumahan_id:perumahan:Perumahan Terkait', 'planned_at:datetime-local:Tanggal & Jam Aktivitas', 'visit_type:select:Jenis Aktivitas Prospek:visit_type', 'location:text:Alamat / Lokasi Aktivitas', 'latitude:gps:Lokasi GPS Aktivitas', 'evidence_path:file:Foto Bukti Aktivitas', 'objective:textarea:Penawaran / Tujuan Aktivitas', 'result:textarea:Hasil Aktivitas *', 'interest_level:select:Tingkat Minat Prospek:interest', 'customer_response:textarea:Tanggapan Prospek', 'next_action:textarea:Tindak Lanjut Berikutnya', 'next_action_at:datetime-local:Jadwal Tindak Lanjut'],
                'detail_fields' => ['visit_no', 'customer', 'contact_name', 'contact_phone', 'organization_name', 'lead_source_note', 'marketing', 'perumahan', 'planned_at', 'started_at', 'finished_at', 'duration_minutes', 'status', 'location', 'check_in_latitude', 'check_in_longitude', 'check_in_accuracy_m', 'check_in_photo_path', 'check_out_latitude', 'check_out_longitude', 'check_out_accuracy_m', 'check_out_photo_path', 'verification_status', 'verification_note', 'objective', 'customer_response', 'objections', 'result', 'interest_level', 'next_action', 'next_action_at', 'record_status', 'approval_status'],
                'date_fields' => ['planned_at', 'started_at', 'finished_at', 'next_action_at'],
                'rules' => ['costumer_id' => ['nullable', 'exists:costumers,id'], 'contact_name' => [Rule::requiredIf(fn () => ! request()->filled('costumer_id')), 'nullable', 'string', 'max:255'], 'contact_phone' => ['nullable', 'string', 'max:50'], 'organization_name' => ['nullable', 'string', 'max:255'], 'lead_source_note' => ['nullable', 'string', 'max:255'], 'marketing_id' => ['nullable', 'exists:users,id'], 'perumahan_id' => ['nullable', 'exists:perumahans,id'], 'planned_at' => ['required', 'date'], 'visit_type' => ['required', Rule::in(array_column($this->referenceOptions('visit_type', ['customer_location' => 'Lokasi Customer', 'office' => 'Kantor', 'housing_site' => 'Lokasi Perumahan', 'online' => 'Online', 'canvassing' => 'Canvassing', 'event' => 'Pameran/Event', 'agency' => 'Instansi/Partner']), 'value'))], 'location' => [Rule::requiredIf(fn () => request()->input('visit_type') !== 'online'), 'nullable', 'string', 'max:500'], 'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'], 'location_accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:250'], 'evidence_path' => ['nullable', 'image', 'max:8192'], 'objective' => ['required', 'string', 'min:3'], 'result' => ['nullable', 'string'], 'interest_level' => ['nullable', Rule::in(array_column($this->referenceOptions('interest_level', ['cold' => 'Dingin', 'warm' => 'Hangat', 'hot' => 'Panas']), 'value'))], 'customer_response' => ['nullable', 'string'], 'next_action' => ['nullable', 'string'], 'next_action_at' => ['nullable', 'date', 'after_or_equal:today']],
            ],
            'action-plans' => [
                'model' => MarketingActionPlan::class, 'module_key' => 'marketing-action-plan', 'permission' => 'marketing-action-plan', 'title' => 'Action Plan Marketing', 'description' => 'Daftar kerja marketing yang terukur berdasarkan customer, prioritas, tenggat, target, hasil, dan hambatan.',
                'number_column' => 'action_no', 'prefix' => 'ACTION', 'with' => ['costumer.assignedMarketing:id,name', 'marketing:id,name', 'perumahan:id,nama_perusahaan'], 'search' => ['action_no', 'title', 'objective', 'expected_result', 'blocker'], 'status_column' => 'status', 'date_column' => 'due_at', 'marketing_column' => 'marketing_id',
                'status_options' => $this->select(['planned' => 'Direncanakan', 'in_progress' => 'Dikerjakan', 'completed' => 'Selesai', 'blocked' => 'Terhambat', 'cancelled' => 'Dibatalkan']),
                'form_fields' => ['costumer_id:customer:Customer', 'marketing_id:marketing:Marketing', 'perumahan_id:perumahan:Perumahan', 'title:text:Judul', 'objective:textarea:Sasaran', 'expected_result:textarea:Target Hasil', 'actual_result:textarea:Hasil Aktual', 'priority:select:Prioritas:priority', 'status:select:Status:action_status', 'start_at:datetime-local:Mulai', 'due_at:datetime-local:Tenggat', 'completed_at:datetime-local:Selesai Aktual', 'blocker:textarea:Hambatan', 'supervisor_note:textarea:Catatan Supervisor'],
                'detail_fields' => ['action_no', 'customer', 'marketing', 'perumahan', 'title', 'objective', 'expected_result', 'actual_result', 'priority', 'status', 'start_at', 'due_at', 'completed_at', 'blocker', 'supervisor_note', 'record_status', 'approval_status'],
                'date_fields' => ['start_at', 'due_at', 'completed_at'],
                'rules' => ['costumer_id' => ['nullable', 'exists:costumers,id'], 'marketing_id' => ['nullable', 'exists:users,id'], 'perumahan_id' => ['nullable', 'exists:perumahans,id'], 'title' => ['required', 'string', 'max:255'], 'objective' => ['required', 'string'], 'expected_result' => ['nullable', 'string'], 'actual_result' => ['nullable', 'string'], 'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])], 'status' => ['required', Rule::in(['planned', 'in_progress', 'completed', 'blocked', 'cancelled'])], 'start_at' => ['required', 'date'], 'due_at' => ['required', 'date', 'after_or_equal:start_at'], 'completed_at' => ['nullable', 'date'], 'blocker' => ['nullable', 'string'], 'supervisor_note' => ['nullable', 'string']],
            ],
            'document-checklists' => [
                'model' => CustomerDocumentChecklist::class, 'module_key' => 'customer-document-checklist', 'permission' => 'customer-document-checklist', 'title' => 'Checklist Kelengkapan Berkas', 'description' => 'Kelengkapan, validitas, masa berlaku, dan kekurangan berkas pelanggan per tahapan proses.',
                'number_column' => 'checklist_no', 'prefix' => 'DOC', 'with' => ['costumer.assignedMarketing:id,name', 'perumahan:id,nama_perusahaan'], 'search' => ['checklist_no', 'process_stage', 'notes'], 'status_column' => 'validation_status', 'date_column' => 'updated_at', 'marketing_column' => null,
                'status_options' => $this->select(['incomplete' => 'Belum Lengkap', 'complete' => 'Lengkap', 'needs_revision' => 'Perlu Revisi']),
                'form_fields' => ['costumer_id:customer:Customer', 'perumahan_id:perumahan:Perumahan', 'process_stage:select:Tahapan Proses:process_stage', 'items:checklist:Daftar Dokumen Otomatis dari Master', 'notes:textarea:Catatan'],
                'detail_fields' => ['checklist_no', 'customer', 'marketing', 'perumahan', 'process_stage', 'completion_percentage', 'validation_status', 'items', 'notes', 'record_status', 'approval_status'],
                'date_fields' => [],
                'rules' => ['costumer_id' => ['required', 'exists:costumers,id'], 'perumahan_id' => ['nullable', 'exists:perumahans,id'], 'process_stage' => ['required', Rule::in(['qualification', 'reservation', 'spr', 'kpr', 'contract'])], 'items' => ['required', 'array', 'min:1'], 'items.*.document_id' => ['nullable', 'exists:dokumen_costumers,id'], 'items.*.requirement_item_id' => ['nullable', 'exists:document_requirement_set_items,id'], 'items.*.customer_document_id' => ['nullable', 'exists:customer_documents,id'], 'items.*.party_scope' => ['nullable', Rule::in(['customer', 'spouse', 'both'])], 'items.*.source' => ['nullable', 'string', 'max:1000'], 'items.*.name' => ['required', 'string', 'max:255'], 'items.*.required' => ['nullable', 'boolean'], 'items.*.status' => ['required', Rule::in(['missing', 'received', 'valid', 'revision', 'expired', 'rejected'])], 'items.*.expires_at' => ['nullable', 'date'], 'items.*.note' => ['nullable', 'string'], 'items.*.file_upload' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'], 'notes' => ['nullable', 'string']],
            ],
        ];
        if ($resource === 'visits') {
            $configs['visits']['form_fields'] = ['costumer_id:customer:Customer Terkonversi (opsional)', 'organization_name:text:Area / Instansi / Komunitas / Event', 'lead_source_note:select:Sumber / Kanal Aktivitas (opsional):activity_source', 'marketing_id:marketing:Marketing', 'perumahan_id:perumahan:Perumahan Terkait', 'planned_at:datetime-local:Tanggal & Jam Aktivitas', 'visit_type:select:Jenis Aktivitas:visit_type', 'location:text:Alamat / Lokasi Aktivitas', 'latitude:gps:Lokasi GPS Aktivitas', 'evidence_path:file:Foto Bukti Aktivitas', 'objective:textarea:Target / Tujuan Aktivitas', 'result:textarea:Hasil Aktivitas', 'interest_level:select:Tingkat Minat Prospek:interest'];
            $configs['visits']['rules']['lead_source_note'] = ['nullable', Rule::in(array_column($this->referenceOptions('activity_source', ['door_to_door' => 'Canvassing door-to-door', 'brochure' => 'Pembagian brosur', 'event' => 'Event / pameran', 'community' => 'Komunitas / instansi', 'partner' => 'Partner / agen', 'referral' => 'Referensi', 'other' => 'Lainnya']), 'value'))];
            $configs['visits']['rules']['contact_name'] = ['nullable', 'string', 'max:255'];
        }

        abort_unless(isset($configs[$resource]), 404);

        return $configs[$resource];
    }

    private function select(array $items): array
    {
        return collect($items)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }

    private function referenceOptions(string $category, array $fallback): array
    {
        return MarketingReferenceOption::options($category, $this->select($fallback));
    }

    private function documentDefaults(?int $customerId = null): array
    {
        if ($customerId) {
            $customer = Costumer::query()->find($customerId);
            $requirements = $customer
                ? app(CustomerDocumentRequirementService::class)->forChecklist($customer)
                : collect();

            if ($requirements->isNotEmpty()) {
                return [
                    'context' => 'Paket persyaratan aktif: '.$requirements->pluck('source')->filter()->unique()->join(', '),
                    'items' => $requirements->map(fn (array $requirement) => [
                        'document_id' => (string) $requirement['document_id'],
                        'requirement_item_id' => $requirement['requirement_item_id'] ?? null,
                        'customer_document_id' => $requirement['customer_document_id'] ?? null,
                        'party_scope' => $requirement['party_scope'] ?? 'customer',
                        'source' => $requirement['source'] ?? null,
                        'name' => trim(($requirement['label'] ?? 'Dokumen').' '.($requirement['code'] ? '('.$requirement['code'].')' : '')),
                        'required' => (bool) ($requirement['required'] ?? false),
                        'status' => ($requirement['uploaded'] ?? false) ? 'received' : 'missing',
                        'expires_at' => $requirement['expires_at'] ?? '',
                        'file_name' => $requirement['file_name'] ?? null,
                        'file_path' => $requirement['file_path'] ?? null,
                        'note' => trim('Sumber: '.($requirement['source'] ?? 'Paket persyaratan').'. '.($requirement['notes'] ?? '')),
                    ])->values()->all(),
                ];
            }
        }

        $repository = $customerId
            ? CustomerDocument::query()->where('costumer_id', $customerId)->where('status', 'active')->latest('id')->get()->unique('dokumen_costumer_id')->keyBy('dokumen_costumer_id')
            : collect();

        $items = DokumenCostumer::query()
            ->finalized()
            ->where('status', 'aktif')
            ->orderByDesc('wajib')
            ->orderBy('kategori_pengajuan')
            ->orderBy('nama_dokumen')
            ->get(['id', 'kode_dokumen', 'nama_dokumen', 'kategori_pengajuan', 'wajib'])
            ->map(function (DokumenCostumer $document) use ($repository): array {
                $uploaded = $repository->get($document->id);

                return [
                    'document_id' => (string) $document->id,
                    'customer_document_id' => $uploaded?->id,
                    'party_scope' => 'customer',
                    'source' => 'Master Dokumen Pelanggan',
                    'name' => $document->nama_dokumen.' ('.$document->kode_dokumen.')',
                    'required' => (bool) $document->wajib,
                    'status' => $uploaded ? 'received' : 'missing',
                    'expires_at' => $uploaded?->expires_at?->format('Y-m-d') ?? '',
                    'file_name' => $uploaded?->nama_file,
                    'file_path' => $uploaded?->path_file,
                    'note' => trim(($document->kategori_pengajuan ? 'Kategori: '.$document->kategori_pengajuan.'. ' : '').($uploaded ? 'Sudah ada di repository customer.' : 'Sumber: Master Dokumen Pelanggan.')),
                ];
            })
            ->values()
            ->all();

        return [
            'context' => $customerId
                ? 'Belum ada paket bank/produk yang cocok. Menggunakan Master Dokumen Pelanggan sebagai fallback.'
                : 'Pilih customer terlebih dahulu agar sistem menentukan paket dokumen yang sesuai.',
            'items' => $customerId ? $items : [],
        ];
    }

    private function mergeChecklistItems(array $submitted, array $defaults, bool $rejectUnknownRequirements = false): array
    {
        $submittedItems = collect($submitted);
        $submittedByKey = $submittedItems
            ->filter(fn (array $item) => $this->checklistItemKey($item) !== null)
            ->keyBy(fn (array $item) => $this->checklistItemKey($item));
        $defaultKeys = collect($defaults)
            ->map(fn (array $item) => $this->checklistItemKey($item))
            ->filter()
            ->values();

        $hasUnknownRequirement = $submittedItems->contains(function (array $item) use ($defaultKeys): bool {
            if (empty($item['requirement_item_id'])) {
                return false;
            }

            return ! $defaultKeys->contains('requirement:'.$item['requirement_item_id']);
        });
        abort_if($rejectUnknownRequirements && $hasUnknownRequirement, 422, 'Paket dokumen tidak sesuai dengan metode pembayaran, bank, atau produk customer. Muat ulang form.');

        $merged = collect($defaults)->map(function (array $default) use ($submittedByKey): array {
            $key = $this->checklistItemKey($default);
            $item = $key && $submittedByKey->has($key)
                ? array_replace($default, $submittedByKey->get($key))
                : $default;

            foreach (['document_id', 'requirement_item_id', 'party_scope', 'source', 'name', 'required'] as $trustedField) {
                if (array_key_exists($trustedField, $default)) {
                    $item[$trustedField] = $default[$trustedField];
                }
            }

            return $item;
        });

        $customItems = $submittedItems->filter(function (array $item) use ($defaultKeys): bool {
            $key = $this->checklistItemKey($item);

            return $key === null || ! $defaultKeys->contains($key);
        });

        return $merged->concat($customItems)->values()->all();
    }

    private function checklistItemKey(array $item): ?string
    {
        if (! empty($item['requirement_item_id'])) {
            return 'requirement:'.$item['requirement_item_id'];
        }
        if (! empty($item['document_id'])) {
            return 'document:'.$item['document_id'].'|'.($item['party_scope'] ?? 'customer');
        }

        return null;
    }
}
