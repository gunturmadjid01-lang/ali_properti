<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_visits', function (Blueprint $table): void {
            $table->decimal('check_in_latitude', 10, 7)->nullable()->after('location_captured_at');
            $table->decimal('check_in_longitude', 10, 7)->nullable()->after('check_in_latitude');
            $table->unsignedInteger('check_in_accuracy_m')->nullable()->after('check_in_longitude');
            $table->string('check_in_photo_path')->nullable()->after('check_in_accuracy_m');
            $table->decimal('check_out_latitude', 10, 7)->nullable()->after('check_in_photo_path');
            $table->decimal('check_out_longitude', 10, 7)->nullable()->after('check_out_latitude');
            $table->unsignedInteger('check_out_accuracy_m')->nullable()->after('check_out_longitude');
            $table->string('check_out_photo_path')->nullable()->after('check_out_accuracy_m');
            $table->string('device_info', 1000)->nullable()->after('check_out_photo_path');
            $table->string('verification_status')->default('draft')->after('device_info');
            $table->text('verification_note')->nullable()->after('verification_status');
            $table->foreignId('verified_by')->nullable()->after('verification_note')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->index(['verification_status', 'finished_at']);
        });

        Schema::table('marketing_lead_activities', function (Blueprint $table): void {
            $table->string('activity_type')->default('status_change')->after('user_id');
            $table->string('title')->nullable()->after('activity_type');
            $table->json('metadata')->nullable()->after('note');
            $table->string('source_url')->nullable()->after('metadata');
            $table->string('ip_address', 45)->nullable()->after('source_url');
            $table->string('user_agent', 1000)->nullable()->after('ip_address');
            $table->index(['activity_type', 'activity_at']);
        });
    }

    public function down(): void
    {
        Schema::table('marketing_lead_activities', function (Blueprint $table): void {
            $table->dropIndex(['activity_type', 'activity_at']);
            $table->dropColumn(['activity_type', 'title', 'metadata', 'source_url', 'ip_address', 'user_agent']);
        });
        Schema::table('marketing_visits', function (Blueprint $table): void {
            $table->dropIndex(['verification_status', 'finished_at']);
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['check_in_latitude', 'check_in_longitude', 'check_in_accuracy_m', 'check_in_photo_path', 'check_out_latitude', 'check_out_longitude', 'check_out_accuracy_m', 'check_out_photo_path', 'device_info', 'verification_status', 'verification_note', 'verified_by', 'verified_at']);
        });
    }
};
