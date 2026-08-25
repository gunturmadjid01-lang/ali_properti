<?php

namespace App\Services;

use App\Models\User;
use App\Support\SchemaMetadata;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class RoleDashboardService
{
    private ?int $perumahanId = null;

    private string $period = 'month';

    private string $periodValue = '';

    private string $periodLabel = '';

    private Carbon $periodStart;

    private Carbon $periodEnd;

    private array $timeline = [];

    public function build(User $user, ?int $activePerumahanId = null, string $period = 'month', ?string $periodValue = null): array
    {
        $this->perumahanId = $activePerumahanId;
        $this->configurePeriod($period, $periodValue);
        $sections = [];
        $charts = [];
        $shortcuts = [];
        $isExecutive = $user->hasAnyRole(['owner', 'manager', 'manajer_pimpro', 'admin', 'super_admin']);
        $isMarketing = $user->hasAnyRole(['marketing', 'area_marketing']);
        $isField = $user->hasAnyRole(['pengawas', 'manajer_pimpro']);
        $isWarehouse = $user->hasAnyRole(['gudang', 'logistik']);
        $isFinance = $user->hasAnyRole(['keuangan', 'finance']);
        $profile = match (true) {
            $isMarketing => ['key' => 'marketing', 'eyebrow' => 'Dasbor Marketing', 'title' => 'Fokus penjualan hari ini', 'description' => 'Pantau prospek, tindak lanjut, SPR, booking, dan pencapaian penjualan dalam satu ringkasan.'],
            $isWarehouse => ['key' => 'warehouse', 'eyebrow' => 'Dasbor Gudang & Logistik', 'title' => 'Kendali persediaan hari ini', 'description' => 'Pantau stok, permintaan, pembelian, penerimaan, dan pemakaian material yang perlu ditindaklanjuti.'],
            $isField && ! $isExecutive => ['key' => 'field', 'eyebrow' => 'Dasbor Pengawas Lapangan', 'title' => 'Kondisi pekerjaan hari ini', 'description' => 'Pantau jadwal, progres, mutu, K3, defect, material, dan pekerjaan lapangan berdasarkan unit aktif.'],
            $isFinance && ! $isExecutive => ['key' => 'finance', 'eyebrow' => 'Dasbor Keuangan', 'title' => 'Posisi keuangan terkini', 'description' => 'Pantau kas, transaksi, piutang, hutang, jurnal, dan tugas review keuangan periode berjalan.'],
            $isExecutive => ['key' => 'executive', 'eyebrow' => 'Dasbor Manajemen', 'title' => 'Ringkasan kinerja perusahaan', 'description' => 'Pantau kondisi keuangan, penjualan, proyek, persediaan, dan antrean keputusan dari seluruh area yang Anda kelola.'],
            default => ['key' => 'general', 'eyebrow' => 'Dasbor Berbasis Hak Akses', 'title' => 'Ringkasan pekerjaan Anda', 'description' => 'Informasi operasional ditampilkan sesuai tugas dan hak akses yang diberikan.'],
        };

        $canSeeFinanceSummary = $user->hasAnyRole(['owner', 'manager', 'manajer_pimpro', 'admin', 'super_admin', 'keuangan'])
            || $this->allowed($user, ['keuangan.view', 'bank-account-ledger.view', 'buku-besar.view', 'neraca-saldo.view', 'laba-rugi.view', 'neraca.view', 'arus-kas.view', 'piutang.view', 'hutang.view', 'petty-cash.view']);

        if ($canSeeFinanceSummary && ! $isMarketing && ! $isField && ! $isWarehouse) {
            $sections[] = $this->financeStats();
            $charts[] = $this->financeChart();
            $shortcuts = [...$shortcuts, ...$this->shortcuts($user, [
                ['keuangan.view', 'Dashboard Keuangan', '/admin/keuangan/dashboard'],
                ['bank-account-ledger.view', 'Mutasi Rekening', '/admin/rekening-bank'],
                ['petty-cash.view', 'Kas Kecil', '/admin/kas-kecil/saldo'],
                ['buku-besar.view', 'Buku Besar', '/admin/keuangan/buku-besar'],
            ])];
        }

        if (! $isMarketing && ($isExecutive || $isFinance || $this->allowed($user, ['approval.view', 'approval.settings', 'spk-kontraktor.approve']))) {
            $sections[] = $this->approvalStats();
            $charts[] = $this->distributionChart('Status Antrean Approval', 'approval_requests', 'status', 'Pengajuan');
            $shortcuts = [...$shortcuts, ...$this->shortcuts($user, [
                ['approval.view', 'Daftar Approval', '/admin/approval'],
                ['spk-kontraktor.approve', 'Approval SPK', '/admin/spk-kontraktor/approval'],
                ['approval.settings', 'Setting Approval', '/admin/approval/settings'],
            ])];
        }

        if (! $isMarketing && ! $isWarehouse && ($isExecutive || $isField || $this->allowed($user, ['rab-unit.view', 'site-schedule.view', 'site-report.view', 'quality-inspection.view', 'field-supervision.view', 'spk-kontraktor.view']))) {
            $sections[] = $this->propertyStats();
            $charts[] = $this->distributionChart('Status Pembangunan Unit', 'detail_rumahs', 'status_pembangunan', 'Unit');
            $charts[] = $this->activityChart('Aktivitas Proyek', [
                ['label' => 'Progress', 'table' => 'progress_pembangunans', 'date' => 'tanggal', 'color' => '#c8962e'],
                ['label' => 'SPK', 'table' => 'spk_kontraktors', 'date' => 'tanggal_spk', 'color' => '#334155'],
            ]);
            $charts[] = $this->progressChart();
            $shortcuts = [...$shortcuts, ...$this->shortcuts($user, [
                ['detail-rumah.view', 'Data Unit Rumah', '/admin/unit-rumah'],
                ['progress.view', 'Progress Pembangunan', '/admin/progress-pembangunan'],
                ['site-schedule.view', 'Jadwal Lapangan', '/admin/jadwal-lapangan'],
                ['spk-kontraktor.view', 'SPK Kontraktor', '/admin/spk-kontraktor'],
            ])];
        }

        if ($isMarketing || $isExecutive || $this->allowed($user, ['marketing.lead-report.view', 'marketing.pipeline.view', 'marketing.pipeline-report.view', 'marketing.campaign.manage', 'marketing.activity.view', 'laporan-marketing.view'])) {
            $sections[] = $this->marketingStats();
            $charts[] = $this->activityChart('Prospek & SPR', [
                ['label' => 'Prospek', 'table' => 'costumers', 'date' => 'created_at', 'color' => '#2563eb'],
                ['label' => 'SPR', 'table' => 'sprs', 'date' => 'tanggal_spr', 'color' => '#c8962e'],
            ]);
            $charts[] = $this->distributionChart('Tahapan Prospek', 'marketing_leads', 'stage', 'Prospek');
            $shortcuts = [...$shortcuts, ...$this->shortcuts($user, [
                ['customer.view', 'Data Pelanggan', '/admin/marketing/calon-konsumen'],
                ['booking.view', 'Data SPR', '/admin/marketing/spr'],
                ['kpr.view', 'Pengajuan KPR', '/admin/kpr'],
            ])];
        }

        if (! $isMarketing && ($isExecutive || $isWarehouse || $this->allowed($user, ['master-material.view', 'site-material-stock.view', 'material-request.view', 'material-purchase.view', 'material-usage.view', 'supplier.view', 'laporan-persediaan-material.view', 'laporan-pembelian.view']))) {
            $sections[] = $this->warehouseStats();
            $charts[] = $this->activityChart('Aktivitas Logistik', [
                ['label' => 'Permintaan', 'table' => 'material_requests', 'date' => 'tanggal', 'color' => '#0f766e'],
                ['label' => 'Pembelian', 'table' => 'material_purchases', 'date' => 'tanggal', 'color' => '#c8962e'],
                ['label' => 'Pemakaian', 'table' => 'material_usages', 'date' => 'tanggal', 'color' => '#334155'],
            ]);
            $shortcuts = [...$shortcuts, ...$this->shortcuts($user, [
                ['site-material-stock.view', 'Stok Material', '/admin/stok-material'],
                ['material-request.view', 'Permintaan Material', '/admin/permintaan-barang'],
                ['material-purchase.view', 'Pembelian Material', '/admin/pembelian-material'],
                ['supplier.view', 'Supplier', '/admin/supplier'],
            ])];
        }

        if ($this->allowed($user, ['company-inventory.view', 'heavy-equipment.view'])) {
            $sections[] = $this->assetStats($user);
            if ($user->can('company-inventory.view')) {
                $charts[] = $this->inventoryChart();
            }
            if ($user->can('heavy-equipment.view')) {
                $charts[] = $this->distributionChart('Kondisi Alat Berat', 'heavy_equipments', 'status', 'Alat');
            }
            $shortcuts = [...$shortcuts, ...$this->shortcuts($user, [
                ['company-inventory.view', 'Inventaris Perusahaan', '/admin/inventaris-perusahaan/dashboard'],
                ['heavy-equipment.view', 'Alat Berat', '/admin/alat-berat/dashboard'],
            ])];
        }

        $charts = collect($charts)->filter(fn (array $chart) => count($chart['labels'] ?? []) > 0)->values()->all();

        return [
            'marketing_activity' => $user->hasAnyRole(['owner', 'manager', 'manajer_pimpro', 'super_admin'])
                ? $this->marketingActivityOverview()
                : null,
            'sections' => array_values(array_filter($sections)),
            'charts' => $charts,
            'shortcuts' => collect($shortcuts)->unique('href')->take(10)->values()->all(),
            'context' => [
                'generated_at' => now()->format('d M Y, H:i'),
                'active_perumahan_id' => $activePerumahanId,
                'roles' => $user->roles->pluck('name')->values()->all(),
                'period_label' => $this->periodLabel,
                'profile' => $profile,
            ],
            'filters' => ['period' => $this->period, 'value' => $this->periodValue],
        ];
    }

    private function marketingActivityOverview(): array
    {
        $marketing = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['marketing', 'area_marketing']))
            ->when($this->perumahanId, fn ($query, int $id) => $query->whereHas('perumahans', fn ($query) => $query->whereKey($id)))
            ->pluck('id');

        $lead = $this->query('costumers')->whereIn('created_by', $marketing)->whereBetween('created_at', [$this->periodStart, $this->periodEnd])
            ->when($this->perumahanId, fn ($query, int $id) => $query->where('perumahan_id', $id))->count();
        $followUp = $this->query('costumer_follow_ups as followups')->join('costumers as customer', 'customer.id', '=', 'followups.costumer_id')
            ->whereIn('followups.user_id', $marketing)->whereBetween('followups.tanggal_follow_up', [$this->periodStart, $this->periodEnd])
            ->when($this->perumahanId, fn ($query, int $id) => $query->where('customer.perumahan_id', $id))->count();
        $survey = $this->query('marketing_survey_schedules')->whereIn('marketing_id', $marketing)->whereBetween('tanggal_survey', [$this->periodStart, $this->periodEnd])
            ->when($this->perumahanId, fn ($query, int $id) => $query->where('perumahan_id', $id))->count();
        $spr = $this->query('sprs as spr')->join('detail_rumahs as unit', 'unit.id', '=', 'spr.detail_rumah_id')
            ->whereNull('spr.deleted_at')->whereIn('spr.created_by', $marketing)->whereBetween('spr.tanggal_spr', [$this->periodStart, $this->periodEnd])
            ->when($this->perumahanId, fn ($query, int $id) => $query->where('unit.perumahan_id', $id))->count();
        $overdue = $this->query('marketing_reminders as reminder')->join('costumers as customer', 'customer.id', '=', 'reminder.costumer_id')
            ->whereIn('reminder.user_id', $marketing)->where('reminder.status', 'menunggu')->where('reminder.remind_at', '<', now())
            ->whereBetween('reminder.remind_at', [$this->periodStart, $this->periodEnd])
            ->when($this->perumahanId, fn ($query, int $id) => $query->where('customer.perumahan_id', $id))->count();

        return [
            'period_label' => $this->periodLabel,
            'items' => [
                ['label' => 'Lead Baru', 'value' => $lead, 'icon' => 'target', 'tone' => 'violet'],
                ['label' => 'Tindak Lanjut', 'value' => $followUp, 'icon' => 'activity', 'tone' => 'emerald'],
                ['label' => 'Survei', 'value' => $survey, 'icon' => 'eye', 'tone' => 'amber'],
                ['label' => 'SPR', 'value' => $spr, 'icon' => 'chart', 'tone' => 'blue'],
                ['label' => 'Reminder Terlambat', 'value' => $overdue, 'icon' => 'alert', 'tone' => 'red'],
            ],
        ];
    }

    private function propertyStats(): array
    {
        return $this->section('Properti & Pembangunan', 'property', [
            $this->stat('Total Unit Rumah', $this->count('detail_rumahs'), 'number', 'home'),
            $this->stat('Unit Tersedia', $this->count('detail_rumahs', fn (Builder $q) => $q->where('status_penjualan', 'tersedia')), 'number', 'check'),
            $this->stat('Unit Terjual / Booking', $this->count('detail_rumahs', fn (Builder $q) => $q->whereIn('status_penjualan', ['terjual', 'sold', 'booking', 'terbooking'])), 'number', 'wallet'),
            $this->stat('Sedang Dibangun', $this->count('detail_rumahs', fn (Builder $q) => $q->where('status_pembangunan', 'sedang_dibangun')), 'number', 'hard-hat'),
            $this->stat('Rata-rata Progress Unit', $this->average('detail_rumahs', 'progress_terakhir'), 'percent', 'trending'),
            $this->stat('Nilai SPK Aktif', $this->sumFiltered('spk_kontraktors', 'nilai_kontrak', fn (Builder $q) => $q->whereIn('status', ['aktif', 'approved', 'disetujui'])), 'currency', 'wallet'),
        ]);
    }

    private function approvalStats(): array
    {
        return $this->section('Approval & Tugas Review', 'approval', [
            $this->stat('Antrean Approval', $this->count('approval_requests', fn (Builder $q) => $q->where('status', 'pending')), 'number', 'clock'),
            $this->stat('SPR Menunggu Review', $this->count('sprs', fn (Builder $q) => $q->whereIn('status', ['menunggu_manager', 'menunggu_owner'])), 'number', 'receipt'),
            $this->stat('Material Menunggu', $this->count('material_purchases', fn (Builder $q) => $q->whereIn('status', ['menunggu_approval_manager', 'menunggu_approval'])), 'number', 'cart'),
            $this->stat('Disetujui Periode', $this->countPeriod('approval_requests', 'updated_at', fn (Builder $q) => $q->where('status', 'approved')), 'number', 'check'),
            $this->stat('Ditolak Periode', $this->countPeriod('approval_requests', 'updated_at', fn (Builder $q) => $q->where('status', 'rejected')), 'number', 'alert'),
        ]);
    }

    private function marketingStats(): array
    {
        return $this->section('Marketing & Penjualan', 'marketing', [
            $this->stat('Prospek Periode', $this->countPeriod('costumers', 'created_at'), 'number', 'user-plus'),
            $this->stat('SPR Periode', $this->countPeriod('sprs', 'tanggal_spr'), 'number', 'file'),
            $this->stat('Nilai SPR Periode', $this->sumPeriod('sprs', 'harga_jual', 'tanggal_spr'), 'currency', 'wallet'),
            $this->stat('Pembayaran Masuk Periode', $this->sumPeriod('customer_receipts', 'amount', 'payment_date'), 'currency', 'wallet'),
            $this->stat('Unit Terjual', $this->count('detail_rumahs', fn (Builder $q) => $q->whereIn('status_penjualan', ['terjual', 'sold'])), 'number', 'home'),
            $this->stat('Pengajuan KPR Aktif', $this->count('kpr_submissions', fn (Builder $q) => $q->whereNotIn('status', ['selesai', 'ditolak', 'akad'])), 'number', 'file'),
        ]);
    }

    private function warehouseStats(): array
    {
        return $this->section('Gudang & Logistik', 'warehouse', [
            $this->stat('Total Stok Lokasi', $this->sum('site_material_stocks', 'qty_available'), 'decimal', 'warehouse'),
            $this->stat('Permintaan Menunggu', $this->count('material_requests', fn (Builder $q) => $q->whereNotIn('status', ['selesai', 'ditolak', 'approved'])), 'number', 'clock'),
            $this->stat('Pembelian Periode', $this->countPeriod('material_purchases', 'tanggal'), 'number', 'cart'),
            $this->stat('Nilai Pembelian Periode', $this->sumPeriod('material_purchases', 'total_nominal', 'tanggal'), 'currency', 'wallet'),
            $this->stat('Pemakaian Periode', $this->countPeriod('material_usages', 'tanggal'), 'number', 'boxes'),
            $this->stat('Stok Kosong', $this->count('site_material_stocks', fn (Builder $q) => $q->where('qty_available', '<=', 0)), 'number', 'alert'),
        ]);
    }

    private function assetStats(User $user): array
    {
        $stats = [];
        if ($user->can('company-inventory.view')) {
            $stats = [
                $this->stat('Barang Inventaris', $this->count('inventory_items'), 'number', 'boxes'),
                $this->stat('Stok Tersedia', $this->sum('inventory_items', 'available_stock'), 'number', 'check'),
                $this->stat('Sedang Dipinjam', $this->sum('inventory_items', 'borrowed_stock'), 'number', 'clock'),
                $this->stat('Rusak / Hilang', $this->sum('inventory_items', 'damaged_stock') + $this->sum('inventory_items', 'lost_stock'), 'number', 'alert'),
                $this->stat('Opname Belum Verifikasi', $this->count('inventory_stock_opnames', fn (Builder $q) => $q->where('status', 'draft')), 'number', 'clock'),
            ];
        }
        if ($user->can('heavy-equipment.view')) {
            $stats = [...$stats,
                $this->stat('Total Alat Berat', $this->count('heavy_equipments'), 'number', 'hard-hat'),
                $this->stat('Alat Digunakan', $this->count('heavy_equipments', fn (Builder $q) => $q->where('status', 'in_use')), 'number', 'activity'),
                $this->stat('Alat Servis / Rusak', $this->count('heavy_equipments', fn (Builder $q) => $q->whereIn('status', ['service', 'damaged'])), 'number', 'wrench'),
                $this->stat('Biaya BBM Periode', $this->sumPeriod('heavy_equipment_fuelings', 'total_cost', 'date'), 'currency', 'wallet'),
                $this->stat('Biaya Maintenance Periode', $this->sumPeriod('heavy_equipment_maintenances', 'cost', 'date'), 'currency', 'wrench'),
            ];
        }

        return $this->section('Inventaris & Alat Berat', 'assets', $stats);
    }

    private function financeStats(): array
    {
        $income = $this->financeSum('pemasukan');
        $expense = $this->financeSum('pengeluaran');

        return $this->section('Keuangan', 'finance', [
            $this->stat('Pemasukan Periode', $income, 'currency', 'trending'),
            $this->stat('Pengeluaran Periode', $expense, 'currency', 'wallet'),
            $this->stat('Arus Bersih Periode', $income - $expense, 'currency', 'activity'),
            $this->stat('Saldo Kas Kecil', $this->sum('petty_cash_accounts', 'balance'), 'currency', 'wallet'),
            $this->stat('Pembayaran Customer', $this->sumPeriod('customer_receipts', 'amount', 'payment_date'), 'currency', 'trending'),
        ]);
    }

    private function inventoryChart(): array
    {
        return ['title' => 'Komposisi Stok Inventaris', 'type' => 'donut', 'unit' => 'Unit', 'labels' => ['Tersedia', 'Dipinjam', 'Rusak', 'Hilang'], 'datasets' => [[
            'label' => 'Inventaris', 'color' => '#c8962e', 'colors' => ['#16a34a', '#2563eb', '#dc2626', '#64748b'],
            'data' => [$this->sum('inventory_items', 'available_stock'), $this->sum('inventory_items', 'borrowed_stock'), $this->sum('inventory_items', 'damaged_stock'), $this->sum('inventory_items', 'lost_stock')],
        ]]];
    }

    private function financeChart(): array
    {
        $income = array_fill(0, count($this->timeline), 0.0);
        $expense = array_fill(0, count($this->timeline), 0.0);
        if (SchemaMetadata::hasTable('transaksi_keuangans') && SchemaMetadata::hasTable('tipe_posts')) {
            $rows = $this->query('transaksi_keuangans as t')->join('tipe_posts as p', 'p.id', '=', 't.tipe_post_id')
                ->where('t.status', 'posted')
                ->whereBetween('t.tanggal', [$this->timeline[0]['start'], $this->timeline[array_key_last($this->timeline)]['end']])->get(['t.tanggal', 't.nominal', 'p.jenis']);
            foreach ($rows as $row) {
                $index = $this->timelineIndex($row->tanggal);
                if ($index === null) {
                    continue;
                } if ($row->jenis === 'pemasukan') {
                    $income[$index] += (float) $row->nominal;
                } else {
                    $expense[$index] += (float) $row->nominal;
                }
            }
        }

        return ['title' => 'Cash In vs Cash Out · '.$this->periodLabel, 'type' => 'bar', 'unit' => 'Rp', 'labels' => array_column($this->timeline, 'label'), 'datasets' => [
            ['label' => 'Pemasukan', 'data' => $income, 'color' => '#16a34a'], ['label' => 'Pengeluaran', 'data' => $expense, 'color' => '#dc2626'],
        ]];
    }

    private function activityChart(string $title, array $series): array
    {
        $datasets = [];
        foreach ($series as $item) {
            $data = array_fill(0, count($this->timeline), 0);
            if (SchemaMetadata::hasTable($item['table']) && SchemaMetadata::hasColumn($item['table'], $item['date'])) {
                $rows = $this->query($item['table'])->whereBetween($item['date'], [$this->timeline[0]['start'], $this->timeline[array_key_last($this->timeline)]['end']])->pluck($item['date']);
                foreach ($rows as $date) {
                    $index = $this->timelineIndex($date);
                    if ($index !== null) {
                        $data[$index]++;
                    }
                }
            }
            $datasets[] = ['label' => $item['label'], 'data' => $data, 'color' => $item['color']];
        }

        return ['title' => $title.' · '.$this->periodLabel, 'type' => 'bar', 'unit' => 'Data', 'labels' => array_column($this->timeline, 'label'), 'datasets' => $datasets];
    }

    private function progressChart(): array
    {
        $data = array_fill(0, count($this->timeline), 0.0);
        $groups = array_fill(0, count($this->timeline), []);
        if (SchemaMetadata::hasTable('progress_pembangunans')) {
            $rows = $this->query('progress_pembangunans')->whereBetween('tanggal', [$this->timeline[0]['start'], $this->timeline[array_key_last($this->timeline)]['end']])->get(['tanggal', 'persentase_total', 'persentase']);
            foreach ($rows as $row) {
                $index = $this->timelineIndex($row->tanggal);
                if ($index !== null) {
                    $groups[$index][] = (float) ($row->persentase_total ?: $row->persentase);
                }
            }
            foreach ($groups as $index => $values) {
                $data[$index] = $values ? round(array_sum($values) / count($values), 2) : 0;
            }
        }

        return ['title' => 'Rata-rata Progress Pembangunan · '.$this->periodLabel, 'type' => 'bar', 'unit' => '%', 'labels' => array_column($this->timeline, 'label'), 'datasets' => [['label' => 'Progress', 'data' => $data, 'color' => '#c8962e']]];
    }

    private function distributionChart(string $title, string $table, string $column, string $unit): array
    {
        if (! SchemaMetadata::hasTable($table) || ! SchemaMetadata::hasColumn($table, $column)) {
            return ['title' => $title, 'type' => 'donut', 'unit' => $unit, 'labels' => [], 'datasets' => []];
        }
        $rows = $this->query($table)->select($column, DB::raw('count(*) as total'))->groupBy($column)->get();

        return ['title' => $title, 'type' => 'donut', 'unit' => $unit, 'labels' => $rows->map(fn ($r) => $this->label($r->{$column} ?: 'belum_diatur'))->all(), 'datasets' => [['label' => $unit, 'data' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(), 'colors' => ['#c8962e', '#2563eb', '#16a34a', '#dc2626', '#7c3aed', '#64748b']]]];
    }

    private function financeSum(string $type): float
    {
        if (! SchemaMetadata::hasTable('transaksi_keuangans') || ! SchemaMetadata::hasTable('tipe_posts')) {
            return 0;
        }

        return (float) $this->query('transaksi_keuangans as t')->join('tipe_posts as p', 'p.id', '=', 't.tipe_post_id')->where('t.status', 'posted')->where('p.jenis', $type)->whereBetween('t.tanggal', [$this->periodStart, $this->periodEnd])->sum('t.nominal');
    }

    private function section(string $title, string $key, array $stats): array
    {
        return compact('title', 'key', 'stats');
    }

    private function stat(string $label, mixed $value, string $format, string $icon): array
    {
        return compact('label', 'value', 'format', 'icon');
    }

    private function allowed(User $user, array $permissions): bool
    {
        return collect($permissions)->contains(fn ($permission) => $user->can($permission));
    }

    private function shortcuts(User $user, array $items): array
    {
        return collect($items)->filter(fn ($i) => $user->can($i[0]))->map(fn ($i) => ['label' => $i[1], 'href' => $i[2]])->values()->all();
    }

    private function label(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }

    private function query(string $table): Builder
    {
        $baseTable = str_contains($table, ' as ') ? explode(' as ', $table)[0] : $table;
        $alias = str_contains($table, ' as ') ? explode(' as ', $table)[1] : null;
        $query = DB::table($table);
        $prefix = $alias ?: $baseTable;
        if (SchemaMetadata::hasColumn($baseTable, 'deleted_at')) {
            $query->whereNull($prefix.'.deleted_at');
        }
        if (SchemaMetadata::hasColumn($baseTable, 'record_status')) {
            $query->where($prefix.'.record_status', 'locked');
        } elseif (SchemaMetadata::hasColumn($baseTable, 'status')) {
            $query->where($prefix.'.status', '!=', 'draft');
        }
        if ($this->perumahanId && $baseTable === 'perumahans') {
            $query->where($prefix.'.id', $this->perumahanId);
        }
        if ($this->perumahanId && SchemaMetadata::hasColumn($baseTable, 'perumahan_id')) {
            $query->where($prefix.'.perumahan_id', $this->perumahanId);
        }

        return $query;
    }

    private function count(string $table, ?callable $callback = null): int
    {
        if (! SchemaMetadata::hasTable($table)) {
            return 0;
        }$q = $this->query($table);
        if ($callback) {
            $callback($q);
        }

        return $q->count();
    }

    private function sum(string $table, string $column): float
    {
        if (! SchemaMetadata::hasTable($table) || ! SchemaMetadata::hasColumn($table, $column)) {
            return 0;
        }

        return (float) $this->query($table)->sum($column);
    }

    private function sumFiltered(string $table, string $column, callable $callback): float
    {
        if (! SchemaMetadata::hasTable($table) || ! SchemaMetadata::hasColumn($table, $column)) {
            return 0;
        }$q = $this->query($table);
        $callback($q);

        return (float) $q->sum($column);
    }

    private function average(string $table, string $column): float
    {
        if (! SchemaMetadata::hasTable($table) || ! SchemaMetadata::hasColumn($table, $column)) {
            return 0;
        }

        return round((float) $this->query($table)->avg($column), 2);
    }

    private function countPeriod(string $table, string $column, ?callable $callback = null): int
    {
        if (! SchemaMetadata::hasTable($table) || ! SchemaMetadata::hasColumn($table, $column)) {
            return 0;
        }

        $query = $this->query($table)->whereBetween($column, [$this->periodStart, $this->periodEnd]);
        if ($callback) {
            $callback($query);
        }

        return $query->count();
    }

    private function sumPeriod(string $table, string $sumColumn, string $dateColumn): float
    {
        if (! SchemaMetadata::hasTable($table) || ! SchemaMetadata::hasColumn($table, $sumColumn) || ! SchemaMetadata::hasColumn($table, $dateColumn)) {
            return 0;
        }

        return (float) $this->query($table)->whereBetween($dateColumn, [$this->periodStart, $this->periodEnd])->sum($sumColumn);
    }

    private function configurePeriod(string $period, ?string $value): void
    {
        $this->period = in_array($period, ['day', 'month', 'year'], true) ? $period : 'month';
        try {
            $selected = match ($this->period) {
                'day' => Carbon::createFromFormat('Y-m-d', $value ?: now()->format('Y-m-d')),'year' => Carbon::createFromFormat('Y', $value ?: now()->format('Y')),'month' => Carbon::createFromFormat('Y-m', $value ?: now()->format('Y-m'))
            };
        } catch (\Throwable) {
            $selected = now();
        }
        if ($this->period === 'day') {
            $this->periodStart = $selected->copy()->startOfDay();
            $this->periodEnd = $selected->copy()->endOfDay();
            $this->periodValue = $selected->format('Y-m-d');
            $this->periodLabel = $selected->translatedFormat('d F Y');
            $this->timeline = collect(range(6, 0))->map(fn ($n) => $selected->copy()->subDays($n))->map(fn (Carbon $d) => ['label' => $d->format('d M'), 'start' => $d->copy()->startOfDay(), 'end' => $d->copy()->endOfDay()])->all();
        } elseif ($this->period === 'year') {
            $this->periodStart = $selected->copy()->startOfYear();
            $this->periodEnd = $selected->copy()->endOfYear();
            $this->periodValue = $selected->format('Y');
            $this->periodLabel = 'Tahun '.$selected->format('Y');
            $this->timeline = collect(range(1, 12))->map(fn ($m) => $selected->copy()->month($m))->map(fn (Carbon $d) => ['label' => $d->translatedFormat('M'), 'start' => $d->copy()->startOfMonth(), 'end' => $d->copy()->endOfMonth()])->all();
        } else {
            $this->periodStart = $selected->copy()->startOfMonth();
            $this->periodEnd = $selected->copy()->endOfMonth();
            $this->periodValue = $selected->format('Y-m');
            $this->periodLabel = $selected->translatedFormat('F Y');
            $this->timeline = collect(range(1, 5))->map(function ($week) use ($selected) {
                $start = $selected->copy()->startOfMonth()->addDays(($week - 1) * 7);
                $end = $start->copy()->addDays(6)->min($selected->copy()->endOfMonth());

                return ['label' => 'M'.$week, 'start' => $start->startOfDay(), 'end' => $end->endOfDay()];
            })->filter(fn ($row) => $row['start']->lte($selected->copy()->endOfMonth()))->values()->all();
        }
    }

    private function timelineIndex(mixed $date): ?int
    {
        if (! $date) {
            return null;
        }$date = Carbon::parse($date);
        foreach ($this->timeline as $index => $point) {
            if ($date->betweenIncluded($point['start'], $point['end'])) {
                return $index;
            }
        }

        return null;
    }
}
