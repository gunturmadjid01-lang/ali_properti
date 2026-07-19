<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tahapan_pembangunans', function (Blueprint $table) {
            $table->foreignId('perumahan_id')->nullable()->after('konteks')->constrained('perumahans')->cascadeOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->after('perumahan_id')->constrained('detail_rumahs')->cascadeOnDelete();
        });

        $this->scopeKawasanStages();
        $this->scopeUnitStages();
    }

    private function scopeKawasanStages(): void
    {
        $groups = DB::table('detail_perumahan_hpps as detail')
            ->join('perumahan_hpps as hpp', 'hpp.id', '=', 'detail.perumahan_hpp_id')
            ->whereNull('detail.deleted_at')
            ->whereNotNull('detail.tahapan_pembangunan_id')
            ->select('hpp.perumahan_id', 'detail.tahapan_pembangunan_id')
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            $stageId = $this->cloneStage(
                (int) $group->tahapan_pembangunan_id,
                (int) $group->perumahan_id,
                null,
                'kawasan',
            );

            DB::table('detail_perumahan_hpps')
                ->whereIn('perumahan_hpp_id', DB::table('perumahan_hpps')->where('perumahan_id', $group->perumahan_id)->select('id'))
                ->where('tahapan_pembangunan_id', $group->tahapan_pembangunan_id)
                ->update(['tahapan_pembangunan_id' => $stageId]);
        }
    }

    private function scopeUnitStages(): void
    {
        $groups = DB::table('detail_rumah_hpp_items as item')
            ->join('detail_rumah_hpps as hpp', 'hpp.id', '=', 'item.detail_rumah_hpp_id')
            ->whereNull('item.deleted_at')
            ->whereNotNull('item.tahapan_pembangunan_id')
            ->select('hpp.detail_rumah_id', 'item.tahapan_pembangunan_id')
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            $stageId = $this->cloneStage(
                (int) $group->tahapan_pembangunan_id,
                null,
                (int) $group->detail_rumah_id,
                'unit',
            );

            DB::table('detail_rumah_hpp_items')
                ->whereIn('detail_rumah_hpp_id', DB::table('detail_rumah_hpps')->where('detail_rumah_id', $group->detail_rumah_id)->select('id'))
                ->where('tahapan_pembangunan_id', $group->tahapan_pembangunan_id)
                ->update(['tahapan_pembangunan_id' => $stageId]);
        }
    }

    private function cloneStage(int $sourceId, ?int $perumahanId, ?int $detailRumahId, string $context): int
    {
        $source = DB::table('tahapan_pembangunans')->where('id', $sourceId)->first();

        if (! $source) {
            return $sourceId;
        }

        $existing = DB::table('tahapan_pembangunans')
            ->where('nama_tahapan', $source->nama_tahapan)
            ->where('konteks', $context)
            ->where('perumahan_id', $perumahanId)
            ->where('detail_rumah_id', $detailRumahId)
            ->whereNull('deleted_at')
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('tahapan_pembangunans')->insertGetId([
            'nama_tahapan' => $source->nama_tahapan,
            'bobot_persen' => $source->bobot_persen,
            'urutan' => $source->urutan,
            'status' => $source->status,
            'konteks' => $context,
            'perumahan_id' => $perumahanId,
            'detail_rumah_id' => $detailRumahId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('tahapan_pembangunans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('detail_rumah_id');
            $table->dropConstrainedForeignId('perumahan_id');
        });
    }
};
