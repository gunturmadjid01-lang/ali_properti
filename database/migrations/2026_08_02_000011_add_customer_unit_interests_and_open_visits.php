<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_unit_interests')) {
            Schema::create('customer_unit_interests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('costumer_id')->constrained('costumers')->cascadeOnDelete();
                $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->nullOnDelete();
                $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
                $table->string('interest_level', 30)->nullable();
                $table->string('payment_plan', 50)->nullable();
                $table->decimal('budget_min', 18, 2)->nullable();
                $table->decimal('budget_max', 18, 2)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['costumer_id', 'detail_rumah_id'], 'cui_customer_unit_idx');
                $table->index(['perumahan_id', 'interest_level'], 'cui_housing_interest_idx');
            });
        }

        Schema::table('marketing_visits', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketing_visits', 'contact_name')) {
                $table->string('contact_name')->nullable()->after('costumer_id');
            }
            if (! Schema::hasColumn('marketing_visits', 'contact_phone')) {
                $table->string('contact_phone', 50)->nullable()->after('contact_name');
            }
            if (! Schema::hasColumn('marketing_visits', 'organization_name')) {
                $table->string('organization_name')->nullable()->after('contact_phone');
            }
            if (! Schema::hasColumn('marketing_visits', 'lead_source_note')) {
                $table->string('lead_source_note')->nullable()->after('organization_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketing_visits', function (Blueprint $table): void {
            foreach (['contact_name', 'contact_phone', 'organization_name', 'lead_source_note'] as $column) {
                if (Schema::hasColumn('marketing_visits', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('customer_unit_interests');
    }
};
