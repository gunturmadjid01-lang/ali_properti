<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetPropertySalesData extends Command
{
    protected $signature = 'property-sales:reset {--force : Jalankan penghapusan permanen}';

    protected $description = 'Bersihkan data proyek dari perumahan sampai SPR tanpa menghapus customer dan user';

    public function handle(): int
    {
        $tables = $this->dependentTables('perumahans');
        $counts = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

        $this->table(['Tabel', 'Jumlah'], $counts->map(fn ($count, $table) => [$table, $count])->values()->all());
        $this->line('Customer dipertahankan: '.DB::table('costumers')->count());
        $this->line('User dipertahankan: '.DB::table('users')->count());
        $userRoles = DB::table('users')
            ->leftJoin('model_has_roles', fn ($join) => $join->on('model_has_roles.model_id', '=', 'users.id')->where('model_has_roles.model_type', 'App\\Models\\User'))
            ->leftJoin('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->select('users.email', DB::raw("COALESCE(GROUP_CONCAT(roles.name ORDER BY roles.name SEPARATOR ', '), '-') as role_names"))
            ->groupBy('users.id', 'users.email')
            ->orderBy('users.id')
            ->get();
        $this->table(['User', 'Role'], $userRoles->map(fn ($row) => [$row->email ?: '(tanpa email)', $row->role_names])->all());
        $this->line('Permission Reservasi: '.DB::table('permissions')->where('name', 'like', 'housing-reservation.%')->count());
        $this->line('Permission langsung pada User: '.DB::table('model_has_permissions')->where('model_type', 'App\\Models\\User')->count());

        if (! $this->option('force')) {
            $this->warn('Mode pemeriksaan saja. Tambahkan --force untuk menghapus data di atas.');

            return self::SUCCESS;
        }

        DB::table('costumers')->update(['perumahan_id' => null]);
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                DB::table($table)->delete();
                DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
            }

            DB::table('approval_requests')->delete();
            DB::statement('ALTER TABLE `approval_requests` AUTO_INCREMENT = 1');

            if (Schema::hasTable('app_notifications')) {
                DB::table('app_notifications')->delete();
                DB::statement('ALTER TABLE `app_notifications` AUTO_INCREMENT = 1');
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('Data proyek berhasil dibersihkan. Customer, user, role, permission, dan Setting Approval dipertahankan.');

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function dependentTables(string $root): array
    {
        $database = DB::connection()->getDatabaseName();
        $protected = [
            'users', 'costumers', 'roles', 'permissions', 'role_has_permissions',
            'model_has_roles', 'model_has_permissions', 'approval_settings', 'migrations',
            'sessions', 'cache', 'cache_locks', 'password_reset_tokens',
        ];
        $tables = [$root];

        for ($index = 0; $index < count($tables); $index++) {
            $children = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('REFERENCED_TABLE_SCHEMA', $database)
                ->where('REFERENCED_TABLE_NAME', $tables[$index])
                ->whereNotNull('REFERENCED_COLUMN_NAME')
                ->pluck('TABLE_NAME');

            foreach ($children as $child) {
                if (! in_array($child, $protected, true) && ! in_array($child, $tables, true)) {
                    $tables[] = $child;
                }
            }
        }

        return array_reverse($tables);
    }
}
