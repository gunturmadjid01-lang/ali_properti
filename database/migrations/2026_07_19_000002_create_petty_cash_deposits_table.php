<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('petty_cash_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_account_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->date('deposit_date');
            $table->decimal('amount', 18, 2);
            $table->string('status')->default('draft')->index();
            $table->string('record_status')->default('draft');
            $table->string('proof_path');
            $table->text('notes')->nullable();
            $table->dateTime('deposited_at')->nullable();
            $table->foreignId('deposited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $financeRoleId = DB::table('roles')->whereRaw('LOWER(name) = ?', ['keuangan'])->value('id');
        $roleIds = $financeRoleId ? [(int) $financeRoleId] : [];
        DB::table('approval_settings')->updateOrInsert(
            ['module_key' => 'petty-cash-deposit', 'action' => 'lock'],
            ['module_label' => 'Penyetoran Kas Kecil ke Kas Perusahaan', 'requires_approval' => true, 'approval_stages' => 1, 'approver_role_ids' => json_encode($roleIds), 'approval_steps' => json_encode([['step' => 1, 'role_ids' => $roleIds]]), 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        DB::table('approval_settings')->where('module_key', 'petty-cash-deposit')->delete();
        Schema::dropIfExists('petty_cash_deposits');
    }
};
