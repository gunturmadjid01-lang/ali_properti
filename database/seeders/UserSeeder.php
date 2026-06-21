<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\CabangPerusahaan;
use App\Models\Perumahan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $cabang = CabangPerusahaan::query()->first();
        $perumahanIds = Perumahan::query()->pluck('id')->all();

        $users = [
            [
                'email' => 'owner@ptali.com',
                'name' => 'Owner',
                'phone' => '081100000001',
                'roles' => ['owner', 'super_admin'],
            ],
            [
                'email' => 'admin@ptali.com',
                'name' => 'Administrator',
                'phone' => '081100000002',
                'role' => 'admin',
            ],
            [
                'email' => 'keuangan@ptali.com',
                'name' => 'Admin Keuangan',
                'phone' => '081100000003',
                'role' => 'keuangan',
            ],
            [
                'email' => 'marketing@ptali.com',
                'name' => 'Tim Marketing',
                'phone' => '081100000004',
                'role' => 'marketing',
            ],
            [
                'email' => 'teknik@ptali.com',
                'name' => 'Tim Teknik',
                'phone' => '081100000005',
                'role' => 'teknik',
            ],
            [
                'email' => 'manager@ptali.com',
                'name' => 'Manager Proyek',
                'phone' => '081100000006',
                'role' => 'manajer_pimpro',
            ],
            [
                'email' => 'gudang@ptali.com',
                'name' => 'Gudang',
                'phone' => '081100000007',
                'role' => 'user_area_gudang',
            ],
            [
                'email' => 'legal@ptali.com',
                'name' => 'Legal',
                'phone' => '081100000008',
                'role' => 'bag_legal',
            ],
            [
                'email' => 'kpr@ptali.com',
                'name' => 'Admin KPR',
                'phone' => '081100000009',
                'role' => 'admin_kpr',
            ],
            [
                'email' => 'konsumen@ptali.com',
                'name' => 'Admin Konsumen',
                'phone' => '081100000010',
                'role' => 'admin_konsumen',
            ],
            [
                'email' => 'pengawas@ptali.com',
                'name' => 'Pengawas',
                'phone' => '081100000011',
                'role' => 'pengawas',
            ],
            [
                'email' => 'supervisor@ptali.com',
                'name' => 'Supervisor Marketing',
                'phone' => '081100000012',
                'role' => 'supervisor_marketing',
            ],
            [
                'email' => 'area-marketing@ptali.com',
                'name' => 'Area Marketing',
                'phone' => '081100000013',
                'role' => 'area_marketing',
            ],
        ];

        foreach ($users as $data) {
            $user = User::query()
                ->where('email', $data['email'])
                ->first();

            if ($user) {
                $user->fill([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'kantor_cabang_id' => $cabang?->id,
                    'avatar' => 'logo.png',
                    'password' => Hash::make('password'),
                ])->save();

            } else {
                $user = User::create([
                    'email' => $data['email'],
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'kantor_cabang_id' => $cabang?->id,
                    'avatar' => 'logo.png',
                    'password' => Hash::make('password'),
                ]);
            }

            if (method_exists($user, 'syncRoles')) {
                $user->syncRoles($data['roles'] ?? [$data['role']]);
            }

            $user->perumahans()->sync($perumahanIds);
        }
    }
}
