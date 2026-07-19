<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_transaction_archives', function (Blueprint $t) {
            $t->id();
            $t->string('module', 30);
            $t->string('section', 50);
            $t->unsignedBigInteger('record_id');
            $t->string('document_no')->unique();
            $t->string('status')->default('draft');
            $t->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('rejected_at')->nullable();
            $t->text('approval_notes')->nullable();
            $t->foreignId('last_printed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('last_printed_at')->nullable();
            $t->unsignedInteger('print_count')->default(0);
            $t->timestamps();
            $t->unique(['module', 'section', 'record_id'], 'operation_archive_record_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_transaction_archives');
    }
};
