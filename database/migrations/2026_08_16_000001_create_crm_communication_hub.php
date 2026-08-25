<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_communication_threads', function (Blueprint $table): void {
            $table->id();
            $table->string('thread_no')->unique();
            $table->string('channel', 20); // whatsapp, email, sms
            $table->string('external_key')->nullable()->index();
            $table->nullableMorphs('contactable');
            $table->string('contact_name')->nullable();
            $table->string('contact_address')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('last_message_at')->nullable()->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['channel', 'status']);
        });

        Schema::create('crm_communication_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thread_id')->constrained('crm_communication_threads')->cascadeOnDelete();
            $table->string('message_key')->unique();
            $table->string('direction', 10); // masuk, keluar
            $table->string('sender_address')->nullable();
            $table->string('recipient_address')->nullable();
            $table->longText('body')->nullable();
            $table->string('template_code')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable()->index();
            $table->string('status', 20)->default('queued'); // antre, terkirim, gagal, dibaca
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['thread_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_communication_messages');
        Schema::dropIfExists('crm_communication_threads');
    }
};
