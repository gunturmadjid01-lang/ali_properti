<?php

namespace App\Http\Controllers\Admin\Bank;

use App\Http\Controllers\Concerns\HandlesBankMasterApproval;
use App\Http\Controllers\Controller;
use App\Models\BankBranch;
use App\Models\BankKredit;
use App\Services\ApprovalWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BankBranchController extends Controller
{
    use HandlesBankMasterApproval;

    public function index(Request $r): Response
    {
        $this->auth($r, 'view');
        $s = trim((string) $r->query('search', ''));
        $workflow = app(ApprovalWorkflowService::class);
        $rows = BankBranch::with('bank:id,nama_bank')->when($s, fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('branch_code', 'like', "%{$s}%")->orWhere('branch_name', 'like', "%{$s}%")->orWhere('city', 'like', "%{$s}%")))->latest('id')->paginate(10)->withQueryString()->through(fn ($x) => [...$x->only(['id', 'bank_kredit_id', 'branch_code', 'branch_name', 'address', 'city', 'pic_name', 'pic_position', 'phone', 'email', 'status']), ...$this->approvalState($x, $workflow), 'bank_name' => $x->bank?->nama_bank]);

        return Inertia::render('Admin/Bank/Branch/Index', ['title' => 'Cabang Bank', 'baseUrl' => route('admin.bank-branch.index', absolute: false), 'rows' => $rows, 'filters' => ['search' => $s], 'banks' => $this->banks(), 'permissions' => $this->perms($r)]);
    }

    public function create(Request $r): Response
    {
        $this->auth($r, 'create');

        return $this->formResponse();
    }

    public function edit(Request $r, BankBranch $branch): Response
    {
        $this->auth($r, 'update');
        $this->abortWhenFinalized($branch);

        return $this->formResponse($branch);
    }

    private function formResponse(?BankBranch $branch = null): Response
    {
        return Inertia::render('Admin/Bank/FormPage', [
            'title' => ($branch ? 'Ubah' : 'Tambah').' Cabang Bank', 'kind' => 'branch',
            'baseUrl' => route('admin.bank-branch.index', absolute: false),
            'actionUrl' => $branch ? route('admin.bank-branch.update', $branch, false) : route('admin.bank-branch.store', absolute: false),
            'method' => $branch ? 'put' : 'post', 'row' => $branch?->toArray(),
            'options' => ['banks' => $this->banks()],
        ]);
    }

    public function store(Request $r, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->auth($r, 'create');

        return $workflow->create('bank-branch', $this->payload($r), fn (array $data) => BankBranch::create($data));
    }

    public function update(Request $r, BankBranch $branch): RedirectResponse
    {
        $this->auth($r, 'update');
        $this->abortWhenFinalized($branch);
        $branch->update($this->payload($r, $branch));

        return back()->with('success', 'Cabang bank berhasil diperbarui.');
    }

    public function destroy(Request $r, BankBranch $branch): RedirectResponse
    {
        $this->auth($r, 'delete');
        $this->abortWhenFinalized($branch);
        $branch->delete();

        return back()->with('success', 'Cabang bank berhasil dihapus.');
    }

    private function payload(Request $r, ?BankBranch $x = null): array
    {
        return $r->validate(['bank_kredit_id' => ['required', 'exists:bank_kredits,id'], 'branch_code' => ['required', 'string', 'max:50', Rule::unique('bank_branches')->where(fn ($q) => $q->where('bank_kredit_id', $r->bank_kredit_id))->ignore($x)], 'branch_name' => ['required', 'string', 'max:255'], 'address' => ['nullable', 'string'], 'city' => ['nullable', 'string', 'max:100'], 'pic_name' => ['nullable', 'string', 'max:255'], 'pic_position' => ['nullable', 'string', 'max:100'], 'phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email', 'max:255'], 'status' => ['required', Rule::in(['aktif', 'nonaktif'])]]);
    }

    private function banks()
    {
        return BankKredit::query()->finalized()->where('status', 'aktif')->orderBy('nama_bank')->get()->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->nama_bank]);
    }

    private function auth(Request $r, string $a): void
    {
        abort_unless($r->user()?->can("bank-branch.{$a}"), 403);
    }

    private function perms(Request $r): array
    {
        return ['create' => $r->user()?->can('bank-branch.create'), 'update' => $r->user()?->can('bank-branch.update'), 'delete' => $r->user()?->can('bank-branch.delete'), 'submit' => $r->user()?->can('bank-branch.submit')];
    }

    protected function approvalModelClass(): string
    {
        return BankBranch::class;
    }

    protected function approvalModuleKey(): string
    {
        return 'bank-branch';
    }

    protected function approvalPermissionKey(): string
    {
        return 'bank-branch';
    }
}
