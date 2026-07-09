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
                'roles' => ['owner'],
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
                'email' => 'manager@ptali.com',
                'name' => 'Manager Proyek',
                'phone' => '081100000006',
                'role' => 'manajer_pimpro',
            ],
            [
                'email' => 'pengawas@ptali.com',
                'name' => 'Pengawas',
                'phone' => '081100000011',
                'role' => 'pengawas',
            ],
            [
                'email' => 'gudang@ptali.com',
                'name' => 'Admin Gudang',
                'phone' => '081100000012',
                'role' => 'user_area_gudang',
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
