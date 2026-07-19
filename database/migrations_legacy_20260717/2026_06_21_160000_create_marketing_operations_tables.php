<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprs', function (Blueprint $table): void {
            if (! Schema::hasColumn('sprs', 'booking_expires_at')) {
                $table->dateTime('booking_expires_at')->nullable()->after('tanggal_spr');
            }
        });

        Schema::create('marketing_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_campaign')->unique();
            $table->string('nama_campaign');
            $table->string('kanal');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->decimal('anggaran', 18, 2)->default(0);
            $table->decimal('realisasi_biaya', 18, 2)->default(0);
            $table->unsignedInteger('target_lead')->default(0);
            $table->string('status')->default('draft');
            $table->text('keterangan')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('costumers', function (Blueprint $table): void {
            if (! Schema::hasColumn('costumers', 'marketing_campaign_id')) {
                $table->foreignId('marketing_campaign_id')->nullable()->after('marketing_lead_source_id')->constrained('marketing_campaigns')->nullOnDelete();
            }
        });

        Schema::create('marketing_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('costumer_id')->nullable()->constrained('costumers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('jenis')->default('follow_up');
            $table->string('judul');
            $table->dateTime('remind_at');
            $table->string('status')->default('menunggu');
            $table->text('catatan')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'remind_at']);
        });

        Schema::create('marketing_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_template')->unique();
            $table->string('nama_template');
            $table->string('kanal')->default('whatsapp');
            $table->string('tahapan')->nullable();
            $table->text('isi_template');
            $table->boolean('is_system')->default(false);
            $table->string('status')->default('aktif');
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('marketing_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->unsignedInteger('target_lead')->default(0);
            $table->unsignedInteger('target_survey')->default(0);
            $table->unsignedInteger('target_spr')->default(0);
            $table->unsignedInteger('target_closing')->default(0);
            $table->decimal('target_nilai_penjualan', 18, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'tahun', 'bulan']);
        });

        Schema::create('marketing_commissions', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_komisi')->unique();
            $table->foreignId('spr_id')->constrained('sprs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('dasar_perhitungan', 18, 2)->default(0);
            $table->decimal('persentase', 8, 4)->default(0);
            $table->decimal('nominal', 18, 2)->default(0);
            $table->string('status')->default('draft');
            $table->date('tanggal_jatuh_tempo')->nullable();
            $table->date('tanggal_dibayar')->nullable();
            $table->text('catatan')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('spr_billing_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spr_id')->constrained('sprs')->cascadeOnDelete();
            $table->string('jenis_tagihan');
            $table->unsignedInteger('termin_ke')->nullable();
            $table->date('tanggal_jatuh_tempo');
            $table->decimal('nominal_tagihan', 18, 2)->default(0);
            $table->decimal('nominal_dibayar', 18, 2)->default(0);
            $table->string('status')->default('belum_bayar');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'tanggal_jatuh_tempo']);
        });

        Schema::create('marketing_document_reviews', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->string('status')->default('menunggu');
            $table->text('catatan_revisi')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['document_type', 'document_id']);
        });

        Schema::create('kpr_stage_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kpr_submission_id')->constrained('kpr_submissions')->cascadeOnDelete();
            $table->string('tahapan');
            $table->string('status');
            $table->dateTime('tanggal_status');
            $table->text('catatan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kpr_submission_id', 'tanggal_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpr_stage_histories');
        Schema::dropIfExists('marketing_document_reviews');
        Schema::dropIfExists('spr_billing_schedules');
        Schema::dropIfExists('marketing_commissions');
        Schema::dropIfExists('marketing_targets');
        Schema::dropIfExists('marketing_templates');
        Schema::dropIfExists('marketing_reminders');

        Schema::table('costumers', function (Blueprint $table): void {
            if (Schema::hasColumn('costumers', 'marketing_campaign_id')) {
                $table->dropConstrainedForeignId('marketing_campaign_id');
            }
        });

        Schema::dropIfExists('marketing_campaigns');

        Schema::table('sprs', function (Blueprint $table): void {
            if (Schema::hasColumn('sprs', 'booking_expires_at')) {
                $table->dropColumn('booking_expires_at');
            }
        });
    }
};
