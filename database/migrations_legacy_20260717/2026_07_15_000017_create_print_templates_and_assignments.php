<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('paper_size', ['a4', 'legal', 'custom'])->default('a4');
            $table->enum('orientation', ['portrait', 'landscape'])->default('portrait');
            $table->decimal('custom_width_mm', 8, 2)->nullable();
            $table->decimal('custom_height_mm', 8, 2)->nullable();
            $table->decimal('margin_top_mm', 8, 2)->default(15);
            $table->decimal('margin_right_mm', 8, 2)->default(15);
            $table->decimal('margin_bottom_mm', 8, 2)->default(15);
            $table->decimal('margin_left_mm', 8, 2)->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('print_template_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_template_id')->constrained()->cascadeOnDelete();
            $table->string('print_key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_template_assignments');
        Schema::dropIfExists('print_templates');
    }
};
