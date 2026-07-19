<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('costumer_id')->constrained('costumers')->cascadeOnDelete();
            $t->foreignId('dokumen_costumer_id')->nullable()->constrained('dokumen_costumers')->nullOnDelete();
            $t->string('label')->nullable();
            $t->string('party_scope')->default('customer');
            $t->string('nama_file');
            $t->string('path_file');
            $t->string('mime_type')->nullable();
            $t->unsignedBigInteger('file_size')->default(0);
            $t->date('document_date')->nullable();
            $t->date('expires_at')->nullable();
            $t->string('status')->default('active');
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('replaces_document_id')->nullable()->constrained('customer_documents')->nullOnDelete();
            $t->text('keterangan')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['costumer_id', 'dokumen_costumer_id', 'status'], 'customer_document_lookup');
        });
        Schema::table('spr_berkas_costumers', function (Blueprint $t) {
            $t->foreignId('customer_document_id')->nullable()->after('dokumen_costumer_id')->constrained('customer_documents')->nullOnDelete();
            $t->boolean('is_selected')->default(true)->after('customer_document_id');
        });
        Schema::create('sales_method_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('attempt_no');
            $t->string('payment_method');
            $t->foreignId('bank_kredit_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('bank_credit_product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status')->default('in_progress');
            $t->string('current_stage')->nullable();
            $t->string('outcome')->nullable();
            $t->string('failure_category')->nullable();
            $t->text('failure_reason')->nullable();
            $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['sales_transaction_id', 'attempt_no']);
        });
        Schema::create('sales_stage_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_method_attempt_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sales_process_step_id')->nullable()->constrained()->nullOnDelete();
            $t->string('stage_code');
            $t->string('event_type');
            $t->string('from_status')->nullable();
            $t->string('to_status')->nullable();
            $t->string('outcome')->nullable();
            $t->string('reason_category')->nullable();
            $t->text('reason')->nullable();
            $t->timestamp('occurred_at');
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['stage_code', 'event_type', 'occurred_at']);
        });
        Schema::table('sprs', function (Blueprint $t) {
            $t->unsignedInteger('revision_no')->default(0)->after('kode_spr');
            $t->string('revision_status')->default('current')->after('revision_no');
            $t->foreignId('superseded_by_spr_id')->nullable()->after('revision_status')->constrained('sprs')->nullOnDelete();
        });
        Schema::table('sales_transactions', function (Blueprint $t) {
            $t->string('outcome')->nullable()->after('status');
            $t->string('failure_stage')->nullable()->after('outcome');
            $t->string('failure_category')->nullable()->after('failure_stage');
            $t->text('failure_reason')->nullable()->after('failure_category');
            $t->timestamp('closed_at')->nullable()->after('failure_reason');
        });
        $sprRows = DB::table('spr_berkas_costumers as sb')->join('sprs as s', 's.id', '=', 'sb.spr_id')->whereNull('sb.deleted_at')->select('sb.*', 's.costumer_id')->orderBy('sb.id')->get();
        foreach ($sprRows as $row) {
            $docId = DB::table('customer_documents')->insertGetId(['costumer_id' => $row->costumer_id, 'dokumen_costumer_id' => $row->dokumen_costumer_id, 'nama_file' => $row->nama_file, 'path_file' => $row->path_file, 'mime_type' => $row->mime_type, 'file_size' => $row->file_size, 'keterangan' => $row->keterangan, 'uploaded_by' => $row->uploaded_by, 'created_at' => $row->created_at, 'updated_at' => $row->updated_at]);
            DB::table('spr_berkas_costumers')->where('id', $row->id)->update(['customer_document_id' => $docId]);
        }
        $transactions = DB::table('sales_transactions')->whereNull('deleted_at')->get();
        foreach ($transactions as $trx) {
            DB::table('sales_method_attempts')->insert(['sales_transaction_id' => $trx->id, 'attempt_no' => 1, 'payment_method' => $trx->payment_method, 'status' => 'in_progress', 'started_at' => $trx->approved_at ?? $trx->created_at, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('sales_transactions', fn (Blueprint $t) => $t->dropColumn(['outcome', 'failure_stage', 'failure_category', 'failure_reason', 'closed_at']));
        Schema::table('sprs', function (Blueprint $t) {
            $t->dropConstrainedForeignId('superseded_by_spr_id');
            $t->dropColumn(['revision_no', 'revision_status']);
        });
        Schema::dropIfExists('sales_stage_events');
        Schema::dropIfExists('sales_method_attempts');
        Schema::table('spr_berkas_costumers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('customer_document_id');
            $t->dropColumn('is_selected');
        });
        Schema::dropIfExists('customer_documents');
    }
};
