<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\QualityUpgradeAddendum;
use App\Models\QualityUpgradeContract;
use App\Services\ApprovalWorkflowService;
use App\Services\QualityUpgradeContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QualityUpgradeAddendumController extends Controller
{
    use HandlesCrudLock {
        lock as protected traitLock;
        unlock as protected traitUnlock;
    }

    public function store(Request $request, QualityUpgradeContract $qualityUpgrade): RedirectResponse
    {
        $this->allow('create');
        abort_unless(in_array($qualityUpgrade->business_status, ['active', 'completed'], true), 422, 'Kontrak belum aktif.');
        $data = $request->validate([
            'addendum_date' => 'required|date', 'reason' => 'required|string|min:10|max:3000',
            'value_change' => 'required|numeric|min:0', 'finish_date_change' => 'nullable|date',
            'billing_due_date' => 'nullable|date',
        ]);
        abort_if((float) $data['value_change'] > 0 && empty($data['billing_due_date']), 422, 'Jatuh tempo tagihan addendum wajib diisi.');
        abort_if((float) $data['value_change'] <= 0 && empty($data['finish_date_change']), 422, 'Addendum harus mengubah nilai kontrak atau tanggal selesai.');
        $sequence = QualityUpgradeAddendum::query()->where('quality_upgrade_contract_id', $qualityUpgrade->id)->count() + 1;
        $qualityUpgrade->addenda()->create($data + [
            'addendum_no' => $qualityUpgrade->contract_no.'/ADD/'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
            'change_snapshot' => ['before_value' => (float) $qualityUpgrade->contract_value, 'before_finish_date' => optional($qualityUpgrade->planned_finish_date)->format('Y-m-d'), 'document_version' => $qualityUpgrade->document_version],
            'created_by' => auth()->id(), 'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Draft addendum dibuat. Finalisasi untuk mengajukan approval.');
    }

    public function destroy(QualityUpgradeAddendum $addendum): RedirectResponse
    {
        $this->allow('delete');
        abort_unless($addendum->record_status === 'draft', 422, 'Hanya draft addendum yang dapat dihapus.');
        $addendum->delete();
        return back()->with('success', 'Draft addendum dihapus.');
    }

    public function lock(string $id): RedirectResponse { $this->allow('lock'); return $this->traitLock($id); }
    public function unlock(string $id): RedirectResponse { $this->allow('unlock'); return $this->traitUnlock($id); }

    public function review(Request $request, QualityUpgradeAddendum $addendum, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $approval = ApprovalRequest::query()->where(['model_type' => QualityUpgradeAddendum::class, 'model_id' => $addendum->id, 'status' => ApprovalRequest::STATUS_PENDING])->latest()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403);
        $decision === 'approve' ? $workflow->approve($approval) : $workflow->reject($approval, $request->validate(['note' => 'required|string|max:1000'])['note']);
        return back()->with('success', $decision === 'approve' ? 'Tahap addendum disetujui.' : 'Addendum ditolak.');
    }

    public function print(QualityUpgradeAddendum $addendum)
    {
        $this->allow('print');
        abort_unless($addendum->status === 'approved', 422, 'Addendum belum disetujui.');
        return response()->view('documents.quality-upgrade-addendum', ['addendum' => $addendum->load('contract.customer', 'contract.unit.perumahan', 'contract.company')]);
    }

    protected function modelClass(): string { return QualityUpgradeAddendum::class; }
    protected function beforeUnlock(QualityUpgradeAddendum $addendum): void { app(QualityUpgradeContractService::class)->reverseAddendum($addendum); }
    private function allow(string $action): void { abort_unless(auth()->user()?->hasRole('super_admin') || auth()->user()?->can("quality-upgrade-addendum.{$action}"), 403); }
}
