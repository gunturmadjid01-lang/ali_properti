<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costumers', function (Blueprint $table): void {
            $table->string('jenis_kelamin')->nullable()->change();
            $table->string('jenis_identitas')->nullable()->change();
            $table->string('no_identitas')->nullable()->change();
            $table->string('status_perkawinan')->nullable()->change();
            $table->string('alamat')->nullable()->change();
        });
        Schema::table('marketing_lead_assignments', function (Blueprint $table): void {
            $table->string('status', 30)->default('offered')->after('reason');
            $table->timestamp('response_due_at')->nullable()->after('assigned_at');
            $table->timestamp('responded_at')->nullable()->after('response_due_at');
            $table->text('response_note')->nullable()->after('responded_at');
            $table->index(['to_marketing_id', 'status', 'response_due_at'], 'lead_assignments_recipient_status_due_index');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_lead_assignments', function (Blueprint $table): void {
            $table->dropIndex('lead_assignments_recipient_status_due_index');
            $table->dropColumn(['status', 'response_due_at', 'responded_at', 'response_note']);
        });
    }
};
