<?php

namespace App\Console\Commands;

use App\Models\MaterialRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExportCurrentDatabaseSnapshot extends Command
{
    protected $signature = 'db:snapshot-seeder {--force : Timpa snapshot yang sudah ada}';

    protected $description = 'Ekspor data database aktif menjadi data CurrentDatabaseSnapshotSeeder.';

    public function handle(): int
    {
        $path = database_path('seeders/data/current_database_snapshot.php');
        if (File::exists($path) && ! $this->option('force')) {
            $this->error('Snapshot sudah ada. Gunakan --force untuk memperbarui.');
            return self::FAILURE;
        }

        $excluded = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'material_requests', 'material_request_details'];
        $tableColumn = 'Tables_in_'.DB::getDatabaseName();
        $tables = collect(DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']))
            ->map(fn ($row) => (array) $row)
            ->map(fn (array $row) => $row[$tableColumn] ?? array_values($row)[0])
            ->reject(fn (string $table) => in_array($table, $excluded, true))
            ->values();

        $snapshot = [];
        foreach ($tables as $table) {
            $query = DB::table($table)->orderBy(DB::raw('1'));
            if ($table === 'approval_requests') {
                $query->where(fn ($builder) => $builder->whereNull('model_type')->orWhere('model_type', '!=', MaterialRequest::class));
            }
            $snapshot[$table] = $query->get()->map(fn ($row) => (array) $row)->all();
            $this->line("{$table}: ".count($snapshot[$table]).' baris');
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, "<?php\n\nreturn ".var_export($snapshot, true).";\n");
        $this->info('Snapshot tersimpan di '.$path);

        return self::SUCCESS;
    }
}
