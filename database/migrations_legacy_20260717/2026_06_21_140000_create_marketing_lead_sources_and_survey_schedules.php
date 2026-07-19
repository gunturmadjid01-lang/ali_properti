<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_lead_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_sumber')->unique();
            $table->string('nama_sumber');
            $table->string('kategori')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('status')->default('aktif');
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('costumers', function (Blueprint $table): void {
            if (! Schema::hasColumn('costumers', 'marketing_lead_source_id')) {
                $table->foreignId('marketing_lead_source_id')->nullable()->after('kode_costumer')->constrained('marketing_lead_sources')->nullOnDelete();
            }

            if (! Schema::hasColumn('costumers', 'status_lead')) {
                $table->string('status_lead')->default('lead_baru')->after('marketing_lead_source_id');
            }
        });

        Schema::create('marketing_survey_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_survey')->unique();
            $table->foreignId('costumer_id')->constrained('costumers')->cascadeOnDelete();
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->nullOnDelete();
            $table->foreignId('marketing_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('tanggal_survey');
            $table->string('metode_survey')->default('kunjungan_lokasi');
            $table->string('status')->default('dijadwalkan');
            $table->text('hasil_survey')->nullable();
            $table->text('catatan')->nullable();
            $table->dateTime('rencana_follow_up_at')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_survey_schedules');

        Schema::table('costumers', function (Blueprint $table): void {
            if (Schema::hasColumn('costumers', 'marketing_lead_source_id')) {
                $table->dropConstrainedForeignId('marketing_lead_source_id');
            }

            if (Schema::hasColumn('costumers', 'status_lead')) {
                $table->dropColumn('status_lead');
            }
        });

        Schema::dropIfExists('marketing_lead_sources');
    }
};
