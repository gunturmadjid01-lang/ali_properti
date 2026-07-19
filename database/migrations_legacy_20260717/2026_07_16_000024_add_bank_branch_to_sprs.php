<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprs', fn (Blueprint $table) => $table->foreignId('bank_branch_id')->nullable()->after('bank_kredit_id')->constrained('bank_branches')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('sprs', fn (Blueprint $table) => $table->dropConstrainedForeignId('bank_branch_id'));
    }
};
