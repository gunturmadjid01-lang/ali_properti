<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\OperationTransactionArchive;
use App\Services\ApprovalWorkflowService;
use App\Services\PrintDataFormatter;
use App\Services\PrintTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OperationTransactionArchiveController extends Controller
{
    private const MAP = ['inventory' => ['receipts' => 'inventory_asset_receipts', 'loans' => 'inventory_loans', 'returns' => 'inventory_returns', 'transfers' => 'inventory_transfers', 'damages' => 'inventory_damage_reports', 'losses' => 'inventory_loss_reports', 'stock-opname' => 'inventory_stock_opnames'], 'heavy' => ['replacements' => 'heavy_component_replacements', 'usage' => 'heavy_equipment_usages', 'maintenance' => 'heavy_equipment_maintenances', 'damages' => 'heavy_equipment_damages', 'fuel' => 'heavy_equipment_fuelings']];

    public function submit(Request $r, string $module, string $section, string $id)
    {
        $this->authorizeAction($module, 'create');
        [, $row] = $this->record($module, $section, $id);
        $archive = $this->archive($module, $section, $row);
        abort_unless(in_array($archive->status, ['draft', 'rejected']), 422, 'Dokumen sudah diajukan atau disetujui.');
        $model = OperationTransactionArchive::findOrFail($archive->id);
        $model->update(['status' => 'submitted', 'submitted_by' => $r->user()->id, 'submitted_at' => now(), 'approved_by' => null, 'approved_at' => null, 'rejected_by' => null, 'rejected_at' => null, 'approval_notes' => null]);
        $approval = app(ApprovalWorkflowService::class)->submitLocked($model, "{$module}-{$section}");

        return back()->with('success', $approval->status === 'approved' ? 'Transaksi disetujui otomatis sesuai Setting Approval.' : "Transaksi masuk approval tahap 1 dari {$approval->total_steps}.");
    }

    public function decide(Request $r, string $module, string $section, string $id)
    {
        $data = $r->validate(['action' => ['required', Rule::in(['approve', 'reject'])], 'notes' => 'nullable|string|max:2000']);
        [, $row] = $this->record($module, $section, $id);
        $archive = $this->archive($module, $section, $row);
        $approval = ApprovalRequest::query()->where(['module_key' => "{$module}-{$section}", 'action' => 'lock', 'model_type' => OperationTransactionArchive::class, 'model_id' => $archive->id, 'status' => 'pending'])->latest('id')->firstOrFail();
        $service = app(ApprovalWorkflowService::class);
        $data['action'] === 'approve' ? $service->approve($approval) : $service->reject($approval, $data['notes'] ?? null);

        return back()->with('success', $data['action'] === 'approve' ? 'Tahap approval diproses.' : 'Arsip transaksi ditolak.');
    }

    public function print(Request $r, string $module, string $section, string $id, PrintTemplateService $templates, PrintDataFormatter $formatter)
    {
        $this->authorizeAction($module, 'print');
        [, $row] = $this->record($module, $section, $id);
        $archive = $this->archive($module, $section, $row);
        $official = $archive->status === 'approved';
        if ($official) {
            DB::table('operation_transaction_archives')->where('id', $archive->id)->update(['last_printed_by' => $r->user()->id, 'last_printed_at' => now(), 'print_count' => DB::raw('print_count + 1'), 'updated_at' => now()]);
            $archive = DB::table('operation_transaction_archives')->find($archive->id);
        } $users = DB::table('users')->whereIn('id', array_filter([$archive->responsible_user_id, $archive->approved_by, $r->user()->id]))->pluck('name', 'id');
        $printConfig = $templates->resolve($module.'.transaction');

        return Pdf::loadView('reports.operation-transaction', ['module' => $module, 'section' => $section, 'rows' => $formatter->rows($row), 'archive' => $archive, 'responsible' => $users[$archive->responsible_user_id] ?? '-', 'approver' => $users[$archive->approved_by] ?? '-', 'printer' => $r->user()->name, 'official' => $official, 'printedAt' => now()->format('d/m/Y H:i'), 'printConfig' => $printConfig])->setPaper($templates->domPdfPaper($printConfig), $printConfig['orientation'])->stream($archive->document_no.'.pdf');
    }

    private function record(string $m, string $s, string $id): array
    {
        $table = self::MAP[$m][$s] ?? abort(404);
        $row = DB::table($table)->where('id', $id)->whereNull('deleted_at')->first();
        abort_if(! $row, 404);

        return [$table, $row];
    }

    private function archive(string $m, string $s, object $row): object
    {
        $found = DB::table('operation_transaction_archives')->where(['module' => $m, 'section' => $s])->where('record_id', $row->id)->first();
        if ($found) {
            return $found;
        }$prefix = $m === 'inventory' ? 'AST' : 'ALT';
        $id = DB::table('operation_transaction_archives')->insertGetId(['module' => $m, 'section' => $s, 'record_id' => $row->id, 'document_no' => $prefix.'-'.strtoupper(str_replace('-', '', $s)).'-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT), 'status' => 'draft', 'responsible_user_id' => $row->created_by ?? auth()->id(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('operation_transaction_archives')->find($id);
    }

    private function authorizeAction(string $m, string $a): void
    {
        $key = $m === 'inventory' ? 'company-inventory' : 'heavy-equipment';
        $section = (string) request()->route('section');
        abort_unless(auth()->user()?->can("{$key}.{$section}.{$a}") || auth()->user()?->hasRole('super_admin'),403);
    }
}
