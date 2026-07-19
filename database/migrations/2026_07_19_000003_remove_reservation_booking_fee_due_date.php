<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('housing_reservations', function (Blueprint $table) {
            $table->dropIndex('housing_reservations_status_payment_due_at_index');
            $table->dateTime('payment_due_at')->nullable()->change();
            $table->index(['status', 'reserved_at']);
        });
    }

    public function down(): void
    {
        Schema::table('housing_reservations', function (Blueprint $table) {
            $table->dropIndex(['status', 'reserved_at']);
            $table->dateTime('payment_due_at')->nullable(false)->change();
            $table->index(['status', 'payment_due_at']);
        });
    }
};
