<?php

namespace App\Services;

use App\Models\PettyCashAccount;
use App\Models\User;
use App\Support\CodeGenerator;
use App\Support\SchemaMetadata;

class UserPettyCashService
{
    public function ensureFor(User $user): ?PettyCashAccount
    {
        if (! SchemaMetadata::hasTable('petty_cash_accounts')) {
            return null;
        }

        $existing = PettyCashAccount::withTrashed()
            ->where('assigned_user_id', $user->id)
            ->oldest('id')
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        return PettyCashAccount::query()->create([
            'code' => CodeGenerator::next(PettyCashAccount::class, 'code', 'KK'),
            'name' => 'Kas Kecil - '.$user->name,
            'branch_id' => $user->kantor_cabang_id,
            'assigned_user_id' => $user->id,
            'target_amount' => 0,
            'balance' => 0,
            'minimum_balance' => 0,
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
