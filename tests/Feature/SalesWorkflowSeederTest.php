<?php

namespace Tests\Feature;

use App\Models\BankKredit;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\Spr;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SprTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesWorkflowSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_prepares_rerunnable_spr_approval_test_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(3, Perumahan::query()->where('status', 'aktif')->count());
        $this->assertGreaterThanOrEqual(36, DetailRumah::query()->where('status', 'aktif')->count());
        $this->assertGreaterThanOrEqual(40, Costumer::query()->count());
        $this->assertSame(20, Spr::query()->where('kode_spr', 'like', 'SPR-TEST-%')->count());
        $this->assertSame(5, BankKredit::query()->where('status', 'aktif')->where('record_status', 'locked')->count());
        $this->assertSame(5, Spr::query()
            ->where('kode_spr', 'like', 'SPR-TEST-%')
            ->where('metode_pembayaran', 'kpr_bank')
            ->whereHas('bankKredit', fn ($query) => $query->where('status', 'aktif')->where('record_status', 'locked'))
            ->count());
        $seededSprs = Spr::query()
            ->with(['costumer:id,created_by,perumahan_id', 'detailRumah:id,perumahan_id'])
            ->where('kode_spr', 'like', 'SPR-TEST-%')
            ->get();
        $this->assertCount(20, $seededSprs);
        $this->assertTrue($seededSprs->every(fn (Spr $spr) => (int) $spr->created_by === (int) $spr->costumer?->created_by
            && (int) $spr->costumer?->perumahan_id === (int) $spr->detailRumah?->perumahan_id));

        foreach (['cash', 'cash_bertahap', 'kpr_bank', 'kpr_developer'] as $method) {
            $this->assertSame(5, Spr::query()
                ->where('kode_spr', 'like', 'SPR-TEST-%')
                ->where('metode_pembayaran', $method)
                ->where('status', Spr::STATUS_DRAFT)
                ->where('record_status', 'draft')
                ->count());
        }

        $spr = Spr::query()->where('kode_spr', 'SPR-TEST-001')->firstOrFail();
        $spr->forceFill(['status' => Spr::STATUS_MENUNGGU_APPROVAL, 'record_status' => 'locked'])->save();

        $this->seed(SprTestingSeeder::class);

        $this->assertSame(20, Spr::query()->where('kode_spr', 'like', 'SPR-TEST-%')->count());
        $this->assertSame(Spr::STATUS_MENUNGGU_APPROVAL, $spr->fresh()->status);
        $this->assertSame('locked', $spr->fresh()->record_status);
    }
}
