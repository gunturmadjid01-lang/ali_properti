<?php

namespace Database\Seeders;

use App\Models\CabangPerusahaan;
use App\Models\Perumahan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $cabang = CabangPerusahaan::query()->first();
        $perumahanIds = Perumahan::query()->pluck('id')->all();

        $users = [[
            'email' => 'admin@ptali.com',
            'name' => 'Super Administrator',
            'phone' => '081100000001',
            'role' => 'super_admin',
        ]];

        foreach ($users as $data) {
            $user = User::query()
                ->where('email', $data['email'])
                ->first();

            if ($user) {
                $user->fill([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'kantor_cabang_id' => $cabang?->id,
                    'avatar' => null,
                    'password' => Hash::make('password'),
                ])->save();

            } else {
                $user = User::create([
                    'email' => $data['email'],
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'kantor_cabang_id' => $cabang?->id,
                    'avatar' => null,
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
