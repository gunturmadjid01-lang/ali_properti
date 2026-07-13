<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Perumahan;
use App\Models\SpkWorkTemplate;
use App\Models\SpkWorkTemplateGroup;
use App\Models\SpkWorkTemplateItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SpkTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $context = $this->contextFromRequest($request);
        $this->abortIfInvalidContext($context);
        $this->abortIfUnauthorized($context, 'view');

        $search = trim((string) $request->query('search', ''));

        $rows = SpkWorkTemplate::query()
            ->with(['perumahan:id,nama_perusahaan', 'groups.items'])
            ->where('konteks', $context)
            ->when(! $this->canSeeAllPerumahans(), fn (Builder $query) => $query->whereIn('perumahan_id', $this->allowedPerumahanIds($request)))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('nama_template', 'like', "%{$search}%")
                        ->orWhereHas('perumahan', fn (Builder $query) => $query->where('nama_perusahaan', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (SpkWorkTemplate $row) => $this->formatRow($row));

        return Inertia::render('Admin/SpkTemplate/Index', [
            'title' => 'Template Pekerjaan SPK',
            'description' => 'Kelola template item pekerjaan SPK untuk perumahan atau unit rumah dalam satu halaman.',
            'context' => $context,
            'baseUrl' => route('admin.spk-template.index', absolute: false),
            'filters' => ['search' => $search],
            'rows' => $rows,
            'options' => [
                'perumahans' => $this->perumahanOptions($request),
                'contexts' => [
                    ['value' => 'perumahan', 'label' => 'Perumahan'],
                    ['value' => 'unit', 'label' => 'Unit'],
                ],
            ],
            'permissions' => [
                'canCreate' => $this->canManage($context, 'create'),
                'canUpdate' => $this->canManage($context, 'update'),
                'canDelete' => $this->canManage($context, 'delete'),
                'canView' => $this->canManage($context, 'view'),
            ],
            'createUrl' => route('admin.spk-template.create', ['context' => $context], false),
        ]);
    }

    public function create(Request $request): Response
    {
        $context = $this->contextFromRequest($request);
        $this->abortIfInvalidContext($context);
        $this->abortIfUnauthorized($context, 'create');

        return $this->renderForm($request, $context);
    }

    public function edit(Request $request, string $id): Response
    {
        $template = $this->findAccessibleTemplate($request, $id);
        $this->abortIfUnauthorized($template->konteks, 'update');

        return $this->renderForm($request, $template->konteks, $template);
    }

    public function show(Request $request, string $id): Response
    {
        $template = $this->findAccessibleTemplate($request, $id);
        $this->abortIfUnauthorized($template->konteks, 'view');

        return Inertia::render('Admin/SpkTemplate/Show', [
            'title' => 'Detail Template Pekerjaan SPK',
            'description' => 'Rincian tahapan dan upah borongan yang menjadi acuan penyusunan SPK.',
            'template' => $this->formatRow($template),
            'indexUrl' => route('admin.spk-template.index', ['context' => $template->konteks], false),
            'editUrl' => route('admin.spk-template.edit', ['id' => $template->id], false),
            'canUpdate' => $this->canManage($template->konteks, 'update'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->contextFromRequest($request);
        $this->abortIfInvalidContext($context);
        $this->abortIfUnauthorized($context, 'create');

        $payload = $this->payload($request, $context);

        DB::transaction(function () use ($payload, $context): void {
            $groups = $payload['work_groups'];
            unset($payload['work_groups']);

            $template = SpkWorkTemplate::query()->create([
                ...$payload,
                'konteks' => $context,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncGroups($template, $groups);
        });

        return redirect()->route('admin.spk-template.index', ['context' => $context])
            ->with('success', 'Template pekerjaan berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->contextFromRequest($request);
        $this->abortIfInvalidContext($context);
        $this->abortIfUnauthorized($context, 'update');

        $template = SpkWorkTemplate::query()
            ->where('konteks', $context)
            ->findOrFail($id);

        $payload = $this->payload($request, $context, $template);

        DB::transaction(function () use ($template, $payload): void {
            $groups = $payload['work_groups'];
            unset($payload['work_groups']);

            $template->update([
                ...$payload,
                'updated_by' => auth()->id(),
            ]);

            $template->groups()->delete();
            $this->syncGroups($template, $groups);
        });

        return redirect()->route('admin.spk-template.show', ['id' => $template->id])
            ->with('success', 'Template pekerjaan berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->contextFromRequest($request);
        $this->abortIfInvalidContext($context);
        $this->abortIfUnauthorized($context, 'delete');

        $template = SpkWorkTemplate::query()
            ->where('konteks', $context)
            ->findOrFail($id);

        $template->delete();

        return back()->with('success', 'Template pekerjaan berhasil dihapus.');
    }

    protected function payload(Request $request, string $context, ?SpkWorkTemplate $template = null): array
    {
        $payload = $request->validate([
            'perumahan_id' => ['required', 'integer', 'exists:perumahans,id'],
            'nama_template' => ['required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
            'work_groups' => ['required', 'array', 'min:1'],
            'work_groups.*.judul_tahapan' => ['required', 'string', 'max:255'],
            'work_groups.*.items' => ['required', 'array', 'min:1'],
            'work_groups.*.items.*.nama_pekerjaan' => ['required', 'string', 'max:255'],
            'work_groups.*.items.*.harga_satuan' => ['required', 'numeric', 'min:0'],
        ], [], [
            'perumahan_id' => 'Perumahan',
            'nama_template' => 'Nama template',
            'work_groups' => 'Tahapan pekerjaan',
        ]);

        $perumahanIds = $this->allowedPerumahanIds($request);
        if (! $this->canSeeAllPerumahans() && ! in_array((int) $payload['perumahan_id'], $perumahanIds, true)) {
            throw ValidationException::withMessages([
                'perumahan_id' => 'Perumahan ini tidak tersedia untuk akun Anda.',
            ]);
        }

        $judulTahapans = collect($payload['work_groups'])
            ->pluck('judul_tahapan')
            ->map(fn (string $value) => (string) str($value)->squish()->lower())
            ->filter();
        $duplicateTahapan = $judulTahapans->duplicates()->values()->all();
        if (! empty($duplicateTahapan)) {
            throw ValidationException::withMessages([
                'work_groups' => 'Judul tahapan tidak boleh dobel dalam satu template.',
            ]);
        }

        $payload['work_groups'] = $this->normalizeGroups($payload['work_groups']);
        $payload['konteks'] = $context;

        if ($template && (int) $template->perumahan_id !== (int) $payload['perumahan_id']) {
            $templatePerumahan = Perumahan::query()->find((int) $payload['perumahan_id']);
            if (! $templatePerumahan) {
                throw ValidationException::withMessages([
                    'perumahan_id' => 'Perumahan tidak ditemukan.',
                ]);
            }
        }

        return $payload;
    }

    protected function renderForm(Request $request, string $context, ?SpkWorkTemplate $template = null): Response
    {
        return Inertia::render('Admin/SpkTemplate/Form', [
            'title' => $template ? 'Edit Template Pekerjaan SPK' : 'Tambah Template Pekerjaan SPK',
            'description' => 'Susun tahapan dan nilai upah borongan sebagai template pekerjaan SPK.',
            'context' => $context,
            'template' => $template ? $this->formatRow($template) : null,
            'options' => [
                'perumahans' => $this->perumahanOptions($request),
                'contexts' => [
                    ['value' => 'perumahan', 'label' => 'Perumahan'],
                    ['value' => 'unit', 'label' => 'Unit'],
                ],
            ],
            'indexUrl' => route('admin.spk-template.index', ['context' => $context], false),
            'storeUrl' => route('admin.spk-template.store', ['context' => $context], false),
            'updateUrl' => $template ? route('admin.spk-template.update', ['id' => $template->id, 'context' => $context], false) : null,
        ]);
    }

    protected function findAccessibleTemplate(Request $request, string $id): SpkWorkTemplate
    {
        return SpkWorkTemplate::query()
            ->with(['perumahan:id,nama_perusahaan', 'groups.items'])
            ->when(! $this->canSeeAllPerumahans(), fn (Builder $query) => $query->whereIn('perumahan_id', $this->allowedPerumahanIds($request)))
            ->findOrFail($id);
    }

    protected function normalizeGroups(array $groups): array
    {
        return collect($groups)
            ->values()
            ->filter(fn (array $group) => filled($group['judul_tahapan'] ?? null))
            ->map(function (array $group, int $groupIndex) {
                return [
                    'judul_tahapan' => trim((string) $group['judul_tahapan']),
                    'urutan' => ($groupIndex + 1) * 100,
                    'items' => collect($group['items'] ?? [])
                        ->values()
                        ->filter(fn (array $item) => filled($item['nama_pekerjaan'] ?? null))
                        ->map(function (array $item, int $itemIndex) {
                            $hargaSatuan = (float) ($item['harga_satuan'] ?? 0);

                            return [
                                'nama_pekerjaan' => trim((string) $item['nama_pekerjaan']),
                                'volume' => 1,
                                'satuan' => 'Ls',
                                'harga_satuan' => $hargaSatuan,
                                'urutan' => ($itemIndex + 1) * 100,
                                'total' => $hargaSatuan,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    protected function syncGroups(SpkWorkTemplate $template, array $groups): void
    {
        foreach ($groups as $group) {
            $savedGroup = $template->groups()->create([
                'judul_tahapan' => $group['judul_tahapan'],
                'urutan' => $group['urutan'],
            ]);

            foreach ($group['items'] as $item) {
                $savedGroup->items()->create([
                    'nama_pekerjaan' => $item['nama_pekerjaan'],
                    'volume' => $item['volume'] ?? 1,
                    'satuan' => $item['satuan'] ?? 'Ls',
                    'harga_satuan' => $item['harga_satuan'],
                    'urutan' => $item['urutan'],
                ]);
            }
        }
    }

    protected function formatRow(SpkWorkTemplate $template): array
    {
        $groups = $template->groups->sortBy('urutan')->values();
        $total = $groups->flatMap(fn (SpkWorkTemplateGroup $group) => $group->items->map(function (SpkWorkTemplateItem $item) {
            return (float) $item->harga_satuan;
        }))->sum();

        return [
            'id' => $template->id,
            'perumahan_id' => (string) $template->perumahan_id,
            'perumahan' => $template->perumahan?->nama_perusahaan ?? '-',
            'konteks' => $template->konteks,
            'nama_template' => $template->nama_template,
            'catatan' => $template->catatan,
            'group_count' => $groups->count(),
            'item_count' => $groups->sum(fn (SpkWorkTemplateGroup $group) => $group->items->count()),
            'total_nilai' => $total,
            'groups' => $groups->map(function (SpkWorkTemplateGroup $group) {
                return [
                    'id' => $group->id,
                    'judul_tahapan' => $group->judul_tahapan,
                    'items' => $group->items->map(fn (SpkWorkTemplateItem $item) => [
                        'id' => $item->id,
                        'nama_pekerjaan' => $item->nama_pekerjaan,
                        'harga_satuan' => $item->harga_satuan,
                        'total' => (float) $item->harga_satuan,
                    ])->values(),
                ];
            })->values(),
            'groups_text' => $groups->map(function (SpkWorkTemplateGroup $group) {
                $items = $group->items->pluck('nama_pekerjaan')->filter()->take(3)->implode(', ');
                return trim($group->judul_tahapan.($items !== '' ? " ({$items})" : ''));
            })->implode(' | '),
            'can_edit' => $this->canManage($template->konteks, 'update'),
            'can_delete' => $this->canManage($template->konteks, 'delete'),
        ];
    }

    protected function perumahanOptions(Request $request): array
    {
        $query = Perumahan::query()->orderBy('nama_perusahaan');

        if (! $this->canSeeAllPerumahans()) {
            $query->whereIn('id', $this->allowedPerumahanIds($request));
        }

        return $query
            ->get(['id', 'nama_perusahaan'])
            ->map(fn (Perumahan $perumahan) => [
                'value' => (string) $perumahan->id,
                'label' => $perumahan->nama_perusahaan,
            ])
            ->values()
            ->all();
    }

    protected function allowedPerumahanIds(Request $request): array
    {
        $user = $request->user();
        if (! $user || $this->canSeeAllPerumahans()) {
            return [];
        }

        return $user->perumahans->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    protected function canSeeAllPerumahans(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['owner', 'super_admin']);
    }

    protected function canManage(string $context, string $action): bool
    {
        return (bool) auth()->user()?->can('spk-template-'.$context.'.'.$action);
    }

    protected function abortIfUnauthorized(string $context, string $action): void
    {
        abort_unless($this->canManage($context, $action), 403, 'Anda tidak memiliki akses ke halaman ini.');
    }

    protected function abortIfInvalidContext(string $context): void
    {
        abort_unless(in_array($context, ['perumahan', 'unit'], true), 404);
    }

    protected function routeName(string $context): string
    {
        return 'admin.spk-template.'.$context;
    }

    protected function contextFromRequest(Request $request): string
    {
        $context = (string) $request->query('context', '');
        if (in_array($context, ['perumahan', 'unit'], true)) {
            return $context;
        }

        $routeName = (string) $request->route()?->getName();

        if (str_contains($routeName, '.unit.') || str_ends_with($routeName, '.unit')) {
            return 'unit';
        }

        if ($this->canManage('perumahan', 'view')) {
            return 'perumahan';
        }

        if ($this->canManage('unit', 'view')) {
            return 'unit';
        }

        return 'perumahan';
    }
}
