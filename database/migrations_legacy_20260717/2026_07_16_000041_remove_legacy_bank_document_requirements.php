<?php

use App\Models\DocumentRequirementSet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_document_requirements')) {
            return;
        }

        DB::table('bank_document_requirements')->whereNull('deleted_at')->orderBy('id')->get()
            ->groupBy(fn ($row) => $row->bank_kredit_id.'|'.($row->bank_credit_product_id ?: 'all'))
            ->each(function ($requirements) {
                $first = $requirements->first();
                $code = 'LEGACY-BANK-'.$first->bank_kredit_id.'-'.($first->bank_credit_product_id ?: 'ALL');
                $setId = DB::table('document_requirement_sets')->where('code', $code)->value('id');
                if (! $setId) {
                    $setId = DB::table('document_requirement_sets')->insertGetId([
                        'code' => $code,
                        'name' => 'Migrasi Persyaratan Bank '.$first->bank_kredit_id,
                        'description' => 'Data hasil migrasi otomatis dari modul persyaratan dokumen bank lama.',
                        'application_types' => json_encode(['kpr_bank']),
                        'status' => 'aktif', 'record_status' => 'locked', 'locked_at' => now(),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                DB::table('document_requirement_set_banks')->insertOrIgnore(['document_requirement_set_id' => $setId, 'bank_kredit_id' => $first->bank_kredit_id]);
                if ($first->bank_credit_product_id) {
                    DB::table('document_requirement_set_products')->insertOrIgnore(['document_requirement_set_id' => $setId, 'bank_credit_product_id' => $first->bank_credit_product_id]);
                }

                foreach ($requirements as $requirement) {
                    $documentId = $requirement->dokumen_costumer_id ?: DB::table('dokumen_costumers')->where('kode_dokumen', $requirement->document_code)->value('id');
                    if (! $documentId) {
                        $documentId = DB::table('dokumen_costumers')->insertGetId([
                            'kode_dokumen' => $requirement->document_code, 'nama_dokumen' => $requirement->document_name,
                            'kategori_pengajuan' => 'kpr_bank', 'wajib' => $requirement->is_required, 'keterangan' => $requirement->notes,
                            'status' => 'aktif', 'record_status' => 'locked', 'locked_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                    DB::table('document_requirement_set_items')->insertOrIgnore([
                        'document_requirement_set_id' => $setId, 'dokumen_costumer_id' => $documentId,
                        'party_scope' => 'customer', 'is_required' => $requirement->is_required,
                        'validity_days' => $requirement->validity_days, 'sort_order' => $requirement->sort_order,
                        'notes' => trim(($requirement->notes ? $requirement->notes.' ' : '').'Asal kategori lama: '.$requirement->requirement_for),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                DB::table('approval_requests')->updateOrInsert(
                    ['module_key' => 'document-requirement-set', 'model_type' => DocumentRequirementSet::class, 'model_id' => $setId, 'action' => 'lock'],
                    ['module_label' => 'Paket Persyaratan Dokumen Pelanggan', 'status' => 'approved', 'current_step' => 1, 'total_steps' => 1, 'reviewed_at' => now(), 'after_data' => json_encode(['migrated' => true]), 'created_at' => now(), 'updated_at' => now()]
                );
            });

        DB::table('approval_requests')->where('model_type', 'App\\Models\\BankDocumentRequirement')->delete();
        Schema::drop('bank_document_requirements');
    }

    public function down(): void
    {
        Schema::create('bank_document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_kredit_id')->constrained('bank_kredits')->restrictOnDelete();
            $table->foreignId('bank_credit_product_id')->nullable()->constrained('bank_credit_products')->cascadeOnDelete();
            $table->foreignId('dokumen_costumer_id')->nullable()->constrained('dokumen_costumers')->nullOnDelete();
            $table->string('document_code');
            $table->string('document_name');
            $table->string('requirement_for')->default('customer');
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('aktif');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['bank_kredit_id', 'bank_credit_product_id', 'document_code'], 'bank_doc_requirement_unique');
        });
    }
};
