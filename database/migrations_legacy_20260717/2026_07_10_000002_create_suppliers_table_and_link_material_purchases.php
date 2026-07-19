<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('kode_supplier')->unique();
            $table->string('nama_supplier');
            $table->string('pic')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('alamat')->nullable();
            $table->string('nama_bank')->nullable();
            $table->string('nomor_rekening')->nullable();
            $table->string('nama_rekening')->nullable();
            $table->string('npwp')->nullable();
            $table->text('catatan')->nullable();
            $table->string('status')->default('aktif');
            $table->string('record_status')->default('draft');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('material_purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('material_purchases', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('kelompok_hpp_id')->constrained('suppliers')->nullOnDelete();
            }
        });

        if (Schema::hasTable('material_purchases')) {
            DB::table('material_purchases')
                ->whereNotNull('supplier')
                ->where('supplier', '!=', '')
                ->where('supplier', '!=', '-')
                ->select('supplier')
                ->distinct()
                ->orderBy('supplier')
                ->get()
                ->each(function ($row): void {
                    $supplierId = DB::table('suppliers')->insertGetId([
                        'kode_supplier' => 'SUP-'.str_pad((string) (DB::table('suppliers')->count() + 1), 5, '0', STR_PAD_LEFT),
                        'nama_supplier' => $row->supplier,
                        'status' => 'aktif',
                        'record_status' => 'draft',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('material_purchases')
                        ->where('supplier', $row->supplier)
                        ->update(['supplier_id' => $supplierId]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('material_purchases', function (Blueprint $table) {
            if (Schema::hasColumn('material_purchases', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }
        });

        Schema::dropIfExists('suppliers');
    }
};
