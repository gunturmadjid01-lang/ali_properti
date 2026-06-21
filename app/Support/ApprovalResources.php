<?php

namespace App\Support;

use App\Models\CabangPerusahaan;
use App\Models\DokumenLegalitas;
use App\Models\DokumenLegalitasRumah;
use App\Models\MasterBank;
use App\Models\Perumahan;
use App\Models\TipePost;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class ApprovalResources
{
    public static function modules(): array
    {
        return [
            'cabang-perusahaan' => [
                'label' => 'Management Cabang Perusahaan',
                'model' => CabangPerusahaan::class,
            ],
            'perumahan' => [
                'label' => 'Management Perumahan',
                'model' => Perumahan::class,
            ],
            'master-bank' => [
                'label' => 'Management Master Bank',
                'model' => MasterBank::class,
            ],
            'dokumen-legalitas' => [
                'label' => 'Management Dokumen Legalitas',
                'model' => DokumenLegalitas::class,
            ],
            'dokumen-legalitas-rumah' => [
                'label' => 'Management Dokumen Legalitas Rumah',
                'model' => DokumenLegalitasRumah::class,
            ],
            'tipe-post' => [
                'label' => 'Management Tipe Post',
                'model' => TipePost::class,
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
        ];
    }

    public static function actions(): array
    {
        return [
            'create' => 'Tambah Data',
            'update' => 'Ubah Data',
            'delete' => 'Hapus Data',
        ];
    }

    public static function module(string $key): array
    {
        return self::modules()[$key] ?? [];
    }

    public static function modelClass(string $key): string
    {
        return self::module($key)['model'] ?? '';
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
    }
}
