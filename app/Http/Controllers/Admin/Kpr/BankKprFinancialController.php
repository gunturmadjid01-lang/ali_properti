<?php

namespace App\Http\Controllers\Admin\Kpr;

use App\Http\Controllers\Controller;
use App\Models\BankKprDisbursement;
use App\Models\BankKprFinancing;
use App\Models\KprSubmission;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BankKprFinancialController extends Controller
{
    private function allow(Request $r, string $p): void
    {
        abort_unless($r->user()?->can($p) || $r->user()?->hasRole('super_admin'), 403);
    }

    public function saveFinancing(Request $r, KprSubmission $submission): RedirectResponse
    {
        $this->allow($r, 'bank-kpr.financing.create');
        $d = $r->validate(['sale_price' => 'required|numeric|min:1', 'approved_limit' => 'required|numeric|min:0', 'tenor_months' => 'nullable|integer|min:1', 'interest_rate' => 'nullable|numeric|min:0', 'booking_fee' => 'required|numeric|min:0', 'down_payment' => 'required|numeric|min:0', 'shortfall' => 'required|numeric|min:0', 'developer_fee' => 'required|numeric|min:0', 'notary_fee' => 'required|numeric|min:0', 'expected_disbursement_date' => 'nullable|date', 'sp3k_number' => 'nullable|string|max:100', 'sp3k_date' => 'nullable|date', 'sp3k_expired_at' => 'nullable|date|after_or_equal:sp3k_date', 'notes' => 'nullable|string']);
        $f = $submission->financing;
        abort_if($f?->record_status === 'locked', 422, 'Struktur pembiayaan sudah dikunci.');
        $submission->financing()->updateOrCreate([], [$d, 'created_by' => $f?->created_by ?: $r->user()?->id, 'updated_by' => $r->user()?->id]);

        return back()->with('success', 'Struktur pembiayaan disimpan sebagai draf.');
    }

    public function lockFinancing(Request $r, BankKprFinancing $financing, ApprovalWorkflowService $w): RedirectResponse
    {
        $this->allow($r, 'bank-kpr.financing.submit');
        abort_unless($financing->record_status === 'draft', 422);
        abort_if(round((float) $financing->approved_limit + (float) $financing->down_payment + (float) $financing->shortfall, 2) < round((float) $financing->sale_price, 2), 422, 'Plafon + DP + kekurangan harus menutup harga jual.');
        $financing->update(['record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $r->user()?->id]);
        $a = $w->submitLocked($financing, 'bank-kpr-financing');

        return back()->with('success', $a->status === 'approved' ? 'Struktur disetujui dan tagihan internal dibuat.' : "Masuk approval tahap {$a->current_step}/{$a->total_steps}.");
    }

    public function storeDisbursement(Request $r, KprSubmission $submission): RedirectResponse
    {
        $this->allow($r, 'bank-kpr.disbursement.create');
        $d = $r->validate(['master_bank_id' => 'required|exists:master_banks,id', 'disbursement_date' => 'required|date', 'amount' => 'required|numeric|min:1', 'bank_reference' => 'required|string|max:100', 'proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', 'notes' => 'nullable|string']);
        $path = $r->file('proof')->store('bank-kpr-disbursements/'.now()->format('Y/m'), 'public');
        $row = $submission->disbursements()->create(['disbursement_no' => 'DISB/'.now()->format('Y').'/'.str_pad((string) (BankKprDisbursement::withTrashed()->count() + 1), 7, '0', STR_PAD_LEFT), 'master_bank_id' => $d['master_bank_id'], 'disbursement_date' => $d['disbursement_date'], 'amount' => $d['amount'], 'bank_reference' => $d['bank_reference'], 'proof_path' => $path, 'proof_original_name' => $r->file('proof')->getClientOriginalName(), 'notes' => $d['notes'] ?? null, 'created_by' => $r->user()?->id, 'updated_by' => $r->user()?->id]);

        return back()->with('success', 'Pencairan disimpan sebagai draf. Lock untuk approval.');
    }

    public function lockDisbursement(Request $r, BankKprDisbursement $disbursement, ApprovalWorkflowService $w): RedirectResponse
    {
        $this->allow($r, 'bank-kpr.disbursement.submit');
        abort_unless($disbursement->record_status === 'draft', 422);
        $disbursement->update(['record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $r->user()?->id]);
        $a = $w->submitLocked($disbursement, 'bank-kpr-disbursement');

        return back()->with('success', $a->status === 'approved' ? 'Pencairan disetujui dan diposting.' : "Pencairan masuk approval tahap {$a->current_step}/{$a->total_steps}.");
    }
}
