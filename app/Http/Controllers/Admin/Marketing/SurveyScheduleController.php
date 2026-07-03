<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\MarketingSurveySchedule;
use App\Models\Perumahan;
use App\Services\Marketing\MarketingLeadStatusService;
use App\Support\CodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SurveyScheduleController extends Controller
{
    use HandlesCrudLock, ScopesActivePerumahan;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $perumahanId = $request->query('perumahan_id');
        $detailRumahId = $request->query('detail_rumah_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $rows = MarketingSurveySchedule::query()
            ->with([
                'costumer:id,kode_costumer,nama,no_identitas,telepon',
                'perumahan:id,nama_perusahaan',
                'detailRumah:id,kode_nlok,nomor_rumah',
                'marketing:id,name',
                'creator:id,name',
                'updater:id,name',
            ])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('marketing_id', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('kode_survey', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('hasil_survey', 'like', "%{$search}%")
                    ->orWhereHas('costumer', fn (Builder $query) => $query
                        ->where('nama', 'like', "%{$search}%")
                        ->orWhere('kode_costumer', 'like', "%{$search}%")
                        ->orWhere('no_identitas', 'like', "%{$search}%")
                        ->orWhere('telepon', 'like', "%{$search}%"));
            }))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($perumahanId, fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
            ->when($detailRumahId, fn (Builder $query) => $query->where('detail_rumah_id', $detailRumahId))
            ->when($dateFrom, fn (Builder $query) => $query->whereDate('tanggal_survey', '>=', $dateFrom))
            ->when($dateTo, fn (Builder $query) => $query->whereDate('tanggal_survey', '<=', $dateTo))
            ->latest('tanggal_survey')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (MarketingSurveySchedule $schedule) => [
                'id' => $schedule->id,
                'kode_survey' => $schedule->kode_survey,
                'costumer_id' => (string) $schedule->costumer_id,
                'customer' => $schedule->costumer?->nama ?? '-',
                'kode_customer' => $schedule->costumer?->kode_costumer ?? '-',
                'telepon' => $schedule->costumer?->telepon ?? '-',
                'perumahan_id' => (string) ($schedule->perumahan_id ?? ''),
                'detail_rumah_id' => (string) ($schedule->detail_rumah_id ?? ''),
                'perumahan' => $schedule->perumahan?->nama_perusahaan ?? '-',
                'unit' => $schedule->detailRumah ? trim($schedule->detailRumah->kode_nlok.' '.$schedule->detailRumah->nomor_rumah) : '-',
                'marketing_id' => (string) ($schedule->marketing_id ?? ''),
                'marketing' => $schedule->marketing?->name ?? '-',
                'tanggal_survey' => optional($schedule->tanggal_survey)->format('Y-m-d\TH:i'),
                'tanggal_survey_display' => optional($schedule->tanggal_survey)->format('d/m/Y H:i'),
                'metode_survey' => $schedule->metode_survey,
                'metode_survey_label' => $this->labelFromOptions($schedule->metode_survey, $this->methodOptions()),
                'status' => $schedule->status,
                'status_label' => $this->labelFromOptions($schedule->status, $this->statusOptions()),
                'hasil_survey' => $schedule->hasil_survey,
                'catatan' => $schedule->catatan,
                'rencana_follow_up_at' => optional($schedule->rencana_follow_up_at)->format('Y-m-d\TH:i'),
                'rencana_follow_up_display' => optional($schedule->rencana_follow_up_at)->format('d/m/Y H:i'),
                'record_status' => $schedule->record_status ?? 'draft',
                'created_by_name' => $schedule->creator?->name ?? '-',
                'updated_by_name' => $schedule->updater?->name ?? '-',
                'can_edit' => ($schedule->record_status ?? 'draft') !== 'locked',
                'can_delete' => ($schedule->record_status ?? 'draft') !== 'locked',
                'can_lock' => (bool) auth()->check() && ($schedule->record_status ?? 'draft') !== 'locked',
                'can_unlock' => $this->currentUserCanManageLockedRecords() && ($schedule->record_status ?? 'draft') === 'locked',
            ]);

        return Inertia::render('Admin/Marketing/SurveySchedule/Index', [
            'title' => 'Jadwal Survey',
            'description' => 'Jadwalkan kunjungan calon customer ke lokasi perumahan atau unit rumah.',
            'baseUrl' => route('admin.marketing.jadwal-survey.index', absolute: false),
            'rows' => $rows,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'perumahan_id' => $perumahanId,
                'detail_rumah_id' => $detailRumahId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'options' => [
                'customers' => $this->customerOptions(),
                'perumahans' => $this->perumahanOptions(),
                'detailRumahs' => $this->detailRumahOptions(),
                'methodOptions' => $this->methodOptions(),
                'statusOptions' => $this->statusOptions(),
            ],
        ]);
    }

    public function store(Request $request, MarketingLeadStatusService $leadStatus): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        if ($this->shouldScopeToActivePerumahan($request)) {
            $validated['perumahan_id'] = $this->ensureActivePerumahan($request);
            $this->ensureDetailRumahAllowed($request, $validated['detail_rumah_id'] ?? null);
        }
        $this->ensureCustomerCanBeUsed($request, (int) $validated['costumer_id']);

        $schedule = MarketingSurveySchedule::query()->create([
            ...$validated,
            'kode_survey' => CodeGenerator::next(MarketingSurveySchedule::class, 'kode_survey', 'SURVEY'),
            'marketing_id' => $request->user()?->id,
        ]);

        $leadStatus->markCustomer(
            (int) $validated['costumer_id'],
            $this->statusFromSurvey($validated['status']),
            MarketingSurveySchedule::class,
            $schedule->id,
            'Jadwal survey dibuat.'
        );

        return back()->with('success', 'Jadwal survey berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $leadStatus = app(MarketingLeadStatusService::class);
        $schedule = MarketingSurveySchedule::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('marketing_id', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->findOrFail($id);
        $this->abortIfLocked($schedule);
        $validated = $this->validatePayload($request);
        if ($this->shouldScopeToActivePerumahan($request)) {
            $validated['perumahan_id'] = $this->ensureActivePerumahan($request);
            $this->ensureDetailRumahAllowed($request, $validated['detail_rumah_id'] ?? null);
        }
        $this->ensureCustomerCanBeUsed($request, (int) $validated['costumer_id']);
        $schedule->update([
            ...$validated,
            'marketing_id' => $schedule->marketing_id ?: $request->user()?->id,
        ]);
        $leadStatus->markCustomer(
            (int) $validated['costumer_id'],
            $this->statusFromSurvey($validated['status']),
            MarketingSurveySchedule::class,
            $schedule->id,
            'Jadwal survey diperbarui.'
        );

        return back()->with('success', 'Jadwal survey berhasil diperbarui.');
    }

    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $leadStatus = app(MarketingLeadStatusService::class);
        $schedule = MarketingSurveySchedule::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('marketing_id', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_column($this->statusOptions(), 'value'))],
            'tanggal_survey' => ['nullable', 'date', 'required_if:status,reschedule'],
            'hasil_survey' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'rencana_follow_up_at' => ['nullable', 'date'],
        ], [
            'status.required' => 'Status survey wajib dipilih.',
            'tanggal_survey.required_if' => 'Tanggal dan jam survey baru wajib diisi saat reschedule.',
        ]);

        $schedule->update([
            ...$validated,
            'tanggal_survey' => $validated['status'] === 'reschedule'
                ? $validated['tanggal_survey']
                : $schedule->tanggal_survey,
        ]);

        $leadStatus->markCustomer(
            (int) $schedule->costumer_id,
            $this->statusFromSurvey($validated['status']),
            MarketingSurveySchedule::class,
            $schedule->id,
            'Status survey diperbarui menjadi '.$this->labelFromOptions($validated['status'], $this->statusOptions()).'.'
        );

        return back()->with('success', 'Status survey berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $schedule = MarketingSurveySchedule::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('marketing_id', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->findOrFail($id);
        $this->abortIfLocked($schedule);
        $schedule->delete();

        return back()->with('success', 'Jadwal survey berhasil dihapus.');
    }

    protected function modelClass(): string
    {
        return MarketingSurveySchedule::class;
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'costumer_id' => ['required', 'exists:costumers,id'],
            'perumahan_id' => ['nullable', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'tanggal_survey' => ['required', 'date'],
            'metode_survey' => ['required', Rule::in(array_column($this->methodOptions(), 'value'))],
            'status' => ['required', Rule::in(array_column($this->statusOptions(), 'value'))],
            'hasil_survey' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'rencana_follow_up_at' => ['nullable', 'date'],
        ], [
            'costumer_id.required' => 'Customer wajib dipilih.',
            'tanggal_survey.required' => 'Tanggal survey wajib diisi.',
        ]);
    }

    private function statusFromSurvey(string $surveyStatus): string
    {
        return match ($surveyStatus) {
            'selesai' => MarketingLeadStatusService::NEGOSIASI,
            'batal' => MarketingLeadStatusService::BATAL,
            default => MarketingLeadStatusService::SURVEY_LOKASI,
        };
    }

    private function ensureCustomerCanBeUsed(Request $request, int $customerId): void
    {
        if (! $this->shouldScopeToCurrentMarketing($request)) {
            return;
        }

        abort_unless(
            Costumer::query()
                ->whereKey($customerId)
                ->where('created_by', $request->user()?->id)
                ->where('perumahan_id', $this->ensureActivePerumahan($request))
                ->exists(),
            403,
        );
    }

    private function customerOptions(): array
    {
        return Costumer::query()
            ->when($this->shouldScopeToCurrentMarketing(request()), fn (Builder $query) => $query->where('created_by', request()->user()?->id))
            ->when($this->shouldScopeToActivePerumahan(request()), fn (Builder $query) => $this->scopeToActivePerumahan($query, request()))
            ->latest('id')
            ->limit(300)
            ->get(['id', 'kode_costumer', 'nama', 'no_identitas', 'telepon'])
            ->map(fn (Costumer $customer) => [
                'value' => (string) $customer->id,
                'label' => "{$customer->nama} - {$customer->kode_costumer} - ".($customer->telepon ?: '-'),
            ])
            ->all();
    }

    private function perumahanOptions(): array
    {
        return Perumahan::query()
            ->when($this->shouldScopeToActivePerumahan(request()), fn (Builder $query) => $query->whereIn('id', $this->assignedPerumahanIds(request())))
            ->orderBy('nama_perusahaan')
            ->get(['id', 'nama_perusahaan'])
            ->map(fn (Perumahan $perumahan) => [
                'value' => (string) $perumahan->id,
                'label' => $perumahan->nama_perusahaan,
            ])
            ->all();
    }

    private function detailRumahOptions(): array
    {
        return DetailRumah::query()
            ->with('perumahan:id,nama_perusahaan')
            ->when($this->shouldScopeToActivePerumahan(request()), fn (Builder $query) => $this->scopeToActivePerumahan($query, request()))
            ->orderBy('kode_nlok')
            ->orderBy('nomor_rumah')
            ->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah'])
            ->map(fn (DetailRumah $unit) => [
                'value' => (string) $unit->id,
                'label' => "{$unit->kode_nlok} {$unit->nomor_rumah} - {$unit->perumahan?->nama_perusahaan}",
                'perumahan_id' => (string) $unit->perumahan_id,
            ])
            ->all();
    }

    private function ensureDetailRumahAllowed(Request $request, mixed $detailRumahId): void
    {
        if (! $detailRumahId) {
            return;
        }

        abort_unless(
            DetailRumah::query()
                ->whereKey($detailRumahId)
                ->where('perumahan_id', $this->ensureActivePerumahan($request))
                ->exists(),
            403,
        );
    }

    private function methodOptions(): array
    {
        return [
            ['value' => 'kunjungan_lokasi', 'label' => 'Kunjungan Lokasi'],
            ['value' => 'survey_unit', 'label' => 'Survey Unit'],
            ['value' => 'video_call', 'label' => 'Video Call'],
            ['value' => 'kunjungan_kantor', 'label' => 'Kunjungan Kantor'],
        ];
    }

    private function statusOptions(): array
    {
        return [
            ['value' => 'dijadwalkan', 'label' => 'Dijadwalkan'],
            ['value' => 'reschedule', 'label' => 'Reschedule'],
            ['value' => 'selesai', 'label' => 'Selesai Survey'],
            ['value' => 'batal', 'label' => 'Batal'],
        ];
    }

    private function labelFromOptions(?string $value, array $options): string
    {
        foreach ($options as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value ?? '-';
    }

    protected function shouldScopeToCurrentMarketing(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing'])
            && ! $user->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin']);
    }
}
