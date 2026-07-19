<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprs', function (Blueprint $table) {
            if (! Schema::hasColumn('sprs', 'refund_status')) {
                $table->string('refund_status')->nullable()->after('refund_at');
            }
            if (! Schema::hasColumn('sprs', 'refund_requested_by')) {
                $table->foreignId('refund_requested_by')->nullable()->after('refund_status')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('sprs', 'refund_requested_at')) {
                $table->timestamp('refund_requested_at')->nullable()->after('refund_requested_by');
            }
            if (! Schema::hasColumn('sprs', 'refund_manager_approved_by')) {
                $table->foreignId('refund_manager_approved_by')->nullable()->after('refund_requested_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('sprs', 'refund_manager_approved_at')) {
                $table->timestamp('refund_manager_approved_at')->nullable()->after('refund_manager_approved_by');
            }
            if (! Schema::hasColumn('sprs', 'refund_owner_approved_by')) {
                $table->foreignId('refund_owner_approved_by')->nullable()->after('refund_manager_approved_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('sprs', 'refund_owner_approved_at')) {
                $table->timestamp('refund_owner_approved_at')->nullable()->after('refund_owner_approved_by');
            }
            if (! Schema::hasColumn('sprs', 'refund_rejected_by')) {
                $table->foreignId('refund_rejected_by')->nullable()->after('refund_owner_approved_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('sprs', 'refund_rejected_at')) {
                $table->timestamp('refund_rejected_at')->nullable()->after('refund_rejected_by');
            }
            if (! Schema::hasColumn('sprs', 'refund_approval_note')) {
                $table->text('refund_approval_note')->nullable()->after('refund_rejected_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sprs', function (Blueprint $table) {
            foreach (['refund_approval_note', 'refund_rejected_at', 'refund_owner_approved_at', 'refund_manager_approved_at', 'refund_requested_at', 'refund_status'] as $column) {
                if (Schema::hasColumn('sprs', $column)) {
                    $table->dropColumn($column);
                }
            }
            foreach (['refund_rejected_by', 'refund_owner_approved_by', 'refund_manager_approved_by', 'refund_requested_by'] as $column) {
                if (Schema::hasColumn('sprs', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });
    }
};
