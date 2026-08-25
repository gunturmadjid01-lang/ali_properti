<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingLead;
use App\Models\SalesLeadImportBatch;
use App\Models\SalesLeadIntakeRow;
use App\Models\SalesWorkItem;
use App\Models\User;
use App\Services\AdminSalesLeadIntakeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSalesLeadIntakeController extends Controller
{
    public function __construct(private readonly AdminSalesLeadIntakeService $intake) {}

    public function importForm(Request $request): Response
    {
        $this->allow($request, 'admin-sales.lead.create');

        return Inertia::render('Admin/AdminSales/Leads/Import', [
            'title' => 'Import Lead Perusahaan',
            'batches' => SalesLeadImportBatch::query()->latest()->limit(15)->get(),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->allow($request, 'admin-sales.lead.create');
        $data = $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx']]);
        $file = $data['file'];
        $rows = $this->intake->read($file->getRealPath(), strtolower($file->getClientOriginalExtension()));
        abort_if(count($rows) > 5000, 422, 'Maksimal 5.000 lead per file.');
        $batch = $this->intake->import($rows, $request->user(), $file->getClientOriginalName());

        return redirect()->route('admin.admin-sales.leads.duplicates', ['batch_id' => $batch->id])
            ->with('success', "Import selesai: {$batch->imported_rows} masuk, {$batch->duplicate_rows} duplikat, {$batch->invalid_rows} tidak valid.");
    }

    public function template(Request $request): StreamedResponse
    {
        $this->allow($request, 'admin-sales.lead.create');

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['nama', 'telepon', 'email', 'nik', 'sumber', 'kanal', 'prioritas', 'catatan']);
            fputcsv($out, ['Contoh Customer', '081234567890', 'contoh@email.com', '', 'Website', 'website', 'normal', 'Hapus baris contoh sebelum import']);
            fclose($out);
        }, 'template-import-lead.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function duplicates(Request $request): Response
    {
        $this->allow($request, 'admin-sales.lead.verify');
        $status = (string) $request->query('status', 'duplicate');
        abort_unless(in_array($status, ['duplicate', 'invalid', 'imported', 'resolved_distinct', 'resolved_existing'], true), 422);
        $rows = SalesLeadIntakeRow::query()->where('status', $status)
            ->when($request->integer('batch_id'), fn (Builder $q, int $id) => $q->where('batch_id', $id))
            ->with(['duplicateLead:id,lead_no,name,phone,email,marketing_id,stage', 'duplicateLead.marketing:id,name', 'duplicateCustomer:id,kode_costumer,nama,telepon,email', 'lead:id,lead_no,name'])
            ->latest()->paginate(25)->withQueryString();

        $directOverrides = MarketingLead::query()->whereNotNull('possible_duplicate_lead_id')
            ->with(['marketing:id,name', 'possibleDuplicate:id,lead_no,name,phone,email,stage', 'duplicateChecker:id,name'])
            ->latest('duplicate_checked_at')->limit(100)->get();

        return Inertia::render('Admin/AdminSales/Leads/Duplicates', ['title' => 'Pusat Lead Duplikat', 'rows' => $rows, 'directOverrides' => $directOverrides, 'filters' => $request->only(['status', 'batch_id']) + ['status' => $status]]);
    }

    public function resolve(Request $request, SalesLeadIntakeRow $row): RedirectResponse
    {
        $this->allow($request, 'admin-sales.lead.verify');
        abort_unless(in_array($row->status, ['duplicate', 'invalid'], true), 422, 'Baris intake ini sudah diproses.');
        $data = $request->validate(['decision' => ['required', Rule::in(['existing', 'distinct', 'discard'])], 'reason' => ['required', 'string', 'min:5', 'max:1000'], 'telepon' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'], 'nik' => ['nullable', 'string', 'max:50']]);
        if ($data['decision'] === 'distinct') {
            $payload = $row->payload;
            $payload['telepon'] = $request->input('telepon', $row->phone);
            $payload['email'] = $request->input('email', $row->email);
            $payload['nik'] = $request->input('nik', $payload['nik'] ?? $payload['no_identitas'] ?? null);
            $new = $this->intake->ingest($payload, 'duplicate_resolution', $request->user());
            abort_if($new->status !== 'imported', 422, 'Data pembeda masih sama atau belum valid. Ubah telepon, email, atau NIK terlebih dahulu.');
            $row->update(['status' => 'resolved_distinct', 'marketing_lead_id' => $new->marketing_lead_id, 'validation_note' => $data['reason'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        } else {
            $row->update(['status' => $data['decision'] === 'existing' ? 'resolved_existing' : 'discarded', 'validation_note' => $data['reason'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        }

        return back()->with('success', 'Keputusan duplikat disimpan beserta alasan dan pemeriksa.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->allow($request, 'admin-sales.report.export');
        $query = MarketingLead::query()->where('ownership_type', 'company')->with(['source:id,nama_sumber', 'marketing:id,name', 'adminSales:id,name', 'perumahan:id,nama_perusahaan'])
            ->when($request->query('verification_status'), fn (Builder $q, string $x) => $q->where('verification_status', $x))
            ->when($request->query('assignment_status'), fn (Builder $q, string $x) => $q->where('assignment_status', $x))
            ->when($request->query('admin_sales_id'), fn (Builder $q, string $x) => $q->where('admin_sales_id', $x))
            ->when($request->query('marketing_id'), fn (Builder $q, string $x) => $q->where('marketing_id', $x))
            ->when($request->query('from'), fn (Builder $q, string $x) => $q->whereDate('created_at', '>=', $x))
            ->when($request->query('to'), fn (Builder $q, string $x) => $q->whereDate('created_at', '<=', $x));

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Kode', 'Nama', 'Telepon', 'Email', 'Sumber', 'Kanal', 'Perumahan', 'Marketing', 'Admin Sales', 'Verifikasi', 'Assignment', 'Prioritas', 'Diterima', 'Batas Respons', 'Kontak Pertama']);
            $query->orderBy('id')->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $x) {
                    fputcsv($out, [$x->lead_no, $x->name, $x->phone, $x->email, $x->source?->nama_sumber, $x->source_channel, $x->perumahan?->nama_perusahaan, $x->marketing?->name, $x->adminSales?->name, $x->verification_status, $x->assignment_status, $x->priority, $x->created_at, $x->first_response_due_at, $x->first_contacted_at]);
                }
            });
            fclose($out);
        }, 'lead-perusahaan-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function report(Request $request): Response
    {
        $this->allow($request, 'admin-sales.report.view');
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'admin_sales_id' => ['nullable', 'integer', 'exists:users,id']]);
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $lead = MarketingLead::query()->where('ownership_type', 'company')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->when($filters['admin_sales_id'] ?? null, fn (Builder $q, $id) => $q->where('admin_sales_id', $id));
        $work = SalesWorkItem::query()->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->when($filters['admin_sales_id'] ?? null, fn (Builder $q, $id) => $q->where('assigned_to', $id));
        $summary = [
            ['label' => 'Lead Perusahaan', 'value' => (clone $lead)->count()],
            ['label' => 'Lead Terverifikasi', 'value' => (clone $lead)->where('verification_status', 'verified')->count()],
            ['label' => 'Lead Duplikat', 'value' => SalesLeadIntakeRow::query()->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->where('status', 'duplicate')->count()],
            ['label' => 'SLA Terlambat', 'value' => (clone $lead)->whereNull('first_contacted_at')->where('first_response_due_at', '<', now())->count()],
            ['label' => 'Tugas Selesai', 'value' => (clone $work)->where('status', 'completed')->count()],
            ['label' => 'Tugas Terlambat', 'value' => (clone $work)->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now())->count()],
        ];
        $byAdmin = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin_sales'))->withCount([
            'assignedSalesWorkItems as total_tasks' => fn ($q) => $q->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']),
            'assignedSalesWorkItems as completed_tasks' => fn ($q) => $q->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->where('status', 'completed'),
        ])->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/AdminSales/Reports/Index', ['title' => 'Laporan Admin Sales', 'summary' => $summary, 'byAdmin' => $byAdmin, 'filters' => compact('from', 'to') + ['admin_sales_id' => $filters['admin_sales_id'] ?? ''], 'adminSales' => User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin_sales'))->orderBy('name')->get(['id', 'name'])]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $configuredKey = (string) config('services.sales_lead_webhook.key');
        abort_if($configuredKey === '' || ! hash_equals($configuredKey, (string) $request->header('X-Lead-Key')), 401);
        $idempotencyKey = (string) $request->header('Idempotency-Key');
        abort_if($idempotencyKey === '', 422, 'Header Idempotency-Key wajib diisi.');
        $payload = $request->validate(['nama' => ['required', 'string', 'max:255'], 'telepon' => ['required', 'string', 'max:30'], 'email' => ['nullable', 'email'], 'nik' => ['nullable', 'string', 'max:50'], 'sumber' => ['required', 'string', 'max:100'], 'kanal' => ['nullable', 'string', 'max:50'], 'prioritas' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])], 'catatan' => ['nullable', 'string', 'max:2000']]);
        $row = $this->intake->ingest($payload, 'api', null, hash('sha256', $idempotencyKey));

        return response()->json(['intake_id' => $row->id, 'status' => $row->status, 'lead_id' => $row->marketing_lead_id, 'duplicate_lead_id' => $row->duplicate_marketing_lead_id, 'duplicate_customer_id' => $row->duplicate_costumer_id], $row->wasRecentlyCreated ? 201 : 200);
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasRole('super_admin') || $request->user()?->can($permission), 403);
    }
}
