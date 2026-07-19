<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spk_work_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perumahan_id')->constrained('perumahans')->restrictOnDelete();
            $table->string('konteks')->default('perumahan');
            $table->string('nama_template');
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['perumahan_id', 'konteks', 'nama_template'], 'spk_work_templates_unique_name');
        });

        Schema::create('spk_work_template_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spk_work_template_id')->constrained('spk_work_templates')->cascadeOnDelete();
            $table->string('judul_tahapan');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
            $table->unique(['spk_work_template_id', 'judul_tahapan'], 'spk_work_template_groups_unique_title');
        });

        Schema::create('spk_work_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spk_work_template_group_id')->constrained('spk_work_template_groups')->cascadeOnDelete();
            $table->string('nama_pekerjaan');
            $table->decimal('volume', 16, 2)->default(0);
            $table->string('satuan')->default('');
            $table->decimal('harga_satuan', 18, 2)->default(0);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spk_work_template_items');
        Schema::dropIfExists('spk_work_template_groups');
        Schema::dropIfExists('spk_work_templates');
    }
};
