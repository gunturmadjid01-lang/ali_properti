<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_keuangans', function (Blueprint $table): void {
            $table->foreignId('perumahan_id')->nullable()->after('cabang_id')->constrained('perumahans')->nullOnDelete();
            $table->foreignId('journal_id')->nullable()->after('tipe_post_id')->constrained('journals')->nullOnDelete();
            $table->nullableMorphs('source');
            $table->string('nomor_referensi')->nullable();
            $table->string('status')->default('posted');
        });

        $accounts = [
            ['1-1000', 'Kas dan Bank', 'aset', 'debit'],
            ['1-1100', 'Piutang Usaha Customer', 'aset', 'debit'],
            ['1-1200', 'Piutang Lain-lain', 'aset', 'debit'],
            ['1-1300', 'Persediaan Material', 'aset', 'debit'],
            ['1-1400', 'Persediaan Tanah dan Bangunan', 'aset', 'debit'],
            ['1-1500', 'Uang Muka Pembelian', 'aset', 'debit'],
            ['1-1600', 'Aset Tetap', 'aset', 'debit'],
            ['1-1690', 'Akumulasi Penyusutan', 'aset_kontra', 'kredit'],
            ['2-1000', 'Uang Muka Customer', 'liabilitas', 'kredit'],
            ['2-2100', 'Hutang Kontraktor', 'liabilitas', 'kredit'],
            ['2-2200', 'Hutang Supplier', 'liabilitas', 'kredit'],
            ['2-2300', 'Hutang Operasional', 'liabilitas', 'kredit'],
            ['2-2400', 'Hutang Pajak', 'liabilitas', 'kredit'],
            ['3-1000', 'Modal Disetor', 'ekuitas', 'kredit'],
            ['3-2000', 'Laba Ditahan', 'ekuitas', 'kredit'],
            ['4-1000', 'Pendapatan Penjualan Unit', 'pendapatan', 'kredit'],
            ['4-2000', 'Pendapatan Administrasi', 'pendapatan', 'kredit'],
            ['4-9000', 'Pendapatan Lain-lain', 'pendapatan_lain', 'kredit'],
            ['5-1100', 'HPP Konstruksi', 'beban_hpp', 'debit'],
            ['5-1200', 'HPP Material', 'beban_hpp', 'debit'],
            ['5-1300', 'HPP Tanah dan Perizinan', 'beban_hpp', 'debit'],
            ['6-1000', 'Beban Gaji dan Tenaga Kerja', 'beban_operasional', 'debit'],
            ['6-2000', 'Beban Marketing dan Promosi', 'beban_operasional', 'debit'],
            ['6-3000', 'Beban Kantor dan Umum', 'beban_operasional', 'debit'],
            ['6-4000', 'Beban Utilitas', 'beban_operasional', 'debit'],
            ['6-5000', 'Beban Penyusutan', 'beban_operasional', 'debit'],
            ['6-6000', 'Beban Bank dan Administrasi', 'beban_operasional', 'debit'],
            ['6-9000', 'Beban Lain-lain', 'beban_lain', 'debit'],
        ];

        foreach ($accounts as [$code, $name, $category, $normal]) {
            DB::table('chart_of_accounts')->updateOrInsert(
                ['kode_akun' => $code],
                [
                    'nama_akun' => $name,
                    'kategori' => $category,
                    'posisi_normal' => $normal,
                    'status' => 'aktif',
                    'is_system' => true,
                    'deleted_at' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        $accountIds = DB::table('chart_of_accounts')->pluck('id', 'kode_akun');
        $postMappings = [
            'Penjualan Unit Cash' => ['1-1000', '4-1000'],
            'DP Cash' => ['1-1000', '2-1000'],
            'Cicilan Cash' => ['1-1000', '2-1000'],
            'Pelunasan Cash' => ['1-1000', '4-1000'],
            'DP KPR' => ['1-1000', '2-1000'],
            'Pembayaran KPR' => ['1-1000', '4-1000'],
            'Administrasi KPR' => ['1-1000', '4-2000'],
            'Pembayaran Booking Fee' => ['1-1000', '2-1000'],
            'Booking Fee SPR' => ['1-1000', '2-1000'],
            'Pembayaran Uang Muka' => ['1-1000', '2-1000'],
            'Uang Muka SPR' => ['1-1000', '2-1000'],
            'Pelunasan Unit Rumah' => ['1-1000', '4-1000'],
            'Pembayaran Lainnya SPR' => ['1-1000', '4-2000'],
            'Pengembalian Dana SPR' => ['2-1000', '1-1000'],
            'Biaya Operasional Kantor' => ['6-3000', '1-1000'],
            'Biaya Material Bangunan' => ['5-1200', '1-1000'],
            'Biaya Tenaga Kerja' => ['6-1000', '1-1000'],
        ];

        foreach ($postMappings as $name => [$debitCode, $creditCode]) {
            $type = str_starts_with($name, 'Pengembalian') || str_starts_with($name, 'Biaya')
                ? 'pengeluaran'
                : 'pemasukan';

            DB::table('tipe_posts')->updateOrInsert(
                ['nama_post' => $name],
                [
                    'jenis' => $type,
                    'debit_account_id' => $accountIds[$debitCode] ?? null,
                    'credit_account_id' => $accountIds[$creditCode] ?? null,
                    'status' => 'aktif',
                    'is_system' => true,
                    'record_status' => 'locked',
                    'locked_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        DB::statement('
            update transaksi_keuangans
            set perumahan_id = (
                select master_banks.perumahan_id
                from master_banks
                where master_banks.id = transaksi_keuangans.master_bank_id
            )
            where perumahan_id is null
        ');

        $transactions = DB::table('transaksi_keuangans')
            ->join('tipe_posts', 'tipe_posts.id', '=', 'transaksi_keuangans.tipe_post_id')
            ->whereNull('transaksi_keuangans.journal_id')
            ->whereNotNull('tipe_posts.debit_account_id')
            ->whereNotNull('tipe_posts.credit_account_id')
            ->select([
                'transaksi_keuangans.*',
                'tipe_posts.debit_account_id',
                'tipe_posts.credit_account_id',
            ])
            ->orderBy('transaksi_keuangans.id')
            ->get();

        foreach ($transactions as $transaction) {
            $journalId = DB::table('journals')->insertGetId([
                'nomor_jurnal' => 'JRN-CASH-BACKFILL-'.str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT),
                'tanggal' => $transaction->tanggal,
                'type' => 'cash_transaction',
                'source_type' => 'App\\Models\\TransaksiKeuangan',
                'source_id' => $transaction->id,
                'perumahan_id' => $transaction->perumahan_id,
                'detail_rumah_id' => null,
                'total_debit' => $transaction->nominal,
                'total_kredit' => $transaction->nominal,
                'keterangan' => $transaction->keterangan,
                'created_by' => $transaction->user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('journal_details')->insert([
                [
                    'journal_id' => $journalId,
                    'chart_of_account_id' => $transaction->debit_account_id,
                    'debit' => $transaction->nominal,
                    'kredit' => 0,
                    'keterangan' => $transaction->keterangan,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'journal_id' => $journalId,
                    'chart_of_account_id' => $transaction->credit_account_id,
                    'debit' => 0,
                    'kredit' => $transaction->nominal,
                    'keterangan' => $transaction->keterangan,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('transaksi_keuangans')->where('id', $transaction->id)->update(['journal_id' => $journalId]);
        }
    }

    public function down(): void
    {
        Schema::table('transaksi_keuangans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('journal_id');
            $table->dropConstrainedForeignId('perumahan_id');
            $table->dropMorphs('source');
            $table->dropColumn(['nomor_referensi', 'status']);
        });
    }
};
