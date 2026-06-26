<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $projectInventoryId = DB::table('chart_of_accounts')->where('kode_akun', '1-1400')->value('id');
        $constructionHppId = DB::table('chart_of_accounts')->where('kode_akun', '5-1100')->value('id');

        if (! $projectInventoryId) {
            return;
        }

        DB::table('tipe_posts')
            ->where('nama_post', 'Tagihan Kontraktor')
            ->update(['debit_account_id' => $projectInventoryId]);

        if ($constructionHppId) {
            DB::table('journal_details')
                ->where('chart_of_account_id', $constructionHppId)
                ->where('debit', '>', 0)
                ->whereIn('journal_id', DB::table('journals')->select('id')->where('type', 'contractor_bill'))
                ->update(['chart_of_account_id' => $projectInventoryId]);
        }
    }

    public function down(): void
    {
        $projectInventoryId = DB::table('chart_of_accounts')->where('kode_akun', '1-1400')->value('id');
        $constructionHppId = DB::table('chart_of_accounts')->where('kode_akun', '5-1100')->value('id');

        if (! $constructionHppId) {
            return;
        }

        DB::table('tipe_posts')
            ->where('nama_post', 'Tagihan Kontraktor')
            ->update(['debit_account_id' => $constructionHppId]);

        if ($projectInventoryId) {
            DB::table('journal_details')
                ->where('chart_of_account_id', $projectInventoryId)
                ->where('debit', '>', 0)
                ->whereIn('journal_id', DB::table('journals')->select('id')->where('type', 'contractor_bill'))
                ->update(['chart_of_account_id' => $constructionHppId]);
        }
    }
};
