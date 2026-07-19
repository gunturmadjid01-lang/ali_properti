<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table): void {
            $table->foreignId('perumahan_id')->nullable()->after('id')->constrained('perumahans')->nullOnDelete();
            $table->foreignId('master_bank_id')->nullable()->after('perumahan_id')->constrained('master_banks')->nullOnDelete();
            $table->index(['perumahan_id', 'status']);
        });
        Schema::table('employee_advances', function (Blueprint $table): void {
            $table->foreignId('perumahan_id')->nullable()->after('id')->constrained('perumahans')->nullOnDelete();
            $table->foreignId('master_bank_id')->nullable()->after('perumahan_id')->constrained('master_banks')->nullOnDelete();
            $table->index(['perumahan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('employee_advances', function (Blueprint $table): void {
            $table->dropIndex(['perumahan_id', 'status']);
            $table->dropConstrainedForeignId('master_bank_id');
            $table->dropConstrainedForeignId('perumahan_id');
        });
        Schema::table('payroll_batches', function (Blueprint $table): void {
            $table->dropIndex(['perumahan_id', 'status']);
            $table->dropConstrainedForeignId('master_bank_id');
            $table->dropConstrainedForeignId('perumahan_id');
        });
    }
};
