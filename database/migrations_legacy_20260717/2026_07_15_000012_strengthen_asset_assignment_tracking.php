<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_loans', function (Blueprint $table): void {
            $table->string('transaction_type')->default('loan')->after('transaction_no');
            $table->string('taken_by_name')->nullable()->after('borrower');
            $table->string('taken_by_phone')->nullable()->after('taken_by_name');
            $table->foreignId('handed_over_by')->nullable()->after('taken_by_phone')->constrained('users')->nullOnDelete();
            $table->timestamp('handed_over_at')->nullable()->after('handed_over_by');
            $table->foreignId('source_location_id')
                ->nullable()
                ->after('inventory_location_id')
                ->constrained('inventory_locations')
                ->nullOnDelete();
            $table->foreignId('detail_rumah_id')
                ->nullable()
                ->after('perumahan_id')
                ->constrained('detail_rumahs')
                ->nullOnDelete();
        });

        Schema::table('inventory_returns', function (Blueprint $table): void {
            $table->foreignId('return_location_id')
                ->nullable()
                ->after('date')
                ->constrained('inventory_locations')
                ->nullOnDelete();
            $table->foreignId('received_by')->nullable()->after('return_location_id')->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable()->after('received_by');
        });

        Schema::table('inventory_return_items', function (Blueprint $table): void {
            $table->unsignedInteger('good_quantity')->default(0)->after('quantity');
            $table->string('outcome')->default('complete_good')->after('condition_in');
            $table->text('notes')->nullable()->after('lost_quantity');
            $table->string('responsible_person')->nullable()->after('notes');
            $table->decimal('estimated_cost', 18, 2)->default(0)->after('responsible_person');
        });

        Schema::table('heavy_equipment_usages', function (Blueprint $table): void {
            $table->foreignId('perumahan_id')
                ->nullable()
                ->after('operator_id')
                ->constrained('perumahans')
                ->nullOnDelete();
            $table->foreignId('detail_rumah_id')
                ->nullable()
                ->after('perumahan_id')
                ->constrained('detail_rumahs')
                ->nullOnDelete();
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('occurred_at');
            $table->string('movement_type');
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->string('reference_no')->nullable();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('office_asset_id')->nullable()->constrained('office_assets')->nullOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('condition_bucket')->default('available');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['inventory_item_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');

        Schema::table('heavy_equipment_usages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('detail_rumah_id');
            $table->dropConstrainedForeignId('perumahan_id');
        });

        Schema::table('inventory_returns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('received_by');
            $table->dropConstrainedForeignId('return_location_id');
            $table->dropColumn('received_at');
        });

        Schema::table('inventory_return_items', function (Blueprint $table): void {
            $table->dropColumn(['good_quantity', 'outcome', 'notes', 'responsible_person', 'estimated_cost']);
        });

        Schema::table('inventory_loans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('handed_over_by');
            $table->dropConstrainedForeignId('detail_rumah_id');
            $table->dropConstrainedForeignId('source_location_id');
            $table->dropColumn(['transaction_type', 'taken_by_name', 'taken_by_phone', 'handed_over_at']);
        });
    }
};
