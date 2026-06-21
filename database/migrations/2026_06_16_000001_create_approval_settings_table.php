<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_settings', function (Blueprint $table) {
            $table->id();
            $table->string('module_key');
            $table->string('module_label');
            $table->string('action');
            $table->boolean('requires_approval')->default(false);
            $table->json('approver_role_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['module_key', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_settings');
    }
};
