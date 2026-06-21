<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Models\Costumer;
use App\Models\CostumerFollowUp;
use App\Services\Marketing\MarketingLeadStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FollowUpController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $followUps = CostumerFollowUp::query()
            ->with(['costumer:id,kode_costumer,nama,no_identitas,telepon,email,alamat,pekerjaan', 'user:id,name'])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('user_id', $request->user()?->id))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('metode_follow_up', 'like', "%{$search}%")
                        ->orWhere('progress_kemampuan', 'like', "%{$search}%")
                        ->orWhere('catatan', 'like', "%{$search}%")
                        ->orWhereHas('costumer', function (Builder $query) use ($search) {
                            $query->where('nama', 'like', "%{$search}%")
                                ->orWhere('no_identitas', 'like', "%{$search}%")
                                ->orWhere('kode_costumer', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('tanggal_follow_up')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (CostumerFollowUp $followUp) => [
                'id' => $followUp->id,
                'costumer_id' => (string) $followUp->costumer_id,
                'tanggal_follow_up' => optional($followUp->tanggal_follow_up)->format('Y-m-d'),
                'customer' => $followUp->costumer?->nama ?? '-',
                'kode_costumer' => $followUp->costumer?->kode_costumer ?? '-',
                'no_identitas' => $followUp->costumer?->no_identitas ?? '-',
                'telepon' => $followUp->costumer?->telepon ?? '-',
                'metode_key' => $followUp->metode_follow_up,
                'metode_follow_up' => $this->labelFromOptions($followUp->metode_follow_up, $this->methodOptions()),
                'status_serius_value' => $followUp->status_serius ? '1' : '0',
                'status_serius' => $followUp->status_serius ? 'Serius' : 'Belum Serius',
                'progress_key' => $followUp->progress_kemampuan,
                'progress_kemampuan' => $this->labelFromOptions($followUp->progress_kemampuan, $this->progressOptions()),
                'status_key' => $followUp->status ?? 'selesai',
                'status_label' => $this->labelFromOptions($followUp->status ?? 'selesai', $this->statusOptions()),
                'catatan' => $followUp->catatan,
                'rencana_follow_up_at' => optional($followUp->rencana_follow_up_at)->format('Y-m-d'),
                'input_oleh' => $followUp->user?->name ?? '-',
                'record_status' => $followUp->record_status ?? 'draft',
                'record_status_label' => ($followUp->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
            ]);

        return Inertia::render('Admin/Marketing/FollowUp/Index', [
            'title' => 'Jejak Follow Up',
            'description' => 'Catat tindak lanjut marketing berdasarkan customer yang sudah diinput.',
            'baseUrl' => route('admin.marketing.jejak-follow-up.index', absolute: false),
            'rows' => $followUps,
            'filters' => [
                'search' => $search,
            ],
            'customers' => $this->customerOptions(),
            'options' => [
                'methodOptions' => $this->methodOptions(),
                'seriousOptions' => $this->seriousOptions(),
                'progressOptions' => $this->progressOptions(),
                'statusOptions' => $this->statusOptions(),
            ],
        ]);
    }

    public function store(Request $request, MarketingLeadStatusService $leadStatus): RedirectResponse
    {
        $validated = $request->validate([
            'costumer_id' => ['required', 'exists:costumers,id'],
            'tanggal_follow_up' => ['required', 'date'],
            'metode_follow_up' => ['required', Rule::in(array_column($this->methodOptions(), 'value'))],
            'status_serius' => ['required', 'boolean'],
            'progress_kemampuan' => ['required', Rule::in(array_column($this->progressOptions(), 'value'))],
            'status' => ['required', Rule::in(array_column($this->statusOptions(), 'value'))],
            'catatan' => ['nullable', 'string'],
            'rencana_follow_up_at' => ['nullable', 'date'],
        ]);

        $this->ensureCustomerCanBeUsed($request, (int) $validated['costumer_id']);

        CostumerFollowUp::create([
            ...$validated,
            'user_id' => $request->user()?->id,
        ]);

        $leadStatus->markCustomer((int) $validated['costumer_id'], $this->statusFromFollowUp($validated['progress_kemampuan']));

        return back()->with('success', 'Follow up customer berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $leadStatus = app(MarketingLeadStatusService::class);
        $validated = $request->validate([
            'costumer_id' => ['required', 'exists:costumers,id'],
            'tanggal_follow_up' => ['required', 'date'],
            'metode_follow_up' => ['required', Rule::in(array_column($this->methodOptions(), 'value'))],
            'status_serius' => ['required', 'boolean'],
            'progress_kemampuan' => ['required', Rule::in(array_column($this->progressOptions(), 'value'))],
            'status' => ['required', Rule::in(array_column($this->statusOptions(), 'value'))],
            'catatan' => ['nullable', 'string'],
            'rencana_follow_up_at' => ['nullable', 'date'],
        ]);

        $row = CostumerFollowUp::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('user_id', $request->user()?->id))
            ->findOrFail($id);
        $this->abortIfLocked($row);
        $this->ensureCustomerCanBeUsed($request, (int) $validated['costumer_id']);

        $row->update([
            ...$validated,
            'user_id' => $request->user()?->id,
        ]);

        $leadStatus->markCustomer((int) $validated['costumer_id'], $this->statusFromFollowUp($validated['progress_kemampuan']));

        return back()->with('success', 'Follow up customer berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $row = CostumerFollowUp::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('user_id', $request->user()?->id))
            ->findOrFail($id);
        $this->abortIfLocked($row);
        $row->delete();

        return back()->with('success', 'Follow up berhasil dihapus.');
    }

    protected function customerOptions(): array
    {
        return Costumer::query()
            ->when($this->shouldScopeToCurrentMarketing(request()), fn (Builder $query) => $query->where('created_by', request()->user()?->id))
            ->select(['id', 'kode_costumer', 'nama', 'no_identitas', 'telepon', 'email', 'alamat', 'pekerjaan'])
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (Costumer $costumer) => [
                'id' => $costumer->id,
                'kode_costumer' => $costumer->kode_costumer,
                'nama' => $costumer->nama,
                'no_identitas' => $costumer->no_identitas,
                'telepon' => $costumer->telepon,
                'email' => $costumer->email,
                'alamat' => $costumer->alamat,
                'pekerjaan' => $costumer->pekerjaan,
                'search' => strtolower(implode(' ', [
                    $costumer->nama,
                    $costumer->no_identitas,
                    $costumer->kode_costumer,
                    $costumer->telepon,
                ])),
            ])
            ->all();
    }

    protected function methodOptions(): array
    {
        return [
            ['value' => 'chat', 'label' => 'Chat'],
            ['value' => 'kunjungan_langsung', 'label' => 'Kunjungan Langsung'],
            ['value' => 'telephone', 'label' => 'Telephone'],
        ];
    }

    protected function seriousOptions(): array
    {
        return [
            ['value' => '1', 'label' => 'Serius'],
            ['value' => '0', 'label' => 'Belum Serius'],
        ];
    }

    protected function progressOptions(): array
    {
        return [
            ['value' => 'low', 'label' => 'Low', 'hint' => 'Customer tidak mau dan tidak ada uang.'],
            ['value' => 'medium', 'label' => 'Medium', 'hint' => 'Customer mau tapi tidak ada uang.'],
            ['value' => 'high', 'label' => 'High', 'hint' => 'Customer mau dan uangnya ada.'],
            ['value' => 'very_high', 'label' => 'Very High', 'hint' => 'Customer mau dan berkas diterima.'],
        ];
    }

    protected function statusOptions(): array
    {
        return [
            ['value' => 'menunggu', 'label' => 'Menunggu'],
            ['value' => 'selesai', 'label' => 'Selesai'],
            ['value' => 'dibatalkan', 'label' => 'Dibatalkan'],
        ];
    }

    protected function labelFromOptions(?string $value, array $options): string
    {
        foreach ($options as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value ?? '-';
    }

    protected function statusFromFollowUp(?string $progress): string
    {
        return in_array($progress, ['high', 'very_high'], true)
            ? MarketingLeadStatusService::NEGOSIASI
            : MarketingLeadStatusService::DIHUBUNGI;
    }

    protected function ensureCustomerCanBeUsed(Request $request, int $customerId): void
    {
        if (! $this->shouldScopeToCurrentMarketing($request)) {
            return;
        }

        abort_unless(
            Costumer::query()
                ->whereKey($customerId)
                ->where('created_by', $request->user()?->id)
                ->exists(),
            403,
        );
    }

    protected function modelClass(): string
    {
        return CostumerFollowUp::class;
    }

    protected function shouldScopeToCurrentMarketing(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing'])
            && ! $user->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin']);
    }
}
