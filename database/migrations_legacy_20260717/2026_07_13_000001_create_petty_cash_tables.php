<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('branch_id')->nullable()->constrained('cabang_perusahaans')->nullOnDelete();
            $table->decimal('target_amount', 16, 2)->default(0);
            $table->decimal('balance', 16, 2)->default(0);
            $table->decimal('minimum_balance', 16, 2)->default(0);
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('petty_cash_fundings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_account_id')->constrained('petty_cash_accounts')->cascadeOnDelete();
            $table->string('number')->unique();
            $table->string('type');
            $table->date('request_date');
            $table->decimal('amount', 16, 2);
            $table->string('status')->default('draft');
            $table->text('request_notes')->nullable();
            $table->string('request_proof_path')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('approval_proof_path')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['petty_cash_account_id', 'status']);
        });

        Schema::create('petty_cash_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_account_id')->constrained('petty_cash_accounts')->cascadeOnDelete();
            $table->string('number')->unique();
            $table->date('expense_date');
            $table->string('category');
            $table->string('cost_type');
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->nullOnDelete();
            $table->foreignId('kelompok_hpp_id')->nullable()->constrained('kelompok_hpps')->nullOnDelete();
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->decimal('amount', 16, 2);
            $table->text('description');
            $table->string('proof_path');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cost_type', 'expense_date']);
            $table->index(['perumahan_id', 'detail_rumah_id']);
        });

        Schema::create('petty_cash_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_account_id')->constrained('petty_cash_accounts')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->string('direction');
            $table->decimal('amount', 16, 2);
            $table->decimal('balance_after', 16, 2);
            $table->nullableMorphs('source');
            $table->string('description');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['petty_cash_account_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_ledgers');
        Schema::dropIfExists('petty_cash_expenses');
        Schema::dropIfExists('petty_cash_fundings');
        Schema::dropIfExists('petty_cash_accounts');
    }
};
