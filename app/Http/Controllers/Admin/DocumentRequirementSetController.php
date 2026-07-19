<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\BankCreditProduct;
use App\Models\BankHousingPartnership;
use App\Models\BankKredit;
use App\Models\CabangPerusahaan;
use App\Models\DocumentRequirementSet;
use App\Models\DokumenCostumer;
use App\Models\Perumahan;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DocumentRequirementSetController extends Controller
{
    private function allow(Request $r, string $action): void
    {
        abort_unless($r->user()?->can("bank-document-requirement.{$action}") || $r->user()?->hasRole('super_admin'), 403);
    }

    public function index(Request $r): Response
    {
        $this->allow($r, 'view');
        $workflow = app(ApprovalWorkflowService::class);
        $rows = DocumentRequirementSet::with(['approvalRequests'])->withCount('items')->latest()->paginate(15)->through(function ($x) use ($workflow) {
            $approval = $x->approvalRequests->first();

            return ['id' => $x->id, 'code' => $x->code, 'name' => $x->name, 'types' => collect($x->application_types)->map(fn ($v) => str($v)->replace('_', ' ')->title())->join(', '), 'items_count' => $x->items_count, 'status' => $x->status, 'record_status' => $x->record_status, 'approval_status' => $approval?->status, 'approval_stage' => $approval?->status === 'pending' ? "{$approval->current_step}/{$approval->total_steps}" : null, 'can_review' => $approval ? $workflow->canReview($approval) : false];
        });

        return Inertia::render('Admin/DocumentRequirements/Index', ['title' => 'Paket Persyaratan Dokumen', 'baseUrl' => route('admin.document-sets.index', absolute: false), 'rows' => $rows]);
    }

    public function create(Request $r): Response
    {
        $this->allow($r, 'create');

        return $this->form();
    }

    public function edit(Request $r, DocumentRequirementSet $set): Response
    {
        $this->allow($r, 'update');
        abort_if($set->record_status === 'locked', 422);

        return $this->form($set->load(['items', 'banks', 'products', 'housings', 'companies', 'partnerships']));
    }

    public function store(Request $r): RedirectResponse
    {
        $this->allow($r, 'create');
        $data = $this->validated($r);
        DB::transaction(function () use ($data, $r) {
            $set = DocumentRequirementSet::create([...collect($data)->except(['items', 'bank_ids', 'product_ids', 'housing_ids', 'company_ids', 'partnership_ids'])->all(), 'created_by' => $r->user()->id, 'updated_by' => $r->user()->id]);
            $this->sync($set, $data);
        });

        return to_route('admin.document-sets.index')->with('success', 'Paket dokumen disimpan sebagai draf.');
    }

    public function update(Request $r, DocumentRequirementSet $set): RedirectResponse
    {
        $this->allow($r, 'update');
        abort_if($set->record_status === 'locked', 422);
        $data = $this->validated($r);
        DB::transaction(function () use ($set, $data, $r) {
            $set->update([...collect($data)->except(['items', 'bank_ids', 'product_ids', 'housing_ids', 'company_ids', 'partnership_ids'])->all(), 'updated_by' => $r->user()->id]);
            $this->sync($set, $data);
        });

        return to_route('admin.document-sets.index')->with('success', 'Paket dokumen diperbarui.');
    }

    public function lock(Request $r, DocumentRequirementSet $set, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->allow($r, 'update');
        abort_if($set->items()->count() === 0, 422, 'Paket belum memiliki dokumen.');
        $set->update(['record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $r->user()->id]);
        $workflow->submitLocked($set, 'document-requirement-set');

        return back()->with('success', 'Paket diajukan ke Setting Approval.');
    }

    public function unlock(Request $r, DocumentRequirementSet $set, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->allow($r, 'update');
        $workflow->cancelPendingLock($set);
        $set->update(['record_status' => 'draft', 'locked_at' => null, 'locked_by' => null]);

        return back()->with('success', 'Paket kembali menjadi draf.');
    }

    public function review(Request $r, DocumentRequirementSet $set, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $approval = ApprovalRequest::where(['model_type' => DocumentRequirementSet::class, 'model_id' => $set->id, 'status' => 'pending'])->latest()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403);
        $decision === 'approve' ? $workflow->approve($approval) : $workflow->reject($approval, $r->validate(['note' => 'required|string|max:1000'])['note']);

        return back()->with('success', 'Approval paket dokumen diproses.');
    }

    private function validated(Request $r): array
    {
        return $r->validate(['code' => 'required|string|max:50', 'name' => 'required|string|max:255', 'description' => 'nullable|string', 'application_types' => 'required|array|min:1', 'application_types.*' => 'in:spr,cash_bertahap,kpr_developer,kpr_bank', 'status' => 'required|in:aktif,nonaktif', 'bank_ids' => 'array', 'bank_ids.*' => 'exists:bank_kredits,id', 'product_ids' => 'array', 'product_ids.*' => 'exists:bank_credit_products,id', 'housing_ids' => 'array', 'housing_ids.*' => 'exists:perumahans,id', 'company_ids' => 'array', 'company_ids.*' => 'exists:cabang_perusahaans,id', 'partnership_ids' => 'array', 'partnership_ids.*' => 'exists:bank_housing_partnerships,id', 'items' => 'required|array|min:1', 'items.*.dokumen_costumer_id' => 'required|exists:dokumen_costumers,id', 'items.*.process_stage_code' => 'nullable|string|max:100', 'items.*.employment_categories' => 'array', 'items.*.marital_statuses' => 'array', 'items.*.party_scope' => 'required|in:customer,spouse,both', 'items.*.is_required' => 'boolean', 'items.*.validity_days' => 'nullable|integer|min:1', 'items.*.notes' => 'nullable|string']);
    }

    private function sync($set, $data): void
    {
        $set->banks()->sync($data['bank_ids'] ?? []);
        $set->products()->sync($data['product_ids'] ?? []);
        $set->housings()->sync($data['housing_ids'] ?? []);
        $set->companies()->sync($data['company_ids'] ?? []);
        $set->partnerships()->sync($data['partnership_ids'] ?? []);
        $set->items()->delete();
        foreach ($data['items'] as $i => $item) {
            $set->items()->create([...$item, 'sort_order' => $i]);
        }
    }

    private function form(?DocumentRequirementSet $set = null): Response
    {
        $opt = fn ($rows, $label) => $rows->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->{$label}]);
        $row = $set ? array_merge($set->toArray(), ['bank_ids' => $set->banks->modelKeys(), 'product_ids' => $set->products->modelKeys(), 'housing_ids' => $set->housings->modelKeys(), 'company_ids' => $set->companies->modelKeys(), 'partnership_ids' => $set->partnerships->modelKeys()]) : null;

        return Inertia::render('Admin/DocumentRequirements/Wizard', ['title' => ($set ? 'Edit' : 'Tambah').' Paket Persyaratan', 'indexUrl' => route('admin.document-sets.index', absolute: false), 'actionUrl' => $set ? route('admin.document-sets.update', $set, absolute: false) : route('admin.document-sets.store', absolute: false), 'method' => $set ? 'put' : 'post', 'row' => $row, 'options' => ['documents' => $opt(DokumenCostumer::query()->finalized()->where('status', 'aktif')->orderBy('nama_dokumen')->get(), 'nama_dokumen'), 'banks' => $opt(BankKredit::query()->finalized()->where('status', 'aktif')->orderBy('nama_bank')->get(), 'nama_bank'), 'products' => $opt(BankCreditProduct::query()->finalized()->where('status', 'aktif')->orderBy('product_name')->get(), 'product_name'), 'housings' => $opt(Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get(), 'nama_perusahaan'), 'companies' => $opt(CabangPerusahaan::query()->finalized()->orderBy('nama_cabang')->get(), 'nama_cabang'), 'partnerships' => BankHousingPartnership::query()->finalized()->with(['bank', 'housing'])->get()->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->agreement_number.' — '.$x->bank?->nama_bank.' — '.$x->housing?->nama_perusahaan])]]);
    }
}
