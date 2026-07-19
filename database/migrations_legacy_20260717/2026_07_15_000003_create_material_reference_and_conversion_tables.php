<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('aktif');
            $table->timestamps();
        });

        Schema::create('material_brands', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('aktif');
            $table->timestamps();
        });

        Schema::create('material_units', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->unique();
            $table->string('symbol')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('aktif');
            $table->timestamps();
        });

        Schema::table('barang_materials', function (Blueprint $table) {
            $type = $table->foreignId('material_type_id')->nullable()->after('nama_barang');
            $brand = $table->foreignId('material_brand_id')->nullable()->after('material_type_id');
            $unit = $table->foreignId('base_unit_id')->nullable()->after('material_brand_id');
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $type->constrained('material_types')->nullOnDelete();
                $brand->constrained('material_brands')->nullOnDelete();
                $unit->constrained('material_units')->nullOnDelete();
            }
        });

        Schema::create('material_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_material_id')->constrained('barang_materials')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->foreignId('parent_unit_id')->constrained('material_units')->restrictOnDelete();
            $table->foreignId('child_unit_id')->constrained('material_units')->restrictOnDelete();
            $table->decimal('factor', 18, 6);
            $table->decimal('cumulative_factor', 18, 6);
            $table->decimal('parent_price', 18, 2)->default(0);
            $table->decimal('child_price', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(['barang_material_id', 'level']);
            $table->unique(['barang_material_id', 'child_unit_id'], 'material_conversion_child_unique');
        });

        $this->seedReferences();
        $this->backfillExistingMaterials();
    }

    private function seedReferences(): void
    {
        $now = now();
        foreach (['Struktur', 'Dinding', 'Atap', 'Plafon', 'Lantai & Keramik', 'Sanitair', 'Pipa & Plumbing', 'Listrik', 'Cat & Finishing', 'Pintu, Jendela & Kusen', 'Besi & Baja', 'Kayu & Papan', 'Pasir, Batu & Semen', 'Alat Kerja', 'Lainnya'] as $index => $name) {
            DB::table('material_types')->insertOrIgnore(['code' => 'JNS-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'name' => $name, 'status' => 'aktif', 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([['PCS', 'Pcs'], ['PAK', 'Pak'], ['DUS', 'Dus'], ['SAK', 'Sak'], ['BTG', 'Batang'], ['M', 'Meter'], ['M2', 'Meter Persegi'], ['M3', 'Meter Kubik'], ['KG', 'Kilogram'], ['LTR', 'Liter'], ['ROLL', 'Roll'], ['SET', 'Set']] as $index => [$symbol, $name]) {
            DB::table('material_units')->insertOrIgnore(['code' => 'STN-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'name' => $name, 'symbol' => $symbol, 'status' => 'aktif', 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function backfillExistingMaterials(): void
    {
        DB::table('barang_materials')->orderBy('id')->get()->each(function ($material): void {
            $typeName = trim((string) ($material->jenis_material ?? $material->kategori_material ?? 'Lainnya')) ?: 'Lainnya';
            $typeId = DB::table('material_types')->whereRaw('lower(name) = ?', [mb_strtolower($typeName)])->value('id')
                ?? DB::table('material_types')->insertGetId(['code' => 'JNS-'.str_pad((string) (DB::table('material_types')->count() + 1), 3, '0', STR_PAD_LEFT), 'name' => $typeName, 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now()]);

            $unitText = trim((string) ($material->satuan ?? 'PCS')) ?: 'PCS';
            $unitId = DB::table('material_units')->whereRaw('lower(symbol) = ? or lower(name) = ?', [mb_strtolower($unitText), mb_strtolower($unitText)])->value('id')
                ?? DB::table('material_units')->insertGetId(['code' => 'STN-'.str_pad((string) (DB::table('material_units')->count() + 1), 3, '0', STR_PAD_LEFT), 'name' => $unitText, 'symbol' => mb_strtoupper($unitText), 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now()]);

            $brandName = trim((string) ($material->merk_material ?? ''));
            $brandId = null;
            if ($brandName !== '') {
                $brandId = DB::table('material_brands')->whereRaw('lower(name) = ?', [mb_strtolower($brandName)])->value('id')
                    ?? DB::table('material_brands')->insertGetId(['code' => 'MRK-'.str_pad((string) (DB::table('material_brands')->count() + 1), 3, '0', STR_PAD_LEFT), 'name' => $brandName, 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now()]);
            }

            DB::table('barang_materials')->where('id', $material->id)->update(['material_type_id' => $typeId, 'material_brand_id' => $brandId, 'base_unit_id' => $unitId]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_unit_conversions');
        Schema::table('barang_materials', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $table->dropColumn(['base_unit_id', 'material_brand_id', 'material_type_id']);
            } else {
                $table->dropConstrainedForeignId('base_unit_id');
                $table->dropConstrainedForeignId('material_brand_id');
                $table->dropConstrainedForeignId('material_type_id');
            }
        });
        Schema::dropIfExists('material_units');
        Schema::dropIfExists('material_brands');
        Schema::dropIfExists('material_types');
    }
};
