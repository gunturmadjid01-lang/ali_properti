<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\MarketingCampaign;
use App\Models\MarketingLeadSource;
use App\Models\MarketingReferenceOption;
use App\Models\Perumahan;
use App\Models\User;
use App\Services\Marketing\MarketingLeadStatusService;
use App\Services\Marketing\MarketingReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketingReportController extends Controller
{
    use ScopesActivePerumahan;

    public function show(Request $request, string $type, MarketingReportService $reports): Response
    {
        $this->authorizeReport($request);

        return Inertia::render('Admin/Marketing/Reports/Index', [
            'title' => 'Laporan '.$reports::TYPES[$type], 'reportType' => $type, 'reportTypes' => $reports::TYPES,
            'report' => $reports->report($type, $request), 'filters' => $this->filters($request), 'options' => $this->options($request),
            'canExport' => $request->user()->hasRole('super_admin') || $request->user()->can('marketing-report.export'),
        ]);
    }

    public function export(Request $request, string $type, string $format, MarketingReportService $reports): StreamedResponse|\Illuminate\Http\Response
    {
        $this->authorizeExport($request);
        abort_unless(in_array($format, ['excel', 'csv', 'pdf'], true), 404);
        $report = $reports->report($type, $request, false);
        $payload = ['title' => 'Laporan '.$report['title'], 'columns' => $report['columns'], 'rows' => $report['rows'], 'filters' => $this->filters($request), 'printedBy' => $request->user()->name, 'printedAt' => now()];
        if ($format === 'pdf') {
            return Pdf::loadView('reports.marketing', $payload)->setPaper('a4', 'landscape')->download('laporan-marketing-'.$type.'.pdf');
        }

        $extension = $format === 'excel' ? 'xls' : 'csv';

        return response()->streamDownload(function () use ($payload, $format): void {
            if ($format === 'excel') {
                echo view('reports.marketing', $payload)->render();

                return;
            }
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $payload['columns']);
            foreach ($payload['rows'] as $row) {
                fputcsv($stream, $row);
            }
            fclose($stream);
        }, 'laporan-marketing-'.$type.'.'.$extension, ['Content-Type' => $format === 'excel' ? 'application/vnd.ms-excel' : 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        return $request->only([
            'date_from', 'date_to', 'marketing_id', 'customer_id', 'perumahan_id', 'status',
            'activity_type', 'result', 'visit_type', 'visit_status', 'verification_status',
            'inactive_days', 'lead_source_id', 'campaign_id', 'payment_plan', 'interest_level',
            'unit_id', 'has_unit_interest',
        ]) + ['date_from' => $request->input('date_from', now()->startOfMonth()->toDateString()), 'date_to' => $request->input('date_to', now()->endOfMonth()->toDateString())];
    }

    private function options(Request $request): array
    {
        $viewAll = $request->user()->hasRole('super_admin') || $request->user()->can('marketing.activity.view-all');
        $activePerumahanId = $this->shouldScopeToActivePerumahan($request)
            ? $this->activePerumahanId($request)
            : null;

        $options = [
            'marketings' => User::query()->whereHas('roles', fn (Builder $q) => $q->whereIn('name', ['marketing', 'area_marketing']))->when(! $viewAll, fn (Builder $q) => $q->whereKey($request->user()->id))->when($activePerumahanId, fn (Builder $q, int $id) => $q->whereHas('perumahans', fn (Builder $q) => $q->whereKey($id)))->orderBy('name')->get(['id', 'name'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->name]),
            'customers' => Costumer::query()->when(! $viewAll, fn (Builder $q) => $q->where('assigned_marketing_id', $request->user()->id))->orderBy('nama')->limit(500)->get(['id', 'nama', 'kode_costumer'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->kode_costumer.' · '.$x->nama]),
            'perumahans' => Perumahan::query()->finalized()->when($activePerumahanId, fn (Builder $q, int $id) => $q->whereKey($id))->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->nama_perusahaan]),
            'leadSources' => MarketingLeadSource::query()->where('status', 'aktif')->orderBy('nama_sumber')->get(['id', 'nama_sumber'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->nama_sumber]),
            'campaigns' => MarketingCampaign::query()->when($activePerumahanId, fn (Builder $q, int $id) => $q->where('perumahan_id', $id))->orderByDesc('tanggal_mulai')->orderBy('nama_campaign')->limit(500)->get(['id', 'kode_campaign', 'nama_campaign'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => trim(($x->kode_campaign ? $x->kode_campaign.' - ' : '').$x->nama_campaign)]),
            'units' => DetailRumah::query()->when($request->filled('perumahan_id'), fn (Builder $q) => $q->where('perumahan_id', $request->integer('perumahan_id')))->when($activePerumahanId, fn (Builder $q, int $id) => $q->where('perumahan_id', $id))->orderBy('kode_nlok')->orderBy('nomor_rumah')->limit(500)->get(['id', 'kode_nlok', 'nomor_rumah', 'tipe_rumah'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => trim(($x->kode_nlok ? 'Blok '.$x->kode_nlok.' ' : '').($x->nomor_rumah ? 'No. '.$x->nomor_rumah : '').($x->tipe_rumah ? ' - '.$x->tipe_rumah : ''))]),
            'statuses' => MarketingLeadStatusService::statusOptions(),
            'paymentOptions' => MarketingReferenceOption::options('payment_plan', [['value' => 'kpr', 'label' => 'KPR'], ['value' => 'cash', 'label' => 'Cash'], ['value' => 'cash_installment', 'label' => 'Cash Bertahap']]),
            'interestOptions' => MarketingReferenceOption::options('interest_level', [['value' => 'cold', 'label' => 'Dingin'], ['value' => 'warm', 'label' => 'Hangat'], ['value' => 'hot', 'label' => 'Panas']]),
            'visitTypes' => MarketingReferenceOption::options('visit_type', [['value' => 'customer_location', 'label' => 'Lokasi Customer'], ['value' => 'office', 'label' => 'Kantor'], ['value' => 'housing_site', 'label' => 'Lokasi Perumahan'], ['value' => 'online', 'label' => 'Online'], ['value' => 'canvassing', 'label' => 'Canvassing'], ['value' => 'event', 'label' => 'Pameran/Event'], ['value' => 'agency', 'label' => 'Instansi/Partner']]),
            'visitStatuses' => MarketingReferenceOption::options('visit_status', [['value' => 'planned', 'label' => 'Direncanakan'], ['value' => 'in_progress', 'label' => 'Berlangsung'], ['value' => 'completed', 'label' => 'Selesai'], ['value' => 'rescheduled', 'label' => 'Dijadwalkan Ulang'], ['value' => 'cancelled', 'label' => 'Dibatalkan']]),
        ];

        if ($activePerumahanId) {
            $allowedCustomerIds = Costumer::query()
                ->where('perumahan_id', $activePerumahanId)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();
            $options['customers'] = collect($options['customers'])
                ->whereIn('value', $allowedCustomerIds)
                ->values();
        }

        return $options;
    }

    private function authorizeReport(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super_admin') || $request->user()?->can('marketing-report.view'), 403);
    }

    private function authorizeExport(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super_admin') || $request->user()?->can('marketing-report.export'), 403);
    }
}
