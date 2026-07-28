<?php

declare(strict_types=1);

use App\Services\Theme\AppThemeService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function expectTrue(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$theme = app(AppThemeService::class);

expectTrue($theme->contrastInk('#FFFFFF') === '#2F3A44', 'White background must use dark ink.');
expectTrue($theme->contrastInk('#000000') === '#FFFFFF', 'Black background must use light ink.');
expectTrue($theme->contrastInk('#5C9E84') === '#2F3A44', 'Brand green background must use dark ink.');
expectTrue($theme->contrastInk('#F5F5F9') === '#2F3A44', 'Light page background must use dark ink.');
expectTrue($theme->contrastInk('#1B2B3A') === '#FFFFFF', 'Dark sidebar must use light ink.');
expectTrue($theme->contrastInk('#0D1B2A') === '#FFFFFF', 'Near-black surface must use light ink.');
expectTrue($theme->contrastInk('#F90606') === '#FFFFFF', 'Bright red navbar must use light ink.');
expectTrue($theme->contrastInk('#FA0000') === '#FFFFFF', 'Bright red sidebar must use light ink.');
expectTrue($theme->contrastInk('#C9C9C9') === '#2F3A44', 'Gray page background must use dark ink.');

$view = $theme->viewData();
expectTrue(array_key_exists('navbar_bg', $view), 'viewData must expose navbar_bg.');
expectTrue(array_key_exists('sidebar_bg', $view), 'viewData must expose sidebar_bg.');
expectTrue(array_key_exists('page_bg', $view), 'viewData must expose page_bg.');

echo "theme-surface-contrast-test: PASS\n";
