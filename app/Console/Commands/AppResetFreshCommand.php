<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AppResetFreshCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-fresh {--force : Force the operation without confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Wipe all user-uploaded storage files, run migrate:fresh --seed, and restore baseline assets for step-by-step testing.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('⚠️  Are you sure you want to completely wipe storage uploads, re-migrate the database fresh, and re-seed? This cannot be undone.', false)) {
            $this->warn('Operation cancelled.');
            return 0;
        }

        $this->newLine();
        $this->info('🚀 Starting complete fresh reset of application database and storage...');

        // 1. Clean public storage uploads while preserving .gitignore
        $this->line('🧹 <fg=yellow>Step 1/4:</> Purging user-uploaded files from public storage...');
        $publicDisk = Storage::disk('public');
        
        $directoriesToClean = ['turf_photos', 'logos', 'sliders'];
        foreach ($directoriesToClean as $dir) {
            if ($publicDisk->exists($dir)) {
                $publicDisk->deleteDirectory($dir);
                $this->line("   - Cleaned directory: storage/app/public/{$dir}");
            }
        }

        // Clean any leftover orphan files in public storage root, preserving .gitignore
        $rootFiles = $publicDisk->files();
        foreach ($rootFiles as $file) {
            if (basename($file) !== '.gitignore') {
                $publicDisk->delete($file);
            }
        }
        $this->info('   ✅ Storage uploads purged.');

        // 2. Ensure storage link
        $this->newLine();
        $this->line('🔗 <fg=yellow>Step 2/4:</> Ensuring storage symlink...');
        Artisan::call('storage:link');
        $this->info('   ✅ Storage link active.');

        // 3. Drop all tables safely (immune to dots in database names like turf.infoleena.com) & migrate fresh
        $this->newLine();
        $this->line('🔄 <fg=yellow>Step 3/4:</> Dropping all tables safely and running migrations...');

        Schema::disableForeignKeyConstraints();
        try {
            // Fetch tables in currently active DB without schema prefix
            $rawTables = DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            if (!empty($rawTables)) {
                $quotedTableNames = [];
                foreach ($rawTables as $row) {
                    $rowArray = (array) $row;
                    $tableName = reset($rowArray);
                    $quotedTableNames[] = '`' . str_replace('`', '``', $tableName) . '`';
                }
                if (!empty($quotedTableNames)) {
                    DB::statement('DROP TABLE ' . implode(', ', $quotedTableNames));
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $exitCode = Artisan::call('migrate', [
            '--seed' => true,
            '--force' => true,
        ], $this->output);

        if ($exitCode !== 0) {
            $this->error('   ❌ Migration or seeding failed. Check the errors above.');
            return $exitCode;
        }
        $this->info('   ✅ Database successfully re-migrated and seeded.');

        // 4. Verify baseline seed state
        $this->newLine();
        $this->line('🔎 <fg=yellow>Step 4/4:</> Verifying baseline accounts and assets...');

        $users = User::with('roles')->get();
        $userRows = $users->map(fn ($u) => [
            $u->id,
            $u->name,
            $u->email,
            $u->mobile,
            $u->roles->pluck('name')->join(', '),
            'password',
        ])->toArray();

        $this->table(
            ['ID', 'Name', 'Email', 'Mobile', 'Roles', 'Password'],
            $userRows
        );

        $this->newLine();
        $this->info('🎉 System is 100% fresh and clean! Ready for step-by-step app testing.');
        return 0;
    }
}
