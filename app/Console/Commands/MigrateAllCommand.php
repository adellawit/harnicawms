<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;

class MigrateAllCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:all
                            {--fresh : Drop all tables and re-run all migrations}
                            {--seed : Seed the database after migration}
                            {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations from all folders in the correct order';

    /**
     * Migration paths in correct order based on dependencies.
     *
     * @var array
     */
    protected array $migrationPaths = [
        'database/migrations/public',
        'database/migrations/master_data',
        'database/migrations/human_resources',
        'database/migrations/auth',
        'database/migrations/product',
        'database/migrations/purchase_order',
        'database/migrations/transaction',
        'database/migrations/operational',
        'database/migrations/configuration',
        'database/migrations/customer',
        'database/migrations/manufacturing',
        'database/migrations/distribution',
        'database/migrations/crm',
        'database/migrations/partner',
    ];

    /**
     * Custom schemas to drop when using --fresh
     *
     * @var array
     */
    protected array $customSchemas = [
        'master_data',
        'public',
        'human_resources',
        'auth',
        'product',
        'operational',
        'configuration',
        'customer',
        'transaction',
        'manufacturing',
        'distribution',
        'crm',
        'partner',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isFresh = $this->option('fresh');
        $withSeed = $this->option('seed');
        $force = $this->option('force');

        $this->info('========================================');
        $this->info('   Migration All Folders');
        $this->info('========================================');
        $this->newLine();

        // Step 1: Fresh - Drop all custom schemas first, then migrate
        if ($isFresh) {
            $this->info('Step 1: Dropping all custom schemas and tables...');

            // Drop all custom schemas
            foreach ($this->customSchemas as $schema) {
                DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
            }

            // Also drop default public tables (failed_jobs, migrations, etc)
            $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
            foreach ($tables as $table) {
                Schema::dropIfExists('public.' . $table->tablename);
            }

            $this->info('  All schemas and tables dropped.');

            $this->newLine();
            $this->info('Step 2: Re-creating schemas and running root migrations...');

            // Recreate schemas
            foreach ($this->customSchemas as $schema) {
                DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
            }

            // Run root migrations
            $this->call('migrate', ['--force' => $force]);
        } else {
            $this->info('Step 1: Running root migrations...');
            $this->call('migrate', ['--force' => $force]);
        }

        $this->newLine();

        // Step 3: Run migrations from each folder in order
        $stepStart = $isFresh ? 3 : 2;

        foreach ($this->migrationPaths as $index => $path) {
            $folderName = basename($path);
            $stepNum = $index + $stepStart;

            $this->info("Step {$stepNum}: Running migrations from [{$folderName}] folder...");

            $output = new BufferedOutput();
            // Sub-migrations pakai BufferedOutput — tanpa --force, Laravel menunggu
            // konfirmasi production yang tidak tampil di terminal (terlihat "stuck").
            Artisan::call('migrate', [
                '--path' => $path,
                '--force' => $force || $isFresh || $this->option('no-interaction'),
            ], $output);

            $response = $output->fetch();

            // Check if there are no pending migrations
            if (str_contains($response, 'Nothing to migrate')) {
                $this->comment('  Nothing to migrate.');
            } else {
                // Show output
                $lines = explode("\n", trim($response));
                foreach ($lines as $line) {
                    if (!empty($line) && !str_contains($line, 'INFO')) {
                        $this->line('  ' . $line);
                    }
                }
                $this->info('  Done.');
            }

            $this->newLine();
        }

        // Step 4: Seed if requested
        if ($withSeed) {
            $totalSteps = count($this->migrationPaths) + $stepStart;
            $this->info("Step {$totalSteps}: Seeding database...");
            $this->call('db:seed', [
                '--force' => $force || $isFresh || $this->option('no-interaction'),
            ]);
            $this->newLine();
        }

        $this->info('========================================');
        $this->info('   All migrations completed successfully!');
        $this->info('========================================');

        return self::SUCCESS;
    }
}
