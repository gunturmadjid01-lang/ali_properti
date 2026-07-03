<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Models\DetailRumah;
use App\Models\DetailRumahHpp;
use App\Models\Kontraktor;
use App\Models\Perumahan;
use App\Models\PerumahanHpp;
use App\Models\SpkKontraktorAddition;
use App\Models\SpkKontraktor;
use App\Models\SpkKontraktorPayment;
use App\Services\AccountingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SpkKontraktorController extends Controller
{
    use HandlesCrudLock {
        lock as protected traitLock;
        unlock as protected traitUnlock;
    }

    public function index(Request $request): Response
    {
        return $this->renderIndex($request, 'spk');
    }

    public function approvalIndex(Request $request): Response
    {
        return $this->renderIndex($request, 'approval');
    }

    public function disbursementIndex(Request $request): Response
    {
        abort_unless($this->canApprovePayment(), 403, 'Hanya manajer atau owner yang dapat mengakses persetujuan pencairan SPK.');

        return $this->renderIndex($request, 'disbursement');
    }

    public function paymentIndex(Request $request): Response
    {
        return $this->renderIndex($request, 'payment');
    }

    protected function renderIndex(Request $request, string $mode): Response
    {
        if ($mode === 'payment') {
            abort_unless($this->canViewSpkPayment(), 403, 'Anda tidak memiliki permission untuk membuka pembayaran SPK.');
        } else {
            $this->authorizeProjectPaymentViewer();
        }
        $search = trim((string) $request->query('search', ''));
        $approvalOnly = $mode === 'approval';
        $paymentOnly = $mode === 'payment';
        $disbursementOnly = $mode === 'disbursement';

        $rows = SpkKontraktor::query()
            ->with(['kontraktor:id,nama_kontraktor', 'perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah', 'payments.contractorOpname:id,kode_opname', 'additions'])
            ->when($disbursementOnly, fn (Builder $query) => $query->whereHas(
                'payments',
                fn (Builder $query) => $query->whereIn('status', $this->spkApprovalPendingStatuses()),
            ))
            ->when($paymentOnly, fn (Builder $query) => $query->whereHas(
                'payments',
                fn (Builder $query) => $query->whereIn('status', ['menunggu_pengajuan', 'menunggu_pembayaran_keuangan']),
            ))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('nomor_spk', 'like', "%{$search}%")
                        ->orWhere('judul_pekerjaan', 'like', "%{$search}%")
                        ->orWhere('jenis_pekerjaan', 'like', "%{$search}%")
                        ->orWhereHas('kontraktor', fn (Builder $query) => $query->where('nama_kontraktor', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(function (SpkKontraktor $row) {
                $hppPlan = $this->hppPlanStatus($row);

                return [
                'id' => $row->id,
                'kontraktor_id' => (string) $row->kontraktor_id,
                'perumahan_id' => (string) ($row->perumahan_id ?? ''),
                'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
                'nomor_spk' => $row->nomor_spk,
                'judul_pekerjaan' => $row->judul_pekerjaan,
                'jenis_pekerjaan' => $row->jenis_pekerjaan,
                'tanggal_spk' => optional($row->tanggal_spk)->format('Y-m-d'),
                'tanggal_mulai' => optional($row->tanggal_mulai)->format('Y-m-d'),
                'tanggal_selesai' => optional($row->tanggal_selesai)->format('Y-m-d'),
                'nilai_kontrak_dasar' => $row->nilai_kontrak_dasar,
                'nilai_kontrak' => $row->nilai_kontrak,
                'total_penambahan' => $row->total_penambahan,
                'metode_pembayaran' => $row->metode_pembayaran,
                'approval_role' => $this->normalizeApprovalRole($row->approval_role),
                'record_status' => $row->record_status ?? 'draft',
                'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
                'lingkup_pekerjaan' => $row->lingkup_pekerjaan,
                'catatan' => $row->catatan,
                'status' => $row->status,
                'hpp_plan_exists' => $hppPlan['exists'],
                'hpp_plan_total' => $hppPlan['total'],
                'hpp_plan_label' => $hppPlan['label'],
                'kontraktor' => $row->kontraktor?->nama_kontraktor ?? '-',
                'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                'unit' => $row->detailRumah ? trim($row->detailRumah->kode_nlok.' '.$row->detailRumah->nomor_rumah) : '-',
                'payments' => $row->payments->sortBy('termin_ke')->map(fn ($payment) => [
                    'id' => $payment->id,
                    'termin_ke' => $payment->termin_ke,
                    'tanggal_jatuh_tempo' => optional($payment->tanggal_jatuh_tempo)->format('Y-m-d'),
                    'tanggal_pembayaran' => optional($payment->tanggal_pembayaran)->format('Y-m-d'),
                    'nominal' => $payment->nominal,
                    'keterangan' => $payment->keterangan,
                    'opname' => $payment->contractorOpname?->kode_opname,
                    'status' => $payment->status,
                    'status_label' => $this->paymentStatusLabel($payment->status),
                ])->values(),
                'additions' => $row->additions->map(fn (SpkKontraktorAddition $addition) => [
                    'id' => $addition->id,
                    'kategori_penambahan' => $addition->kategori_penambahan,
                    'judul_penambahan' => $addition->judul_penambahan,
                    'deskripsi' => $addition->deskripsi,
                    'volume' => $addition->volume,
                    'satuan' => $addition->satuan,
                    'harga_satuan' => $addition->harga_satuan,
                    'total' => $addition->total,
                    'keterangan' => $addition->keterangan,
                ])->values(),
                'can_edit' => $this->canManageSpk() && ($row->record_status ?? 'draft') !== 'locked',
                'can_delete' => $this->canManageSpk() && ($row->record_status ?? 'draft') !== 'locked',
                'can_lock' => (bool) auth()->check() && ($row->record_status ?? 'draft') !== 'locked',
                'can_unlock' => $this->canManageSpkLock() && ($row->record_status ?? 'draft') === 'locked',
                ];
            });

        $component = match ($mode) {
            'disbursement' => 'Admin/SpkKontraktor/Disbursement',
            'payment' => 'Admin/SpkKontraktor/Payment',
            default => 'Admin/SpkKontraktor/Index',
        };

        return Inertia::render($component, [
            'title' => match ($mode) {
                'approval' => 'Approval Pembayaran SPK',
                'disbursement' => 'Persetujuan Pencairan SPK',
                'payment' => 'Pembayaran SPK Kontraktor',
                default => 'SPK Kontraktor',
            },
            'description' => match ($mode) {
                'approval' => 'Daftar termin SPK yang membutuhkan persetujuan manajer atau owner.',
                'disbursement' => 'Owner atau manajer memeriksa detail termin sebelum menyetujui pencairan.',
                'payment' => 'Admin keuangan mengajukan termin dan mencatat pembayaran setelah disetujui.',
                default => 'Buat surat perjanjian kontrak dan jadwal pembayaran kontraktor.',
            },
            'baseUrl' => route('admin.spk-kontraktor.index', absolute: false),
            'pageUrl' => match ($mode) {
                'approval' => route('admin.spk-kontraktor.approval.index', absolute: false),
                'disbursement' => route('admin.spk-kontraktor.disbursement.index', absolute: false),
                'payment' => route('admin.spk-kontraktor.payment.index', absolute: false),
                default => route('admin.spk-kontraktor.index', absolute: false),
            },
            'rows' => $rows,
            'filters' => ['search' => $search],
            'options' => $this->options(),
            'permissions' => [
                'canManageSpk' => $this->canManageSpk(),
                'canViewPaymentDetail' => $this->canApprovePayment(),
                'canRequestPayment' => $this->canRequestSpkPayment(),
                'canApprovePayment' => $this->canApprovePayment(),
                'canReleasePayment' => $this->canReleaseSpkPayment(),
            ],
            'approvalOnly' => $approvalOnly,
            'paymentOnly' => $paymentOnly,
            'disbursementOnly' => $disbursementOnly,
        ]);
    }

    public function requestPayment(Request $request, string $id, string $paymentId, AccountingService $accounting): RedirectResponse
    {
        abort_unless($this->canRequestSpkPayment(), 403, 'Anda tidak memiliki permission untuk mengajukan pembayaran SPK.');
        $payment = $this->payment($id, $paymentId);
        abort_unless($payment->status === 'menunggu_pengajuan', 422, 'Termin ini sudah diajukan sebelumnya.');
        if (! $payment->spkKontraktor?->perumahan_id) {
            throw ValidationException::withMessages([
                'location' => 'Lokasi perumahan SPK belum dipilih. Perbarui SPK sebelum mengajukan pembayaran.',
            ]);
        }
        $hppPlan = $this->hppPlanStatus($payment->spkKontraktor);

        if (! $hppPlan['exists'] && ! $request->boolean('confirm_without_hpp')) {
            throw ValidationException::withMessages([
                'hpp' => 'Rencana HPP '.$hppPlan['label'].' belum diisi. Konfirmasi diperlukan untuk tetap mengajukan pembayaran.',
            ]);
        }

        DB::transaction(function () use ($payment, $accounting) {
            $payment->update([
                'status' => 'menunggu_approval_manajer',
                'requested_by' => auth()->id(),
                'requested_at' => now(),
            ]);

            $accounting->recordContractorBill($payment->fresh('spkKontraktor'));
            $this->syncSpkStatus($payment->spkKontraktor);
        });

        return back()->with('success', 'Pembayaran termin berhasil diajukan ke manajer.');
    }

    public function approvePayment(string $id, string $paymentId): RedirectResponse
    {
        abort_unless($this->canApprovePayment(), 403, 'Hanya manajer atau owner yang dapat menyetujui pembayaran termin.');
        $payment = $this->payment($id, $paymentId);
        abort_unless(in_array($payment->status, $this->spkApprovalPendingStatuses(), true), 422, 'Termin belum diajukan atau sudah diproses.');
        $payment->update([
            'status' => 'menunggu_pembayaran_keuangan',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        $this->syncSpkStatus($payment->spkKontraktor);

        return back()->with('success', 'Pembayaran termin berhasil di-approve manajer.');
    }

    public function releasePayment(string $id, string $paymentId, AccountingService $accounting): RedirectResponse
    {
        abort_unless($this->canReleaseSpkPayment(), 403, 'Anda tidak memiliki permission untuk mencatat pembayaran SPK.');
        $payment = $this->payment($id, $paymentId);
        abort_unless($payment->status === 'menunggu_pembayaran_keuangan', 422, 'Termin belum disetujui manajer atau sudah dibayar.');
        DB::transaction(function () use ($payment, $accounting) {
            $paidAt = now();
            $payment->update([
                'status' => 'dana_cair',
                'released_by' => auth()->id(),
                'released_at' => $paidAt,
                'paid_at' => $paidAt,
                'tanggal_pembayaran' => $paidAt->toDateString(),
            ]);

            $accounting->recordContractorPayment($payment->fresh('spkKontraktor.perumahan'));
            $this->syncSpkStatus($payment->spkKontraktor);
        });

        return back()->with('success', 'Dana termin berhasil dicairkan.');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeOwnerOrManager();
        $payload = $this->payload($request);
        $payload = $this->preparePayloadTotals($payload);
        $this->ensurePaymentTotalMatchesContract($payload);

        DB::transaction(function () use ($payload) {
            $payments = $this->paymentsPayload($payload);
            $additions = $this->additionsPayload($payload);
            unset($payload['payments']);
            unset($payload['additions']);

            $spk = SpkKontraktor::query()->create([
                ...$payload,
                'nomor_spk' => $this->nextNumber(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncPayments($spk, $payments);
            $this->syncAdditions($spk, $additions);
        });

        return back()->with('success', 'SPK kontraktor berhasil dibuat.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeOwnerOrManager();
        $payload = $this->payload($request);
        $payload = $this->preparePayloadTotals($payload);
        $this->ensurePaymentTotalMatchesContract($payload);
        $spk = SpkKontraktor::query()->findOrFail($id);
        abort_if(($spk->record_status ?? 'draft') === 'locked', 422, 'SPK sudah dikunci. Buka lock terlebih dahulu sebelum mengubah data.');
        abort_if(
            $spk->payments()->where('status', '!=', 'menunggu_pengajuan')->exists(),
            422,
            'SPK tidak dapat diubah karena sudah memiliki termin yang diproses. Batalkan proses pembayaran terlebih dahulu.',
        );

        DB::transaction(function () use ($spk, $payload) {
            $payments = $this->paymentsPayload($payload);
            $additions = $this->additionsPayload($payload);
            unset($payload['payments']);
            unset($payload['additions']);

            $spk->update([
                ...$payload,
                'updated_by' => auth()->id(),
            ]);

            $this->syncPayments($spk, $payments);
            $this->syncAdditions($spk, $additions);
        });

        return back()->with('success', 'SPK kontraktor berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeOwnerOrManager();
        $spk = SpkKontraktor::query()->findOrFail($id);
        abort_if(($spk->record_status ?? 'draft') === 'locked', 422, 'SPK sudah dikunci. Buka lock terlebih dahulu sebelum menghapus data.');
        $spk->delete();

        return back()->with('success', 'SPK kontraktor berhasil dihapus.');
    }

    protected function payload(Request $request): array
    {
        $payload = $request->validate([
            'kontraktor_id' => ['required', 'exists:kontraktors,id'],
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_id' => ['required_if:jenis_pekerjaan,rumah', 'nullable', 'exists:detail_rumahs,id'],
            'judul_pekerjaan' => ['required', 'string', 'max:255'],
            'jenis_pekerjaan' => ['required', 'in:rumah,jalan,pembukaan_lahan,lainnya'],
            'tanggal_spk' => ['required', 'date'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date'],
            'nilai_kontrak' => ['required', 'numeric', 'min:0'],
            'metode_pembayaran' => ['required', 'in:cash,cicil'],
            'approval_role' => ['required', 'in:manajer,admin'],
            'lingkup_pekerjaan' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,aktif,selesai,batal'],
            'payments' => ['required', 'array', 'min:1'],
            'nilai_kontrak_dasar' => ['required', 'numeric', 'min:0'],
            'payments.*.tanggal_jatuh_tempo' => ['nullable', 'date'],
            'payments.*.nominal' => ['required', 'numeric', 'min:0'],
            'payments.*.keterangan' => ['nullable', 'string'],
            'additions' => ['nullable', 'array'],
            'additions.*.kategori_penambahan' => ['required_with:additions', 'in:lahan,pekerjaan_tambahan,lainnya'],
            'additions.*.judul_penambahan' => ['required_with:additions', 'string', 'max:255'],
            'additions.*.deskripsi' => ['nullable', 'string'],
            'additions.*.volume' => ['required_with:additions', 'numeric', 'min:0'],
            'additions.*.satuan' => ['nullable', 'string', 'max:50'],
            'additions.*.harga_satuan' => ['required_with:additions', 'numeric', 'min:0'],
            'additions.*.keterangan' => ['nullable', 'string'],
        ], [], [
            'kontraktor_id' => 'Kontraktor',
            'judul_pekerjaan' => 'Judul pekerjaan',
            'jenis_pekerjaan' => 'Jenis pekerjaan',
            'tanggal_spk' => 'Tanggal SPK',
            'nilai_kontrak_dasar' => 'Nilai dasar kontrak',
            'nilai_kontrak' => 'Nilai kontrak',
            'metode_pembayaran' => 'Metode pembayaran',
            'approval_role' => 'Approval SPK',
            'payments' => 'Jadwal pembayaran',
            'payments.*.nominal' => 'Nominal pembayaran',
            'payments.*.tanggal_jatuh_tempo' => 'Tanggal jatuh tempo',
            'additions.*.judul_penambahan' => 'Judul penambahan',
        ]);

        if (! empty($payload['detail_rumah_id'])) {
            $unitMatchesPerumahan = DetailRumah::query()
                ->whereKey($payload['detail_rumah_id'])
                ->where('perumahan_id', $payload['perumahan_id'])
                ->exists();

            if (! $unitMatchesPerumahan) {
                throw ValidationException::withMessages([
                    'detail_rumah_id' => 'Unit rumah harus berasal dari perumahan yang dipilih.',
                ]);
            }
        }

        return $payload;
    }

    protected function paymentsPayload(array $payload): array
    {
        $payments = $payload['payments'] ?? [];

        if (($payload['metode_pembayaran'] ?? 'cash') === 'cash') {
            $first = $payments[0] ?? [];

            return [[
                'termin_ke' => 1,
                'tanggal_jatuh_tempo' => $first['tanggal_jatuh_tempo'] ?? $payload['tanggal_spk'],
                'tanggal_pembayaran' => null,
                'nominal' => $payload['nilai_kontrak'],
                'keterangan' => $first['keterangan'] ?? 'Pembayaran cash/lunas.',
                'status' => 'menunggu_pengajuan',
            ]];
        }

        return collect($payments)
            ->values()
            ->map(fn (array $payment, int $index) => [
                'termin_ke' => $index + 1,
                'tanggal_jatuh_tempo' => $payment['tanggal_jatuh_tempo'] ?? $payload['tanggal_spk'],
                'tanggal_pembayaran' => null,
                'nominal' => $payment['nominal'] ?? 0,
                'keterangan' => $payment['keterangan'] ?? null,
                'status' => 'menunggu_pengajuan',
            ])
            ->all();
    }

    protected function preparePayloadTotals(array $payload): array
    {
        $additions = $this->additionsPayload($payload);
        $totalPenambahan = $this->calculateTotalPenambahan($additions);
        $nilaiDasar = (float) ($payload['nilai_kontrak_dasar'] ?? 0);

        $payload['additions'] = $additions;
        $payload['total_penambahan'] = $totalPenambahan;
        $payload['nilai_kontrak'] = $nilaiDasar + $totalPenambahan;

        return $payload;
    }

    protected function additionsPayload(array $payload): array
    {
        return collect($payload['additions'] ?? [])
            ->values()
            ->filter(fn (array $addition) => filled($addition['judul_penambahan'] ?? null))
            ->map(function (array $addition) {
                $volume = (float) ($addition['volume'] ?? 0);
                $hargaSatuan = (float) ($addition['harga_satuan'] ?? 0);

                return [
                    'kategori_penambahan' => $addition['kategori_penambahan'] ?? 'lainnya',
                    'judul_penambahan' => $addition['judul_penambahan'],
                    'deskripsi' => $addition['deskripsi'] ?? null,
                    'volume' => $volume,
                    'satuan' => $addition['satuan'] ?? null,
                    'harga_satuan' => $hargaSatuan,
                    'total' => $volume * $hargaSatuan,
                    'keterangan' => $addition['keterangan'] ?? null,
                ];
            })
            ->all();
    }

    protected function calculateTotalPenambahan(array $additions): float
    {
        return (float) collect($additions)->sum(fn (array $addition) => (float) ($addition['total'] ?? 0));
    }

    public function lock(string $id): RedirectResponse
    {
        abort_unless(auth()->check(), 403, 'Silakan login untuk mengunci SPK.');
        $spk = SpkKontraktor::query()->findOrFail($id);
        $spk->update([
            'record_status' => 'locked',
            'locked_at' => now(),
            'locked_by' => auth()->id(),
            'status' => $spk->status === 'batal' ? 'batal' : 'aktif',
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'SPK berhasil dikunci dan tidak dapat diedit.');
    }

    public function unlock(string $id): RedirectResponse
    {
        abort_unless($this->canManageSpkLock(), 403, 'Hanya user yang diberi akses yang dapat membuka lock SPK.');
        $spk = SpkKontraktor::query()->findOrFail($id);
        $hasProcessedPayment = $spk->payments()->where('status', '!=', 'menunggu_pengajuan')->exists();
        $spk->update([
            'record_status' => 'draft',
            'locked_at' => null,
            'locked_by' => null,
            'status' => $spk->status === 'batal' ? 'batal' : ($hasProcessedPayment ? 'aktif' : 'draft'),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Lock SPK berhasil dibuka.');
    }

    protected function payment(string $spkId, string $paymentId): SpkKontraktorPayment
    {
        return SpkKontraktorPayment::query()
            ->where('spk_kontraktor_id', $spkId)
            ->findOrFail($paymentId);
    }

    protected function paymentStatusLabel(?string $status): string
    {
        return match ($status) {
            'menunggu_approval_manager', 'menunggu_approval_manajer' => 'Menunggu Approval Manajer',
            'menunggu_pembayaran_keuangan' => 'Menunggu Pembayaran Keuangan',
            'dana_cair' => 'Dana Cair / Terbayar',
            default => 'Belum Diajukan',
        };
    }

    protected function ensurePaymentTotalMatchesContract(array $payload): void
    {
        if (($payload['metode_pembayaran'] ?? 'cash') === 'cash') {
            return;
        }

        $nilaiKontrak = (int) round((float) ($payload['nilai_kontrak'] ?? 0));
        $totalTermin = collect($payload['payments'] ?? [])
            ->sum(fn (array $payment) => (int) round((float) ($payment['nominal'] ?? 0)));

        if ($totalTermin !== $nilaiKontrak) {
            throw ValidationException::withMessages([
                'payments' => 'Total nominal termin harus sama dengan nilai kontrak. Total termin saat ini Rp '.number_format($totalTermin, 0, ',', '.').' dari nilai kontrak Rp '.number_format($nilaiKontrak, 0, ',', '.').'.',
            ]);
        }
    }

    protected function syncPayments(SpkKontraktor $spk, array $payments): void
    {
        $spk->payments()->delete();

        foreach ($payments as $payment) {
            $spk->payments()->create($payment);
        }
    }

    protected function syncAdditions(SpkKontraktor $spk, array $additions): void
    {
        $spk->additions()->delete();

        foreach ($additions as $addition) {
            $spk->additions()->create($addition);
        }
    }

    protected function nextNumber(): string
    {
        $next = SpkKontraktor::withTrashed()->count() + 1;

        return 'SPK/'.now()->format('Ym').'/'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    protected function options(): array
    {
        return [
            'kontraktors' => Kontraktor::query()->where('status', 'aktif')->orderBy('nama_kontraktor')->get(['id', 'nama_kontraktor'])->map(fn (Kontraktor $row) => ['value' => (string) $row->id, 'label' => $row->nama_kontraktor])->values(),
            'perumahans' => Perumahan::query()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn (Perumahan $row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])->values(),
            'detailRumahs' => DetailRumah::query()->orderBy('kode_nlok')->orderBy('nomor_rumah')->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah'])->map(fn (DetailRumah $row) => [
                'value' => (string) $row->id,
                'label' => trim($row->kode_nlok.' '.$row->nomor_rumah),
                'perumahan_id' => (string) $row->perumahan_id,
            ])->values(),
            'jenisPekerjaan' => [
                ['value' => 'rumah', 'label' => 'Pembangunan Rumah'],
                ['value' => 'jalan', 'label' => 'Pembangunan Jalan'],
                ['value' => 'pembukaan_lahan', 'label' => 'Pembukaan Lahan'],
                ['value' => 'lainnya', 'label' => 'Lainnya'],
            ],
            'metodePembayaran' => [
                ['value' => 'cash', 'label' => 'Cash / Sekaligus'],
                ['value' => 'cicil', 'label' => 'Cicil / Termin'],
            ],
            'approvalRoles' => [
                ['value' => 'manajer', 'label' => 'Manajer'],
                ['value' => 'admin', 'label' => 'Admin'],
            ],
            'kategoriPenambahan' => [
                ['value' => 'lahan', 'label' => 'Penambahan Lahan'],
                ['value' => 'pekerjaan_tambahan', 'label' => 'Pekerjaan Tambahan'],
                ['value' => 'lainnya', 'label' => 'Lainnya'],
            ],
            'status' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'aktif', 'label' => 'Aktif'],
                ['value' => 'selesai', 'label' => 'Selesai'],
                ['value' => 'batal', 'label' => 'Batal'],
            ],
        ];
    }

    protected function authorizeProjectPaymentViewer(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        abort_unless(
            $user->can('spk-kontraktor.view')
            || $user->can('spk-payment.view')
            || $user->hasAnyRole(['owner', 'super_admin']),
            403,
            'Anda tidak memiliki permission untuk mengakses SPK kontraktor.',
        );
    }

    protected function authorizePaymentDisbursement(): void
    {
        abort_unless(
            $this->canRequestSpkPayment() || $this->canReleaseSpkPayment(),
            403,
            'Anda tidak memiliki permission untuk memproses pembayaran termin.',
        );
    }

    protected function canManagePaymentDisbursement(): bool
    {
        return $this->canRequestSpkPayment() || $this->canReleaseSpkPayment();
    }

    protected function canViewSpkPayment(): bool
    {
        return (bool) auth()->user()?->can('spk-payment.view');
    }

    protected function canRequestSpkPayment(): bool
    {
        return (bool) auth()->user()?->can('spk-payment.create');
    }

    protected function canReleaseSpkPayment(): bool
    {
        return (bool) auth()->user()?->can('spk-payment.update');
    }

    protected function canApprovePayment(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['owner', 'super_admin', 'manajer_pimpro']);
    }

    protected function canManageSpk(): bool
    {
        return (bool) auth()->user()?->can('spk-kontraktor.create')
            || auth()->user()?->can('spk-kontraktor.update')
            || auth()->user()?->can('spk-kontraktor.delete')
            || auth()->user()?->hasAnyRole(['owner', 'super_admin']);
    }

    protected function canManageSpkLock(): bool
    {
        $user = auth()->user();

        return (bool) $user && (
            $user->hasAnyRole(['super_admin'])
            || $user->can('spk-kontraktor.unlock')
            || $user->can('spk-kontraktor.manage')
        );
    }

    protected function hppPlanStatus(SpkKontraktor $spk): array
    {
        if (! $spk->perumahan_id) {
            return [
                'exists' => false,
                'total' => 0,
                'label' => 'lokasi SPK',
            ];
        }

        if ($spk->detail_rumah_id) {
            $total = (float) DetailRumahHpp::query()
                ->where('detail_rumah_id', $spk->detail_rumah_id)
                ->withSum('items', 'jumlah_rab')
                ->get()
                ->sum('items_sum_jumlah_rab');

            return [
                'exists' => $total > 0,
                'total' => $total,
                'label' => 'unit rumah',
            ];
        }

        $total = (float) PerumahanHpp::query()
            ->where('perumahan_id', $spk->perumahan_id)
            ->withSum('detailPerumahanHpps', 'jumlah_rab')
            ->get()
            ->sum('detail_perumahan_hpps_sum_jumlah_rab');

        return [
            'exists' => $total > 0,
            'total' => $total,
            'label' => 'perumahan',
        ];
    }

    protected function syncSpkStatus(?SpkKontraktor $spk): void
    {
        if (! $spk || $spk->status === 'batal') {
            return;
        }

        $payments = $spk->payments()->get(['status']);
        $allPaid = $payments->isNotEmpty() && $payments->every(fn ($payment) => $payment->status === 'dana_cair');
        $hasProcessed = $payments->contains(fn ($payment) => $payment->status !== 'menunggu_pengajuan');

        $spk->update([
            'status' => $allPaid ? 'selesai' : (($spk->record_status === 'locked' || $hasProcessed) ? 'aktif' : 'draft'),
            'updated_by' => auth()->id(),
        ]);
    }

    protected function normalizeApprovalRole(?string $value): string
    {
        return $value === 'admin' ? 'admin' : 'manajer';
    }

    protected function spkApprovalPendingStatuses(): array
    {
        return ['menunggu_approval_manager', 'menunggu_approval_manajer'];
    }

    protected function authorizeOwnerOrManager(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        abort_unless(
            $user->can('spk-kontraktor.create')
            || $user->can('spk-kontraktor.update')
            || $user->can('spk-kontraktor.delete')
            || $user->hasAnyRole(['owner', 'super_admin']),
            403,
            'Hanya user yang memiliki permission SPK kontraktor yang dapat mengelolanya.',
        );
    }

    protected function modelClass(): string
    {
        return SpkKontraktor::class;
    }
}
