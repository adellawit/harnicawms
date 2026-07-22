<?php

declare(strict_types=1);

use App\Models\IamHasAccess;
use App\Models\Menu;
use App\Models\Partner\Agent;
use App\Models\Partner\Reseller;
use App\Models\User;
use App\Services\Partner\ResellerMappingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function expectTrue(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$menu = Menu::where('name', 'Partner Reseller Mapping')->firstOrFail();
expectTrue($menu->parent?->name === 'Network' || str_contains((string) $menu->parent?->code, 'partner-network'), 'Reseller Mapping must sit under Customer Network.');
expectTrue(Route::has('partner.resellers.mapping.index'), 'Mapping index route missing.');
expectTrue(Route::has('partner.resellers.mapping.store'), 'Mapping store route missing.');
expectTrue(Route::has('partner.resellers.mapping.update'), 'Single mapping update route missing.');
expectTrue(
    IamHasAccess::where('iam_access_id', 'b0763f22-c9d1-41de-b7b9-28b523a7a354')
        ->where('sidebar_menu_id', $menu->id)
        ->where('is_read', true)
        ->where('is_update', true)
        ->exists(),
    'Administrator must have Reseller Mapping read+update.'
);

$service = app(ResellerMappingService::class);
$admin = User::query()->whereDoesntHave('partnerAgent')->orderBy('created_at')->first();
expectTrue($admin !== null, 'Admin user fixture required.');

$agent = Agent::query()->active()->first();
$reseller = Reseller::query()->whereNull('deleted_at')->first();
expectTrue($agent !== null && $reseller !== null, 'Agent and Reseller fixtures required.');

$connection = $reseller->getConnectionName();
DB::connection($connection)->beginTransaction();
try {
    $originalAgentId = $reseller->agent_id;
    $service->assign($agent->id, [$reseller->id], $admin);
    $reseller->refresh();
    expectTrue($reseller->agent_id === $agent->id, 'Assign must set reseller.agent_id.');
    $reseller->load('activeAssignment');
    expectTrue($reseller->activeAssignment?->agent_id === $agent->id, 'Active assignment must match agent.');

    $service->unassign([$reseller->id], $admin);
    $reseller->refresh();
    expectTrue($reseller->agent_id === null, 'Unassign must clear agent_id.');
    $reseller->load('activeAssignment');
    expectTrue($reseller->activeAssignment === null, 'Unassign must clear active assignment.');

    // restore
    if ($originalAgentId) {
        $service->assign($originalAgentId, [$reseller->id], $admin);
    }
} finally {
    DB::connection($connection)->rollBack();
}

echo "Reseller mapping tests passed.\n";
