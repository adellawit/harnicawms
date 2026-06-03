<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'public.provinces',
        'public.cities',
        'public.parameters',
        'public.parameter_details',
        'public.stock_mutation_types',
        'master_data.positions',
        'master_data.divisions',
        'master_data.business_units',
        'master_data.roles',
        'master_data.menus',
        'human_resources.employees',
        'auth.users',
        'auth.iam_accesses',
        'auth.iam_has_accesses',
        'configuration.notifications',
        'operational.reimbursements',
        'operational.reimbursement_details',
    ];

    private array $columns = ['created_by', 'updated_by', 'deleted_by'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            foreach ($this->columns as $column) {
                if ($this->columnExists($table, $column)) {
                    DB::statement("
                        ALTER TABLE {$table}
                        ALTER COLUMN {$column} SET DATA TYPE uuid
                        USING CASE WHEN {$column}::text ~ '^[0-9a-fA-F-]{36}$' THEN {$column}::uuid ELSE NULL END
                    ");
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            foreach ($this->columns as $column) {
                if ($this->columnExists($table, $column)) {
                    DB::statement("
                        ALTER TABLE {$table}
                        ALTER COLUMN {$column} SET DATA TYPE varchar(255)
                        USING {$column}::varchar
                    ");
                }
            }
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $parts = explode('.', $table);
        $schema = $parts[0];
        $tableName = $parts[1];

        return DB::scalar("
            SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = ? AND table_name = ? AND column_name = ?
        ", [$schema, $tableName, $column]) > 0;
    }
};
