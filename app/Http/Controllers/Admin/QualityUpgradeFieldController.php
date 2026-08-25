<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\QualityUpgradeContract;
use App\Models\QualityUpgradeDefect;
use App\Models\QualityUpgradeHandover;
use App\Services\ApprovalWorkflowService;
use App\Services\QualityUpgradeContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QualityUpgradeFieldController extends Controller
{
    use HandlesCrudLock {
        lock as protected traitLock;
        unlock as protected traitUnlock;
    }

    public function saveHandover(Request $request, QualityUpgradeContract $qualityUpgrade): RedirectResponse
    {
        $this->allow('quality-upgrade-handover', 'create');
        abort_if($qualityUpgrade->handover?->record_status === 'locked', 422, 'Serah terima sudah difinalisasi.');
        $data = $request->validate([
            'handover_date' => 'required|date', 'notes' => 'nullable|string|max:3000',
            'checklist' => 'required|array', 'checklist.work_complete' => 'accepted',
            'checklist.site_clean' => 'accepted', 'checklist.documents_complete' => 'accepted',
            'customer_evidence' => 'nullable|image|max:6144', 'supervisor_evidence' => 'nullable|image|max:6144',
        ]);
        $handover = $qualityUpgrade->handover ?: new QualityUpgradeHandover(['quality_upgrade_contract_id' => $qualityUpgrade->id, 'created_by' => auth()->id()]);
        $handover->fill(['handover_date' => $data['handover_date'], 'final_progress_percent' => $qualityUpgrade->progress_percent, 'checklist' => $data['checklist'], 'notes' => $data['notes'] ?? null, 'updated_by' => auth()->id()]);
        if ($request->hasFile('customer_evidence')) {
            if ($handover->customer_evidence_path) Storage::disk('public')->delete($handover->customer_evidence_path);
            $handover->customer_evidence_path = $request->file('customer_evidence')->store('quality-upgrade-handover', 'public');
        }
        if ($request->hasFile('supervisor_evidence')) {
            if ($handover->supervisor_evidence_path) Storage::disk('public')->delete($handover->supervisor_evidence_path);
            $handover->supervisor_evidence_path = $request->file('supervisor_evidence')->store('quality-upgrade-handover', 'public');
        }
        $handover->save();
        return back()->with('success', 'Draft serah terima disimpan. Finalisasi untuk approval.');
    }

    public function storeDefect(Request $request, QualityUpgradeContract $qualityUpgrade): RedirectResponse
    {
        $this->allow('quality-upgrade-defect', 'create');
        $data = $request->validate(['quality_upgrade_contract_item_id' => 'nullable|exists:quality_upgrade_contract_items,id', 'reported_date' => 'required|date', 'severity' => 'required|in:minor,major,critical', 'description' => 'required|string|max:3000', 'target_date' => 'nullable|date', 'evidence' => 'nullable|image|max:6144']);
        if (! empty($data['quality_upgrade_contract_item_id'])) abort_unless($qualityUpgrade->items()->whereKey($data['quality_upgrade_contract_item_id'])->exists(), 422, 'Item bukan milik kontrak.');
        $data['evidence_path'] = $request->file('evidence')?->store('quality-upgrade-defects', 'public');
        unset($data['evidence']);
        $qualityUpgrade->defects()->create($data + ['defect_no' => 'DF-MUTU/'.now()->format('Ymd').'/'.str_pad((string) (QualityUpgradeDefect::count() + 1), 5, '0', STR_PAD_LEFT), 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
        return back()->with('success', 'Temuan pekerjaan dicatat.');
    }

    public function resolveDefect(Request $request, QualityUpgradeDefect $defect): RedirectResponse
    {
        $this->allow('quality-upgrade-defect', 'update');
        $data = $request->validate(['resolution_notes' => 'required|string|min:5|max:3000', 'resolution_evidence' => 'nullable|image|max:6144']);
        $path = $request->file('resolution_evidence')?->store('quality-upgrade-defects/resolution', 'public');
        $defect->update(['status' => 'resolved', 'resolution_notes' => $data['resolution_notes'], 'resolution_evidence_path' => $path ?: $defect->resolution_evidence_path, 'resolved_at' => now(), 'resolved_by' => auth()->id(), 'updated_by' => auth()->id()]);
        return back()->with('success', 'Defect ditandai selesai.');
    }

    public function destroyDefect(QualityUpgradeDefect $defect): RedirectResponse
    {
        $this->allow('quality-upgrade-defect', 'delete');
        abort_unless($defect->status === 'open', 422, 'Hanya temuan terbuka yang dapat dihapus.');
        if ($defect->evidence_path) Storage::disk('public')->delete($defect->evidence_path);
        $defect->delete();
        return back()->with('success', 'Temuan dihapus.');
    }

    public function lock(string $id): RedirectResponse { $this->allow('quality-upgrade-handover', 'lock'); return $this->traitLock($id); }
    public function unlock(string $id): RedirectResponse { $this->allow('quality-upgrade-handover', 'unlock'); return $this->traitUnlock($id); }
    public function review(Request $request, QualityUpgradeHandover $handover, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $approval = ApprovalRequest::query()->where(['model_type' => QualityUpgradeHandover::class, 'model_id' => $handover->id, 'status' => ApprovalRequest::STATUS_PENDING])->latest()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403);
        $decision === 'approve' ? $workflow->approve($approval) : $workflow->reject($approval, $request->validate(['note' => 'required|string|max:1000'])['note']);
        return back()->with('success', $decision === 'approve' ? 'Serah terima disetujui.' : 'Serah terima ditolak.');
    }
    protected function modelClass(): string { return QualityUpgradeHandover::class; }
    protected function beforeUnlock(QualityUpgradeHandover $handover): void { app(QualityUpgradeContractService::class)->reverseHandover($handover); }
    private function allow(string $module, string $action): void { abort_unless(auth()->user()?->hasRole('super_admin') || auth()->user()?->can("{$module}.{$action}"), 403); }
}
