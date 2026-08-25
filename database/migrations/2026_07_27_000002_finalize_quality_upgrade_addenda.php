<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_upgrade_addenda', function (Blueprint $table): void {
            $table->string('status', 30)->default('draft')->after('change_snapshot');
            $table->date('billing_due_date')->nullable()->after('finish_date_change');
            $table->timestamp('applied_at')->nullable()->after('locked_by');
            $table->foreignId('applied_by')->nullable()->after('applied_at')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quality_upgrade_addenda', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('applied_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['status', 'billing_due_date', 'applied_at']);
        });
    }
};
