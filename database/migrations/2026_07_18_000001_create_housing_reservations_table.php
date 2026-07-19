<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('housing_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_no')->unique();
            $table->foreignId('costumer_id')->constrained('costumers');
            $table->foreignId('detail_rumah_id')->constrained('detail_rumahs');
            $table->string('invoice_no')->unique();
            $table->string('payment_method');
            $table->string('booking_fee_source_type')->nullable();
            $table->unsignedBigInteger('booking_fee_source_id')->nullable();
            $table->json('booking_fee_snapshot')->nullable();
            $table->decimal('booking_fee', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->dateTime('reserved_at');
            $table->dateTime('payment_due_at');
            $table->dateTime('paid_at')->nullable();
            $table->string('status')->default('pending_payment');
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('spr_id')->nullable()->constrained('sprs')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'payment_due_at']);
        });

        Schema::table('sprs', function (Blueprint $table) {
            $table->foreignId('housing_reservation_id')->nullable()->after('id')->constrained('housing_reservations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sprs', fn (Blueprint $table) => $table->dropConstrainedForeignId('housing_reservation_id'));
        Schema::dropIfExists('housing_reservations');
    }
};
