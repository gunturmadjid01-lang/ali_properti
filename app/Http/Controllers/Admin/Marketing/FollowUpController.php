<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\CostumerFollowUp;
use App\Models\MarketingLead;
use App\Models\MarketingLeadAssignment;
use App\Models\MarketingReferenceOption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FollowUpController extends Controller
{
    use HandlesCrudLock, ScopesActivePerumahan;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'view');
        $search = trim((string) $request->query('search', ''));

        $followUps = CostumerFollowUp::query()
            ->with(['lead:id,lead_no,name,phone,identity_no,perumahan_id,converted_costumer_id', 'lead.customer:id,kode_costumer,nama', 'user:id,name'])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('user_id', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('lead', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('metode_follow_up', 'like', "%{$search}%")
                        ->orWhere('progress_kemampuan', 'like', "%{$search}%")
                        ->orWhere('catatan', 'like', "%{$search}%")
                        ->orWhereHas('lead', function (Builder $query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('identity_no', 'like', "%{$search}%")
                                ->orWhere('lead_no', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('tanggal_follow_up')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (CostumerFollowUp $followUp) => [
                'id' => $followUp->id,
                'costumer_id' => (string) ($followUp->lead?->converted_costumer_id ?? ''),
                'marketing_lead_id' => (string) $followUp->marketing_lead_id,
                'tanggal_follow_up' => optional($followUp->tanggal_follow_up)->format('Y-m-d'),
                'customer' => $followUp->lead?->name ?? '-',
                'kode_costumer' => $followUp->lead?->lead_no ?? '-',
                'no_identitas' => $followUp->lead?->identity_no ?? '-',
                'telepon' => $followUp->lead?->phone ?? '-',
                'metode_key' => $followUp->metode_follow_up,
                'metode_follow_up' => $this->labelFromOptions($followUp->metode_follow_up, $this->methodOptions()),
                'status_serius_value' => $followUp->status_serius ? '1' : '0',
                'status_serius' => $followUp->status_serius ? 'Serius' : 'Belum Serius',
                'progress_key' => $followUp->progress_kemampuan,
                'progress_kemampuan' => $this->labelFromOptions($followUp->progress_kemampuan, $this->progressOptions()),
                'status_key' => $followUp->status ?? 'selesai',
                'status_label' => $this->labelFromOptions($followUp->status ?? 'selesai', $this->statusOptions()),
                'catatan' => $followUp->catatan,
                'rencana_follow_up_at' => optional($followUp->rencana_follow_up_at)->format('Y-m-d'),
                'input_oleh' => $followUp->user?->name ?? '-',
                'record_status' => $followUp->record_status ?? 'draft',
                'record_status_label' => ($followUp->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
                'can_edit' => ($followUp->record_status ?? 'draft') !== 'locked',
                'can_delete' => ($followUp->record_status ?? 'draft') !== 'locked',
                'can_lock' => ($followUp->record_status ?? 'draft') !== 'locked' && (bool) auth()->check(),
                'can_unlock' => $this->currentUserCanManageLockedRecords() && ($followUp->record_status ?? 'draft') === 'locked',
            ]);

        return Inertia::render('Admin/Marketing/FollowUp/Index', [
            'title' => 'Jejak Follow Up',
            'description' => 'Catat tindak lanjut sejak masih Lead; riwayat tetap tersambung setelah menjadi Customer.',
            'baseUrl' => route('admin.marketing.jejak-follow-up.index', absolute: false),
            'rows' => $followUps,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'create');

        return $this->renderForm(null, (string) $request->query('marketing_lead_id', ''));
    }

    public function edit(Request $request, string $id): Response
    {
        $this->authorizePermission($request, 'update');
        $row = CostumerFollowUp::query()->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $q) => $q->where('user_id', $request->user()?->id))->when($this->shouldScopeToActivePerumahan($request), fn (Builder $q) => $q->whereHas('lead', fn (Builder $q) => $this->scopeToActivePerumahan($q, $request)))->findOrFail($id);
        $this->abortIfLocked($row);

        return $this->renderForm($row);
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorizePermission($request, 'view');
        $row = CostumerFollowUp::query()->with(['lead:id,lead_no,name', 'user:id,name'])->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $q) => $q->where('user_id', $request->user()?->id))->when($this->shouldScopeToActivePerumahan($request), fn (Builder $q) => $q->whereHas('lead', fn (Builder $q) => $this->scopeToActivePerumahan($q, $request)))->findOrFail($id);

        return Inertia::render('Admin/Marketing/FollowUp/Show', ['title' => 'Detail Follow Up '.$row->lead?->name, 'baseUrl' => route('admin.marketing.jejak-follow-up.index', absolute: false), 'row' => [
            'id' => $row->id, 'customer' => $row->lead?->name ?? '-', 'kode_costumer' => $row->lead?->lead_no ?? '-', 'tanggal_follow_up' => optional($row->followed_up_at ?? $row->tanggal_follow_up)->format('d/m/Y H:i'), 'metode_follow_up' => $this->labelFromOptions($row->metode_follow_up, $this->methodOptions()), 'status_serius' => $row->status_serius ? 'Serius' : 'Belum Serius', 'progress_kemampuan' => $this->labelFromOptions($row->progress_kemampuan, $this->progressOptions()), 'result_label' => $this->labelFromOptions($row->result_code, $this->resultOptions()), 'interest_label' => $this->labelFromOptions($row->interest_level, $this->interestOptions()), 'status_label' => $this->labelFromOptions($row->status, $this->statusOptions()), 'rencana_follow_up_at' => optional($row->rencana_follow_up_at)->format('d/m/Y'), 'input_oleh' => $row->user?->name ?? '-', 'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft', 'catatan' => $row->catatan, 'obstacle' => $row->obstacle, 'next_action' => $row->next_action, 'attachment_url' => $row->attachment_path ? route('admin.marketing.jejak-follow-up.evidence', $row->id, false) : null, 'can_edit' => ($row->record_status ?? 'draft') !== 'locked',
        ]]);
    }

    public function evidence(Request $request, string $id): BinaryFileResponse
    {
        $this->authorizePermission($request, 'view');
        $row = CostumerFollowUp::query()->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $q) => $q->where('user_id', $request->user()?->id))->when($this->shouldScopeToActivePerumahan($request), fn (Builder $q) => $q->whereHas('lead', fn (Builder $q) => $this->scopeToActivePerumahan($q, $request)))->findOrFail($id);
        abort_unless($row->attachment_path && is_file(storage_path('app/public/'.$row->attachment_path)), 404);

        return response()->file(storage_path('app/public/'.$row->attachment_path), ['Cache-Control' => 'private, max-age=300']);
    }

    private function renderForm(?CostumerFollowUp $row, string $leadId = ''): Response
    {
        return Inertia::render('Admin/Marketing/FollowUp/FormPage', [
            'title' => $row ? 'Edit Follow Up' : 'Tambah Follow Up', 'baseUrl' => route('admin.marketing.jejak-follow-up.index', absolute: false),
            'actionUrl' => $row ? route('admin.marketing.jejak-follow-up.update', $row->id, false) : route('admin.marketing.jejak-follow-up.store', absolute: false), 'method' => $row ? 'put' : 'post',
            'customers' => $this->leadOptions(), 'options' => ['methodOptions' => $this->methodOptions(), 'seriousOptions' => $this->seriousOptions(), 'progressOptions' => $this->progressOptions(), 'resultOptions' => $this->resultOptions(), 'interestOptions' => $this->interestOptions(), 'statusOptions' => $this->statusOptions()],
            'row' => $row ? ['marketing_lead_id' => (string) $row->marketing_lead_id, 'tanggal_follow_up' => optional($row->tanggal_follow_up)->format('Y-m-d'), 'metode_follow_up' => $row->metode_follow_up, 'status_serius' => $row->status_serius ? '1' : '0', 'progress_kemampuan' => $row->progress_kemampuan, 'result_code' => $row->result_code, 'interest_level' => $row->interest_level, 'status' => $row->status, 'catatan' => $row->catatan, 'obstacle' => $row->obstacle, 'next_action' => $row->next_action, 'attachment_path' => $row->attachment_path, 'rencana_follow_up_at' => optional($row->rencana_follow_up_at)->format('Y-m-d')] : ['marketing_lead_id' => $leadId],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'create');
        $validated = $request->validate([
            'marketing_lead_id' => ['required', 'exists:marketing_leads,id'],
            'tanggal_follow_up' => ['required', 'date'],
            'metode_follow_up' => ['required', Rule::in(array_column($this->methodOptions(), 'value'))],
            'status_serius' => ['required', 'boolean'],
            'progress_kemampuan' => ['required', Rule::in(array_column($this->progressOptions(), 'value'))],
            'result_code' => ['required', Rule::in(array_column($this->resultOptions(), 'value'))],
            'interest_level' => ['required', Rule::in(array_column($this->interestOptions(), 'value'))],
            'status' => ['required', Rule::in(array_column($this->statusOptions(), 'value'))],
            'catatan' => ['required', 'string', 'min:5'],
            'obstacle' => ['nullable', 'string'],
            'next_action' => [Rule::requiredIf(fn () => ! in_array($request->input('result_code'), ['not_interested', 'wrong_number'], true)), 'nullable', 'string', 'min:3'],
            'rencana_follow_up_at' => [Rule::requiredIf(fn () => ! in_array($request->input('result_code'), ['not_interested', 'wrong_number'], true)), 'nullable', 'date', 'after_or_equal:today'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);

        $lead = $this->ensureLeadCanBeUsed($request, (int) $validated['marketing_lead_id']);

        if ($request->hasFile('attachment')) {
            $validated['attachment_path'] = $request->file('attachment')->store('marketing/follow-up-evidence', 'public');
        }
        unset($validated['attachment']);
        $followUp = CostumerFollowUp::create([
            ...$validated,
            'followed_up_at' => now(),
            'user_id' => $request->user()?->id,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->syncLeadFromFollowUp($lead, $validated);

        return back()->with('success', 'Follow up Lead berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'update');
        $validated = $request->validate([
            'marketing_lead_id' => ['required', 'exists:marketing_leads,id'],
            'tanggal_follow_up' => ['required', 'date'],
            'metode_follow_up' => ['required', Rule::in(array_column($this->methodOptions(), 'value'))],
            'status_serius' => ['required', 'boolean'],
            'progress_kemampuan' => ['required', Rule::in(array_column($this->progressOptions(), 'value'))],
            'result_code' => ['required', Rule::in(array_column($this->resultOptions(), 'value'))],
            'interest_level' => ['required', Rule::in(array_column($this->interestOptions(), 'value'))],
            'status' => ['required', Rule::in(array_column($this->statusOptions(), 'value'))],
            'catatan' => ['required', 'string', 'min:5'],
            'obstacle' => ['nullable', 'string'],
            'next_action' => [Rule::requiredIf(fn () => ! in_array($request->input('result_code'), ['not_interested', 'wrong_number'], true)), 'nullable', 'string', 'min:3'],
            'rencana_follow_up_at' => [Rule::requiredIf(fn () => ! in_array($request->input('result_code'), ['not_interested', 'wrong_number'], true)), 'nullable', 'date', 'after_or_equal:today'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);

        $row = CostumerFollowUp::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('user_id', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('lead', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            ->findOrFail($id);
        $this->abortIfLocked($row);
        $before = $row->getAttributes();
        $lead = $this->ensureLeadCanBeUsed($request, (int) $validated['marketing_lead_id']);
        if ($request->hasFile('attachment')) {
            $validated['attachment_path'] = $request->file('attachment')->store('marketing/follow-up-evidence', 'public');
        }
        unset($validated['attachment']);

        $row->update([
            ...$validated,
            'followed_up_at' => now(),
            'user_id' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->syncLeadFromFollowUp($lead, $validated);

        return back()->with('success', 'Follow up customer berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'delete');
        $row = CostumerFollowUp::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('user_id', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('lead', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            ->findOrFail($id);
        $this->abortIfLocked($row);
        $row->delete();

        return back()->with('success', 'Follow up berhasil dihapus.');
    }

    protected function leadOptions(): array
    {
        return MarketingLead::query()
            ->when($this->shouldScopeToCurrentMarketing(request()), fn (Builder $query) => $query->where('marketing_id', request()->user()?->id))
            ->when($this->shouldScopeToActivePerumahan(request()), fn (Builder $query) => $this->scopeToActivePerumahan($query, request()))
            ->whereNotIn('stage', ['lost'])
            ->where('do_not_contact', false)
            ->select(['id', 'lead_no', 'name', 'identity_no', 'phone', 'email', 'stage'])
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (MarketingLead $lead) => [
                'id' => $lead->id,
                'kode_costumer' => $lead->lead_no,
                'nama' => $lead->name,
                'no_identitas' => $lead->identity_no,
                'telepon' => $lead->phone,
                'email' => $lead->email,
                'stage' => $lead->stage,
                'search' => strtolower(implode(' ', [
                    $lead->name,
                    $lead->identity_no,
                    $lead->lead_no,
                    $lead->phone,
                ])),
            ])
            ->all();
    }

    protected function methodOptions(): array
    {
        return MarketingReferenceOption::options('follow_up_method', [
            ['value' => 'chat', 'label' => 'Chat'],
            ['value' => 'kunjungan_langsung', 'label' => 'Kunjungan Langsung'],
            ['value' => 'telephone', 'label' => 'Telephone'],
            ['value' => 'whatsapp', 'label' => 'WhatsApp'],
            ['value' => 'email', 'label' => 'Email'],
            ['value' => 'meeting', 'label' => 'Pertemuan'],
        ]);
    }

    protected function resultOptions(): array
    {
        return MarketingReferenceOption::options('follow_up_result', collect(['interested' => 'Berminat', 'callback' => 'Minta Dihubungi Kembali', 'no_response' => 'Belum Merespons', 'scheduled_visit' => 'Jadwal Kunjungan', 'reservation_ready' => 'Siap Reservasi', 'not_interested' => 'Tidak Berminat', 'wrong_number' => 'Nomor Tidak Valid'])->map(fn ($label, $value) => compact('value', 'label'))->values()->all());
    }

    protected function interestOptions(): array
    {
        return MarketingReferenceOption::options('interest_level', collect(['cold' => 'Dingin', 'warm' => 'Hangat', 'hot' => 'Panas'])->map(fn ($label, $value) => compact('value', 'label'))->values()->all());
    }

    protected function seriousOptions(): array
    {
        return [
            ['value' => '1', 'label' => 'Serius'],
            ['value' => '0', 'label' => 'Belum Serius'],
        ];
    }

    protected function progressOptions(): array
    {
        return [
            ['value' => 'low', 'label' => 'Low', 'hint' => 'Customer tidak mau dan tidak ada uang.'],
            ['value' => 'medium', 'label' => 'Medium', 'hint' => 'Customer mau tapi tidak ada uang.'],
            ['value' => 'high', 'label' => 'High', 'hint' => 'Customer mau dan uangnya ada.'],
            ['value' => 'very_high', 'label' => 'Very High', 'hint' => 'Customer mau dan berkas diterima.'],
        ];
    }

    protected function statusOptions(): array
    {
        return [
            ['value' => 'menunggu', 'label' => 'Menunggu'],
            ['value' => 'selesai', 'label' => 'Selesai'],
            ['value' => 'dibatalkan', 'label' => 'Dibatalkan'],
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

    protected function statusFromFollowUp(?string $progress, ?string $result = null): string
    {
        if ($result === 'no_response') {
            return MarketingLeadStatusService::TIDAK_MERESPONS;
        }
        if ($result === 'not_interested') {
            return MarketingLeadStatusService::TIDAK_BERMINAT;
        }
        if ($result === 'scheduled_visit') {
            return MarketingLeadStatusService::JADWAL_KUNJUNGAN;
        }
        if ($result === 'reservation_ready') {
            return MarketingLeadStatusService::RESERVASI;
        }

        return in_array($progress, ['high', 'very_high'], true)
            ? MarketingLeadStatusService::POTENSIAL
            : MarketingLeadStatusService::FOLLOW_UP;
    }

    protected function ensureLeadCanBeUsed(Request $request, int $leadId): MarketingLead
    {
        $query = MarketingLead::query()->whereKey($leadId);
        if ($this->shouldScopeToCurrentMarketing($request)) {
            $query->where('marketing_id', $request->user()?->id)
                ->where('perumahan_id', $this->ensureActivePerumahan($request));
        }

        $lead = $query->firstOrFail();
        abort_if($lead->do_not_contact || $lead->consent_status === 'denied', 422, 'Lead menolak komunikasi. Perbarui consent dengan bukti sebelum mencatat follow-up baru.');

        $channel = match ((string) $request->input('metode_follow_up')) {
            'telephone' => 'phone',
            'chat', 'whatsapp' => 'whatsapp',
            'email' => 'email',
            default => null,
        };
        if ($lead->consent_status === 'granted' && $channel && ! in_array($channel, $lead->consent_channels ?? [], true)) {
            abort(422, 'Kanal komunikasi ini tidak termasuk consent Lead.');
        }

        return $lead;
    }

    protected function syncLeadFromFollowUp(MarketingLead $lead, array $data): void
    {
        $firstResponse = $lead->first_contacted_at === null;
        $stage = match ($data['result_code']) {
            'not_interested', 'wrong_number' => 'lost',
            default => $lead->stage === 'qualified' ? 'qualified' : 'nurturing',
        };
        $lead->update([
            'stage' => $lead->stage === 'converted' ? 'converted' : $stage,
            'qualification_status' => $stage === 'lost' ? 'disqualified' : $lead->qualification_status,
            'first_contacted_at' => $lead->first_contacted_at ?? now(),
            'last_activity_at' => now(),
            'next_action_at' => $data['rencana_follow_up_at'] ?? null,
            'qualified_at' => $lead->qualified_at,
            'qualified_by' => $lead->qualified_by,
            'lost_reason' => $stage === 'lost' ? ($data['catatan'] ?? $data['result_code']) : null,
            'updated_by' => request()->user()?->id,
            'assignment_status' => $firstResponse && in_array($lead->assignment_status, ['offered', 'accepted'], true) ? 'responded' : $lead->assignment_status,
        ]);
        if ($firstResponse) {
            MarketingLeadAssignment::query()->where('marketing_lead_id', $lead->id)->whereIn('status', ['offered', 'accepted'])->latest('assigned_at')->limit(1)->update(['status' => 'responded', 'responded_at' => now(), 'response_note' => 'Respons pertama tercatat dari follow-up Marketing.']);
        }
    }

    protected function modelClass(): string
    {
        return CostumerFollowUp::class;
    }

    protected function abortIfLocked(Model $model): void
    {
        abort_if(($model->record_status ?? 'draft') === 'locked', 422, 'Data sudah dikunci. Gunakan Unlock sebelum melakukan perubahan.');
    }

    protected function lockableQuery()
    {
        return CostumerFollowUp::query()
            ->when($this->shouldScopeToCurrentMarketing(request()), fn (Builder $query) => $query->where('user_id', request()->user()?->id))
            ->when($this->shouldScopeToActivePerumahan(request()), fn (Builder $query) => $query->whereHas('lead', fn (Builder $query) => $this->scopeToActivePerumahan($query, request())));
    }

    protected function authorizeLockPermission(): void
    {
        $this->authorizePermission(request(), 'lock');
    }

    protected function shouldScopeToCurrentMarketing(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing'])
            && ! $user->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin']);
    }

    private function authorizePermission(Request $request, string $action): void
    {
        $user = $request->user();
        abort_unless(
            $user?->hasRole('super_admin')
                || $user?->can("customer-follow-up.{$action}")
                || ($action === 'view' && $user?->can('customer.follow-up')),
            403,
        );
    }
}
