<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_positions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('normalized_name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('job_position_id')->nullable()->after('job_title')->constrained('job_positions')->nullOnDelete();
        });

        DB::table('users')->whereNotNull('job_title')->where('job_title', '!=', '')->orderBy('id')->get(['id', 'job_title'])->each(function (object $user): void {
            $name = Str::squish($user->job_title);
            $normalized = Str::lower($name);
            $positionId = DB::table('job_positions')->where('normalized_name', $normalized)->value('id');

            if (! $positionId) {
                $positionId = DB::table('job_positions')->insertGetId([
                    'name' => $name,
                    'normalized_name' => $normalized,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('users')->where('id', $user->id)->update(['job_position_id' => $positionId]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('job_position_id');
        });
        Schema::dropIfExists('job_positions');
    }
};
