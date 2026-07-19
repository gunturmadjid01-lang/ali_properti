<?php

namespace App\Http\Controllers\Admin\Bank;

use App\Http\Controllers\Concerns\HandlesBankMasterApproval;
use App\Http\Controllers\Controller;
use App\Models\BankBranch;
use App\Models\BankHousingPartnership;
use App\Models\BankKredit;
use App\Models\Perumahan;
use App\Services\ApprovalWorkflowService;
use App\Services\BankPartnershipService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BankHousingPartnershipController extends Controller
{
    use HandlesBankMasterApproval;

    public function index(Request $r): Response
    {
        $this->auth($r, 'view');
        $s = trim((string) $r->query('search', ''));
        $workflow = app(ApprovalWorkflowService::class);
        $rows = BankHousingPartnership::with(['bank:id,nama_bank', 'branch:id,branch_name', 'housing:id,nama_perusahaan'])->when($s, fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('agreement_number', 'like', "%{$s}%")->orWhere('agreement_name', 'like', "%{$s}%")))->latest('id')->paginate(10)->withQueryString()->through(fn ($x) => [...$x->only(['id', 'bank_kredit_id', 'bank_branch_id', 'perumahan_id', 'agreement_number', 'agreement_name', 'effective_from', 'effective_until', 'current_version', 'status', 'notes']), ...$this->approvalState($x, $workflow), 'bank_name' => $x->bank?->nama_bank, 'branch_name' => $x->branch?->branch_name, 'housing_name' => $x->housing?->nama_perusahaan]);

        return Inertia::render('Admin/Bank/Partnership/Index', ['title' => 'Kerja Sama Bank dan Perumahan', 'baseUrl' => route('admin.bank-partnership.index', absolute: false), 'rows' => $rows, 'filters' => ['search' => $s], 'banks' => $this->banks(), 'branches' => $this->branches(), 'housings' => Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get()->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->nama_perusahaan]), 'permissions' => $this->perms($r)]);
    }

    public function create(Request $r): Response
    {
        $this->auth($r, 'create');

        return $this->formResponse();
    }

    public function edit(Request $r, BankHousingPartnership $partnership): Response
    {
        $this->auth($r, 'update');
        $this->abortWhenFinalized($partnership);

        return $this->formResponse($partnership);
    }

    private function formResponse(?BankHousingPartnership $partnership = null): Response
    {
        return Inertia::render('Admin/Bank/FormPage', [
            'title' => ($partnership ? 'Ubah' : 'Tambah').' Kerja Sama Bank', 'kind' => 'partnership',
            'baseUrl' => route('admin.bank-partnership.index', absolute: false),
            'actionUrl' => $partnership ? route('admin.bank-partnership.update', $partnership, false) : route('admin.bank-partnership.store', absolute: false),
            'method' => $partnership ? 'put' : 'post', 'row' => $partnership?->toArray(),
            'options' => ['banks' => $this->banks(), 'branches' => $this->branches(), 'housings' => Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get()->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->nama_perusahaan])],
        ]);
    }

    public function store(Request $r, BankPartnershipService $svc, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->auth($r, 'create');
        $payload = $this->payload($r);

        return $workflow->create('bank-housing-partnership', $payload, function (array $data) use ($svc) {
            DB::transaction(function () use ($data, $svc) {
                $x = BankHousingPartnership::create($data);
                $svc->createVersion($x);
            });
        });
    }

    public function update(Request $r, BankHousingPartnership $partnership, BankPartnershipService $svc): RedirectResponse
    {
        $this->auth($r, 'update');
        $this->abortWhenFinalized($partnership);
        DB::transaction(fn () => $svc->updateWithVersion($partnership, $this->payload($r, $partnership)));

        return back()->with('success', 'Kerja sama diperbarui dan versi riwayat dibuat.');
    }

    public function destroy(Request $r, BankHousingPartnership $partnership): RedirectResponse
    {
        $this->auth($r, 'delete');
        $this->abortWhenFinalized($partnership);
        $partnership->delete();

        return back()->with('success', 'Kerja sama berhasil dihapus.');
    }

    private function payload(Request $r, ?BankHousingPartnership $x = null): array
    {
        return $r->validate(['bank_kredit_id' => ['required', 'exists:bank_kredits,id'], 'bank_branch_id' => ['nullable', Rule::exists('bank_branches', 'id')->where(fn ($q) => $q->where('bank_kredit_id', $r->bank_kredit_id))], 'perumahan_id' => ['required', 'exists:perumahans,id'], 'agreement_number' => ['required', 'string', 'max:100', Rule::unique('bank_housing_partnerships')->where(fn ($q) => $q->where('bank_kredit_id', $r->bank_kredit_id)->where('perumahan_id', $r->perumahan_id))->ignore($x)], 'agreement_name' => ['required', 'string', 'max:255'], 'effective_from' => ['required', 'date'], 'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'], 'status' => ['required', Rule::in(['aktif', 'nonaktif'])], 'notes' => ['nullable', 'string']]);
    }

    private function banks()
    {
        return BankKredit::query()->finalized()->where('status', 'aktif')->orderBy('nama_bank')->get()->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->nama_bank]);
    }

    private function branches()
    {
        return BankBranch::query()->finalized()->where('status', 'aktif')->orderBy('branch_name')->get()->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->branch_name, 'bank_id' => (string) $x->bank_kredit_id]);
    }

    private function auth(Request $r, string $a): void
    {
        abort_unless($r->user()?->can("bank-housing-partnership.{$a}"), 403);
    }

    private function perms(Request $r): array
    {
        return ['create' => $r->user()?->can('bank-housing-partnership.create'), 'update' => $r->user()?->can('bank-housing-partnership.update'), 'delete' => $r->user()?->can('bank-housing-partnership.delete'), 'submit' => $r->user()?->can('bank-housing-partnership.submit')];
    }

    protected function approvalModelClass(): string
    {
        return BankHousingPartnership::class;
    }

    protected function approvalModuleKey(): string
    {
        return 'bank-housing-partnership';
    }

    protected function approvalPermissionKey(): string
    {
        return 'bank-housing-partnership';
    }
}
