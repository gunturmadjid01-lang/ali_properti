<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\MarketingEvaluation;
use App\Models\MarketingScoreSetting;
use App\Models\Perumahan;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\Marketing\MarketingEvaluationService;
use App\Support\CodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MarketingEvaluationController extends Controller
{
    use ScopesActivePerumahan;

    public function index(Request $request): Response
    {
        $this->authorizeView($request);
        $rows = MarketingEvaluation::query()->with(['marketing:id,name', 'perumahan:id,nama_perusahaan', 'latestApproval'])
            ->when(! $this->canViewAll($request), fn (Builder $q) => $q->where('marketing_id', $request->user()->id))
            ->when($request->integer('marketing_id'), fn (Builder $q, int $id) => $q->where('marketing_id', $id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $q) => $this->scopeToActivePerumahan($q, $request))
            ->when($request->integer('perumahan_id'), fn (Builder $q, int $id) => $q->where('perumahan_id', $id))
            ->latest('period_end')->paginate(15)->withQueryString()->through(fn (MarketingEvaluation $row) => $this->row($row));

        return Inertia::render('Admin/Marketing/Evaluations/Index', ['title' => 'Evaluasi Kinerja Marketing', 'rows' => $rows, 'filters' => $request->only(['marketing_id', 'perumahan_id']), 'options' => $this->options(), 'canManage' => $this->canManage($request)]);
    }

    public function create(Request $request): Response
    {
        abort_unless($this->canManage($request), 403);

        return $this->form(null);
    }

    public function edit(Request $request, MarketingEvaluation $evaluation): Response
    {
        abort_unless($this->canManage($request), 403);
        $this->ensureEvaluationScope($request, $evaluation);
        abort_if($evaluation->record_status === 'locked', 422, 'Evaluasi locked harus di-unlock dahulu.');

        return $this->form($evaluation);
    }

    public function store(Request $request, MarketingEvaluationService $service): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        $data = $this->validated($request);
        $row = DB::transaction(function () use ($request, $data, $service): MarketingEvaluation {
            $row = MarketingEvaluation::query()->create([...$data, 'evaluation_no' => CodeGenerator::next(MarketingEvaluation::class, 'evaluation_no', 'EVAL'), 'record_status' => 'draft', 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

            return $service->recalculate($row);
        });

        return redirect()->route('admin.marketing.evaluations.show', $row)->with('success', 'Evaluasi dibuat dari data aktivitas aktual.');
    }

    public function update(Request $request, MarketingEvaluation $evaluation, MarketingEvaluationService $service): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        $this->ensureEvaluationScope($request, $evaluation);
        abort_if($evaluation->record_status === 'locked', 422, 'Evaluasi locked tidak dapat diubah.');
        $evaluation->update([...$this->validated($request, $evaluation->id), 'updated_by' => $request->user()->id]);
        $service->recalculate($evaluation);

        return redirect()->route('admin.marketing.evaluations.show', $evaluation)->with('success', 'Evaluasi dan sumber nilainya diperbarui.');
    }

    public function show(Request $request, MarketingEvaluation $evaluation): Response
    {
        $this->authorizeView($request);
        $this->ensureEvaluationScope($request, $evaluation);
        abort_if(! $this->canViewAll($request) && $evaluation->marketing_id !== $request->user()->id, 403);
        $evaluation->load(['marketing:id,name', 'perumahan:id,nama_perusahaan', 'details', 'latestApproval']);

        return Inertia::render('Admin/Marketing/Evaluations/Show', ['title' => 'Detail Evaluasi '.$evaluation->evaluation_no, 'row' => [...$this->row($evaluation), 'manager_note' => $evaluation->manager_note, 'coaching_plan' => $evaluation->coaching_plan, 'details' => $evaluation->details], 'canManage' => $this->canManage($request)]);
    }

    public function lock(Request $request, MarketingEvaluation $evaluation, MarketingEvaluationService $service, ApprovalWorkflowService $workflow): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        $this->ensureEvaluationScope($request, $evaluation);
        abort_unless($evaluation->record_status === 'draft', 422, 'Evaluasi sudah locked.');
        $service->recalculate($evaluation);
        abort_if($evaluation->details()->count() === 0, 422, 'Konfigurasi penilaian aktif belum tersedia.');
        $evaluation->forceFill(['record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $request->user()->id])->save();
        $workflow->submitLocked($evaluation, 'marketing-evaluation');

        return back()->with('success', 'Evaluasi difinalisasi dan dikirim ke Setting Approval.');
    }

    public function unlock(Request $request, MarketingEvaluation $evaluation, ApprovalWorkflowService $workflow): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        $this->ensureEvaluationScope($request, $evaluation);
        $workflow->cancelPendingLock($evaluation);
        $evaluation->forceFill(['record_status' => 'draft', 'locked_at' => null, 'locked_by' => null, 'updated_by' => $request->user()->id])->save();

        return back()->with('success', 'Evaluasi dibuka kembali untuk perbaikan.');
    }

    public function review(Request $request, MarketingEvaluation $evaluation, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->ensureEvaluationScope($request, $evaluation);
        $approval = $evaluation->latestApproval()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403);
        if ($decision === 'approve') {
            $workflow->approve($approval);
        } else {
            $workflow->reject($approval, $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null);
        }

        return back()->with('success', $decision === 'approve' ? 'Tahap evaluasi disetujui.' : 'Evaluasi ditolak dan dikembalikan ke draft.');
    }

    public function settings(Request $request): Response
    {
        $this->authorizeView($request);

        $rows = MarketingScoreSetting::query()->with('latestApproval')->orderBy('id')->get()->map(fn ($row) => [...$row->toArray(), 'approval_status' => $row->latestApproval?->status, 'approval_step' => $row->latestApproval?->current_step, 'approval_total_steps' => $row->latestApproval?->total_steps, 'can_review' => $row->latestApproval ? app(ApprovalWorkflowService::class)->canReview($row->latestApproval) : false]);

        return Inertia::render('Admin/Marketing/Evaluations/Settings', ['title' => 'Pengaturan Bobot Kinerja', 'rows' => $rows, 'totalWeight' => MarketingScoreSetting::query()->where('is_active', true)->sum('weight'), 'canManage' => $this->canManage($request)]);
    }

    public function updateSetting(Request $request, MarketingScoreSetting $setting): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        abort_if($setting->record_status === 'locked', 422, 'Buka konfigurasi terlebih dahulu sebelum mengubah bobot.');
        $setting->update([...$request->validate(['weight' => ['required', 'numeric', 'min:0', 'max:100'], 'description' => ['nullable', 'string'], 'is_active' => ['required', 'boolean']]), 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Bobot tersimpan sebagai draft.');
    }

    public function lockSetting(Request $request, MarketingScoreSetting $setting, ApprovalWorkflowService $workflow): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        abort_unless($setting->record_status === 'draft', 422);
        abort_if(abs((float) MarketingScoreSetting::query()->where('is_active', true)->sum('weight') - 100) > 0.01, 422, 'Total bobot aktif wajib tepat 100%.');
        $setting->forceFill(['record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $request->user()->id])->save();
        $workflow->submitLocked($setting, 'marketing-score-setting');

        return back()->with('success', 'Konfigurasi dikunci dan diajukan melalui Setting Approval.');
    }

    public function unlockSetting(Request $request, MarketingScoreSetting $setting, ApprovalWorkflowService $workflow): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        $workflow->cancelPendingLock($setting);
        $setting->forceFill(['record_status' => 'draft', 'locked_at' => null, 'locked_by' => null])->save();

        return back()->with('success', 'Konfigurasi dibuka untuk perubahan.');
    }

    public function reviewSetting(Request $request, MarketingScoreSetting $setting, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $approval = $setting->latestApproval()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403);
        $decision === 'approve' ? $workflow->approve($approval) : $workflow->reject($approval, $request->input('note'));

        return back()->with('success', 'Pemeriksaan konfigurasi bobot diproses.');
    }

    private function validated(Request $request, ?int $ignore = null): array
    {
        $data = $request->validate(['marketing_id' => ['required', 'exists:users,id'], 'perumahan_id' => ['nullable', 'exists:perumahans,id'], 'period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after_or_equal:period_start', Rule::unique('marketing_evaluations')->where(fn ($q) => $q->where('marketing_id', $request->input('marketing_id'))->where('perumahan_id', $request->input('perumahan_id'))->where('period_start', $request->input('period_start')))->ignore($ignore)], 'manager_note' => ['nullable', 'string'], 'coaching_plan' => ['nullable', 'string']]);

        if ($this->shouldScopeToActivePerumahan($request)) {
            $data['perumahan_id'] = $this->ensureActivePerumahan($request);
            abort_unless(User::query()->whereKey($data['marketing_id'])->whereHas('perumahans', fn (Builder $query) => $query->whereKey($data['perumahan_id']))->exists(), 403);
        }

        return $data;
    }

    private function row(MarketingEvaluation $row): array
    {
        return ['id' => $row->id, 'evaluation_no' => $row->evaluation_no, 'marketing' => $row->marketing?->name, 'perumahan' => $row->perumahan?->nama_perusahaan, 'period_start' => $row->period_start?->format('Y-m-d'), 'period_end' => $row->period_end?->format('Y-m-d'), 'total_score' => $row->total_score, 'rating' => $row->rating, 'record_status' => $row->record_status, 'approval_status' => $row->latestApproval?->status, 'approval_step' => $row->latestApproval?->current_step, 'approval_total_steps' => $row->latestApproval?->total_steps, 'can_review' => $row->latestApproval ? app(ApprovalWorkflowService::class)->canReview($row->latestApproval) : false];
    }

    private function form(?MarketingEvaluation $row): Response
    {
        return Inertia::render('Admin/Marketing/Evaluations/Form', ['title' => $row ? 'Edit Evaluasi Marketing' : 'Buat Evaluasi Marketing', 'row' => $row, 'options' => $this->options(), 'actionUrl' => $row ? route('admin.marketing.evaluations.update', $row, false) : route('admin.marketing.evaluations.store', absolute: false), 'method' => $row ? 'put' : 'post']);
    }

    private function options(): array
    {
        $request = request();
        $activePerumahanId = $this->shouldScopeToActivePerumahan($request) ? $this->activePerumahanId($request) : null;

        return ['marketings' => User::query()->whereHas('roles', fn (Builder $q) => $q->whereIn('name', ['marketing', 'area_marketing']))->when($activePerumahanId, fn (Builder $q, int $id) => $q->whereHas('perumahans', fn (Builder $q) => $q->whereKey($id)))->orderBy('name')->get(['id', 'name'])->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->name]), 'perumahans' => Perumahan::query()->finalized()->when($activePerumahanId, fn (Builder $q, int $id) => $q->whereKey($id))->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->nama_perusahaan])];
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super_admin') || $request->user()?->can('marketing-evaluation.view'), 403);
    }

    private function ensureEvaluationScope(Request $request, MarketingEvaluation $evaluation): void
    {
        if ($this->shouldScopeToActivePerumahan($request)) {
            abort_unless((int) $evaluation->perumahan_id === $this->ensureActivePerumahan($request), 404);
        }
    }

    private function canManage(Request $request): bool
    {
        return (bool) ($request->user()?->hasRole('super_admin') || $request->user()?->can('marketing-evaluation.manage'));
    }

    private function canViewAll(Request $request): bool
    {
        return (bool) ($request->user()?->hasRole('super_admin') || $request->user()?->can('marketing.activity.view-all'));
    }
}
