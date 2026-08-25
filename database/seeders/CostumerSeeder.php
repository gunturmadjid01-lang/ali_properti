<?php

namespace Database\Seeders;

use App\Models\Costumer;
use App\Models\MarketingLeadSource;
use App\Models\Perumahan;
use App\Models\User;
use Illuminate\Database\Seeder;

class CostumerSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn('CostumerSeeder dinonaktifkan: data customer harus berasal dari proses CRM nyata, bukan data contoh.');

        return;

        $perumahanIds = Perumahan::query()->orderBy('id')->pluck('id')->values();

        if ($perumahanIds->isEmpty()) {
            $this->command?->warn('CostumerSeeder dilewati karena belum ada data perumahan.');

            return;
        }

        $sourceIds = MarketingLeadSource::query()
            ->where('status', 'aktif')
            ->orderBy('id')
            ->pluck('id')
            ->values();
        $marketing = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['marketing', 'area_marketing']))
            ->orderBy('id')
            ->first() ?? User::query()->orderBy('id')->first();

        $names = [
            'Andi Saputra', 'Nur Aisyah', 'Muhammad Fajar', 'Siti Rahmawati', 'Rizky Pratama',
            'Dewi Lestari', 'Ahmad Hidayat', 'Nurul Hikmah', 'Ilham Ramadhan', 'Fitri Handayani',
            'Ardiansyah Putra', 'Maya Sari', 'Reza Maulana', 'Nabila Putri', 'Agus Salim',
            'Indah Permatasari', 'Dedi Kurniawan', 'Sri Wahyuni', 'Hendra Gunawan', 'Aulia Rahman',
            'Rudi Hartono', 'Mutmainnah Yusuf', 'Firman Jaya', 'Rina Marlina', 'Syahrul Mubarak',
            'Hasriani Basri', 'Akbar Tanjung', 'Rahmiati Saleh', 'Zulkifli Hasan', 'Nurfadillah Amin',
            'Irfan Kurnia', 'Reski Amelia', 'Junaidi Karim', 'Ayu Puspitasari', 'Wahyu Setiawan',
            'Mirnawati Hamzah', 'Alfian Nugraha', 'Herlina Usman', 'Bambang Irawan', 'Citra Maharani',
        ];
        $femaleIndexes = [1, 3, 5, 7, 9, 11, 13, 15, 17, 19, 21, 23, 25, 27, 29, 31, 33, 35, 37, 39];
        $cities = ['Makassar', 'Gowa', 'Maros', 'Takalar', 'Bone', 'Soppeng', 'Parepare', 'Bulukumba'];
        $jobs = ['Karyawan Swasta', 'Wiraswasta', 'ASN', 'Guru', 'Tenaga Kesehatan', 'Kontraktor', 'Pedagang', 'Konsultan'];
        $companies = ['PT Sulawesi Sejahtera', 'CV Cahaya Timur', 'Pemprov Sulawesi Selatan', 'Yayasan Pendidikan Mandiri', 'Klinik Sehat Utama', 'CV Bina Konstruksi', 'Usaha Mandiri', 'PT Nusantara Konsultan'];
        $statuses = ['lead_baru', 'lead_baru', 'dihubungi', 'dihubungi', 'survey_lokasi', 'negosiasi'];

        foreach ($names as $index => $name) {
            $number = $index + 1;
            $female = in_array($index, $femaleIndexes, true);
            $maritalStatus = $number % 10 === 0 ? 'cerai' : ($number % 3 === 0 ? 'belum menikah' : 'menikah');
            $jobIndex = $index % count($jobs);
            $birthYear = 1978 + ($index % 20);
            $birthMonth = ($index % 12) + 1;
            $birthDay = ($index % 27) + 1;
            $isMarried = $maritalStatus === 'menikah';
            $code = 'CST-SEED-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $customer = Costumer::withTrashed()->firstOrNew(['kode_costumer' => $code]);

            if ($customer->trashed()) {
                $customer->restore();
            }

            $customer->fill([
                'created_by' => $marketing?->id,
                'updated_by' => $marketing?->id,
                'perumahan_id' => $perumahanIds[$index % $perumahanIds->count()],
                'marketing_lead_source_id' => $sourceIds->isNotEmpty() ? $sourceIds[$index % $sourceIds->count()] : null,
                'marketing_campaign_id' => null,
                'status_lead' => $statuses[$index % count($statuses)],
                'nama' => $name,
                'jenis_kelamin' => $female ? 'perempuan' : 'laki-laki',
                'jenis_identitas' => 'ktp',
                'no_identitas' => '7371'.str_pad((string) $number, 12, '0', STR_PAD_LEFT),
                'tanggal_lahir' => sprintf('%04d-%02d-%02d', $birthYear, $birthMonth, $birthDay),
                'tempat_lahir' => $cities[$index % count($cities)],
                'status_perkawinan' => $maritalStatus,
                'alamat' => 'Jl. '.['Sultan Alauddin', 'Perintis Kemerdekaan', 'Hertasning', 'Antang Raya', 'Pettarani', 'Tamalate', 'Daya', 'Barombong'][$index % 8].' No. '.($number + 10),
                'email' => 'customer'.str_pad((string) $number, 2, '0', STR_PAD_LEFT).'@example.test',
                'npwp' => $number % 4 === 0 ? null : '73.'.str_pad((string) $number, 3, '0', STR_PAD_LEFT).'.'.str_pad((string) ($number * 17), 3, '0', STR_PAD_LEFT).'.0-801.000',
                'telepon' => '0813'.str_pad((string) (10000000 + $number), 8, '0', STR_PAD_LEFT),
                'penghasilan' => 4500000 + (($index % 12) * 750000),
                'keterangan' => 'Data contoh calon konsumen ke-'.$number.' untuk pengujian alur marketing dan SPR.',
                'pekerjaan' => $jobs[$jobIndex],
                'nama_perusahaan' => $companies[$jobIndex],
                'alamat_perusahaan' => 'Kawasan Bisnis '.($cities[($index + 2) % count($cities)]).', Sulawesi Selatan',
                'telepon_perusahaan' => '0411'.str_pad((string) (700000 + $number), 6, '0', STR_PAD_LEFT),
                'keterangan_perusahaan' => 'Perusahaan tempat bekerja calon konsumen.',
                'nama_lengkap_pasangan' => $isMarried ? ($female ? 'Muhammad '.explode(' ', $name)[0] : 'Nur '.explode(' ', $name)[0]) : null,
                'jenis_kelamin_pasangan' => $isMarried ? ($female ? 'laki-laki' : 'perempuan') : null,
                'jenis_identitas_pasangan' => $isMarried ? 'ktp' : null,
                'no_identitas_pasangan' => $isMarried ? '7372'.str_pad((string) $number, 12, '0', STR_PAD_LEFT) : null,
                'tanggal_lahir_pasangan' => $isMarried ? sprintf('%04d-%02d-%02d', $birthYear + 1, (($birthMonth + 4) % 12) + 1, (($birthDay + 6) % 27) + 1) : null,
                'tempat_lahir_pasangan' => $isMarried ? $cities[($index + 1) % count($cities)] : null,
            ]);
            $customer->save();
        }

        $this->command?->info('40 data calon konsumen berhasil disiapkan.');
    }
}
