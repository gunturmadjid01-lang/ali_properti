<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\BankBranch;
use App\Models\BankCreditProduct;
use App\Models\BankHousingPartnership;
use App\Models\BankKprDisbursement;
use App\Models\BankKprFinancing;
use App\Models\BankKredit;
use App\Models\BarangMaterial;
use App\Models\CabangPerusahaan;
use App\Models\CashInstallmentContract;
use App\Models\CashInstallmentScheme;
use App\Models\CashSale;
use App\Models\Costumer;
use App\Models\CostumerFollowUp;
use App\Models\CustomerCharge;
use App\Models\CustomerDocumentChecklist;
use App\Models\CustomerReceipt;
use App\Models\CustomerRefund;
use App\Models\DetailRumah;
use App\Models\DeveloperKprApplication;
use App\Models\DeveloperKprProduct;
use App\Models\DocumentRequirementSet;
use App\Models\DokumenCostumer;
use App\Models\DokumenLegalitas;
use App\Models\DokumenLegalitasRumah;
use App\Models\EmployeeAdvance;
use App\Models\FieldDefect;
use App\Models\Gudang;
use App\Models\HousingReservation;
use App\Models\InternalHandover;
use App\Models\Journal;
use App\Models\Kontraktor;
use App\Models\KprSubmission;
use App\Models\MarketingActionPlan;
use App\Models\MarketingCampaign;
use App\Models\MarketingCommission;
use App\Models\MarketingEvaluation;
use App\Models\MarketingLeadSource;
use App\Models\MarketingReferenceOption;
use App\Models\MarketingScoreSetting;
use App\Models\MarketingSurveySchedule;
use App\Models\MarketingTarget;
use App\Models\MarketingTemplate;
use App\Models\MarketingVisit;
use App\Models\MasterBank;
use App\Models\MaterialBrand;
use App\Models\MaterialGroup;
use App\Models\MaterialOpeningBalance;
use App\Models\MaterialPriceHistory;
use App\Models\MaterialPurchase;
use App\Models\MaterialPurchaseRequest;
use App\Models\MaterialRequest;
use App\Models\MaterialReturn;
use App\Models\MaterialStockOpname;
use App\Models\MaterialType;
use App\Models\MaterialUnit;
use App\Models\MaterialUsage;
use App\Models\OperationTransactionArchive;
use App\Models\PayrollBatch;
use App\Models\Perumahan;
use App\Models\PettyCashDeposit;
use App\Models\PettyCashFunding;
use App\Models\ProgressPembangunan;
use App\Models\QualityInspection;
use App\Models\QualityUpgradeAddendum;
use App\Models\QualityUpgradeContract;
use App\Models\QualityUpgradeHandover;
use App\Models\SafetyReport;
use App\Models\SalesProcessStep;
use App\Models\SalesResolutionRequest;
use App\Models\SiteManpowerLog;
use App\Models\SiteReport;
use App\Models\SiteSchedule;
use App\Models\SpkKontraktor;
use App\Models\Spr;
use App\Models\Supplier;
use App\Models\TipePost;
use App\Models\TransaksiKeuangan;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\WaterBillingPeriod;
use App\Models\WaterPayment;
use App\Models\WorkChangeRequest;
use App\Services\BankCreditProductService;
use App\Services\BankPartnershipService;
use App\Services\MaterialGroupService;
use App\Services\MaterialUnitConversionService;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class ApprovalResources
{
    public static function modules(): array
    {
        return [
            'water-billing-period' => ['label' => 'Periode Tagihan Air', 'model' => WaterBillingPeriod::class],
            'water-payment' => ['label' => 'Pembayaran Air', 'model' => WaterPayment::class],
            'attendance' => [
                'label' => 'Absensi Pegawai',
                'model' => AttendanceRecord::class,
            ],
            'attendance-setting' => ['label' => 'Pengaturan Jam Absensi', 'model' => AttendanceSetting::class],
            'cabang-perusahaan' => [
                'label' => 'Management Cabang Perusahaan',
                'model' => CabangPerusahaan::class,
            ],
            'perumahan' => [
                'label' => 'Management Perumahan',
                'model' => Perumahan::class,
            ],
            'detail-rumah' => [
                'label' => 'Management Detail Rumah',
                'model' => DetailRumah::class,
            ],
            'master-bank' => [
                'label' => 'Management Master Bank',
                'model' => MasterBank::class,
            ],
            'customer' => [
                'label' => 'Customer / Calon Konsumen',
                'model' => Costumer::class,
            ],
            'marketing-lead-source' => [
                'label' => 'Marketing Sumber Lead',
                'model' => MarketingLeadSource::class,
            ],
            'dokumen-legalitas' => [
                'label' => 'Management Dokumen Legalitas',
                'model' => DokumenLegalitas::class,
            ],
            'dokumen-customer' => [
                'label' => 'Jenis Dokumen Pelanggan',
                'model' => DokumenCostumer::class,
            ],
            'dokumen-legalitas-rumah' => [
                'label' => 'Management Dokumen Legalitas Rumah',
                'model' => DokumenLegalitasRumah::class,
            ],
            'tipe-post' => [
                'label' => 'Management Tipe Post',
                'model' => TipePost::class,
            ],
            'financial-transaction' => [
                'label' => 'Transaksi Kas & Bank Manual',
                'model' => TransaksiKeuangan::class,
            ],
            'manual-journal' => [
                'label' => 'Jurnal Umum Manual',
                'model' => Journal::class,
                'relation_keys' => ['details'],
            ],
            'progress' => [
                'label' => 'Progress Pembangunan',
                'model' => ProgressPembangunan::class,
            ],
            'site-schedule' => [
                'label' => 'Jadwal Lapangan',
                'model' => SiteSchedule::class,
            ],
            'site-report' => [
                'label' => 'Laporan Lapangan',
                'model' => SiteReport::class,
            ],
            'quality-inspection' => [
                'label' => 'Kontrol Kualitas',
                'model' => QualityInspection::class,
            ],
            'quality-upgrade' => ['label' => 'Kontrak Penambahan Mutu Bangunan', 'model' => QualityUpgradeContract::class, 'relation_keys' => ['items']],
            'quality-upgrade-addendum' => ['label' => 'Addendum Kontrak Penambahan Mutu', 'model' => QualityUpgradeAddendum::class],
            'quality-upgrade-handover' => ['label' => 'Serah Terima Penambahan Mutu', 'model' => QualityUpgradeHandover::class],
            'field-supervision' => [
                'label' => 'Pengawasan Lapangan',
                'model' => FieldDefect::class,
            ],
            'material-request' => [
                'label' => 'Permintaan Material',
                'model' => MaterialRequest::class,
            ],
            'master-material' => [
                'label' => 'Master Material',
                'model' => BarangMaterial::class,
                'relation_keys' => ['conversions'],
            ],
            'material-type' => ['label' => 'Jenis Material', 'model' => MaterialType::class],
            'material-brand' => ['label' => 'Merk Material', 'model' => MaterialBrand::class],
            'material-unit' => ['label' => 'Satuan Material', 'model' => MaterialUnit::class],
            'material-group' => [
                'label' => 'Kelompok Material',
                'model' => MaterialGroup::class,
                'relation_keys' => ['items'],
            ],
            'bank-credit-master' => ['label' => 'Master Bank Kredit', 'model' => BankKredit::class],
            'bank-branch' => ['label' => 'Cabang Bank', 'model' => BankBranch::class],
            'bank-credit-product' => ['label' => 'Produk Kredit Bank', 'model' => BankCreditProduct::class],
            'bank-housing-partnership' => ['label' => 'Kerja Sama Bank dan Perumahan', 'model' => BankHousingPartnership::class],
            'cash-installment-scheme' => ['label' => 'Skema Cash Bertahap', 'model' => CashInstallmentScheme::class, 'relation_keys' => ['steps', 'housings']],
            'developer-kpr-product' => ['label' => 'Produk KPR Developer', 'model' => DeveloperKprProduct::class, 'relation_keys' => ['housings']],
            'document-requirement-set' => ['label' => 'Paket Persyaratan Kredit', 'model' => DocumentRequirementSet::class, 'relation_keys' => ['items']],
            'supplier' => [
                'label' => 'Supplier',
                'model' => Supplier::class,
            ],
            'material-purchase' => [
                'label' => 'Pembelian Material',
                'model' => MaterialPurchase::class,
            ],
            'spk-kontraktor' => [
                'label' => 'SPK Kontraktor',
                'model' => SpkKontraktor::class,
            ],
            'spr' => [
                'label' => 'Pengajuan SPR',
                'model' => Spr::class,
            ],
            'user' => [
                'label' => 'Management User',
                'model' => User::class,
                'relation_keys' => ['role_ids'],
            ],
            'role-permission' => [
                'label' => 'Management Role & Permission',
                'model' => Role::class,
                'relation_keys' => ['permission_ids'],
            ],
            'gudang' => ['label' => 'Gudang', 'model' => Gudang::class],
            'kontraktor' => ['label' => 'Kontraktor', 'model' => Kontraktor::class],
            'customer-follow-up' => ['label' => 'Follow Up Customer', 'model' => CostumerFollowUp::class],
            'marketing-visit' => ['label' => 'Laporan Kunjungan Customer', 'model' => MarketingVisit::class],
            'marketing-action-plan' => ['label' => 'Action Plan Marketing', 'model' => MarketingActionPlan::class],
            'customer-document-checklist' => ['label' => 'Checklist Kelengkapan Berkas', 'model' => CustomerDocumentChecklist::class],
            'kpr-submission' => ['label' => 'Pengajuan KPR', 'model' => KprSubmission::class],
            'marketing-survey' => ['label' => 'Jadwal Survey Marketing', 'model' => MarketingSurveySchedule::class],
            'cash-sale' => ['label' => 'Transaksi Penjualan Cash', 'model' => CashSale::class],
            'material-opening-balance' => ['label' => 'Saldo Awal Material', 'model' => MaterialOpeningBalance::class],
            'material-stock-opname' => ['label' => 'Stock Opname Material', 'model' => MaterialStockOpname::class, 'relation_keys' => ['details']],
            'material-price' => ['label' => 'Harga Material', 'model' => MaterialPriceHistory::class],
            'material-purchase-request' => ['label' => 'Permintaan Pembelian Material', 'model' => MaterialPurchaseRequest::class],
            'material-return' => ['label' => 'Pengembalian Material', 'model' => MaterialReturn::class],
            'material-usage' => ['label' => 'Pemakaian Material', 'model' => MaterialUsage::class],
            'unit-ownership' => ['label' => 'Kepemilikan Unit', 'model' => UnitOwnership::class],
            'inventory-loans' => ['label' => 'Aset - Peminjaman/Pengambilan', 'model' => OperationTransactionArchive::class],
            'inventory-receipts' => ['label' => 'Aset - Penerimaan/Penambahan', 'model' => OperationTransactionArchive::class],
            'inventory-returns' => ['label' => 'Aset - Pengembalian', 'model' => OperationTransactionArchive::class],
            'inventory-transfers' => ['label' => 'Aset - Mutasi', 'model' => OperationTransactionArchive::class],
            'inventory-damages' => ['label' => 'Aset - Kerusakan', 'model' => OperationTransactionArchive::class],
            'inventory-losses' => ['label' => 'Aset - Kehilangan', 'model' => OperationTransactionArchive::class],
            'inventory-stock-opname' => ['label' => 'Aset - Stock Opname', 'model' => OperationTransactionArchive::class],
            'heavy-replacements' => ['label' => 'Alat Berat - Penggantian Komponen', 'model' => OperationTransactionArchive::class],
            'heavy-usage' => ['label' => 'Alat Berat - Penggunaan', 'model' => OperationTransactionArchive::class],
            'heavy-maintenance' => ['label' => 'Alat Berat - Maintenance', 'model' => OperationTransactionArchive::class],
            'heavy-damages' => ['label' => 'Alat Berat - Kerusakan', 'model' => OperationTransactionArchive::class],
            'heavy-fuel' => ['label' => 'Alat Berat - Pengisian BBM', 'model' => OperationTransactionArchive::class],
            'marketing-campaign' => ['label' => 'Marketing Campaign', 'model' => MarketingCampaign::class],
            'marketing-template' => ['label' => 'Template Marketing', 'model' => MarketingTemplate::class],
            'marketing-target' => ['label' => 'Target Marketing', 'model' => MarketingTarget::class],
            'marketing-evaluation' => ['label' => 'Evaluasi Kinerja Marketing', 'model' => MarketingEvaluation::class],
            'marketing-score-setting' => ['label' => 'Konfigurasi Bobot Kinerja Marketing', 'model' => MarketingScoreSetting::class],
            'marketing-reference-option' => ['label' => 'Master Pilihan Marketing', 'model' => MarketingReferenceOption::class],
            'marketing-commission' => ['label' => 'Komisi Marketing', 'model' => MarketingCommission::class],
            'field-defect' => ['label' => 'Lapangan - Defect', 'model' => FieldDefect::class],
            'field-work-change' => ['label' => 'Lapangan - Perubahan Pekerjaan', 'model' => WorkChangeRequest::class],
            'field-manpower' => ['label' => 'Lapangan - Tenaga Kerja dan Alat', 'model' => SiteManpowerLog::class],
            'field-safety' => ['label' => 'Lapangan - K3', 'model' => SafetyReport::class],
            'field-handover' => ['label' => 'Lapangan - Serah Terima Internal', 'model' => InternalHandover::class],
            'customer-receipt' => ['label' => 'Penerimaan Customer', 'model' => CustomerReceipt::class],
            'customer-refund' => ['label' => 'Refund Booking Fee & Uang Muka', 'model' => CustomerRefund::class],
            'housing-reservation' => ['label' => 'Reservasi & Penerimaan Booking Fee', 'model' => HousingReservation::class],
            'customer-charge' => ['label' => 'Tagihan Tambahan & Talangan Customer', 'model' => CustomerCharge::class],
            'customer-charge-reversal' => ['label' => 'Reversal Tagihan/Talangan Customer', 'model' => CustomerCharge::class],
            'cash-installment-contract' => ['label' => 'Kontrak Cash Bertahap', 'model' => CashInstallmentContract::class],
            'developer-kpr-contract' => ['label' => 'Kontrak KPR Developer', 'model' => DeveloperKprApplication::class],
            'bank-kpr-financing' => ['label' => 'Struktur Pembiayaan KPR Bank', 'model' => BankKprFinancing::class],
            'bank-kpr-disbursement' => ['label' => 'Pencairan KPR Bank', 'model' => BankKprDisbursement::class],
            'sales-process-step' => ['label' => 'Tahapan Penjualan sampai Customer Menempati Unit', 'model' => SalesProcessStep::class],
            'sales-resolution-request' => ['label' => 'Penanganan Proses Penjualan Gagal', 'model' => SalesResolutionRequest::class],
            'petty-cash-funding' => ['label' => 'Pengisian Kas Kecil', 'model' => PettyCashFunding::class],
            'petty-cash-deposit' => ['label' => 'Penyetoran Kas Kecil ke Kas Perusahaan', 'model' => PettyCashDeposit::class],
            'employee-payroll' => ['label' => 'Penggajian Pegawai', 'model' => PayrollBatch::class],
            'employee-advance' => ['label' => 'Panjar Pegawai', 'model' => EmployeeAdvance::class],
        ];
    }

    public static function actions(): array
    {
        return [
            'lock' => 'Finalisasi / Lock Data',
        ];
    }

    public static function categories(): array
    {
        return [
            'management' => ['label' => 'Manajemen & Akses', 'modules' => ['cabang-perusahaan', 'user', 'role-permission']],
            'property' => ['label' => 'Properti & Legalitas', 'modules' => ['perumahan', 'detail-rumah', 'dokumen-legalitas', 'dokumen-legalitas-rumah', 'unit-ownership', 'water-billing-period']],
            'marketing' => ['label' => 'Marketing & Pelanggan', 'modules' => ['customer', 'customer-follow-up', 'marketing-visit', 'marketing-action-plan', 'customer-document-checklist', 'marketing-lead-source', 'marketing-campaign', 'marketing-template', 'marketing-target', 'marketing-evaluation', 'marketing-score-setting', 'marketing-reference-option', 'marketing-commission', 'marketing-survey', 'dokumen-customer', 'document-requirement-set']],
            'sales' => ['label' => 'Penjualan & Pembiayaan', 'modules' => ['housing-reservation', 'spr', 'cash-sale', 'kpr-submission', 'cash-installment-scheme', 'cash-installment-contract', 'developer-kpr-product', 'developer-kpr-contract', 'bank-credit-master', 'master-bank', 'bank-branch', 'bank-credit-product', 'bank-housing-partnership', 'bank-kpr-financing', 'bank-kpr-disbursement', 'sales-process-step', 'sales-resolution-request']],
            'finance' => ['label' => 'Keuangan', 'modules' => ['tipe-post', 'financial-transaction', 'manual-journal', 'quality-upgrade', 'quality-upgrade-addendum', 'quality-upgrade-handover', 'customer-receipt', 'customer-refund', 'customer-charge', 'customer-charge-reversal', 'water-payment', 'petty-cash-funding', 'petty-cash-deposit']],
            'materials' => ['label' => 'Material & Logistik', 'modules' => ['master-material', 'material-type', 'material-brand', 'material-unit', 'material-group', 'supplier', 'gudang', 'material-request', 'material-purchase-request', 'material-purchase', 'material-opening-balance', 'material-price', 'material-return', 'material-usage']],
            'project' => ['label' => 'Proyek & Lapangan', 'modules' => ['progress', 'site-schedule', 'site-report', 'quality-inspection', 'field-supervision', 'field-defect', 'field-work-change', 'field-manpower', 'field-safety', 'field-handover', 'spk-kontraktor', 'kontraktor']],
            'assets' => ['label' => 'Aset & Alat Berat', 'modules' => ['inventory-loans', 'inventory-receipts', 'inventory-returns', 'inventory-transfers', 'inventory-damages', 'inventory-losses', 'inventory-stock-opname', 'heavy-replacements', 'heavy-usage', 'heavy-maintenance', 'heavy-damages', 'heavy-fuel']],
            'employees' => ['label' => 'Kepegawaian', 'modules' => ['attendance', 'attendance-setting', 'employee-payroll', 'employee-advance']],
            'other' => ['label' => 'Lainnya', 'modules' => []],
        ];
    }

    public static function category(string $moduleKey): array
    {
        foreach (self::categories() as $key => $category) {
            if (in_array($moduleKey, $category['modules'], true)) {
                return ['key' => $key, 'label' => $category['label']];
            }
        }

        return ['key' => 'other', 'label' => self::categories()['other']['label']];
    }

    public static function module(string $key): array
    {
        return self::modules()[$key] ?? [];
    }

    public static function modelClass(string $key): string
    {
        return self::module($key)['model'] ?? '';
    }

    public static function keyForModel(Model|string $model): string
    {
        $class = is_string($model) ? $model : $model::class;

        return collect(self::modules())->search(fn ($config) => $config['model'] === $class)
            ?: str((new $class)->getTable())->singular()->replace('_', '-')->toString();
    }

    public static function label(string $key): string
    {
        return self::module($key)['label'] ?? $key;
    }

    public static function relationKeys(string $key): array
    {
        return self::module($key)['relation_keys'] ?? [];
    }

    public static function modelPayload(string $moduleKey, array $payload): array
    {
        return collect($payload)
            ->except(self::relationKeys($moduleKey))
            ->reject(fn ($value, $key) => $key === 'password' && $value === '')
            ->toArray();
    }

    public static function syncRelations(string $moduleKey, Model $model, array $payload): void
    {
        if ($moduleKey === 'user') {
            $model->syncRoles($payload['role_ids'] ?? []);
        }

        if ($moduleKey === 'role-permission') {
            $model->syncPermissions($payload['permission_ids'] ?? []);
        }

        if ($moduleKey === 'master-material') {
            app(MaterialUnitConversionService::class)->sync($model, $payload['conversions'] ?? []);
            if (! $model->priceHistories()->exists()) {
                $model->priceHistories()->create([
                    'tanggal_berlaku' => now()->toDateString(),
                    'harga_satuan' => (float) $model->harga_hpp,
                    'keterangan' => 'Harga awal material setelah approval.',
                    'status' => 'aktif',
                    'created_by' => auth()->id(),
                ]);
            }
        }

        if ($moduleKey === 'material-group') {
            app(MaterialGroupService::class)->syncItems($model, $payload['items'] ?? []);
        }

        if ($moduleKey === 'bank-credit-product') {
            app(BankCreditProductService::class)->createVersion($model);
        }

        if ($moduleKey === 'bank-housing-partnership') {
            app(BankPartnershipService::class)->createVersion($model);
        }
    }
}
