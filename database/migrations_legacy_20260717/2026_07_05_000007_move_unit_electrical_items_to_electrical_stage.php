<?php

use App\Models\DetailRumahHppItem;
use App\Models\TahapanPembangunan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $stage = ['PEK. INSTALASI LISTRIK', 10, 3.13];

    public function up(): void
    {
        DB::transaction(function (): void {
            DetailRumahHppItem::query()
                ->with('detailRumahHpp.detailRumah:id,perumahan_id')
                ->where(function ($query): void {
                    $query->where('nama_pekerjaan', 'like', '%kabel%')
                        ->orWhere('nama_pekerjaan', 'like', '%lampu%')
                        ->orWhere('nama_pekerjaan', 'like', '%saklar%')
                        ->orWhere('nama_pekerjaan', 'like', '%listrik%');
                })
                ->get()
                ->each(function (DetailRumahHppItem $item): void {
                    $rumah = $item->detailRumahHpp?->detailRumah;

                    if (! $rumah) {
                        return;
                    }

                    $stage = $this->stageFor((int) $rumah->perumahan_id, (int) $rumah->id);
                    $item->update(['tahapan_pembangunan_id' => $stage->id]);
                });

            $this->rebalanceTemplate();
        });
    }

    public function down(): void
    {
        // Tidak dibalik otomatis.
    }

    private function rebalanceTemplate(): void
    {
        if (! Schema::hasTable('hpp_template_stages') || ! Schema::hasTable('hpp_template_items')) {
            return;
        }

        [$name, $order, $weight] = $this->stage;

        DB::table('hpp_template_stages')->updateOrInsert(
            ['konteks' => 'unit', 'nama_tahapan' => $name],
            ['bobot_persen' => $weight, 'urutan' => $order, 'created_at' => now(), 'updated_at' => now()],
        );

        $stageId = DB::table('hpp_template_stages')
            ->where('konteks', 'unit')
            ->where('nama_tahapan', $name)
            ->value('id');

        DB::table('hpp_template_items')
            ->whereIn('id', DB::table('hpp_template_items as items')
                ->join('hpp_template_stages as stages', 'stages.id', '=', 'items.hpp_template_stage_id')
                ->where('stages.konteks', 'unit')
                ->where(function ($query): void {
                    $query->where('items.nama_pekerjaan', 'like', '%kabel%')
                        ->orWhere('items.nama_pekerjaan', 'like', '%lampu%')
                        ->orWhere('items.nama_pekerjaan', 'like', '%saklar%')
                        ->orWhere('items.nama_pekerjaan', 'like', '%listrik%');
                })
                ->select('items.id'))
            ->where(function ($query): void {
                $query->where('nama_pekerjaan', 'like', '%kabel%')
                    ->orWhere('nama_pekerjaan', 'like', '%lampu%')
                    ->orWhere('nama_pekerjaan', 'like', '%saklar%')
                    ->orWhere('nama_pekerjaan', 'like', '%listrik%');
            })
            ->update(['hpp_template_stage_id' => $stageId, 'updated_at' => now()]);
    }

    private function stageFor(int $perumahanId, int $detailRumahId): TahapanPembangunan
    {
        [$name, $order, $weight] = $this->stage;

        return TahapanPembangunan::query()->updateOrCreate(
            [
                'nama_tahapan' => $name,
                'konteks' => 'unit',
                'perumahan_id' => $perumahanId,
                'detail_rumah_id' => $detailRumahId,
            ],
            [
                'urutan' => $order,
                'bobot_persen' => $weight,
                'status' => 'aktif',
            ],
        );
    }
};
