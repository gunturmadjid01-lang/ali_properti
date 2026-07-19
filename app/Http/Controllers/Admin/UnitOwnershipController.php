<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\CabangPerusahaan;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\UnitOwnership;
use App\Services\UnitOwnershipService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UnitOwnershipController extends Controller
{
    use HandlesCrudLock {
        lock as protected traitLock;
        unlock as protected traitUnlock;
    }

    public function index(Request $request): Response
    {
        $this->authorizeAction('view');
        $search = trim((string) $request->query('search', ''));
        $source = trim((string) $request->query('source', ''));
        $status = trim((string) $request->query('status', 'active'));
        $branchId = trim((string) $request->query('cabang_id', ''));
        $projectId = trim((string) $request->query('perumahan_id', ''));

        $rows = UnitOwnership::query()
            ->with(['detailRumah.perumahan.cabang:id,nama_cabang', 'costumer:id,kode_costumer,nama', 'creator:id,name', 'updater:id,name'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('owner_name', 'like', "%{$search}%")
                        ->orWhere('identity_number', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%")
                        ->orWhereHas('detailRumah', fn (Builder $query) => $query
                            ->where('kode_nlok', 'like', "%{$search}%")
                            ->orWhere('nomor_rumah', 'like', "%{$search}%")
                            ->orWhereHas('perumahan', fn (Builder $query) => $query->where('nama_perusahaan', 'like', "%{$search}%")));
                });
            })
            ->when($source !== '', fn (Builder $query) => $query->where('source_type', $source))
            ->when($projectId !== '', fn (Builder $query) => $query->whereHas('detailRumah', fn (Builder $query) => $query->where('perumahan_id', $projectId)))
            ->when($branchId !== '', fn (Builder $query) => $query->whereHas('detailRumah.perumahan', fn (Builder $query) => $query->where('cabang_id', $branchId)))
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->latest('acquired_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (UnitOwnership $row) => $this->row($row));

        return Inertia::render('Admin/UnitOwnership/Index', [
            'title' => 'Data Pemilik Unit',
            'description' => 'Kelola pemilik unit lama dan pantau kepemilikan yang tersinkron otomatis dari akad KPR atau serah terima cash.',
            'baseUrl' => route('admin.unit-ownership.index', absolute: false),
            'rows' => $rows,
            'filters' => [
                'search' => $search,
                'source' => $source,
                'status' => $status,
                'cabang_id' => $branchId,
                'perumahan_id' => $projectId,
            ],
            'options' => $this->options(),
            'permissions' => [
                'canCreate' => $this->can('create'),
                'canUpdate' => $this->can('update'),
                'canDeactivate' => $this->can('delete'),
                'canUnlock' => $this->can('unlock'),
            ],
        ]);
    }

    public function store(Request $request, UnitOwnershipService $service): RedirectResponse
    {
        $this->authorizeAction('create');
        $validated = $this->validated($request);

        DB::transaction(function () use ($request, $validated, $service): void {
            $current = UnitOwnership::query()
                ->where('detail_rumah_id', $validated['detail_rumah_id'])
                ->where('is_active', true)
                ->first();
            abort_if(
                $current && $current->source_type !== 'legacy',
                422,
                'Unit sudah memiliki pemilik dari transaksi otomatis. Perubahan harus dilakukan melalui transaksi asal.',
            );
            $customer = $this->resolveCustomer($validated, $request->user()?->id);
            $attachment = $request->file('attachment')?->store('unit-ownerships', 'public');
            $service->createLegacy([...$validated, 'attachment_path' => $attachment], $customer, $request->user()?->id);
        });

        return back()->with('success', 'Data pemilik lama berhasil disimpan dan unit otomatis ditandai terjual.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAction('update');
        $ownership = UnitOwnership::query()->findOrFail($id);
        abort_unless($ownership->source_type === 'legacy', 422, 'Data otomatis dari transaksi tidak dapat diedit dari halaman ini.');
        $this->abortIfLocked($ownership);
        $validated = $this->validated($request, $ownership);

        DB::transaction(function () use ($request, $ownership, $validated): void {
            $customer = $this->resolveCustomer($validated, $request->user()?->id);
            $attachment = $ownership->attachment_path;
            if ($request->hasFile('attachment')) {
                if ($attachment) {
                    Storage::disk('public')->delete($attachment);
                }
                $attachment = $request->file('attachment')->store('unit-ownerships', 'public');
            }

            $ownership->update([
                'detail_rumah_id' => $validated['detail_rumah_id'],
                'costumer_id' => $customer->id,
                'acquisition_method' => $validated['acquisition_method'],
                'acquired_at' => $validated['acquired_at'],
                'owner_name' => $validated['owner_name'],
                'identity_type' => $validated['identity_type'],
                'identity_number' => $validated['identity_number'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'],
                'spouse_name' => $validated['spouse_name'] ?? null,
                'document_number' => $validated['document_number'] ?? null,
                'attachment_path' => $attachment,
                'notes' => $validated['notes'] ?? null,
                'updated_by' => $request->user()?->id,
            ]);
            $ownership->detailRumah()->update(['status_penjualan' => 'terjual']);
        });

        return back()->with('success', 'Data pemilik berhasil diperbarui.');
    }

    public function deactivate(Request $request, string $id, UnitOwnershipService $service): RedirectResponse
    {
        $this->authorizeAction('delete');
        $ownership = UnitOwnership::query()->with('detailRumah')->findOrFail($id);
        abort_unless($ownership->is_active, 422, 'Data kepemilikan ini sudah tidak aktif.');
        abort_unless($ownership->source_type === 'legacy', 422, 'Kepemilikan dari transaksi harus dibatalkan melalui transaksi asalnya.');
        $this->abortIfLocked($ownership);
        $validated = $request->validate(['ended_at' => ['nullable', 'date', 'after_or_equal:'.$ownership->acquired_at->toDateString()]]);
        $service->deactivate($ownership, $request->user()?->id, $validated['ended_at'] ?? null);

        return back()->with('success', 'Kepemilikan dinonaktifkan. Riwayat tetap tersimpan.');
    }

    public function lock(string $id): RedirectResponse
    {
        $this->authorizeAction('update');

        return $this->traitLock($id);
    }

    public function unlock(string $id): RedirectResponse
    {
        $this->authorizeAction('unlock');

        return $this->traitUnlock($id);
    }

    protected function validated(Request $request, ?UnitOwnership $ownership = null): array
    {
        return $request->validate([
            'detail_rumah_id' => ['required', 'exists:detail_rumahs,id', ...($ownership ? [Rule::in([(string) $ownership->detail_rumah_id, (int) $ownership->detail_rumah_id])] : [])],
            'costumer_id' => ['nullable', 'exists:costumers,id'],
            'owner_name' => ['required', 'string', 'max:255'],
            'identity_type' => ['required', Rule::in(['KTP', 'SIM', 'Paspor', 'Lainnya'])],
            'identity_number' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string'],
            'spouse_name' => ['nullable', 'string', 'max:255'],
            'acquisition_method' => ['required', Rule::in(['data_lama', 'kpr', 'cash', 'hibah', 'waris', 'lainnya'])],
            'acquired_at' => ['required', 'date'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
        ]);
    }

    protected function resolveCustomer(array $payload, ?int $userId): Costumer
    {
        if (! empty($payload['costumer_id'])) {
            return Costumer::query()->findOrFail($payload['costumer_id']);
        }

        $unit = DetailRumah::query()->finalized()->findOrFail($payload['detail_rumah_id']);
        $existing = Costumer::query()->where('no_identitas', $payload['identity_number'])->first();
        if ($existing) {
            return $existing;
        }

        return Costumer::query()->create([
            'kode_costumer' => 'CST-'.str_pad((string) ((Costumer::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT),
            'created_by' => $userId,
            'updated_by' => $userId,
            'perumahan_id' => $unit->perumahan_id,
            'status_lead' => 'closing',
            'nama' => $payload['owner_name'],
            'jenis_kelamin' => 'tidak_diketahui',
            'jenis_identitas' => $payload['identity_type'],
            'no_identitas' => $payload['identity_number'],
            'status_perkawinan' => filled($payload['spouse_name'] ?? null) ? 'menikah' : 'tidak_diketahui',
            'alamat' => $payload['address'],
            'email' => $payload['email'] ?? null,
            'telepon' => $payload['phone'] ?? null,
            'nama_lengkap_pasangan' => $payload['spouse_name'] ?? null,
            'keterangan' => 'Dibuat otomatis dari input data pemilik unit lama.',
        ]);
    }

    protected function options(): array
    {
        return [
            'branches' => CabangPerusahaan::query()->finalized()->orderBy('nama_cabang')->get(['id', 'nama_cabang'])
                ->map(fn (CabangPerusahaan $branch) => ['value' => (string) $branch->id, 'label' => $branch->nama_cabang])->values(),
            'projects' => Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get(['id', 'cabang_id', 'nama_perusahaan'])
                ->map(fn (Perumahan $project) => [
                    'value' => (string) $project->id,
                    'label' => $project->nama_perusahaan,
                    'cabang_id' => (string) $project->cabang_id,
                ])->values(),
            'units' => DetailRumah::query()->finalized()
                ->with(['perumahan:id,cabang_id,nama_perusahaan', 'currentOwnership'])
                ->orderBy('perumahan_id')->orderBy('kode_nlok')->orderBy('nomor_rumah')
                ->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah', 'tipe_rumah', 'status_penjualan'])
                ->map(fn (DetailRumah $unit) => [
                    'value' => (string) $unit->id,
                    'label' => ($unit->perumahan?->nama_perusahaan ?? '-').' — Blok '.$unit->kode_nlok.' No. '.$unit->nomor_rumah,
                    'status' => $unit->status_penjualan,
                    'current_owner' => $unit->currentOwnership?->owner_name,
                    'perumahan_id' => (string) $unit->perumahan_id,
                    'cabang_id' => (string) ($unit->perumahan?->cabang_id ?? ''),
                ])->values(),
            'customers' => Costumer::query()->orderBy('nama')->get(['id', 'kode_costumer', 'nama', 'jenis_identitas', 'no_identitas', 'telepon', 'email', 'alamat', 'nama_lengkap_pasangan'])
                ->map(fn (Costumer $customer) => [
                    'value' => (string) $customer->id,
                    'label' => $customer->nama.' — '.$customer->kode_costumer.' — '.($customer->no_identitas ?: '-'),
                    'owner_name' => $customer->nama,
                    'identity_type' => $customer->jenis_identitas,
                    'identity_number' => $customer->no_identitas,
                    'phone' => $customer->telepon,
                    'email' => $customer->email,
                    'address' => $customer->alamat,
                    'spouse_name' => $customer->nama_lengkap_pasangan,
                ])->values(),
            'sources' => [
                ['value' => '', 'label' => 'Semua Sumber'],
                ['value' => 'legacy', 'label' => 'Data Lama / Manual'],
                ['value' => 'kpr_akad', 'label' => 'Akad KPR'],
                ['value' => 'cash_handover', 'label' => 'Serah Terima Cash'],
            ],
            'statuses' => [
                ['value' => 'all', 'label' => 'Semua Riwayat'],
                ['value' => 'active', 'label' => 'Pemilik Aktif'],
                ['value' => 'inactive', 'label' => 'Tidak Aktif'],
            ],
            'methods' => [
                ['value' => 'data_lama', 'label' => 'Data Lama'],
                ['value' => 'kpr', 'label' => 'KPR'],
                ['value' => 'cash', 'label' => 'Cash'],
                ['value' => 'hibah', 'label' => 'Hibah'],
                ['value' => 'waris', 'label' => 'Waris'],
                ['value' => 'lainnya', 'label' => 'Lainnya'],
            ],
            'identityTypes' => collect(['KTP', 'SIM', 'Paspor', 'Lainnya'])->map(fn ($value) => ['value' => $value, 'label' => $value])->values(),
        ];
    }

    protected function row(UnitOwnership $row): array
    {
        return [
            'id' => $row->id,
            'detail_rumah_id' => (string) $row->detail_rumah_id,
            'costumer_id' => (string) ($row->costumer_id ?? ''),
            'project' => $row->detailRumah?->perumahan?->nama_perusahaan ?? '-',
            'branch' => $row->detailRumah?->perumahan?->cabang?->nama_cabang ?? '-',
            'unit' => $row->detailRumah ? 'Blok '.$row->detailRumah->kode_nlok.' No. '.$row->detailRumah->nomor_rumah : '-',
            'owner_name' => $row->owner_name,
            'identity_type' => $row->identity_type,
            'identity_number' => $row->identity_number,
            'phone' => $row->phone,
            'email' => $row->email,
            'address' => $row->address,
            'spouse_name' => $row->spouse_name,
            'source_type' => $row->source_type,
            'source_label' => match ($row->source_type) {
                'kpr_akad' => 'Akad KPR', 'cash_handover' => 'Serah Terima Cash', default => 'Data Lama / Manual',
            },
            'acquisition_method' => $row->acquisition_method,
            'acquired_at' => optional($row->acquired_at)->format('Y-m-d'),
            'ended_at' => optional($row->ended_at)->format('Y-m-d'),
            'document_number' => $row->document_number,
            'notes' => $row->notes,
            'attachment_url' => $row->attachment_path ? route('media', ['path' => $row->attachment_path], false) : null,
            'is_active' => $row->is_active,
            'record_status' => $row->record_status ?? 'draft',
            'created_by' => $row->creator?->name ?? '-',
            'updated_by' => $row->updater?->name ?? '-',
            'can_edit' => $row->source_type === 'legacy' && $this->can('update') && (($row->record_status ?? 'draft') !== 'locked' || $this->can('unlock')),
            'can_deactivate' => $row->source_type === 'legacy' && $row->is_active && $this->can('delete') && (($row->record_status ?? 'draft') !== 'locked' || $this->can('unlock')),
        ];
    }

    protected function can(string $action): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasRole('super_admin') || $user?->can("unit-ownership.{$action}") || $user?->can('unit-ownership.manage'));
    }

    protected function authorizeAction(string $action): void
    {
        abort_unless($this->can($action), 403, 'Anda tidak memiliki permission data pemilik unit.');
    }

    protected function modelClass(): string
    {
        return UnitOwnership::class;
    }
}
