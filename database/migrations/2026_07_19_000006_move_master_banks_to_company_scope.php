<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('master_banks', function (Blueprint $table) {
            $table->foreignId('cabang_id')->nullable()->after('id')->constrained('cabang_perusahaans')->nullOnDelete();
        });

        DB::table('master_banks')
            ->join('perumahans', 'perumahans.id', '=', 'master_banks.perumahan_id')
            ->update(['master_banks.cabang_id' => DB::raw('perumahans.cabang_id')]);

        Schema::table('master_banks', function (Blueprint $table) {
            $table->foreignId('perumahan_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('master_banks')->whereNull('perumahan_id')->delete();
        Schema::table('master_banks', function (Blueprint $table) {
            $table->foreignId('perumahan_id')->nullable(false)->change();
            $table->dropConstrainedForeignId('cabang_id');
        });
    }
};
