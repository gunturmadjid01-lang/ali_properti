<?php

namespace App\Http\Controllers\Admin\Bank;

use App\Http\Controllers\Concerns\HandlesBankMasterApproval;
use App\Http\Controllers\Controller;
use App\Models\BankBranch;
use App\Models\BankCreditProduct;
use App\Models\BankKredit;
use App\Services\ApprovalWorkflowService;
use App\Services\BankCreditProductService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BankCreditProductController extends Controller
{
    use HandlesBankMasterApproval;

    public function index(Request $r): Response
    {
        $this->auth($r, 'view');
        $s = trim((string) $r->query('search', ''));
        $workflow = app(ApprovalWorkflowService::class);
        $rows = BankCreditProduct::with(['bank:id,nama_bank', 'branch:id,branch_name'])->when($s, fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('product_code', 'like', "%{$s}%")->orWhere('product_name', 'like', "%{$s}%")))->latest('id')->paginate(10)->withQueryString()->through(fn ($x) => [...$x->only(BankCreditProductService::VERSIONED_FIELDS), ...$x->only(['id', 'current_version']), ...$this->approvalState($x, $workflow), 'bank_name' => $x->bank?->nama_bank, 'branch_name' => $x->branch?->branch_name]);

        return Inertia::render('Admin/Bank/Product/Index', ['title' => 'Produk Kredit Bank', 'baseUrl' => route('admin.bank-product.index', absolute: false), 'rows' => $rows, 'filters' => ['search' => $s], 'banks' => $this->banks(), 'branches' => $this->branches(), 'permissions' => $this->perms($r)]);
    }

    public function create(Request $r): Response
    {
        $this->auth($r, 'create');

        return $this->formResponse();
    }

    public function edit(Request $r, BankCreditProduct $product): Response
    {
        $this->auth($r, 'update');
        $this->abortWhenFinalized($product);

        return $this->formResponse($product);
    }

    private function formResponse(?BankCreditProduct $product = null): Response
    {
        return Inertia::render('Admin/Bank/FormPage', [
            'title' => ($product ? 'Ubah' : 'Tambah').' Produk Kredit Bank', 'kind' => 'product',
            'baseUrl' => route('admin.bank-product.index', absolute: false),
            'actionUrl' => $product ? route('admin.bank-product.update', $product, false) : route('admin.bank-product.store', absolute: false),
            'method' => $product ? 'put' : 'post', 'row' => $product?->toArray(),
            'options' => ['banks' => $this->banks(), 'branches' => $this->branches()],
        ]);
    }

    public function store(Request $r, BankCreditProductService $svc, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->auth($r, 'create');
        $payload = $this->payload($r);

        return $workflow->create('bank-credit-product', $payload, function (array $data) use ($svc) {
            DB::transaction(function () use ($data, $svc) {
                $p = BankCreditProduct::create($data);
                $svc->createVersion($p);
            });
        });
    }

    public function update(Request $r, BankCreditProduct $product, BankCreditProductService $svc): RedirectResponse
    {
        $this->auth($r, 'update');
        $this->abortWhenFinalized($product);
        DB::transaction(fn () => $svc->updateWithVersion($product, $this->payload($r, $product)));

        return back()->with('success', 'Produk diperbarui dan ketentuan baru disimpan sebagai versi.');
    }

    public function destroy(Request $r, BankCreditProduct $product): RedirectResponse
    {
        $this->auth($r, 'delete');
        $this->abortWhenFinalized($product);
        $product->delete();

        return back()->with('success', 'Produk kredit berhasil dihapus.');
    }

    private function payload(Request $r, ?BankCreditProduct $p = null): array
    {
        return $r->validate(['bank_kredit_id' => ['required', 'exists:bank_kredits,id'], 'bank_branch_id' => ['nullable', Rule::exists('bank_branches', 'id')->where(fn ($q) => $q->where('bank_kredit_id', $r->bank_kredit_id))], 'product_code' => ['required', 'string', 'max:50', Rule::unique('bank_credit_products')->ignore($p)], 'product_name' => ['required', 'string', 'max:255'], 'product_type' => ['required', 'string', 'max:100'], 'subsidy_type' => ['required', Rule::in(['subsidi', 'non_subsidi'])], 'scheme_type' => ['required', Rule::in(['konvensional', 'syariah'])], 'minimum_ceiling' => ['required', 'numeric', 'min:0'], 'maximum_ceiling' => ['required', 'numeric', 'gte:minimum_ceiling'], 'minimum_down_payment' => ['required', 'numeric', 'min:0'], 'maximum_tenor_months' => ['required', 'integer', 'min:1'], 'indicative_interest_margin' => ['required', 'numeric', 'min:0'], 'provision_fee' => ['nullable', 'numeric', 'min:0'], 'administration_fee' => ['nullable', 'numeric', 'min:0'], 'appraisal_fee' => ['nullable', 'numeric', 'min:0'], 'insurance_fee' => ['nullable', 'numeric', 'min:0'], 'notary_fee' => ['nullable', 'numeric', 'min:0'], 'disbursement_method' => ['required', Rule::in(['sekaligus', 'bertahap', 'berdasarkan_progress', 'sesuai_perjanjian'])], 'estimated_sla_days' => ['nullable', 'integer', 'min:1'], 'effective_from' => ['required', 'date'], 'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'], 'status' => ['required', Rule::in(['aktif', 'nonaktif'])], 'notes' => ['nullable', 'string']]);
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
        abort_unless($r->user()?->can("bank-credit-product.{$a}"), 403);
    }

    private function perms(Request $r): array
    {
        return ['create' => $r->user()?->can('bank-credit-product.create'), 'update' => $r->user()?->can('bank-credit-product.update'), 'delete' => $r->user()?->can('bank-credit-product.delete'), 'submit' => $r->user()?->can('bank-credit-product.submit')];
    }

    protected function approvalModelClass(): string
    {
        return BankCreditProduct::class;
    }

    protected function approvalModuleKey(): string
    {
        return 'bank-credit-product';
    }

    protected function approvalPermissionKey(): string
    {
        return 'bank-credit-product';
    }
}
