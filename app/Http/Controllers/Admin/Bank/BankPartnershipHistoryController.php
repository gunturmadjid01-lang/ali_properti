<?php

namespace App\Http\Controllers\Admin\Bank;

use App\Http\Controllers\Controller;
use App\Models\BankHousingPartnershipVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankPartnershipHistoryController extends Controller
{
    public function index(Request $r): Response
    {
        abort_unless($r->user()?->can('bank-partnership-history.view'), 403);
        $s = trim((string) $r->query('search', ''));
        $rows = BankHousingPartnershipVersion::with(['partnership.bank:id,nama_bank', 'partnership.housing:id,nama_perusahaan'])->when($s, fn (Builder $q) => $q->whereHas('partnership', fn (Builder $q) => $q->where('agreement_number', 'like', "%{$s}%")->orWhere('agreement_name', 'like', "%{$s}%")))->latest('id')->paginate(15)->withQueryString()->through(fn ($x) => ['id' => $x->id, 'partnership_id' => $x->bank_housing_partnership_id, 'version_number' => $x->version_number, 'bank_name' => $x->partnership?->bank?->nama_bank, 'housing_name' => $x->partnership?->housing?->nama_perusahaan, 'agreement_number' => $x->agreement_snapshot['agreement_number'] ?? '-', 'agreement_name' => $x->agreement_snapshot['agreement_name'] ?? '-', 'effective_from' => optional($x->effective_from)->format('Y-m-d'), 'effective_until' => optional($x->effective_until)->format('Y-m-d'), 'status' => $x->agreement_snapshot['status'] ?? '-', 'snapshot' => $x->agreement_snapshot, 'created_at' => $x->created_at?->format('d/m/Y H:i')]);

        return Inertia::render('Admin/Bank/PartnershipHistory/Index', ['title' => 'Riwayat / Versi Kerja Sama', 'baseUrl' => route('admin.bank-partnership-history.index', absolute: false), 'rows' => $rows, 'filters' => ['search' => $s]]);
    }
}
