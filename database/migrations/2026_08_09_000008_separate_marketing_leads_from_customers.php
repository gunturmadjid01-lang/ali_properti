<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_leads', function (Blueprint $table): void {
            $table->id();
            $table->string('lead_no')->unique();
            $table->string('name');
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('identity_no', 60)->nullable()->index();
            $table->string('ownership_type', 20)->default('marketing')->index();
            $table->string('source_channel', 50)->default('direct');
            $table->foreignId('lead_source_id')->nullable()->constrained('marketing_lead_sources')->nullOnDelete();
            $table->foreignId('source_visit_id')->nullable()->constrained('marketing_visits')->nullOnDelete();
            $table->unsignedBigInteger('source_contact_id')->nullable();
            $table->foreignId('marketing_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('admin_sales_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->string('interest_level', 20)->default('cold');
            $table->string('preferred_payment_method', 30)->nullable();
            $table->string('stage', 30)->default('new')->index();
            $table->string('qualification_status', 30)->default('unqualified');
            $table->text('qualification_note')->nullable();
            $table->text('lost_reason')->nullable();
            $table->timestamp('first_contacted_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->foreignId('qualified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_costumer_id')->nullable()->constrained('costumers')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['marketing_id', 'stage', 'next_action_at']);
            $table->index(['ownership_type', 'stage', 'created_at']);
        });
        Schema::create('marketing_activity_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketing_visit_id')->constrained('marketing_visits')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('organization')->nullable();
            $table->string('outcome', 30);
            $table->string('interest_level', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('marketing_lead_id')->nullable()->constrained('marketing_leads')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['marketing_visit_id', 'outcome']);
        });
        Schema::table('marketing_leads', fn (Blueprint $table) => $table->foreign('source_contact_id')->references('id')->on('marketing_activity_contacts')->nullOnDelete());
        Schema::table('marketing_visits', fn (Blueprint $table) => $table->foreignId('marketing_lead_id')->nullable()->after('costumer_id')->constrained('marketing_leads')->nullOnDelete());
        Schema::table('costumers', function (Blueprint $table): void {
            $table->foreignId('source_marketing_lead_id')->nullable()->unique()->after('kode_costumer')->constrained('marketing_leads')->nullOnDelete();
            $table->string('customer_stage', 30)->default('legacy')->after('source_marketing_lead_id')->index();
        });

        DB::table('costumers')->orderBy('id')->chunkById(250, function ($customers): void {
            foreach ($customers as $c) {
                $leadId = DB::table('marketing_leads')->insertGetId(['lead_no' => 'LEGACY-'.str_pad((string) $c->id, 6, '0', STR_PAD_LEFT), 'name' => $c->nama ?: 'Customer Legacy '.$c->id, 'phone' => $c->telepon, 'email' => $c->email, 'identity_no' => $c->no_identitas, 'ownership_type' => $c->lead_ownership_type ?: 'marketing', 'source_channel' => $c->lead_source_channel ?: 'legacy', 'lead_source_id' => $c->marketing_lead_source_id, 'marketing_id' => $c->assigned_marketing_id, 'admin_sales_id' => $c->admin_sales_id, 'perumahan_id' => $c->perumahan_id, 'interest_level' => $c->interest_level ?: 'cold', 'preferred_payment_method' => $c->preferred_payment_method, 'stage' => 'converted', 'qualification_status' => 'qualified', 'first_contacted_at' => $c->first_contacted_at, 'last_activity_at' => $c->last_activity_at, 'next_action_at' => $c->next_action_at, 'qualified_at' => $c->created_at, 'converted_costumer_id' => $c->id, 'converted_at' => $c->created_at, 'notes' => trim("Migrasi aman dari customer lama.\n".($c->keterangan ?: '')), 'created_by' => $c->created_by, 'updated_by' => $c->updated_by, 'created_at' => $c->created_at ?: now(), 'updated_at' => $c->updated_at ?: now()]);
                DB::table('costumers')->where('id', $c->id)->update(['source_marketing_lead_id' => $leadId, 'customer_stage' => 'legacy']);
            }
        });
        $permissions = collect(['marketing-lead.view', 'marketing-lead.create', 'marketing-lead.update', 'marketing-lead.qualify', 'marketing-lead.convert', 'marketing-activity-contact.create', 'marketing-activity-contact.convert'])->map(fn (string $name) => Permission::findOrCreate($name, 'web'));
        foreach (['marketing', 'area_marketing', 'admin_sales', 'manager', 'owner', 'supervisor_marketing', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo($permissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('costumers', fn (Blueprint $table) => $table->dropConstrainedForeignId('source_marketing_lead_id'));
        Schema::table('costumers', fn (Blueprint $table) => $table->dropColumn('customer_stage'));
        Schema::table('marketing_visits', fn (Blueprint $table) => $table->dropConstrainedForeignId('marketing_lead_id'));
        Schema::table('marketing_leads', fn (Blueprint $table) => $table->dropForeign(['source_contact_id']));
        Schema::dropIfExists('marketing_activity_contacts');
        Schema::dropIfExists('marketing_leads');
    }
};
