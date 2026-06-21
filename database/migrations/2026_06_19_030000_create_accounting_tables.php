<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('kode_akun')->unique();
            $table->string('nama_akun');
            $table->string('kategori');
            $table->string('posisi_normal')->default('debit');
            $table->string('status')->default('aktif');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_jurnal')->unique();
            $table->date('tanggal');
            $table->string('type');
            $table->nullableMorphs('source');
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->nullOnDelete();
            $table->decimal('total_debit', 16, 2)->default(0);
            $table->decimal('total_kredit', 16, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['source_type', 'source_id', 'type']);
        });

        Schema::create('journal_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained('journals')->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->decimal('debit', 16, 2)->default(0);
            $table->decimal('kredit', 16, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::table('tipe_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('tipe_posts', 'debit_account_id')) {
                $table->foreignId('debit_account_id')->nullable()->after('jenis')->constrained('chart_of_accounts')->nullOnDelete();
            }
            if (! Schema::hasColumn('tipe_posts', 'credit_account_id')) {
                $table->foreignId('credit_account_id')->nullable()->after('debit_account_id')->constrained('chart_of_accounts')->nullOnDelete();
            }
            if (! Schema::hasColumn('tipe_posts', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('status');
            }
        });

        Schema::table('material_purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('material_purchases', 'metode_pembayaran')) {
                $table->string('metode_pembayaran')->default('tunai')->after('supplier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_purchases', function (Blueprint $table) {
            if (Schema::hasColumn('material_purchases', 'metode_pembayaran')) {
                $table->dropColumn('metode_pembayaran');
            }
        });

        Schema::table('tipe_posts', function (Blueprint $table) {
            if (Schema::hasColumn('tipe_posts', 'debit_account_id')) {
                $table->dropConstrainedForeignId('debit_account_id');
            }
            if (Schema::hasColumn('tipe_posts', 'credit_account_id')) {
                $table->dropConstrainedForeignId('credit_account_id');
            }
            if (Schema::hasColumn('tipe_posts', 'is_system')) {
                $table->dropColumn('is_system');
            }
        });

        Schema::dropIfExists('journal_details');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('chart_of_accounts');
    }
};
