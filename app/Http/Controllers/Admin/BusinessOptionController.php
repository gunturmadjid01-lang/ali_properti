<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\BankBranch;
use App\Models\BankCreditProduct;
use App\Models\BankHousingPartnership;
use App\Models\DetailRumah;
use App\Models\DocumentRequirementSet;
use App\Models\Perumahan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessOptionController extends Controller
{
    use ScopesActivePerumahan;

    public function housings(Request $request): JsonResponse
    {
        $this->authorizeOptions($request);
        $data = $request->validate(['branch_id' => 'required|integer|exists:cabang_perusahaans,id', 'search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $query = Perumahan::query()->finalized()->where('cabang_id', $data['branch_id'])->where('status', 'aktif');
        $this->scopeAllowedHousing($query, $request);

        return $this->paginate($query->when($data['search'] ?? null, fn ($q, $v) => $q->where('nama_perusahaan', 'like', "%{$v}%"))->orderBy('nama_perusahaan'), fn ($r) => ['value' => (string) $r->id, 'label' => $r->nama_perusahaan]);
    }

    public function units(Request $request): JsonResponse
    {
        $this->authorizeOptions($request);
        $data = $request->validate(['housing_project_id' => 'required|integer|exists:perumahans,id', 'block' => 'nullable|string|max:50', 'purpose' => 'nullable|in:reservation,spr,handover,general', 'search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $this->ensurePerumahanAllowed($request, (int) $data['housing_project_id']);
        $query = DetailRumah::query()->finalized()->where('perumahan_id', $data['housing_project_id'])->where('status', 'aktif')
            ->when($data['block'] ?? null, fn ($q, $v) => $q->where('kode_nlok', $v));
        if (in_array($data['purpose'] ?? 'general', ['reservation', 'spr'], true)) {
            $query->where(fn ($q) => $q->whereNull('status_penjualan')->orWhereIn('status_penjualan', ['tersedia', 'available', 'released']));
        }
        $query->when($data['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('kode_nlok', 'like', "%{$v}%")->orWhere('nomor_rumah', 'like', "%{$v}%")->orWhere('tipe_rumah', 'like', "%{$v}%")));

        return $this->paginate($query->orderBy('kode_nlok')->orderBy('nomor_rumah'), fn ($r) => ['value' => (string) $r->id, 'label' => trim($r->kode_nlok.'-'.$r->nomor_rumah).' — Type '.$r->tipe_rumah.' — '.($r->status_penjualan ?: 'Tersedia'), 'housing_project_id' => (string) $r->perumahan_id]);
    }

    public function creditBanks(Request $request): JsonResponse
    {
        $this->authorizeOptions($request);
        $data = $this->housingRequest($request);
        $query = BankHousingPartnership::query()->finalized()->with('bank:id,kode_bank,nama_bank,status')->where('perumahan_id', $data['housing_project_id'])->where('status', 'aktif')->whereDate('effective_from', '<=', today())->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))->whereHas('bank', fn ($q) => $q->finalized()->where('status', 'aktif'));
        $rows = $query->get()->unique('bank_kredit_id')->map(fn ($p) => ['value' => (string) $p->bank_kredit_id, 'label' => $p->bank->kode_bank.' — '.$p->bank->nama_bank, 'partnership_id' => (string) $p->id])->values();

        return response()->json(['data' => $rows, 'meta' => ['total' => $rows->count()]]);
    }

    public function bankBranches(Request $request): JsonResponse
    {
        $this->authorizeOptions($request);
        $data = $request->validate(['credit_bank_id' => 'required|integer|exists:bank_kredits,id', 'housing_project_id' => 'required|integer|exists:perumahans,id']);
        $this->ensurePerumahanAllowed($request, (int) $data['housing_project_id']);
        $branchIds = BankHousingPartnership::query()->finalized()->where($this->activePartnership($data))->whereDate('effective_from', '<=', today())->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))->whereNotNull('bank_branch_id')->pluck('bank_branch_id');
        $rows = BankBranch::query()->finalized()->where('bank_kredit_id', $data['credit_bank_id'])->where('status', 'aktif')->whereIn('id', $branchIds)->orderBy('branch_name')->get()->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->branch_code.' — '.$r->branch_name]);

        return response()->json(['data' => $rows, 'meta' => ['total' => $rows->count()]]);
    }

    public function creditProducts(Request $request): JsonResponse
    {
        $this->authorizeOptions($request);
        $data = $request->validate(['credit_bank_id' => 'required|integer|exists:bank_kredits,id', 'credit_bank_branch_id' => 'nullable|integer|exists:bank_branches,id', 'housing_project_id' => 'required|integer|exists:perumahans,id', 'search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $this->ensurePerumahanAllowed($request, (int) $data['housing_project_id']);
        abort_unless(BankHousingPartnership::query()->finalized()->where($this->activePartnership($data))->whereDate('effective_from', '<=', today())->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))->exists(), 422, 'Bank tidak mempunyai kerja sama aktif dengan perumahan.');
        $query = BankCreditProduct::query()->finalized()->where('bank_kredit_id', $data['credit_bank_id'])->where('status', 'aktif')->whereDate('effective_from', '<=', today())->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))
            ->when($data['credit_bank_branch_id'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->whereNull('bank_branch_id')->orWhere('bank_branch_id', $v)))
            ->when($data['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('product_code', 'like', "%{$v}%")->orWhere('product_name', 'like', "%{$v}%")));

        return $this->paginate($query->orderBy('product_name'), fn ($r) => ['value' => (string) $r->id, 'label' => $r->product_code.' — '.$r->product_name.' — max '.$r->maximum_tenor_months.' bulan', 'bank_id' => (string) $r->bank_kredit_id, 'branch_id' => $r->bank_branch_id ? (string) $r->bank_branch_id : null, 'version' => $r->current_version]);
    }

    public function documentRequirements(Request $request): JsonResponse
    {
        $this->authorizeOptions($request);
        $data = $request->validate(['credit_product_id' => 'required|integer|exists:bank_credit_products,id', 'housing_project_id' => 'required|integer|exists:perumahans,id', 'process' => 'nullable|in:customer,kpr,akad,pencairan']);
        $this->ensurePerumahanAllowed($request, (int) $data['housing_project_id']);
        $product = BankCreditProduct::query()->finalized()->where('status', 'aktif')->findOrFail($data['credit_product_id']);
        $partnership = BankHousingPartnership::query()->finalized()->where($this->activePartnership(['credit_bank_id' => $product->bank_kredit_id, 'housing_project_id' => $data['housing_project_id'], 'credit_bank_branch_id' => $product->bank_branch_id]))->whereDate('effective_from', '<=', today())->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))->first();
        abort_unless($partnership, 422, 'Produk tidak berlaku untuk perumahan ini.');
        $housing = Perumahan::query()->findOrFail($data['housing_project_id']);
        $sets = DocumentRequirementSet::query()->with(['items.document', 'banks:id', 'products:id', 'housings:id', 'companies:id', 'partnerships:id'])
            ->where('status', 'aktif')->where('record_status', 'locked')->whereHas('approvalRequests', fn ($q) => $q->where('status', 'approved'))->get()
            ->filter(function ($set) use ($product, $housing, $partnership) {
                if ($set->application_types && ! in_array('kpr_bank', $set->application_types, true)) {
                    return false;
                }

                return (! $set->banks->count() || $set->banks->contains('id', $product->bank_kredit_id))
                    && (! $set->products->count() || $set->products->contains('id', $product->id))
                    && (! $set->housings->count() || $set->housings->contains('id', $housing->id))
                    && (! $set->companies->count() || $set->companies->contains('id', $housing->cabang_id))
                    && (! $set->partnerships->count() || $set->partnerships->contains('id', $partnership->id));
            });
        $rows = $sets->flatMap(fn ($set) => $set->items->map(fn ($item) => ['value' => (string) $item->dokumen_costumer_id, 'code' => $item->document?->kode_dokumen, 'label' => $item->document?->nama_dokumen, 'required' => (bool) $item->is_required, 'validity_days' => $item->validity_days, 'employment_categories' => $item->employment_categories ?? [], 'marital_statuses' => $item->marital_statuses ?? [], 'party_scope' => $item->party_scope, 'source' => $set->name]))
            ->filter(fn ($row) => $row['code'] && $row['label'])->groupBy(fn ($row) => $row['value'].'|'.$row['party_scope'])->map(function ($group) {
                $row = $group->first();
                $row['required'] = $group->contains('required', true);
                $row['source'] = $group->pluck('source')->unique()->join(', ');

                return $row;
            })->values();

        return response()->json(['data' => $rows, 'meta' => ['total' => $rows->count()]]);
    }

    private function housingRequest(Request $request): array
    {
        $data = $request->validate(['housing_project_id' => 'required|integer|exists:perumahans,id']);
        $this->ensurePerumahanAllowed($request, (int) $data['housing_project_id']);

        return $data;
    }

    private function activePartnership(array $data): array
    {
        $where = ['bank_kredit_id' => $data['credit_bank_id'], 'perumahan_id' => $data['housing_project_id'], 'status' => 'aktif'];
        if (! empty($data['credit_bank_branch_id'])) {
            $where['bank_branch_id'] = $data['credit_bank_branch_id'];
        }

        return $where;
    }

    private function scopeAllowedHousing(Builder $query, Request $request): void
    {
        if ($this->shouldScopeToActivePerumahan($request)) {
            $query->whereIn('id', $this->assignedPerumahanIds($request));
        }
    }

    private function authorizeOptions(Request $request): void
    {
        abort_unless(collect(['booking.view', 'booking.create', 'booking.manage', 'kpr.view', 'kpr.create', 'bank-credit-master.view'])->contains(fn ($p) => $request->user()?->can($p)), 403);
    }

    private function paginate(Builder $query, callable $map): JsonResponse
    {
        $page = $query->paginate(20);

        return response()->json(['data' => collect($page->items())->map($map)->values(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }
}
