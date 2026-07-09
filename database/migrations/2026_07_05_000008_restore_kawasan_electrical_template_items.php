<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $kawasanStages = [
        'II RAB SARANA' => [2, 10.15],
        'III RAB PRASARANA' => [3, 12.54],
    ];

    private array $items = [
        'Instalasi Listrik' => ['II RAB SARANA', 150, 'Unit', 3500000, 3],
        'Jaringan & Tiang Listrik PLN' => ['III RAB PRASARANA', 150, 'Unit', 1800000, 7],
        'Trafo Listrik 50 KVA' => ['III RAB PRASARANA', 3, 'Unit', 30000000, 8],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('hpp_template_stages') || ! Schema::hasTable('hpp_template_items')) {
            return;
        }

        DB::transaction(function (): void {
            $stageIds = [];
            foreach ($this->kawasanStages as $stageName => [$order, $weight]) {
                DB::table('hpp_template_stages')->updateOrInsert(
                    ['konteks' => 'kawasan', 'nama_tahapan' => $stageName],
                    ['bobot_persen' => $weight, 'urutan' => $order, 'created_at' => now(), 'updated_at' => now()],
                );

                $stageIds[$stageName] = DB::table('hpp_template_stages')
                    ->where('konteks', 'kawasan')
                    ->where('nama_tahapan', $stageName)
                    ->value('id');
            }

            foreach ($this->items as $jobName => [$stageName, $volume, $unit, $price, $order]) {
                DB::table('hpp_template_items')
                    ->where('nama_pekerjaan', $jobName)
                    ->update([
                        'hpp_template_stage_id' => $stageIds[$stageName],
                        'volume' => 0,
                        'satuan' => $unit,
                        'urutan' => $order,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function down(): void
    {
        // Tidak dibalik otomatis.
    }
};
