<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialBrand;
use App\Models\MaterialType;
use App\Models\MaterialUnit;
use App\Services\ApprovalWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MaterialReferenceController extends Controller
{
    private const CONFIG = [
        'jenis' => ['model' => MaterialType::class, 'permission' => 'material-type', 'title' => 'Jenis Material', 'prefix' => 'JNS', 'symbol' => false],
        'merk' => ['model' => MaterialBrand::class, 'permission' => 'material-brand', 'title' => 'Merk Material', 'prefix' => 'MRK', 'symbol' => false],
        'satuan' => ['model' => MaterialUnit::class, 'permission' => 'material-unit', 'title' => 'Satuan Material', 'prefix' => 'STN', 'symbol' => true],
    ];

    public function index(Request $request, string $section): Response
    {
        $config = $this->config($section);
        $this->authorizeAction($request, $config, 'view');
        $search = trim((string) $request->query('search', ''));
        $model = $config['model'];

        return Inertia::render('Admin/Logistik/MaterialReference/Index', [
            'title' => 'Kelola '.$config['title'],
            'section' => $section,
            'baseUrl' => route('admin.material-reference.index', $section, false),
            'search' => $search,
            'usesSymbol' => $config['symbol'],
            'rows' => $model::query()
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
                ->orderBy('name')->paginate(15)->withQueryString(),
            'permissions' => [
                'create' => $this->can($request, $config, 'create'),
                'update' => $this->can($request, $config, 'update'),
                'delete' => $this->can($request, $config, 'delete'),
            ],
        ]);
    }

    public function store(Request $request, string $section, ApprovalWorkflowService $approvalWorkflow): RedirectResponse
    {
        $config = $this->config($section);
        $this->authorizeAction($request, $config, 'create');
        $model = $config['model'];
        $payload = $this->payload($request, $config, $model);
        $payload['code'] = $this->nextCode($model, $config['prefix']);

        return $approvalWorkflow->create($config['permission'], $payload, fn (array $data) => $model::query()->create($data));
    }

    public function update(Request $request, string $section, string $id): RedirectResponse
    {
        $config = $this->config($section);
        $this->authorizeAction($request, $config, 'update');
        $model = $config['model'];
        $row = $model::query()->findOrFail($id);
        $row->update($this->payload($request, $config, $row));

        return back()->with('success', $config['title'].' berhasil diperbarui.');
    }

    public function destroy(Request $request, string $section, string $id): RedirectResponse
    {
        $config = $this->config($section);
        $this->authorizeAction($request, $config, 'delete');
        $model = $config['model'];
        $row = $model::query()->findOrFail($id);
        abort_if(method_exists($row, 'materials') && $row->materials()->exists(), 422, 'Data masih dipakai material dan tidak dapat dihapus. Nonaktifkan saja.');
        $row->delete();

        return back()->with('success', $config['title'].' berhasil dihapus.');
    }

    private function payload(Request $request, array $config, Model|string $row): array
    {
        $modelClass = is_string($row) ? $row : $row::class;
        $ignoreId = is_string($row) ? null : $row->getKey();
        $rules = [
            'name' => ['required', 'string', 'max:100', Rule::unique((new $modelClass)->getTable(), 'name')->ignore($ignoreId)],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ];
        if ($config['symbol']) {
            $rules['symbol'] = ['required', 'string', 'max:20', Rule::unique((new $modelClass)->getTable(), 'symbol')->ignore($ignoreId)];
        }

        return $request->validate($rules);
    }

    private function nextCode(string $model, string $prefix): string
    {
        return $prefix.'-'.str_pad((string) (((int) $model::query()->max('id')) + 1), 3, '0', STR_PAD_LEFT);
    }

    private function config(string $section): array
    {
        abort_unless(isset(self::CONFIG[$section]), 404);

        return self::CONFIG[$section];
    }

    private function can(Request $request, array $config, string $action): bool
    {
        return (bool) ($request->user()?->can($config['permission'].'.'.$action) || $request->user()?->can($config['permission'].'.manage'));
    }

    private function authorizeAction(Request $request, array $config, string $action): void
    {
        abort_unless($this->can($request, $config, $action), 403);
    }
}
