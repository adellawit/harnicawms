<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('distribution.replenishment_orders')) {
            return;
        }

        Schema::table('distribution.replenishment_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('distribution.replenishment_orders', 'invoice_number')) {
                $table->string('invoice_number', 80)->nullable()->after('price_list_id');
            }
            if (! Schema::hasColumn('distribution.replenishment_orders', 'payment_reference')) {
                $table->string('payment_reference', 120)->nullable()->after('invoice_number');
            }
            if (! Schema::hasColumn('distribution.replenishment_orders', 'payment_status')) {
                $table->string('payment_status', 30)->default('unpaid')->after('payment_reference');
            }
            if (! Schema::hasColumn('distribution.replenishment_orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_status');
            }
        });

        if (Schema::hasTable('partner.agents')) {
            $this->backfillLegacyBranchAgents();
            $this->dropForeignIfExists('distribution.replenishment_orders', 'replenishment_orders_agent_id_foreign');
            $this->dropForeignIfExists('distribution.replenishment_orders', 'distribution_replenishment_orders_agent_id_foreign');
            $this->dropForeignIfExists('distribution.returns', 'returns_agent_id_foreign');
            $this->dropForeignIfExists('distribution.returns', 'distribution_returns_agent_id_foreign');

            Schema::table('distribution.replenishment_orders', function (Blueprint $table) {
                $table->foreign('agent_id')
                    ->references('id')
                    ->on('partner.agents')
                    ->onDelete('cascade');
            });

            if (Schema::hasTable('distribution.returns')) {
                Schema::table('distribution.returns', function (Blueprint $table) {
                    $table->foreign('agent_id')
                        ->references('id')
                        ->on('partner.agents')
                        ->onDelete('cascade');
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('distribution.replenishment_orders')) {
            return;
        }

        $this->dropForeignIfExists('distribution.replenishment_orders', 'replenishment_orders_agent_id_foreign');
        $this->dropForeignIfExists('distribution.replenishment_orders', 'distribution_replenishment_orders_agent_id_foreign');
        $this->dropForeignIfExists('distribution.returns', 'returns_agent_id_foreign');
        $this->dropForeignIfExists('distribution.returns', 'distribution_returns_agent_id_foreign');

        Schema::table('distribution.replenishment_orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'payment_reference', 'payment_status', 'paid_at']);
        });

        Schema::table('distribution.replenishment_orders', function (Blueprint $table) {
            $table->foreign('agent_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('cascade');
        });

        if (Schema::hasTable('distribution.returns')) {
            Schema::table('distribution.returns', function (Blueprint $table) {
                $table->foreign('agent_id')
                    ->references('id')
                    ->on('master_data.business_units')
                    ->onDelete('cascade');
            });
        }
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $table, $constraint));
    }

    private function backfillLegacyBranchAgents(): void
    {
        DB::statement("
            INSERT INTO partner.agents (
                id, company_id, default_warehouse_id, code, name, email, phone, address, city, province, postal_code,
                status, approval_status, approved_at, notes, created_at, updated_at
            )
            SELECT DISTINCT
                bu.id,
                bu.parent_id,
                (
                    SELECT w.id
                    FROM master_data.warehouses w
                    WHERE w.branch_id = bu.id
                      AND w.deleted_at IS NULL
                    ORDER BY w.is_default DESC, w.created_at
                    LIMIT 1
                ),
                COALESCE(NULLIF(bu.code, ''), 'AG-' || substr(bu.id::text, 1, 8)),
                bu.name,
                bu.email,
                bu.phone,
                bu.address,
                bu.city,
                bu.province,
                bu.postal_code,
                'active',
                'approved',
                now(),
                'Backfilled from legacy BRANCH agent during EPIC 12 migration.',
                now(),
                now()
            FROM master_data.business_units bu
            WHERE bu.type_code = 'BRANCH'
              AND bu.parent_id IS NOT NULL
              AND bu.deleted_at IS NULL
              AND (
                  EXISTS (SELECT 1 FROM distribution.replenishment_orders ro WHERE ro.agent_id = bu.id)
                  OR EXISTS (SELECT 1 FROM distribution.returns r WHERE r.agent_id = bu.id)
              )
              AND NOT EXISTS (SELECT 1 FROM partner.agents a WHERE a.id = bu.id)
        ");

        DB::statement("
            UPDATE master_data.warehouses w
            SET owner_type = 'AGENT',
                owner_id = w.branch_id
            WHERE w.branch_id IN (
                SELECT id FROM partner.agents
            )
              AND w.deleted_at IS NULL
        ");
    }
};
