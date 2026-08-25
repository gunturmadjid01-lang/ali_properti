<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingReferenceOption;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MarketingReferenceController extends Controller
{
    private const CATEGORIES = ['interest_level' => 'Tingkat Ketertarikan', 'follow_up_method' => 'Metode Follow-up', 'follow_up_result' => 'Hasil Follow-up', 'visit_type' => 'Jenis Kunjungan', 'visit_result' => 'Hasil Kunjungan', 'cancellation_reason' => 'Alasan Pembatalan', 'activity_type' => 'Jenis Aktivitas', 'verification_status' => 'Status Verifikasi'];

    public function index(Request $request): Response
    {
        $this->authorizeAction($request, 'view');
        $category = $request->input('category', 'interest_level');
        $rows = MarketingReferenceOption::query()->with('latestApproval')->where('category', $category)->orderBy('sort_order')->paginate(30)->through(fn ($x) => [...$x->only(['id', 'category', 'code', 'label', 'description', 'sort_order', 'is_active', 'record_status']), 'approval_status' => $x->latestApproval?->status, 'can_review' => $x->latestApproval ? app(ApprovalWorkflowService::class)->canReview($x->latestApproval) : false]);

        return Inertia::render('Admin/Marketing/References/Index', ['title' => 'Master Pilihan Marketing', 'rows' => $rows, 'categories' => $this->categories(), 'category' => $category, 'permissions' => collect(['create', 'update', 'delete', 'lock', 'unlock'])->mapWithKeys(fn ($x) => [$x => $request->user()->hasRole('super_admin') || $request->user()->can('marketing-reference.'.$x)])]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeAction($request, 'create');

        return $this->form(null, $request->input('category'));
    }

    public function edit(Request $request, MarketingReferenceOption $option): Response
    {
        $this->authorizeAction($request, 'update');
        abort_if($option->record_status === 'locked', 422);

        return $this->form($option);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAction($request, 'create');
        MarketingReferenceOption::create([...$this->validated($request), 'record_status' => 'draft', 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return redirect()->route('admin.marketing.references.index', ['category' => $request->input('category')])->with('success', 'Pilihan marketing ditambahkan sebagai draft.');
    }

    public function update(Request $request, MarketingReferenceOption $option): RedirectResponse
    {
        $this->authorizeAction($request, 'update');
        abort_if($option->record_status === 'locked', 422);
        $option->update([...$this->validated($request, $option), 'updated_by' => $request->user()->id]);

        return redirect()->route('admin.marketing.references.index', ['category' => $option->category])->with('success', 'Pilihan diperbarui.');
    }

    public function destroy(Request $request, MarketingReferenceOption $option): RedirectResponse
    {
        $this->authorizeAction($request, 'delete');
        abort_if($option->record_status === 'locked', 422, 'Data yang sudah digunakan tidak dihapus; nonaktifkan melalui edit.');
        $option->delete();

        return back()->with('success', 'Draft pilihan dihapus.');
    }

    public function lock(Request $request, MarketingReferenceOption $option, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeAction($request, 'lock');
        abort_unless($option->record_status === 'draft', 422);
        $option->forceFill(['record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $request->user()->id])->save();
        $workflow->submitLocked($option, 'marketing-reference-option');

        return back()->with('success', 'Pilihan difinalisasi melalui Setting Approval.');
    }

    public function unlock(Request $request, MarketingReferenceOption $option, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeAction($request, 'unlock');
        $workflow->cancelPendingLock($option);
        $option->forceFill(['record_status' => 'draft', 'locked_at' => null, 'locked_by' => null])->save();

        return back()->with('success', 'Pilihan dibuka untuk revisi.');
    }

    public function review(Request $request, MarketingReferenceOption $option, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $approval = $option->latestApproval()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403);
        $decision === 'approve' ? $workflow->approve($approval) : $workflow->reject($approval, $request->input('note'));

        return back()->with('success', 'Pemeriksaan master marketing diproses.');
    }

    private function form(?MarketingReferenceOption $row, ?string $category = null): Response
    {
        return Inertia::render('Admin/Marketing/References/Form', ['title' => $row ? 'Edit Pilihan Marketing' : 'Tambah Pilihan Marketing', 'row' => $row, 'categories' => $this->categories(), 'defaultCategory' => $category ?: 'interest_level', 'actionUrl' => $row ? route('admin.marketing.references.update', $row, false) : route('admin.marketing.references.store', absolute: false), 'method' => $row ? 'put' : 'post']);
    }

    private function categories(): array
    {
        return collect(self::CATEGORIES)->map(fn ($label, $value) => compact('value', 'label'))->values()->all();
    }

    private function validated(Request $request, ?MarketingReferenceOption $option = null): array
    {
        return $request->validate(['category' => ['required', Rule::in(array_keys(self::CATEGORIES))], 'code' => ['required', 'alpha_dash', 'max:80', Rule::unique('marketing_reference_options')->where('category', $request->input('category'))->ignore($option?->id)], 'label' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'sort_order' => ['required', 'integer', 'min:0'], 'is_active' => ['required', 'boolean']]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($request->user()?->hasRole('super_admin') || $request->user()?->can('marketing-reference.'.$action), 403);
    }
}
