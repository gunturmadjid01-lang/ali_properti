<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_leads', function (Blueprint $table): void {
            $table->string('priority', 20)->default('normal')->after('interest_level');
            $table->string('verification_status', 30)->default('pending')->after('qualification_status')->index();
            $table->text('verification_note')->nullable()->after('verification_status');
            $table->foreignId('verified_by')->nullable()->after('verification_note')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->string('assignment_status', 30)->default('unassigned')->after('marketing_id')->index();
            $table->timestamp('assigned_at')->nullable()->after('assignment_status');
            $table->timestamp('first_response_due_at')->nullable()->after('assigned_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_leads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['priority', 'verification_status', 'verification_note', 'verified_at', 'assignment_status', 'assigned_at', 'first_response_due_at']);
        });
    }
};
