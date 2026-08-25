<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table): void {
            $table->string('record_status')->default('posted')->after('type')->index();
            $table->timestamp('locked_at')->nullable()->after('record_status');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('locked_by');
            $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('posted_by');
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn(['record_status', 'locked_at', 'posted_at']);
        });
    }
};
