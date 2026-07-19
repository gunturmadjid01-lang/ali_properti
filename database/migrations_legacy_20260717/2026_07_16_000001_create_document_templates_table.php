<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('document_type', ['spr', 'ppjb', 'handover']);
            $table->string('description')->nullable();
            $table->longText('header_html')->nullable();
            $table->longText('body_html');
            $table->longText('footer_html')->nullable();
            $table->enum('paper_size', ['a4', 'legal'])->default('a4');
            $table->enum('orientation', ['portrait', 'landscape'])->default('portrait');
            $table->decimal('margin_top_mm', 6, 2)->default(20);
            $table->decimal('margin_right_mm', 6, 2)->default(20);
            $table->decimal('margin_bottom_mm', 6, 2)->default(20);
            $table->decimal('margin_left_mm', 6, 2)->default(20);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
