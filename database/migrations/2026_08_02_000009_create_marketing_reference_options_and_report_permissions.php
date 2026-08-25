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
        if (! Schema::hasTable('marketing_reference_options')) {
            Schema::create('marketing_reference_options', function (Blueprint $table): void {
                $table->id();
                $table->string('category', 60);
                $table->string('code', 80);
                $table->string('label');
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('record_status')->default('draft');
                $table->timestamp('locked_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['category', 'code']);
                $table->index(['category', 'is_active', 'sort_order'], 'mkt_ref_category_active_idx');
            });
        }
        $rows = [
            'interest_level' => ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'very_high' => 'Sangat Tinggi'],
            'follow_up_method' => ['phone' => 'Telepon', 'whatsapp' => 'WhatsApp', 'sms' => 'Pesan Singkat', 'email' => 'Email', 'video_call' => 'Video Call', 'meeting' => 'Pertemuan Langsung', 'visit' => 'Kunjungan', 'other' => 'Lainnya'],
            'follow_up_result' => ['no_response' => 'Belum Merespons', 'callback' => 'Minta Dihubungi Kembali', 'considering' => 'Masih Mempertimbangkan', 'waiting_family' => 'Menunggu Keluarga', 'waiting_funds' => 'Menunggu Dana', 'scheduled_visit' => 'Ingin Survei Lokasi', 'reservation_ready' => 'Ingin Reservasi', 'not_interested' => 'Tidak Berminat', 'wrong_number' => 'Nomor Tidak Valid'],
            'visit_type' => ['customer_location' => 'Lokasi Customer', 'office' => 'Kantor', 'housing_site' => 'Lokasi Perumahan', 'online' => 'Online', 'canvassing' => 'Canvassing', 'event' => 'Pameran/Event', 'agency' => 'Instansi/Partner'],
            'visit_result' => ['interested' => 'Berminat', 'follow_up' => 'Perlu Follow-up', 'negotiation' => 'Negosiasi', 'reservation_ready' => 'Siap Reservasi', 'not_interested' => 'Tidak Berminat', 'reschedule' => 'Jadwal Ulang'],
            'cancellation_reason' => ['price' => 'Harga Tidak Sesuai', 'location' => 'Lokasi Tidak Sesuai', 'financing' => 'Pembiayaan Tidak Lolos', 'family' => 'Keputusan Keluarga', 'competitor' => 'Memilih Properti Lain', 'unresponsive' => 'Tidak Dapat Dihubungi', 'other' => 'Lainnya'],
            'activity_type' => ['follow_up' => 'Follow-up', 'visit' => 'Kunjungan', 'survey' => 'Survei', 'canvassing' => 'Canvassing', 'event' => 'Pameran/Event', 'administration' => 'Administrasi', 'other' => 'Aktivitas Lain'],
            'verification_status' => ['draft' => 'Draft', 'pending_review' => 'Menunggu Pemeriksaan', 'verified' => 'Terverifikasi', 'revision' => 'Perlu Perbaikan', 'rejected' => 'Ditolak'],
        ];
        $now = now();
        $insert = [];
        foreach ($rows as $category => $options) {
            foreach ($options as $code => $label) {
                $insert[] = compact('category', 'code', 'label') + ['sort_order' => count($insert) + 1, 'is_active' => true, 'record_status' => 'locked', 'locked_at' => $now, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::table('marketing_reference_options')->upsert($insert, ['category', 'code'], ['label', 'updated_at']);

        $names = ['marketing-report.view', 'marketing-report.export', 'marketing-audit.view', 'marketing-reference.view', 'marketing-reference.create', 'marketing-reference.update', 'marketing-reference.delete', 'marketing-reference.lock', 'marketing-reference.unlock', 'lead.assign', 'lead.transfer', 'customer.view-all', 'follow-up.verify', 'visit-report.verify'];
        $permissions = collect($names)->map(fn (string $name) => Permission::findOrCreate($name, 'web'));
        Role::findOrCreate('marketing', 'web')->givePermissionTo($permissions->whereIn('name', ['marketing-report.view']));
        foreach (['owner', 'manager', 'supervisor_marketing', 'admin_sales'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo($permissions->whereIn('name', ['marketing-report.view', 'marketing-report.export', 'marketing-audit.view', 'customer.view-all']));
        }
        Role::findOrCreate('manager', 'web')->givePermissionTo($permissions->whereIn('name', ['lead.assign', 'lead.transfer', 'follow-up.verify', 'visit-report.verify', 'marketing-reference.view', 'marketing-reference.create', 'marketing-reference.update', 'marketing-reference.delete', 'marketing-reference.lock', 'marketing-reference.unlock']));
        Role::findOrCreate('admin_sales', 'web')->givePermissionTo($permissions->whereIn('name', ['lead.assign', 'lead.transfer', 'follow-up.verify', 'visit-report.verify', 'marketing-reference.view']));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_reference_options');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
