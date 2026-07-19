<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spr_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('spr_payments', 'status')) {
                $table->string('status')->default('menunggu_konfirmasi')->after('keterangan');
            }

            if (! Schema::hasColumn('spr_payments', 'confirmed_at')) {
                $table->dateTime('confirmed_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('spr_payments', 'confirmed_by')) {
                $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('spr_payments', 'confirmation_note')) {
                $table->text('confirmation_note')->nullable()->after('confirmed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spr_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('spr_payments', 'confirmed_by')) {
                $table->dropConstrainedForeignId('confirmed_by');
            }

            foreach (['confirmation_note', 'confirmed_at', 'status'] as $column) {
                if (Schema::hasColumn('spr_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
