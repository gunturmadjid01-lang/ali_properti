<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\Costumer;
use App\Models\CostumerFollowUp;
use App\Models\CustomerDocument;
use App\Models\CustomerDocumentChecklist;
use App\Models\DetailRumah;
use App\Models\DokumenCostumer;
use App\Models\MarketingActionPlan;
use App\Models\MarketingLead;
use App\Models\MarketingSurveySchedule;
use App\Models\MarketingVisit;
use App\Models\Perumahan;
use App\Models\ProgressPembangunan;
use App\Services\Marketing\MarketingLeadStatusService;
use App\Services\Marketing\MarketingOperationsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketingController extends Controller
{
    use ScopesActivePerumahan;

    public function index(Request $request, MarketingOperationsService $operations): Response
    {
        $this->authorizeSection($request, 'marketing');
        $operations->syncAutomaticReminders($request->user()?->id);

        return $this->render('marketing', $request);
    }

    public function show(Request $request, string $slug): Response
    {
        abort_unless(in_array($slug, array_keys($this->sections()), true), 404);

        return $this->render($slug, $request);
    }

    protected function render(string $slug, Request $request): Response
    {
        $this->authorizeSection($request, $slug);
        $section = $this->sections()[$slug] ?? $this->sections()['marketing'];

        if ($slug === 'marketing') {
            return Inertia::render('Admin/Marketing/Index', [
                'title' => $section['title'],
                'roles' => $request->user()?->loadMissing('roles')?->roles?->pluck('name')->values()->all() ?? [],
                'today' => $this->today($request),
            ]);
        }

        $summary = $this->summary($request, $slug);

        return Inertia::render('Admin/Marketing/Index', [
            'title' => $section['title'],
            'description' => $section['description'],
            'slug' => $slug,
            'roles' => $request->user()?->loadMissing('roles')?->roles?->pluck('name')->values()->all() ?? [],
            'summary' => $summary,
            'points' => $section['points'],
            'menus' => $section['menus'],
            'featured' => $section['featured'] ?? [],
            'customers' => $slug === 'marketing' || $slug === 'konsumen' || $slug === 'calon-konsumen'
                ? $this->customerRows($request)
                : [],
            'progressRows' => $slug === 'marketing' || $slug === 'operasional' || $slug === 'laporan'
                ? $this->progressRows($request)
                : [],
            'quickActions' => $section['quickActions'] ?? [],
            'today' => null,
        ]);
    }

    protected function summary(Request $request, string $slug = 'marketing'): array
    {
        $customerQuery = Costumer::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('assigned_marketing_id', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request));

        $base = [
            'total_customers' => (clone $customerQuery)->count(),
            'high_prospects' => MarketingLead::query()->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('marketing_id', $request->user()?->id))->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))->where('stage', 'qualified')->count(),
        ];

        if ($this->shouldScopeToCurrentMarketing($request) && $slug === 'marketing') {
            return $base + [
                'documents' => CustomerDocumentChecklist::query()
                    ->where('validation_status', '!=', 'complete')
                    ->whereHas('costumer', fn (Builder $query) => $query->where('assigned_marketing_id', $request->user()?->id))
                    ->count(),
                'recent_progress' => 0,
                'active_projects' => 0,
                'active_units' => 0,
            ];
        }

        return [
            ...$base,
            'documents' => CustomerDocument::query()->whereHas('customer', fn (Builder $query) => $query
                ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('assigned_marketing_id', $request->user()?->id))
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))->count(),
            'recent_progress' => ProgressPembangunan::query()
                ->whereDate('tanggal', '>=', now()->subDays(30))
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
                ->count(),
            'active_projects' => Perumahan::query()->finalized()
                ->where('status', 'aktif')
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereKey($this->activePerumahanId($request)))
                ->count(),
            'active_units' => DetailRumah::query()->finalized()
                ->where('status', 'aktif')
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
                ->count(),
        ];
    }

    protected function customerRows(Request $request)
    {
        return Costumer::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('assigned_marketing_id', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Costumer $customer) => [
                'id' => $customer->id,
                'nama' => $customer->nama,
                'no_identitas' => $customer->no_identitas,
                'telepon' => $customer->telepon ?? '-',
                'pekerjaan' => $customer->pekerjaan ?? '-',
                'penghasilan' => number_format((float) ($customer->penghasilan ?? 0), 0, ',', '.'),
            ]);
    }

    protected function progressRows(Request $request)
    {
        return ProgressPembangunan::query()
            ->with(['detailRumah.perumahan', 'user:id,name'])
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            ->latest('tanggal')
            ->limit(5)
            ->get()
            ->map(fn (ProgressPembangunan $progress) => [
                'id' => $progress->id,
                'tanggal' => optional($progress->tanggal)->format('Y-m-d'),
                'proyek' => $progress->detailRumah?->perumahan?->nama_perusahaan ?? '-',
                'unit' => $progress->detailRumah ? "{$progress->detailRumah->kode_nlok} {$progress->detailRumah->nomor_rumah}" : '-',
                'tahapan' => $progress->tahapan,
                'persentase' => $progress->persentase,
                'user' => $progress->user?->name ?? '-',
            ]);
    }

    protected function today(Request $request): array
    {
        $userId = $request->user()?->id;
        $canMonitorTeam = (bool) $request->user()?->hasAnyRole([
            'owner',
            'manager',
            'manajer_pimpro',
            'supervisor_marketing',
            'super_admin',
        ]) || (bool) $request->user()?->can('marketing.activity.view-all');
        $activePerumahanId = $this->shouldScopeToActivePerumahan($request)
            ? $this->activePerumahanId($request)
            : null;
        $scopeCustomer = function (Builder $query) use ($request, $userId): void {
            $query->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('assigned_marketing_id', $userId))
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request));
        };

        $customerCounts = MarketingLead::query()
            ->whereNotIn('stage', ['converted', 'lost'])
            ->where(fn (Builder $query) => $query->whereNull('next_action_at')->where('stage', 'new')->orWhereDate('next_action_at', '<=', today()))
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('marketing_id', $userId))
            ->when($activePerumahanId, fn (Builder $query, int $perumahanId) => $query->where('perumahan_id', $perumahanId))
            ->selectRaw('COUNT(*) as due_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN next_action_at < ? THEN 1 ELSE 0 END), 0) as overdue_count', [now()])
            ->first();

        $visitCount = MarketingVisit::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('marketing_id', $userId))
            ->when($activePerumahanId, fn (Builder $query, int $perumahanId) => $query->where('perumahan_id', $perumahanId))
            ->whereIn('status', ['planned', 'in_progress'])
            ->whereDate('planned_at', today())
            ->count();

        $incompleteDocuments = CustomerDocumentChecklist::query()->where('validation_status', '!=', 'complete')
            ->whereHas('costumer', $scopeCustomer)->count();

        $activities = collect()
            ->concat(CostumerFollowUp::query()->with('lead:id,name')->whereDate('tanggal_follow_up', today())
                ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('user_id', $userId))
                ->when($activePerumahanId, fn (Builder $query, int $perumahanId) => $query->whereHas('lead', fn (Builder $lead) => $lead->where('perumahan_id', $perumahanId)))
                ->latest('id')->limit(30)->get()
                ->map(fn (CostumerFollowUp $row) => ['id' => 'follow-'.$row->id, 'type' => 'Follow-up', 'customer' => $row->lead?->name, 'time' => optional($row->created_at)->format('H:i'), 'result' => $row->catatan ?: $this->leadLabel($row->status), 'status' => $row->record_status ?? 'draft', 'url' => route('admin.marketing.jejak-follow-up.show', $row->id, false)]))
            ->concat(MarketingVisit::query()->with('costumer:id,nama')->whereDate('planned_at', today())
                ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('marketing_id', $userId))
                ->when($activePerumahanId, fn (Builder $query, int $perumahanId) => $query->where('perumahan_id', $perumahanId))
                ->latest('planned_at')->limit(30)->get()
                ->map(fn (MarketingVisit $row) => ['id' => 'visit-'.$row->id, 'type' => 'Kunjungan', 'customer' => $row->costumer?->nama, 'time' => $row->planned_at?->format('H:i'), 'result' => $row->result ?: $row->objective, 'status' => $row->status, 'url' => route('admin.marketing.crm.show', ['visits', $row->id], false)]))
            ->concat(MarketingSurveySchedule::query()->with('costumer:id,nama')->whereDate('tanggal_survey', today())
                ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('marketing_id', $userId))
                ->when($activePerumahanId, fn (Builder $query, int $perumahanId) => $query->where('perumahan_id', $perumahanId))
                ->latest('tanggal_survey')->limit(30)->get()
                ->map(fn (MarketingSurveySchedule $row) => ['id' => 'survey-'.$row->id, 'type' => 'Survei', 'customer' => $row->costumer?->nama, 'time' => $row->tanggal_survey?->format('H:i'), 'result' => $row->hasil_survey ?: $row->catatan, 'status' => $row->status, 'url' => route('admin.marketing.jadwal-survey.index', false)]))
            ->concat(MarketingActionPlan::query()->with('costumer:id,nama')->whereDate('start_at', today())
                ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('marketing_id', $userId))
                ->when($activePerumahanId, fn (Builder $query, int $perumahanId) => $query->where('perumahan_id', $perumahanId))
                ->latest('start_at')->limit(30)->get()
                ->map(fn (MarketingActionPlan $row) => ['id' => 'plan-'.$row->id, 'type' => 'Aktivitas Lain', 'customer' => $row->costumer?->nama, 'time' => $row->start_at?->format('H:i'), 'result' => $row->actual_result ?: $row->objective, 'status' => $row->status, 'url' => route('admin.marketing.crm.show', ['action-plans', $row->id], false)]))
            ->sortByDesc('time')->take(40)->values();

        $quickActions = collect([
            $this->shouldScopeToCurrentMarketing($request)
                ? ['permission' => 'marketing-visit.create', 'label' => 'Catat Prospek Lapangan', 'description' => 'Catat penawaran ke rumah prospek, event, atau canvassing. Konversi menjadi lead setelah prospek tertarik.', 'href' => route('admin.marketing.crm.create', 'visits', false)]
                : ['permission' => 'customer.create', 'label' => 'Tambah Prospek', 'description' => 'Catat calon pelanggan baru hari ini.', 'href' => route('admin.marketing.calon-konsumen.create', false)],
            ['permission' => 'customer-follow-up.create', 'label' => 'Catat Follow-up', 'description' => 'Telepon, WhatsApp, atau pertemuan dengan prospek.', 'href' => route('admin.marketing.jejak-follow-up.create', false)],
            ['permission' => 'marketing-visit.create', 'label' => 'Catat Kunjungan', 'description' => 'Rencana dan hasil kunjungan ke pelanggan.', 'href' => route('admin.marketing.crm.create', 'visits', false)],
            ['permission' => 'marketing-action-plan.create', 'label' => 'Catat Aktivitas Lain', 'description' => 'Canvassing, event, promosi, atau pekerjaan marketing lain.', 'href' => route('admin.marketing.crm.create', 'action-plans', false)],
            ['permission' => 'marketing-survey.create', 'label' => 'Jadwal Survei', 'description' => 'Kelola jadwal survei lokasi atau unit.', 'href' => route('admin.marketing.jadwal-survey.create', false)],
        ])->filter(fn (array $action) => $request->user()?->hasRole('super_admin') || $request->user()?->can($action['permission']))
            ->map(fn (array $action) => collect($action)->except('permission')->all())
            ->values()
            ->all();

        return [
            'eyebrow' => $canMonitorTeam ? 'Monitoring Harian Tim Marketing' : 'Buku Kerja Harian Marketing',
            'heading' => $canMonitorTeam ? 'Apa yang dikerjakan tim hari ini?' : 'Apa yang Anda kerjakan hari ini?',
            'description' => $canMonitorTeam
                ? 'Pantau input prospek, follow-up, kunjungan, survei, dan aktivitas lapangan seluruh tim sesuai lingkup perumahan Anda.'
                : 'Masukkan setiap prospek, follow-up, kunjungan, survei, dan aktivitas lapangan. Hasil serta rencana berikutnya menjadi bukti kerja dan bahan monitoring atasan.',
            'counts' => [
                'due' => (int) ($customerCounts?->due_count ?? 0),
                'overdue' => (int) ($customerCounts?->overdue_count ?? 0),
                'visits' => $visitCount,
                'incomplete_documents' => $incompleteDocuments,
                'activities' => $activities->count(),
            ],
            'activities' => $activities,
            'quick_actions' => $quickActions,
            'monitoring_url' => $canMonitorTeam
                ? route('admin.marketing.tools.show', 'monitoring-aktivitas', false)
                : null,
        ];
    }

    private function leadScore(Costumer $customer): int
    {
        $score = match ($customer->status_lead) {
            'closing' => 100, 'spr' => 90, 'booking_fee' => 80, 'negosiasi' => 65,
            'survey_lokasi' => 50, 'dihubungi' => 30, 'batal' => 0, default => 15,
        };
        if ((float) $customer->penghasilan > 0) {
            $score += 5;
        }
        if ($customer->marketing_campaign_id) {
            $score += 3;
        }
        if ($customer->next_action_at && ! $customer->next_action_at->isPast()) {
            $score += 2;
        }

        return min(100, $score);
    }

    private function leadLabel(?string $status): string
    {
        return data_get(collect(MarketingLeadStatusService::statusOptions())->firstWhere('value', $status), 'label', $status ?: 'Lead Baru');
    }

    protected function sections(): array
    {
        return [
            'marketing' => [
                'title' => 'Area Marketing',
                'description' => 'Workspace untuk calon konsumen, follow up, SPR, dan laporan penjualan.',
                'points' => [
                    'Kelola calon konsumen dan status prospek',
                    'Lihat jejak follow up dan progress pekerjaan',
                    'Akses SPR dan operasional marketing',
                    'Ringkasan laporan penjualan dan promosi',
                ],
                'menus' => [
                    ['label' => 'Calon Konsumen', 'description' => 'Masuk ke data calon pembeli yang bisa diinput dan diupdate.', 'href' => '/admin/marketing/calon-konsumen'],
                    ['label' => 'Jejak Follow Up', 'description' => 'Pantau interaksi marketing dengan prospek.', 'href' => '/admin/marketing/jejak-follow-up'],
                    ['label' => 'Konsumen', 'description' => 'Lihat data konsumen hasil follow up.', 'href' => '/admin/marketing/konsumen'],
                    ['label' => 'Transaksi SPR', 'description' => 'Kelola proses Surat Pemesanan Rumah.', 'href' => '/admin/marketing/spr'],
                    ['label' => 'Transaksi Cash', 'description' => 'Kelola pembelian cash dan pembayarannya.', 'href' => '/admin/marketing/transaksi-pembelian/cash'],
                    ['label' => 'Operasional', 'description' => 'Pantau aktivitas promosi dan penjualan.', 'href' => '/admin/marketing/operasional'],
                    ['label' => 'Laporan', 'description' => 'Buka laporan lead, prospek, dan performa marketing.', 'href' => '/admin/marketing/laporan'],
                ],
                'featured' => [
                    ['label' => 'Kelola prospek', 'value' => 'Calon konsumen tersimpan rapi untuk follow up harian.'],
                    ['label' => 'Pantau progres', 'value' => 'Progress pembangunan unit dapat dijadikan bahan follow up.'],
                    ['label' => 'Laporan cepat', 'value' => 'Semua ringkasan marketing tampil di satu halaman.'],
                ],
                'quickActions' => [
                    ['label' => 'Buka Calon Konsumen', 'href' => '/admin/marketing/calon-konsumen'],
                    ['label' => 'Lihat Jejak Follow Up', 'href' => '/admin/marketing/jejak-follow-up'],
                ],
            ],
            'calon-konsumen' => [
                'title' => 'Calon Konsumen',
                'description' => 'Input dan kelola data calon pembeli, status prospek, dan kemampuan bayar.',
                'points' => [
                    'Input data prospek baru',
                    'Update status follow up',
                    'Lacak dokumen customer',
                    'Siapkan daftar calon booking',
                ],
                'menus' => [
                    ['label' => 'Tambah Data', 'description' => 'Gunakan tombol tambah untuk mencatat prospek baru.', 'href' => '/admin/marketing/calon-konsumen'],
                    ['label' => 'Jejak Follow Up', 'description' => 'Lihat tindak lanjut setelah data masuk.', 'href' => '/admin/marketing/jejak-follow-up'],
                ],
                'featured' => [
                    ['label' => 'Total Customer', 'value' => (string) Costumer::query()->when($this->shouldScopeToCurrentMarketing(request()), fn (Builder $query) => $query->where('created_by', request()->user()?->id))->count()],
                    ['label' => 'Dokumen Customer', 'value' => (string) DokumenCostumer::query()->count()],
                ],
            ],
            'jejak-follow-up' => [
                'title' => 'Jejak Follow Up',
                'description' => 'Pantau aktivitas follow up, progress pembangunan, dan histori interaksi prospek.',
                'points' => [
                    'Pantau interaksi terakhir',
                    'Sinkronkan dengan progress bangunan',
                    'Gunakan sebagai bahan closing',
                    'Siapkan follow up berikutnya',
                ],
                'menus' => [
                    ['label' => 'Calon Konsumen', 'description' => 'Kembali ke data prospek utama.', 'href' => '/admin/marketing/calon-konsumen'],
                    ['label' => 'Laporan', 'description' => 'Lihat ringkasan follow up dan hasilnya.', 'href' => '/admin/marketing/laporan'],
                ],
            ],
            'konsumen' => [
                'title' => 'Konsumen',
                'description' => 'Daftar konsumen hasil follow up dan proses booking unit.',
                'points' => [
                    'Customer yang sudah lanjut',
                    'Booking dan tanda jadi',
                    'Dokumen customer',
                    'Status siap transaksi',
                ],
                'menus' => [
                    ['label' => 'Calon Konsumen', 'description' => 'Lihat sumber data prospek awal.', 'href' => '/admin/marketing/calon-konsumen'],
                    ['label' => 'SPR', 'description' => 'Buka transaksi pemesanan rumah.', 'href' => '/admin/marketing/spr'],
                ],
            ],
            'spr' => [
                'title' => 'Transaksi SPR',
                'description' => 'Alur Surat Pemesanan Rumah dengan opsi cash, bertahap, KPR bank, atau KPR developer.',
                'points' => [
                    'Pilih skema pembayaran',
                    'Pantau status booking',
                    'Dokumen pembayaran',
                    'Keterhubungan dengan unit rumah',
                ],
                'menus' => [
                    ['label' => 'Transaksi Cash', 'description' => 'Kelola pembelian cash yang berasal dari SPR disetujui.', 'href' => '/admin/marketing/transaksi-pembelian/cash'],
                    ['label' => 'Penerimaan Customer', 'description' => 'Catat Booking Fee, DP, tagihan, atau pembayaran lebih melalui satu sumber.', 'href' => '/admin/keuangan/penerimaan-customer'],
                    ['label' => 'Konsumen', 'description' => 'Lanjutkan transaksi dari data calon konsumen.', 'href' => '/admin/marketing/konsumen'],
                    ['label' => 'Laporan SPR', 'description' => 'Lihat ringkasan transaksi SPR.', 'href' => '/admin/marketing/laporan'],
                ],
            ],
            'transaksi-pembelian' => [
                'title' => 'Transaksi Pembelian',
                'description' => 'Pilih alur pembelian cash atau KPR sesuai SPR yang dibuat marketing.',
                'points' => [
                    'Cash untuk pembelian langsung',
                    'KPR untuk pembelian melalui bank',
                    'Pembayaran cash otomatis masuk kas',
                    'KPR tetap bisa dipantau sebagai proses terpisah',
                ],
                'menus' => [
                    ['label' => 'Cash', 'description' => 'Buka transaksi pembelian cash.', 'href' => '/admin/marketing/transaksi-pembelian/cash'],
                    ['label' => 'KPR', 'description' => 'Buka daftar pengajuan KPR.', 'href' => '/admin/kpr'],
                ],
            ],
            'operasional' => [
                'title' => 'Operasional Marketing',
                'description' => 'Panel aktivitas promosi, progress bangunan, dan koordinasi harian tim marketing.',
                'points' => [
                    'Brosur dan konten promosi',
                    'Update progress proyek',
                    'Koordinasi survei lokasi',
                    'Monitoring respon prospek',
                ],
                'menus' => [
                    ['label' => 'Jejak Follow Up', 'description' => 'Lacak reaksi prospek dari promosi.', 'href' => '/admin/marketing/jejak-follow-up'],
                    ['label' => 'Laporan', 'description' => 'Lihat performa operasional marketing.', 'href' => '/admin/marketing/laporan'],
                ],
            ],
            'laporan' => [
                'title' => 'Laporan Marketing',
                'description' => 'Ringkasan data prospek, customer, follow up, dan progress proyek.',
                'points' => [
                    'Laporan lead',
                    'Laporan follow up',
                    'Laporan SPR',
                    'Laporan promosi',
                ],
                'menus' => [
                    ['label' => 'Calon Konsumen', 'description' => 'Buka daftar prospek terbaru.', 'href' => '/admin/marketing/calon-konsumen'],
                    ['label' => 'Operasional', 'description' => 'Lihat aktivitas marketing.', 'href' => '/admin/marketing/operasional'],
                ],
            ],
        ];
    }

    protected function shouldScopeToCurrentMarketing(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing'])
            && ! $user->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin']);
    }

    private function authorizeSection(Request $request, string $slug): void
    {
        $permissions = match ($slug) {
            'marketing' => ['dashboard.view'],
            'calon-konsumen', 'konsumen' => ['customer.view'],
            'jejak-follow-up' => ['customer-follow-up.view', 'customer.follow-up'],
            'spr' => ['booking.view', 'booking.manage'],
            'transaksi-pembelian' => ['cash-sale.view', 'kpr.view'],
            'operasional' => ['marketing.activity.view'],
            'laporan' => ['marketing-report.view'],
            default => [],
        };
        $user = $request->user();

        abort_unless(
            $user?->hasRole('super_admin')
            || collect($permissions)->contains(fn (string $permission) => $user?->can($permission)),
            403
        );
    }
}
