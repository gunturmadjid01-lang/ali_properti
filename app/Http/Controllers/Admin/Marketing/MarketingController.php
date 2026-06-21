<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Costumer;
use App\Models\DokumenCostumer;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\ProgressPembangunan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketingController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render('marketing', $request);
    }

    public function show(Request $request, string $slug): Response
    {
        abort_unless(in_array($slug, array_keys($this->sections()), true), 404);

        return $this->render($slug, $request);
    }

    protected function render(string $slug, Request $request): Response
    {
        $section = $this->sections()[$slug] ?? $this->sections()['marketing'];
        $summary = $this->summary($request);

        return Inertia::render('Admin/Marketing/Index', [
            'title' => $section['title'],
            'description' => $section['description'],
            'slug' => $slug,
            'roles' => $request->user()?->loadMissing('roles')?->roles?->pluck('name')->values()->all() ?? [],
            'summary' => $summary,
            'points' => $section['points'],
            'menus' => $section['menus'],
            'featured' => $section['featured'] ?? [],
            'customers' => $slug === 'marketing' || $slug === 'konsumen' || $slug === 'calon-konsumen'
                ? $this->customerRows($request)
                : [],
            'progressRows' => $slug === 'marketing' || $slug === 'operasional' || $slug === 'laporan'
                ? $this->progressRows()
                : [],
            'quickActions' => $section['quickActions'] ?? [],
        ]);
    }

    protected function summary(Request $request): array
    {
        $customerQuery = Costumer::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('created_by', $request->user()?->id));

        return [
            'total_customers' => (clone $customerQuery)->count(),
            'high_prospects' => 0,
            'documents' => DokumenCostumer::query()->count(),
            'recent_progress' => ProgressPembangunan::query()->whereDate('tanggal', '>=', now()->subDays(30))->count(),
            'active_projects' => Perumahan::query()->where('status', 'aktif')->count(),
            'active_units' => DetailRumah::query()->where('status', 'aktif')->count(),
        ];
    }

    protected function customerRows(Request $request)
    {
        return Costumer::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('created_by', $request->user()?->id))
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Costumer $customer) => [
                'id' => $customer->id,
                'nama' => $customer->nama,
                'no_identitas' => $customer->no_identitas,
                'telepon' => $customer->telepon ?? '-',
                'pekerjaan' => $customer->pekerjaan ?? '-',
                'penghasilan' => number_format((float) ($customer->penghasilan ?? 0), 0, ',', '.'),
            ]);
    }

    protected function progressRows()
    {
        return ProgressPembangunan::query()
            ->with(['detailRumah.perumahan', 'user:id,name'])
            ->latest('tanggal')
            ->limit(5)
            ->get()
            ->map(fn (ProgressPembangunan $progress) => [
                'id' => $progress->id,
                'tanggal' => optional($progress->tanggal)->format('Y-m-d'),
                'proyek' => $progress->detailRumah?->perumahan?->nama_perusahaan ?? '-',
                'unit' => $progress->detailRumah ? "{$progress->detailRumah->kode_nlok} {$progress->detailRumah->nomor_rumah}" : '-',
                'tahapan' => $progress->tahapan,
                'persentase' => $progress->persentase,
                'user' => $progress->user?->name ?? '-',
            ]);
    }

    protected function sections(): array
    {
        return [
            'marketing' => [
                'title' => 'Area Marketing',
                'description' => 'Workspace untuk calon konsumen, follow up, SPR, dan laporan penjualan.',
                'points' => [
                    'Kelola calon konsumen dan status prospek',
                    'Lihat jejak follow up dan progress pekerjaan',
                    'Akses SPR dan operasional marketing',
                    'Ringkasan laporan penjualan dan promosi',
                ],
                'menus' => [
                    ['label' => 'Calon Konsumen', 'description' => 'Masuk ke data calon pembeli yang bisa diinput dan diupdate.', 'href' => '/admin/marketing/calon-konsumen'],
                    ['label' => 'Jejak Follow Up', 'description' => 'Pantau interaksi marketing dengan prospek.', 'href' => '/admin/marketing/jejak-follow-up'],
                    ['label' => 'Konsumen', 'description' => 'Lihat data konsumen hasil follow up.', 'href' => '/admin/marketing/konsumen'],
                    ['label' => 'Transaksi SPR', 'description' => 'Kelola proses Surat Pemesanan Rumah.', 'href' => '/admin/marketing/spr'],
                    ['label' => 'Transaksi Cash', 'description' => 'Kelola pembelian cash dan pembayarannya.', 'href' => '/admin/marketing/transaksi-pembelian/cash'],
                    ['label' => 'Operasional', 'description' => 'Pantau aktivitas promosi dan penjualan.', 'href' => '/admin/marketing/operasional'],
                    ['label' => 'Laporan', 'description' => 'Buka laporan lead, prospek, dan performa marketing.', 'href' => '/admin/marketing/laporan'],
                ],
                'featured' => [
                    ['label' => 'Kelola prospek', 'value' => 'Calon konsumen tersimpan rapi untuk follow up harian.'],
                    ['label' => 'Pantau progres', 'value' => 'Progress pembangunan unit dapat dijadikan bahan follow up.'],
                    ['label' => 'Laporan cepat', 'value' => 'Semua ringkasan marketing tampil di satu halaman.'],
                ],
                'quickActions' => [
                    ['label' => 'Buka Calon Konsumen', 'href' => '/admin/marketing/calon-konsumen'],
                    ['label' => 'Lihat Jejak Follow Up', 'href' => '/admin/marketing/jejak-follow-up'],
                ],
            ],
            'calon-konsumen' => [
                'title' => 'Calon Konsumen',
                'description' => 'Input dan kelola data calon pembeli, status prospek, dan kemampuan bayar.',
                'points' => [
                    'Input data prospek baru',
                    'Update status follow up',
                    'Lacak dokumen customer',
                    'Siapkan daftar calon booking',
                ],
                'menus' => [
                    ['label' => 'Tambah Data', 'description' => 'Gunakan tombol tambah untuk mencatat prospek baru.', 'href' => '/admin/marketing/calon-konsumen'],
                    ['label' => 'Jejak Follow Up', 'description' => 'Lihat tindak lanjut setelah data masuk.', 'href' => '/admin/marketing/jejak-follow-up'],
                ],
                'featured' => [
                    ['label' => 'Total Customer', 'value' => (string) Costumer::query()->when($this->shouldScopeToCurrentMarketing(request()), fn (Builder $query) => $query->where('created_by', request()->user()?->id))->count()],
                    ['label' => 'Dokumen Customer', 'value' => (string) DokumenCostumer::query()->count()],
                ],
            ],
            'jejak-follow-up' => [
                'title' => 'Jejak Follow Up',
                'description' => 'Pantau aktivitas follow up, progress pembangunan, dan histori interaksi prospek.',
                'points' => [
                    'Pantau interaksi terakhir',
                    'Sinkronkan dengan progress bangunan',
                    'Gunakan sebagai bahan closing',
                    'Siapkan follow up berikutnya',
                ],
                'menus' => [
                    ['label' => 'Calon Konsumen', 'description' => 'Kembali ke data prospek utama.', 'href' => '/admin/marketing/calon-konsumen'],
                    ['label' => 'Laporan', 'description' => 'Lihat ringkasan follow up dan hasilnya.', 'href' => '/admin/marketing/laporan'],
                ],
            ],
            'konsumen' => [
                'title' => 'Konsumen',
                'description' => 'Daftar konsumen hasil follow up dan proses booking unit.',
                'points' => [
                    'Customer yang sudah lanjut',
                    'Booking dan tanda jadi',
                    'Dokumen customer',
                    'Status siap transaksi',
                ],
                'menus' => [
                    ['label' => 'Calon Konsumen', 'description' => 'Lihat sumber data prospek awal.', 'href' => '/admin/marketing/calon-konsumen'],
                    ['label' => 'SPR', 'description' => 'Buka transaksi pemesanan rumah.', 'href' => '/admin/marketing/spr'],
                ],
            ],
            'spr' => [
                'title' => 'Transaksi SPR',
                'description' => 'Alur Surat Pemesanan Rumah dengan opsi cash, bertahap, KPR bank, atau KPR developer.',
                'points' => [
                    'Pilih skema pembayaran',
                    'Pantau status booking',
                    'Dokumen pembayaran',
                    'Keterhubungan dengan unit rumah',
                ],
                'menus' => [
                    ['label' => 'Transaksi Cash', 'description' => 'Kelola pembelian cash yang berasal dari SPR disetujui.', 'href' => '/admin/marketing/transaksi-pembelian/cash'],
                    ['label' => 'Pembayaran SPR', 'description' => 'Bayar booking fee dan uang muka SPR.', 'href' => '/admin/marketing/pembayaran-spr'],
                    ['label' => 'Konsumen', 'description' => 'Lanjutkan transaksi dari data calon konsumen.', 'href' => '/admin/marketing/konsumen'],
                    ['label' => 'Laporan SPR', 'description' => 'Lihat ringkasan transaksi SPR.', 'href' => '/admin/marketing/laporan'],
                ],
            ],
            'transaksi-pembelian' => [
                'title' => 'Transaksi Pembelian',
                'description' => 'Pilih alur pembelian cash atau KPR sesuai SPR yang dibuat marketing.',
                'points' => [
                    'Cash untuk pembelian langsung',
                    'KPR untuk pembelian melalui bank',
                    'Pembayaran cash otomatis masuk kas',
                    'KPR tetap bisa dipantau sebagai proses terpisah',
                ],
                'menus' => [
                    ['label' => 'Cash', 'description' => 'Buka transaksi pembelian cash.', 'href' => '/admin/marketing/transaksi-pembelian/cash'],
                    ['label' => 'KPR', 'description' => 'Buka daftar pengajuan KPR.', 'href' => '/admin/kpr'],
                ],
            ],
            'operasional' => [
                'title' => 'Operasional Marketing',
                'description' => 'Panel aktivitas promosi, progress bangunan, dan koordinasi harian tim marketing.',
                'points' => [
                    'Brosur dan konten promosi',
                    'Update progress proyek',
                    'Koordinasi survei lokasi',
                    'Monitoring respon prospek',
                ],
                'menus' => [
                    ['label' => 'Jejak Follow Up', 'description' => 'Lacak reaksi prospek dari promosi.', 'href' => '/admin/marketing/jejak-follow-up'],
                    ['label' => 'Laporan', 'description' => 'Lihat performa operasional marketing.', 'href' => '/admin/marketing/laporan'],
                ],
            ],
            'laporan' => [
                'title' => 'Laporan Marketing',
                'description' => 'Ringkasan data prospek, customer, follow up, dan progress proyek.',
                'points' => [
                    'Laporan lead',
                    'Laporan follow up',
                    'Laporan SPR',
                    'Laporan promosi',
                ],
                'menus' => [
                    ['label' => 'Calon Konsumen', 'description' => 'Buka daftar prospek terbaru.', 'href' => '/admin/marketing/calon-konsumen'],
                    ['label' => 'Operasional', 'description' => 'Lihat aktivitas marketing.', 'href' => '/admin/marketing/operasional'],
                ],
            ],
        ];
    }

    protected function shouldScopeToCurrentMarketing(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing'])
            && ! $user->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin']);
    }
}
