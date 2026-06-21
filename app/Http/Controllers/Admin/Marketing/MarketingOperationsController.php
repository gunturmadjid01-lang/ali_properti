<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Models\BerkasCostumer;
use App\Models\Costumer;
use App\Models\KprSubmission;
use App\Models\MarketingCampaign;
use App\Models\MarketingCommission;
use App\Models\MarketingDocumentReview;
use App\Models\MarketingLeadActivity;
use App\Models\MarketingReminder;
use App\Models\MarketingTarget;
use App\Models\MarketingTemplate;
use App\Models\Spr;
use App\Models\SprBerkasCostumer;
use App\Models\SprPayment;
use App\Models\User;
use App\Services\Marketing\MarketingLeadStatusService;
use App\Services\Marketing\MarketingOperationsService;
use App\Support\CodeGenerator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MarketingOperationsController extends Controller
{
    public function show(Request $request, string $section, MarketingOperationsService $service): Response
    {
        abort_unless(in_array($section, $this->sections(), true), 404);

        $service->syncAutomaticReminders($request->user()?->id);
        if ($section === 'piutang') {
            Spr::query()->where('status', Spr::STATUS_DISETUJUI)->with('payments')->get()->each(fn (Spr $spr) => $service->syncBillingSchedules($spr));
        }

        return Inertia::render('Admin/Marketing/Operations/Index', [
            'title' => $this->title($section),
            'section' => $section,
            'baseUrl' => route('admin.marketing.operasional.show', $section, absolute: false),
            'data' => $this->data($request, $section),
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request, string $section): RedirectResponse
    {
        match ($section) {
            'campaign' => $this->storeCampaign($request),
            'reminder' => $this->storeReminder($request),
            'target-komisi' => $this->storeTargetOrCommission($request),
            'template' => $this->storeTemplate($request),
            default => abort(404),
        };

        return back()->with('success', 'Data berhasil disimpan.');
    }

    public function update(Request $request, string $section, string $id): RedirectResponse
    {
        match ($section) {
            'campaign' => $this->updateCampaign($request, $id),
            'reminder' => $this->updateReminder($request, $id),
            'target-komisi' => $this->updateTargetOrCommission($request, $id),
            'template' => $this->updateTemplate($request, $id),
            default => abort(404),
        };

        return back()->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(Request $request, string $section, string $id): RedirectResponse
    {
        $model = match ($section) {
            'campaign' => MarketingCampaign::query()->findOrFail($id),
            'reminder' => MarketingReminder::query()->findOrFail($id),
            'template' => MarketingTemplate::query()->findOrFail($id),
            'target-komisi' => $request->query('type') === 'commission'
                ? MarketingCommission::query()->findOrFail($id)
                : MarketingTarget::query()->findOrFail($id),
            default => abort(404),
        };

        abort_if(($model->record_status ?? 'draft') === 'locked', 422, 'Data yang sudah di-lock tidak dapat dihapus.');
        abort_if(($model->is_system ?? false), 422, 'Template bawaan sistem tidak dapat dihapus.');
        $model->delete();

        return back()->with('success', 'Data berhasil dihapus.');
    }

    public function completeReminder(string $id): RedirectResponse
    {
        MarketingReminder::query()->findOrFail($id)->update([
            'status' => 'selesai',
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Reminder ditandai selesai.');
    }

    public function lock(string $section, string $id): RedirectResponse
    {
        $model = $this->lockableModel($section, $id);
        $model->forceFill([
            'record_status' => 'locked',
            'locked_at' => now(),
            'locked_by' => auth()->id(),
        ])->save();

        return back()->with('success', 'Data berhasil di-lock.');
    }

    public function unlock(string $section, string $id): RedirectResponse
    {
        $user = request()->user();
        abort_unless($user === null || $user->hasAnyRole(['owner', 'super_admin']), 403, 'Hanya owner yang dapat membuka lock data.');

        $model = $this->lockableModel($section, $id);
        $model->forceFill([
            'record_status' => 'draft',
            'locked_at' => null,
            'locked_by' => null,
        ])->save();

        return back()->with('success', 'Lock data berhasil dibuka.');
    }

    public function reviewDocument(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', Rule::in([SprBerkasCostumer::class, BerkasCostumer::class])],
            'document_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(['menunggu', 'valid', 'revisi', 'ditolak'])],
            'catatan_revisi' => ['nullable', 'string'],
        ]);

        MarketingDocumentReview::query()->updateOrCreate(
            ['document_type' => $validated['document_type'], 'document_id' => $validated['document_id']],
            [
                'status' => $validated['status'],
                'catatan_revisi' => $validated['catatan_revisi'] ?? null,
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
            ],
        );

        return back()->with('success', 'Status validasi berkas berhasil diperbarui.');
    }

    public function expireBookings(MarketingOperationsService $service): RedirectResponse
    {
        $total = $service->expireBookings();

        return back()->with('success', "{$total} booking kedaluwarsa berhasil dilepas.");
    }

    public function receipt(string $id): Response
    {
        $payment = SprPayment::query()
            ->with(['spr.costumer:id,nama', 'spr:id,kode_spr,costumer_id', 'masterBank:id,nama_bank,nomor_rekening,nama_rekening'])
            ->findOrFail($id);

        return Inertia::render('Admin/Marketing/Receipt/Show', [
            'title' => 'Kwitansi '.$payment->spr?->kode_spr,
            'receipt' => [
                'number' => 'KWT-'.str_pad((string) $payment->id, 7, '0', STR_PAD_LEFT),
                'date' => optional($payment->tanggal_pembayaran)->format('d/m/Y'),
                'customer' => $payment->spr?->costumer?->nama ?? '-',
                'type' => ucwords(str_replace('_', ' ', $payment->jenis_pembayaran)),
                'spr' => $payment->spr?->kode_spr ?? '-',
                'bank' => trim(($payment->masterBank?->nama_bank ?? '-').' - '.($payment->masterBank?->nomor_rekening ?? '-').' - '.($payment->masterBank?->nama_rekening ?? '-')),
                'note' => $payment->keterangan,
                'amount' => (float) $payment->nominal,
            ],
        ]);
    }

    protected function data(Request $request, string $section): array
    {
        return match ($section) {
            'dashboard' => $this->dashboardData(),
            'pipeline' => $this->pipelineData(),
            'campaign' => $this->campaignData(),
            'reminder' => $this->reminderData(),
            'dokumen' => $this->documentData(),
            'piutang' => $this->receivableData(),
            'target-komisi' => $this->targetData(),
            'template' => $this->templateData(),
        };
    }

    protected function dashboardData(): array
    {
        $month = now()->month;
        $year = now()->year;

        return [
            'stats' => [
                'lead' => Costumer::query()->count(),
                'follow_up_due' => MarketingReminder::query()->where('status', 'menunggu')->where('remind_at', '<=', now()->addDays(3))->count(),
                'spr_month' => Spr::query()->whereMonth('tanggal_spr', $month)->whereYear('tanggal_spr', $year)->count(),
                'kpr_active' => KprSubmission::query()->whereNotIn('status', ['ditolak', 'serah_terima_selesai'])->count(),
                'booking_expiring' => Spr::query()->where('status', Spr::STATUS_DISETUJUI)->whereBetween('booking_expires_at', [now(), now()->addDays(7)])->count(),
                'overdue' => \App\Models\SprBillingSchedule::query()->whereIn('status', ['jatuh_tempo', 'sebagian'])->count(),
            ],
            'performance' => User::query()
                ->withCount([
                    'sprs as spr_count' => fn (Builder $query) => $query->whereMonth('tanggal_spr', $month)->whereYear('tanggal_spr', $year),
                    'kprSubmissions as kpr_count' => fn (Builder $query) => $query->whereMonth('created_at', $month)->whereYear('created_at', $year),
                ])
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['marketing', 'supervisor_marketing', 'area_marketing']))
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'spr' => $user->spr_count, 'kpr' => $user->kpr_count])
                ->values(),
            'recent' => MarketingLeadActivity::query()->with(['costumer:id,nama', 'user:id,name'])->latest('activity_at')->limit(10)->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'customer' => $row->costumer?->nama ?? '-',
                    'status' => $row->status_to,
                    'user' => $row->user?->name ?? '-',
                    'at' => optional($row->activity_at)->format('d/m/Y H:i'),
                ]),
        ];
    }

    protected function pipelineData(): array
    {
        $statuses = app(MarketingLeadStatusService::class)->statusOptions();
        $customers = Costumer::query()
            ->with(['leadSource:id,nama_sumber', 'campaign:id,nama_campaign'])
            ->withMax('leadActivities', 'activity_at')
            ->latest('id')
            ->get();

        return [
            'columns' => collect($statuses)->map(fn ($status) => [
                ...$status,
                'customers' => $customers->where('status_lead', $status['value'])->map(fn (Costumer $customer) => [
                    'id' => $customer->id,
                    'kode' => $customer->kode_costumer,
                    'nama' => $customer->nama,
                    'telepon' => $customer->telepon,
                    'source' => $customer->leadSource?->nama_sumber ?? '-',
                    'campaign' => $customer->campaign?->nama_campaign ?? '-',
                    'last_activity' => $customer->lead_activities_max_activity_at
                        ? Carbon::parse($customer->lead_activities_max_activity_at)->format('d/m/Y')
                        : '-',
                ])->values(),
            ])->values(),
        ];
    }

    protected function campaignData(): array
    {
        return [
            'rows' => MarketingCampaign::query()->withCount('customers')->latest('id')->get()->map(fn (MarketingCampaign $row) => [
                ...$row->only(['id', 'kode_campaign', 'nama_campaign', 'kanal', 'anggaran', 'realisasi_biaya', 'target_lead', 'status', 'keterangan', 'record_status']),
                'tanggal_mulai' => optional($row->tanggal_mulai)->format('Y-m-d'),
                'tanggal_selesai' => optional($row->tanggal_selesai)->format('Y-m-d'),
                'lead_count' => $row->customers_count,
            ]),
        ];
    }

    protected function reminderData(): array
    {
        return [
            'rows' => MarketingReminder::query()->with(['costumer:id,nama,telepon', 'user:id,name'])->orderByRaw("CASE WHEN status = 'menunggu' THEN 0 ELSE 1 END")->orderBy('remind_at')->get()
                ->map(fn (MarketingReminder $row) => [
                    ...$row->only(['id', 'costumer_id', 'user_id', 'jenis', 'judul', 'status', 'catatan']),
                    'customer' => $row->costumer?->nama ?? '-',
                    'telepon' => $row->costumer?->telepon ?? '-',
                    'user' => $row->user?->name ?? '-',
                    'remind_at' => optional($row->remind_at)->format('Y-m-d\TH:i'),
                    'is_overdue' => $row->status === 'menunggu' && $row->remind_at?->isPast(),
                ]),
        ];
    }

    protected function documentData(): array
    {
        $reviews = MarketingDocumentReview::query()->get()->keyBy(fn ($row) => $row->document_type.'-'.$row->document_id);
        $sprDocuments = SprBerkasCostumer::query()->with(['spr.costumer:id,nama', 'spr:id,kode_spr,costumer_id', 'dokumen:id,nama_dokumen'])->latest('id')->get()
            ->map(fn ($row) => $this->documentRow($row, SprBerkasCostumer::class, $reviews));
        $kprDocuments = BerkasCostumer::query()->with(['submission.spr.costumer:id,nama', 'submission:id,kode_kpr,spr_id', 'dokumen:id,nama_dokumen'])->latest('id')->get()
            ->map(fn ($row) => $this->documentRow($row, BerkasCostumer::class, $reviews));

        return ['rows' => $sprDocuments->concat($kprDocuments)->sortByDesc('created_at')->values()];
    }

    protected function receivableData(): array
    {
        $schedules = \App\Models\SprBillingSchedule::query()->with(['spr.costumer:id,nama', 'spr.detailRumah.perumahan:id,nama_perusahaan'])->orderBy('tanggal_jatuh_tempo')->get();
        $payments = SprPayment::query()->with(['spr.costumer:id,nama', 'spr:id,kode_spr,costumer_id', 'masterBank:id,nama_bank,nomor_rekening'])->latest('tanggal_pembayaran')->limit(100)->get();

        return [
            'summary' => [
                'tagihan' => (float) $schedules->sum('nominal_tagihan'),
                'dibayar' => (float) $schedules->sum('nominal_dibayar'),
                'sisa' => (float) $schedules->sum(fn ($row) => max(0, $row->nominal_tagihan - $row->nominal_dibayar)),
                'jatuh_tempo' => $schedules->whereIn('status', ['jatuh_tempo', 'sebagian'])->count(),
            ],
            'schedules' => $schedules->map(fn ($row) => [
                'id' => $row->id,
                'kode_spr' => $row->spr?->kode_spr ?? '-',
                'customer' => $row->spr?->costumer?->nama ?? '-',
                'jenis' => $row->jenis_tagihan,
                'termin_ke' => $row->termin_ke,
                'tanggal_jatuh_tempo' => optional($row->tanggal_jatuh_tempo)->format('d/m/Y'),
                'nominal_tagihan' => $row->nominal_tagihan,
                'nominal_dibayar' => $row->nominal_dibayar,
                'sisa' => max(0, $row->nominal_tagihan - $row->nominal_dibayar),
                'status' => $row->status,
            ]),
            'receipts' => $payments->map(fn (SprPayment $row) => [
                'id' => $row->id,
                'nomor' => 'KWT-'.str_pad((string) $row->id, 7, '0', STR_PAD_LEFT),
                'kode_spr' => $row->spr?->kode_spr ?? '-',
                'customer' => $row->spr?->costumer?->nama ?? '-',
                'tanggal' => optional($row->tanggal_pembayaran)->format('d/m/Y'),
                'jenis' => $row->jenis_pembayaran,
                'nominal' => $row->nominal,
                'bank' => trim(($row->masterBank?->nama_bank ?? '-').' '.($row->masterBank?->nomor_rekening ?? '')),
                'print_url' => route('admin.marketing.kwitansi.show', $row->id, absolute: false),
            ]),
        ];
    }

    protected function targetData(): array
    {
        return [
            'targets' => MarketingTarget::query()->with('user:id,name')->latest('tahun')->latest('bulan')->get()->map(fn ($row) => [
                ...$row->only(['id', 'user_id', 'tahun', 'bulan', 'target_lead', 'target_survey', 'target_spr', 'target_closing', 'target_nilai_penjualan', 'catatan', 'record_status']),
                'user' => $row->user?->name ?? '-',
                'type' => 'target',
            ]),
            'commissions' => MarketingCommission::query()->with(['user:id,name', 'spr:id,kode_spr'])->latest('id')->get()->map(fn ($row) => [
                ...$row->only(['id', 'spr_id', 'user_id', 'dasar_perhitungan', 'persentase', 'nominal', 'status', 'catatan', 'record_status']),
                'user' => $row->user?->name ?? '-',
                'spr' => $row->spr?->kode_spr ?? '-',
                'tanggal_jatuh_tempo' => optional($row->tanggal_jatuh_tempo)->format('Y-m-d'),
                'tanggal_dibayar' => optional($row->tanggal_dibayar)->format('Y-m-d'),
                'type' => 'commission',
            ]),
        ];
    }

    protected function templateData(): array
    {
        return ['rows' => MarketingTemplate::query()->latest('id')->get()];
    }

    protected function options(): array
    {
        return [
            'customers' => Costumer::query()->orderBy('nama')->get(['id', 'nama', 'no_identitas'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => "{$row->nama} - {$row->no_identitas}"]),
            'users' => User::query()->orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name]),
            'sprs' => Spr::query()->with('costumer:id,nama')->where('status', Spr::STATUS_DISETUJUI)->latest('id')->get(['id', 'kode_spr', 'costumer_id', 'nilai_pengajuan_akhir', 'harga_jual', 'created_by'])->map(fn ($row) => [
                'value' => (string) $row->id,
                'label' => "{$row->kode_spr} - ".($row->costumer?->nama ?? '-'),
                'amount' => (float) ($row->nilai_pengajuan_akhir ?: $row->harga_jual),
                'user_id' => $row->created_by,
            ]),
        ];
    }

    protected function documentRow($row, string $type, $reviews): array
    {
        $review = $reviews->get($type.'-'.$row->id);
        $isKpr = $type === BerkasCostumer::class;

        return [
            'id' => $row->id,
            'document_type' => $type,
            'source' => $isKpr ? 'KPR' : 'SPR',
            'reference' => $isKpr ? ($row->submission?->kode_kpr ?? '-') : ($row->spr?->kode_spr ?? '-'),
            'customer' => $isKpr ? ($row->submission?->spr?->costumer?->nama ?? '-') : ($row->spr?->costumer?->nama ?? '-'),
            'document' => $row->dokumen?->nama_dokumen ?? '-',
            'file' => $row->nama_file,
            'url' => route('media', ['path' => $row->path_file], false),
            'status' => $review?->status ?? 'menunggu',
            'catatan_revisi' => $review?->catatan_revisi,
            'created_at' => optional($row->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    protected function storeCampaign(Request $request): void
    {
        MarketingCampaign::create([
            ...$request->validate($this->campaignRules()),
            'kode_campaign' => CodeGenerator::next(MarketingCampaign::class, 'kode_campaign', 'CMP'),
        ]);
    }

    protected function updateCampaign(Request $request, string $id): void
    {
        $row = MarketingCampaign::query()->findOrFail($id);
        abort_if($row->record_status === 'locked', 422, 'Campaign sudah di-lock.');
        $row->update($request->validate($this->campaignRules()));
    }

    protected function campaignRules(): array
    {
        return [
            'nama_campaign' => ['required', 'string', 'max:255'],
            'kanal' => ['required', 'string', 'max:100'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'anggaran' => ['required', 'numeric', 'min:0'],
            'realisasi_biaya' => ['nullable', 'numeric', 'min:0'],
            'target_lead' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'aktif', 'selesai', 'dibatalkan'])],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    protected function storeReminder(Request $request): void
    {
        MarketingReminder::create($request->validate($this->reminderRules()));
    }

    protected function updateReminder(Request $request, string $id): void
    {
        MarketingReminder::query()->findOrFail($id)->update($request->validate($this->reminderRules()));
    }

    protected function reminderRules(): array
    {
        return [
            'costumer_id' => ['nullable', 'exists:costumers,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'jenis' => ['required', 'string', 'max:100'],
            'judul' => ['required', 'string', 'max:255'],
            'remind_at' => ['required', 'date'],
            'status' => ['required', Rule::in(['menunggu', 'selesai', 'dibatalkan'])],
            'catatan' => ['nullable', 'string'],
        ];
    }

    protected function storeTargetOrCommission(Request $request): void
    {
        if ($request->input('type') === 'commission') {
            $data = $request->validate($this->commissionRules());
            MarketingCommission::create([
                ...$data,
                'kode_komisi' => CodeGenerator::next(MarketingCommission::class, 'kode_komisi', 'KMS'),
                'nominal' => (float) $data['dasar_perhitungan'] * ((float) $data['persentase'] / 100),
            ]);
            return;
        }

        MarketingTarget::create($request->validate($this->targetRules()));
    }

    protected function updateTargetOrCommission(Request $request, string $id): void
    {
        if ($request->input('type') === 'commission') {
            $row = MarketingCommission::query()->findOrFail($id);
            abort_if($row->record_status === 'locked', 422, 'Komisi sudah di-lock.');
            $data = $request->validate($this->commissionRules());
            $row->update([
                ...$data,
                'nominal' => (float) $data['dasar_perhitungan'] * ((float) $data['persentase'] / 100),
            ]);
            return;
        }

        $row = MarketingTarget::query()->findOrFail($id);
        abort_if($row->record_status === 'locked', 422, 'Target sudah di-lock.');
        $row->update($request->validate($this->targetRules($row->id)));
    }

    protected function targetRules(?int $ignoreId = null): array
    {
        return [
            'type' => ['nullable'],
            'user_id' => ['required', 'exists:users,id'],
            'tahun' => ['required', 'integer', 'min:2020', 'max:2100'],
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'target_lead' => ['required', 'integer', 'min:0'],
            'target_survey' => ['required', 'integer', 'min:0'],
            'target_spr' => ['required', 'integer', 'min:0'],
            'target_closing' => ['required', 'integer', 'min:0'],
            'target_nilai_penjualan' => ['required', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string'],
        ];
    }

    protected function commissionRules(): array
    {
        return [
            'type' => ['required', Rule::in(['commission'])],
            'spr_id' => ['required', 'exists:sprs,id'],
            'user_id' => ['required', 'exists:users,id'],
            'dasar_perhitungan' => ['required', 'numeric', 'min:0'],
            'persentase' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(['draft', 'diajukan', 'disetujui', 'dibayar', 'dibatalkan'])],
            'tanggal_jatuh_tempo' => ['nullable', 'date'],
            'tanggal_dibayar' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string'],
        ];
    }

    protected function storeTemplate(Request $request): void
    {
        MarketingTemplate::create([
            ...$request->validate($this->templateRules()),
            'kode_template' => CodeGenerator::next(MarketingTemplate::class, 'kode_template', 'TPL'),
        ]);
    }

    protected function updateTemplate(Request $request, string $id): void
    {
        $row = MarketingTemplate::query()->findOrFail($id);
        abort_if($row->record_status === 'locked', 422, 'Template sudah di-lock.');
        $row->update($request->validate($this->templateRules()));
    }

    protected function templateRules(): array
    {
        return [
            'nama_template' => ['required', 'string', 'max:255'],
            'kanal' => ['required', Rule::in(['whatsapp', 'sms', 'email'])],
            'tahapan' => ['nullable', 'string', 'max:100'],
            'isi_template' => ['required', 'string'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ];
    }

    protected function sections(): array
    {
        return ['dashboard', 'pipeline', 'campaign', 'reminder', 'dokumen', 'piutang', 'target-komisi', 'template'];
    }

    protected function lockableModel(string $section, string $id)
    {
        return match ($section) {
            'campaign' => MarketingCampaign::query()->findOrFail($id),
            'template' => MarketingTemplate::query()->findOrFail($id),
            'target' => MarketingTarget::query()->findOrFail($id),
            'commission' => MarketingCommission::query()->findOrFail($id),
            default => abort(404),
        };
    }

    protected function title(string $section): string
    {
        return [
            'dashboard' => 'Dashboard Performa Marketing',
            'pipeline' => 'Pipeline Marketing',
            'campaign' => 'Campaign dan Promosi',
            'reminder' => 'Reminder Follow Up',
            'dokumen' => 'Validasi Berkas Customer',
            'piutang' => 'Jadwal Tagihan dan Kwitansi',
            'target-komisi' => 'Target, KPI, dan Komisi',
            'template' => 'Template Komunikasi',
        ][$section];
    }
}
