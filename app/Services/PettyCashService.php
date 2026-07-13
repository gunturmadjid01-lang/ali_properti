<?php

namespace App\Services;

use App\Models\DetailRumah;
use App\Models\HppRealisasi;
use App\Models\PettyCashAccount;
use App\Models\PettyCashExpense;
use App\Models\PettyCashFunding;
use App\Models\PettyCashLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PettyCashService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public const HPP_CATEGORIES = ['material', 'upah_tukang', 'perbaikan_unit', 'pekerjaan_proyek'];

    public function detectCostType(string $category, ?int $perumahanId, ?int $detailRumahId): string
    {
        if (! in_array($category, self::HPP_CATEGORIES, true)) {
            return 'operational';
        }

        if ($detailRumahId) {
            return 'unit_hpp';
        }

        if ($perumahanId) {
            return 'project_hpp';
        }

        throw ValidationException::withMessages([
            'perumahan_id' => 'Kategori biaya pembangunan wajib diarahkan ke perumahan atau unit rumah.',
        ]);
    }

    public function approveFunding(PettyCashFunding $funding, int $approverId, string $proofPath, ?string $notes): void
    {
        DB::transaction(function () use ($funding, $approverId, $proofPath, $notes): void {
            $funding = PettyCashFunding::query()->lockForUpdate()->findOrFail($funding->id);
            if ($funding->status !== PettyCashFunding::PENDING) {
                throw ValidationException::withMessages(['status' => 'Permohonan ini sudah diproses.']);
            }

            $account = PettyCashAccount::query()->lockForUpdate()->findOrFail($funding->petty_cash_account_id);
            $newBalance = (float) $account->balance + (float) $funding->amount;
            $account->update(['balance' => $newBalance, 'updated_by' => $approverId]);

            $funding->update([
                'status' => PettyCashFunding::APPROVED,
                'approved_by' => $approverId,
                'approved_at' => now(),
                'approval_proof_path' => $proofPath,
                'approval_notes' => $notes,
                'rejection_notes' => null,
            ]);

            PettyCashLedger::query()->create([
                'petty_cash_account_id' => $account->id,
                'transaction_date' => now()->toDateString(),
                'direction' => 'in',
                'amount' => $funding->amount,
                'balance_after' => $newBalance,
                'source_type' => PettyCashFunding::class,
                'source_id' => $funding->id,
                'description' => ($funding->type === 'initial' ? 'Pembentukan' : 'Pengisian kembali').' kas kecil '.$funding->number,
                'created_by' => $approverId,
            ]);

            $this->accounting->recordPettyCashFunding($funding->fresh());
        });
    }

    public function createExpense(array $payload, int $userId): PettyCashExpense
    {
        return DB::transaction(function () use ($payload, $userId): PettyCashExpense {
            $account = PettyCashAccount::query()->lockForUpdate()->findOrFail($payload['petty_cash_account_id']);
            $unitId = $payload['detail_rumah_id'] ?? null;
            $perumahanId = $payload['perumahan_id'] ?? null;

            if ($unitId) {
                $unit = DetailRumah::query()->findOrFail($unitId);
                if ($perumahanId && (int) $unit->perumahan_id !== (int) $perumahanId) {
                    throw ValidationException::withMessages(['detail_rumah_id' => 'Unit tidak berada pada perumahan yang dipilih.']);
                }
                $perumahanId = (int) $unit->perumahan_id;
            }

            $costType = $this->detectCostType($payload['category'], $perumahanId ? (int) $perumahanId : null, $unitId ? (int) $unitId : null);
            if ((float) $account->balance < (float) $payload['amount']) {
                throw ValidationException::withMessages(['amount' => 'Saldo kas kecil tidak mencukupi untuk transaksi ini.']);
            }

            $expense = PettyCashExpense::query()->create([
                ...$payload,
                'perumahan_id' => $costType === 'operational' ? ($perumahanId ?: null) : $perumahanId,
                'detail_rumah_id' => $costType === 'unit_hpp' ? $unitId : null,
                'cost_type' => $costType,
                'created_by' => $userId,
            ]);

            $newBalance = (float) $account->balance - (float) $expense->amount;
            $account->update(['balance' => $newBalance, 'updated_by' => $userId]);
            PettyCashLedger::query()->create([
                'petty_cash_account_id' => $account->id,
                'transaction_date' => $expense->expense_date,
                'direction' => 'out',
                'amount' => $expense->amount,
                'balance_after' => $newBalance,
                'source_type' => PettyCashExpense::class,
                'source_id' => $expense->id,
                'description' => $expense->description,
                'created_by' => $userId,
            ]);

            if ($costType !== 'operational') {
                HppRealisasi::query()->create([
                    'target_type' => $costType === 'unit_hpp' ? DetailRumah::class : 'App\\Models\\Perumahan',
                    'target_id' => $costType === 'unit_hpp' ? $unitId : $perumahanId,
                    'perumahan_id' => $perumahanId,
                    'detail_rumah_id' => $costType === 'unit_hpp' ? $unitId : null,
                    'tahapan_pembangunan_id' => $payload['tahapan_pembangunan_id'] ?? null,
                    'kelompok_hpp_id' => $payload['kelompok_hpp_id'] ?? null,
                    'source_type' => PettyCashExpense::class,
                    'source_id' => $expense->id,
                    'sumber_type' => PettyCashExpense::class,
                    'sumber_id' => $expense->id,
                    'tanggal' => $expense->expense_date,
                    'nominal' => $expense->amount,
                    'keterangan' => 'Kas kecil '.$expense->number.' - '.$expense->description,
                    'user_id' => $userId,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            $this->accounting->recordPettyCashExpense($expense->fresh());

            return $expense;
        });
    }
}
