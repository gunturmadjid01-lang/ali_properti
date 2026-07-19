<?php

use App\Models\CabangPerusahaan;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\Spr;
use App\Models\User;
use App\Services\FixedSalesDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('fitur dokumen penjualan menyediakan format baku dan berita acara penandatanganan', function () {
    $service = app(FixedSalesDocumentService::class);
    expect(collect($service->catalog())->pluck('id')->all())->toBe(['spr', 'ppjb', 'handover', 'signing-minutes']);
    foreach (['spr', 'ppjb', 'handover'] as $type) {
        expect(resource_path("documents/fixed/{$type}.docx"))->toBeFile();
    }
});

test('penggantian teks word berhenti ketika hasil penggantian masih memuat teks sumber', function () {
    $service = app(FixedSalesDocumentService::class);
    $method = new ReflectionMethod($service, 'replaceParagraphText');
    $xml = '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>SIDRATUL MUNTAHA</w:t></w:r></w:p></w:body></w:document>';
    $result = $method->invoke($service, $xml, ['SIDRATUL MUNTAHA' => 'PERUMAHAN SIDRATUL MUNTAHA']);

    expect(strip_tags($result))->toContain('PERUMAHAN SIDRATUL MUNTAHA')
        ->not->toContain('PERUMAHAN PERUMAHAN');
});

test('dokumen word baku diisi otomatis menggunakan data spr customer dan unit', function () {
    $branch = CabangPerusahaan::create(['kode_cabang' => 'DOC', 'nama_cabang' => 'Cabang Dokumen', 'address' => '-', 'phone' => '-', 'emaiil' => 'doc@test.local', 'manager_name' => 'Manager', 'status' => 'aktif']);
    $housing = Perumahan::create(['cabang_id' => $branch->id, 'nama_perusahaan' => 'Perumahan Terintegrasi', 'alamat' => 'Jalan Integrasi', 'luas_lahan' => 1000, 'jumlah_unit' => 1, 'tanggal_mulai' => '2026-01-01', 'status' => 'aktif']);
    $unit = DetailRumah::create(['perumahan_id' => $housing->id, 'kode_nlok' => 'Z', 'nomor_rumah' => '99', 'tipe_rumah' => '45', 'luas_tanah' => 90, 'luas_bangunan' => 45, 'status' => 'aktif']);
    $customer = Costumer::create(['kode_costumer' => 'DOC-1', 'perumahan_id' => $housing->id, 'nama' => 'CUSTOMER OTOMATIS', 'jenis_kelamin' => 'L', 'jenis_identitas' => 'KTP', 'no_identitas' => 'DOC-1', 'status_perkawinan' => 'belum_menikah', 'alamat' => 'Alamat Customer', 'telepon' => '08123456789']);
    $spr = Spr::create(['kode_spr' => 'SPR-DOC-001', 'costumer_id' => $customer->id, 'detail_rumah_id' => $unit->id, 'tanggal_spr' => '2026-07-16', 'metode_pembayaran' => 'cash', 'harga_jual' => 250000000, 'nilai_pengajuan_akhir' => 250000000, 'status' => 'disetujui']);
    Permission::findOrCreate('booking.view', 'web');
    $user = User::factory()->create(['phone' => '081200009999']);
    $user->givePermissionTo('booking.view');
    $response = $this->actingAs($user)->get(route('admin.document-templates.spr.print', [$spr, 'spr']));
    $response->assertOk()->assertDownload('spr-spr-doc-001.docx');

    $generated = app(FixedSalesDocumentService::class)->generate($spr, 'spr');
    $zip = new ZipArchive;
    $zip->open($generated);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($generated);
    expect(strip_tags($xml))->toContain('SPR-DOC-001')->toContain('CUSTOMER OTOMATIS')->toContain('Perumahan Terintegrasi')->toContain('Z')->toContain('99');
});

test('pengguna tanpa permission tidak dapat membuka dokumen baku', function () {
    $this->actingAs(User::factory()->create(['phone' => '081200008888']))->get('/admin/master-dokumen')->assertForbidden();
});
