<?php

namespace App\Http\Controllers\Admin\Bank;

use App\Http\Controllers\Concerns\HandlesBankMasterApproval;
use App\Http\Controllers\Controller;
use App\Models\BankKredit;
use App\Services\ApprovalWorkflowService;
use App\Support\CodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BankCreditMasterController extends Controller
{
    use HandlesBankMasterApproval;

    public function index(Request $request): Response
    {
        $this->authorizeAction($request, 'view');
        $search = trim((string) $request->query('search', ''));
        $workflow = app(ApprovalWorkflowService::class);
        $rows = BankKredit::query()->when($search, fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('kode_bank', 'like', "%{$search}%")->orWhere('nama_bank', 'like', "%{$search}%")))->latest('id')->paginate(10)->withQueryString()->through(fn ($row) => [...$row->only(['id', 'kode_bank', 'nama_bank', 'jenis_bank', 'alamat_pusat', 'website', 'nomor_telepon', 'email', 'catatan', 'status']), ...$this->approvalState($row, $workflow), 'logo_url' => $row->logo ? route('media', ['path' => $row->logo], false) : null]);

        return Inertia::render('Admin/Bank/Master/Index', ['title' => 'Master Bank Kredit', 'baseUrl' => route('admin.bank-master.index', absolute: false), 'rows' => $rows, 'filters' => ['search' => $search], 'permissions' => $this->permissions($request)]);
    }

    public function store(Request $request, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeAction($request, 'create');
        $payload = $this->payload($request);
        $payload['kode_bank'] = $payload['kode_bank'] ?: CodeGenerator::next(BankKredit::class, 'kode_bank', 'BNK');
        if ($request->hasFile('logo')) {
            $payload['logo'] = $request->file('logo')->store('bank-logos', 'public');
        }

        return $workflow->create('bank-credit-master', $payload, fn (array $data) => BankKredit::create($data));
    }

    public function create(Request $request): Response
    {
        $this->authorizeAction($request, 'create');

        return $this->formResponse(null);
    }

    public function edit(Request $request, BankKredit $bank): Response
    {
        $this->authorizeAction($request, 'update');
        $this->abortWhenFinalized($bank);

        return $this->formResponse($bank);
    }

    public function update(Request $request, BankKredit $bank): RedirectResponse
    {
        $this->authorizeAction($request, 'update');
        $this->abortWhenFinalized($bank);
        $payload = $this->payload($request, $bank);
        if ($request->hasFile('logo')) {
            if ($bank->logo) {
                Storage::disk('public')->delete($bank->logo);
            } $payload['logo'] = $request->file('logo')->store('bank-logos', 'public');
        }
        $bank->update($payload);

        return to_route('admin.bank-master.index')->with('success', 'Master bank kredit berhasil diperbarui.');
    }

    public function destroy(Request $request, BankKredit $bank): RedirectResponse
    {
        $this->authorizeAction($request, 'delete');
        $this->abortWhenFinalized($bank);
        $bank->delete();

        return back()->with('success', 'Master bank kredit berhasil dihapus.');
    }

    private function formResponse(?BankKredit $bank): Response
    {
        return Inertia::render('Admin/Bank/Master/Form', [
            'title' => $bank ? 'Edit Master Bank Kredit' : 'Tambah Master Bank Kredit',
            'indexUrl' => route('admin.bank-master.index', absolute: false),
            'actionUrl' => $bank ? route('admin.bank-master.update', $bank, absolute: false) : route('admin.bank-master.store', absolute: false),
            'method' => $bank ? 'put' : 'post',
            'bank' => $bank ? [...$bank->only(['id', 'kode_bank', 'nama_bank', 'jenis_bank', 'alamat_pusat', 'website', 'nomor_telepon', 'email', 'catatan', 'status']), 'logo_url' => $bank->logo ? route('media', ['path' => $bank->logo], false) : null] : null,
        ]);
    }

    private function payload(Request $request, ?BankKredit $bank = null): array
    {
        return $request->validate(['kode_bank' => ['nullable', 'string', 'max:50', Rule::unique('bank_kredits', 'kode_bank')->ignore($bank)], 'nama_bank' => ['required', 'string', 'max:255'], 'jenis_bank' => ['required', Rule::in(['konvensional', 'syariah'])], 'status' => ['required', Rule::in(['aktif', 'nonaktif'])], 'alamat_pusat' => ['nullable', 'string'], 'website' => ['nullable', 'url', 'max:255'], 'logo' => ['nullable', 'image', 'max:2048'], 'nomor_telepon' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email', 'max:255'], 'catatan' => ['nullable', 'string']]);
    }

    private function authorizeAction(Request $r, string $a): void
    {
        abort_unless($r->user()?->can("bank-credit-master.{$a}"), 403);
    }

    private function permissions(Request $r): array
    {
        return ['create' => $r->user()?->can('bank-credit-master.create'), 'update' => $r->user()?->can('bank-credit-master.update'), 'delete' => $r->user()?->can('bank-credit-master.delete'), 'submit' => $r->user()?->can('bank-credit-master.submit')];
    }

    protected function approvalModelClass(): string
    {
        return BankKredit::class;
    }

    protected function approvalModuleKey(): string
    {
        return 'bank-credit-master';
    }

    protected function approvalPermissionKey(): string
    {
        return 'bank-credit-master';
    }
}
