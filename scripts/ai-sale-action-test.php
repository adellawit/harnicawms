<?php

declare(strict_types=1);

/**
 * Uji logika draf penjualan chatbot tanpa database.
 *
 * Jalankan: php scripts/ai-sale-action-test.php
 */

use App\Services\Ai\Actions\SaleDraftCalculator;

require __DIR__.'/../vendor/autoload.php';

$failures = [];
$checks = 0;

function check(string $label, bool $condition): void
{
    global $failures, $checks;

    $checks++;

    if ($condition) {
        echo "  [OK]   {$label}\n";

        return;
    }

    echo "  [FAIL] {$label}\n";
    $failures[] = $label;
}

$calc = new SaleDraftCalculator();

$draft = [
    'items' => [],
    'customer_id' => null,
    'customer_name' => 'Walk-in Customer',
    'payment_method_id' => null,
    'confirmation_token' => null,
];

$kopi = [
    'variant_id' => 'v-kopi',
    'product_id' => 'p-kopi',
    'unit_id' => 'u-pcs',
    'sku' => 'KOPI-01',
    'label' => 'Kopi Arabica',
    'unit_price' => 15000,
    'quantity' => 2,
    'stock' => 10,
];

$draft = $calc->addItem($draft, $kopi);
check('add item creates one line', count($draft['items']) === 1);
check('line total is qty * price', (float) $draft['items'][0]['line_total'] === 30000.0);
check('subtotal matches line', (float) $draft['subtotal'] === 30000.0);

$draft = $calc->addItem($draft, $kopi);
check('same variant merges quantity', (float) $draft['items'][0]['quantity'] === 4.0);
check('merged line total updates', (float) $draft['items'][0]['line_total'] === 60000.0);

$susu = $kopi;
$susu['variant_id'] = 'v-susu';
$susu['sku'] = 'SUSU-01';
$susu['label'] = 'Susu UHT';
$susu['quantity'] = 1;
$susu['unit_price'] = 8000;

$draft = $calc->addItem($draft, $susu);
check('different variant is a new line', count($draft['items']) === 2);
check('subtotal includes both lines', (float) $draft['subtotal'] === 68000.0);

$draft = $calc->withConfirmationToken($draft, 'token-abc');
check('token stored', $calc->tokenMatches($draft, 'token-abc'));
check('wrong token rejected', ! $calc->tokenMatches($draft, 'token-xyz'));
check('empty token rejected', ! $calc->tokenMatches($draft, ''));

$draft = $calc->addItem($draft, $susu);
check('mutating draft clears confirmation token', ($draft['confirmation_token'] ?? null) === null);

$draft = $calc->withCustomer($draft, 'c-1', 'Budi');
check('customer name set', $draft['customer_name'] === 'Budi');

echo "\n{$checks} checks, ".count($failures)." failed.\n";

exit($failures === [] ? 0 : 1);
