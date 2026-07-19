<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_ownerships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('detail_rumah_id')->constrained('detail_rumahs')->cascadeOnDelete();
            $table->foreignId('costumer_id')->nullable()->constrained('costumers')->nullOnDelete();
            $table->foreignId('spr_id')->nullable()->constrained('sprs')->nullOnDelete();
            $table->string('source_type')->default('legacy');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('acquisition_method')->default('data_lama');
            $table->date('acquired_at');
            $table->date('ended_at')->nullable();
            $table->string('owner_name');
            $table->string('identity_type')->nullable();
            $table->string('identity_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('spouse_name')->nullable();
            $table->string('document_number')->nullable();
            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['detail_rumah_id', 'is_active']);
            $table->index(['source_type', 'source_id']);
        });

        $this->backfillKprOwnerships();
        $this->backfillCashOwnerships();
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_ownerships');
    }

    private function backfillKprOwnerships(): void
    {
        if (! Schema::hasTable('kpr_milestones')) {
            return;
        }

        $rows = DB::table('kpr_milestones as milestone')
            ->join('kpr_submissions as kpr', 'kpr.id', '=', 'milestone.kpr_submission_id')
            ->join('sprs as spr', 'spr.id', '=', 'kpr.spr_id')
            ->join('costumers as customer', 'customer.id', '=', 'spr.costumer_id')
            ->where('milestone.jenis', 'akad')
            ->whereNull('milestone.deleted_at')
            ->select([
                'milestone.id as source_id', 'milestone.tanggal_proses', 'milestone.nomor_dokumen',
                'spr.id as spr_id', 'spr.detail_rumah_id', 'customer.id as costumer_id', 'customer.nama',
                'customer.jenis_identitas', 'customer.no_identitas', 'customer.telepon', 'customer.email',
                'customer.alamat', 'customer.nama_lengkap_pasangan',
            ])
            ->orderBy('milestone.tanggal_proses')
            ->get();

        foreach ($rows as $row) {
            $this->insertOwnership($row, 'kpr_akad', 'kpr', $row->tanggal_proses);
        }
    }

    private function backfillCashOwnerships(): void
    {
        if (! Schema::hasTable('cash_sales')) {
            return;
        }

        $rows = DB::table('cash_sales as cash')
            ->join('sprs as spr', 'spr.id', '=', 'cash.spr_id')
            ->join('costumers as customer', 'customer.id', '=', 'cash.costumer_id')
            ->where('cash.status_pembayaran', 'serah_terima')
            ->whereNull('cash.deleted_at')
            ->select([
                'cash.id as source_id', 'cash.updated_at as tanggal_proses', DB::raw('NULL as nomor_dokumen'),
                'spr.id as spr_id', 'cash.detail_rumah_id', 'customer.id as costumer_id', 'customer.nama',
                'customer.jenis_identitas', 'customer.no_identitas', 'customer.telepon', 'customer.email',
                'customer.alamat', 'customer.nama_lengkap_pasangan',
            ])
            ->orderBy('cash.updated_at')
            ->get();

        foreach ($rows as $row) {
            $this->insertOwnership($row, 'cash_handover', 'cash', $row->tanggal_proses);
        }
    }

    private function insertOwnership(object $row, string $sourceType, string $method, mixed $date): void
    {
        if (! $row->detail_rumah_id || DB::table('unit_ownerships')->where('detail_rumah_id', $row->detail_rumah_id)->where('is_active', true)->exists()) {
            return;
        }

        DB::table('unit_ownerships')->insert([
            'detail_rumah_id' => $row->detail_rumah_id,
            'costumer_id' => $row->costumer_id,
            'spr_id' => $row->spr_id,
            'source_type' => $sourceType,
            'source_id' => $row->source_id,
            'acquisition_method' => $method,
            'acquired_at' => date('Y-m-d', strtotime((string) $date)),
            'owner_name' => $row->nama,
            'identity_type' => $row->jenis_identitas,
            'identity_number' => $row->no_identitas,
            'phone' => $row->telepon,
            'email' => $row->email,
            'address' => $row->alamat,
            'spouse_name' => $row->nama_lengkap_pasangan,
            'document_number' => $row->nomor_dokumen,
            'is_active' => true,
            'record_status' => 'locked',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('detail_rumahs')->where('id', $row->detail_rumah_id)->update(['status_penjualan' => 'terjual']);
    }
};
