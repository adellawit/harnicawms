<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const AGENT_ROLE_ID = '2ac6f6a1-7b8c-4d9e-9f10-111213141516';

    public function up(): void
    {
        if (! Schema::hasTable('partner.agents')) {
            return;
        }

        Schema::table('partner.agents', function (Blueprint $table) {
            if (! Schema::hasColumn('partner.agents', 'user_id')) {
                $table->uuid('user_id')->nullable()->after('customer_id');
                $table->unique('user_id');
                $table->foreign('user_id')->references('id')->on('auth.users')->onDelete('set null');
            }
        });

        $this->backfillAgentUsers();
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner.agents') || ! Schema::hasColumn('partner.agents', 'user_id')) {
            return;
        }

        Schema::table('partner.agents', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
        });
    }

    private function backfillAgentUsers(): void
    {
        if (! Schema::hasTable('auth.users') || ! Schema::hasTable('master_data.roles')) {
            return;
        }

        DB::table('master_data.roles')->updateOrInsert(
            ['id' => self::AGENT_ROLE_ID],
            ['name' => 'Agent', 'updated_at' => now(), 'created_at' => now()]
        );

        $hasEmail = Schema::hasColumn('auth.users', 'email');
        $hasPhone = Schema::hasColumn('auth.users', 'phone');

        DB::table('partner.agents')
            ->whereNull('user_id')
            ->orderBy('created_at')
            ->get()
            ->each(function ($agent) use ($hasEmail, $hasPhone) {
                $username = $this->availableUsername($agent);
                $nameParts = preg_split('/\s+/', trim((string) $agent->name), 2, PREG_SPLIT_NO_EMPTY) ?: ['Agent'];
                $userId = (string) Str::uuid();

                $payload = [
                    'id' => $userId,
                    'role_id' => self::AGENT_ROLE_ID,
                    'current_business_unit_id' => $agent->company_id,
                    'first_name' => $nameParts[0],
                    'last_name' => $nameParts[1] ?? $nameParts[0],
                    'username' => $username,
                    'password' => Hash::make('agent12345'),
                    'need_update_password' => true,
                    'url_image' => config('app.url') . '/assets/img/ars/avatar/user-default.jpg',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($hasEmail) {
                    $payload['email'] = $agent->email ?: $username;
                }

                if ($hasPhone) {
                    $payload['phone'] = $agent->phone;
                }

                DB::table('auth.users')->insert($payload);
                DB::table('partner.agents')->where('id', $agent->id)->update([
                    'user_id' => $userId,
                    'updated_at' => now(),
                ]);
            });
    }

    private function availableUsername(object $agent): string
    {
        $base = $agent->email ?: strtolower((string) $agent->code) . '@agent.local';
        $username = $base;
        $counter = 2;

        while (DB::table('auth.users')->where('username', $username)->exists()) {
            $username = preg_replace('/(@.*)$/', '+' . $counter . '$1', $base) ?: $base . '-' . $counter;
            $counter++;
        }

        return $username;
    }
};
