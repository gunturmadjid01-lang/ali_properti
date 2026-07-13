<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_purchase_details', function (Blueprint $table) {
            $table->string('inspection_status')->default('pending')->after('qty_diterima');
            $table->text('inspection_note')->nullable()->after('inspection_status');
            $table->foreignId('checked_by')->nullable()->after('inspection_note')->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable()->after('checked_by');
        });
    }

    public function down(): void
    {
        Schema::table('material_purchase_details', function (Blueprint $table) {
            $table->dropColumn('checked_at');
            $table->dropConstrainedForeignId('checked_by');
            $table->dropColumn(['inspection_note', 'inspection_status']);
        });
    }
};
