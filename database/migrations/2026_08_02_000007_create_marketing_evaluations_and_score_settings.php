<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketing_score_settings')) {
            Schema::create('marketing_score_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('metric_key')->unique();
                $table->string('label');
                $table->decimal('weight', 5, 2);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('record_status')->default('draft');
                $table->timestamp('locked_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['is_active', 'record_status']);
            });
        }

        if (! Schema::hasTable('marketing_evaluations')) {
            Schema::create('marketing_evaluations', function (Blueprint $table): void {
                $table->id();
                $table->string('evaluation_no')->unique();
                $table->foreignId('marketing_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('total_score', 6, 2)->default(0);
                $table->string('rating', 30)->nullable();
                $table->text('manager_note')->nullable();
                $table->text('coaching_plan')->nullable();
                $table->string('record_status')->default('draft');
                $table->timestamp('locked_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['marketing_id', 'perumahan_id', 'period_start', 'period_end'], 'marketing_evaluation_period_unique');
                $table->index(['period_start', 'period_end', 'record_status'], 'mkt_eval_period_status_idx');
            });
        } else {
            $indexExists = collect(DB::select("SHOW INDEX FROM marketing_evaluations WHERE Key_name = 'mkt_eval_period_status_idx'"))->isNotEmpty();
            if (! $indexExists) {
                Schema::table('marketing_evaluations', fn (Blueprint $table) => $table->index(
                    ['period_start', 'period_end', 'record_status'],
                    'mkt_eval_period_status_idx'
                ));
            }
        }

        if (! Schema::hasTable('marketing_evaluation_details')) {
            Schema::create('marketing_evaluation_details', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('marketing_evaluation_id')->constrained()->cascadeOnDelete();
                $table->string('metric_key');
                $table->string('label');
                $table->decimal('weight', 5, 2);
                $table->decimal('achievement', 7, 2)->default(0);
                $table->decimal('score', 7, 2)->default(0);
                $table->json('evidence')->nullable();
                $table->timestamps();
                $table->unique(['marketing_evaluation_id', 'metric_key'], 'marketing_evaluation_metric_unique');
            });
        }

        $now = now();
        DB::table('marketing_score_settings')->upsert(collect([
            ['lead_response_speed', 'Kecepatan merespons lead', 10], ['follow_up_timeliness', 'Follow-up tepat waktu', 15],
            ['follow_up_quality', 'Kelengkapan catatan follow-up', 10], ['visit_execution', 'Kunjungan terlaksana', 10],
            ['visit_report_quality', 'Kelengkapan laporan kunjungan', 10], ['customer_progress', 'Perkembangan status customer', 15],
            ['reservation_spr', 'Reservasi dan SPR', 10], ['closing', 'Closing', 15], ['administration', 'Kelengkapan administrasi', 5],
        ])->map(fn (array $item) => ['metric_key' => $item[0], 'label' => $item[1], 'weight' => $item[2], 'description' => 'Bobot awal dapat diubah melalui pengaturan penilaian.', 'is_active' => true, 'record_status' => 'locked', 'locked_at' => $now, 'created_at' => $now, 'updated_at' => $now])->all(), ['metric_key'], ['label', 'weight', 'description', 'updated_at']);
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_evaluation_details');
        Schema::dropIfExists('marketing_evaluations');
        Schema::dropIfExists('marketing_score_settings');
    }
};
