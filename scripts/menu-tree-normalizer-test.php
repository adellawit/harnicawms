<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Services\MenuTreeNormalizer;

function expectSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)
        );
    }
}

function expectInvalid(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
}

$normalizer = new MenuTreeNormalizer;
$allowedIds = ['root-a', 'root-b', 'child-a', 'child-b', 'grandchild-a'];

$positions = $normalizer->normalize([
    [
        'id' => 'root-b',
        'children' => [
            [
                'id' => 'child-a',
                'children' => [
                    ['id' => 'grandchild-a', 'children' => []],
                ],
            ],
            ['id' => 'child-b', 'children' => []],
        ],
    ],
    ['id' => 'root-a', 'children' => []],
], $allowedIds);

expectSame([
    ['id' => 'root-b', 'parent_id' => null, 'level_sidebar' => 1, 'order_number' => 1],
    ['id' => 'child-a', 'parent_id' => 'root-b', 'level_sidebar' => 2, 'order_number' => 1],
    ['id' => 'grandchild-a', 'parent_id' => 'child-a', 'level_sidebar' => 3, 'order_number' => 1],
    ['id' => 'child-b', 'parent_id' => 'root-b', 'level_sidebar' => 2, 'order_number' => 2],
    ['id' => 'root-a', 'parent_id' => null, 'level_sidebar' => 1, 'order_number' => 2],
], $positions, 'The tree must be normalized into parent, level, and sibling order values.');

expectInvalid(
    fn () => $normalizer->normalize([
        ['id' => 'root-a', 'children' => []],
        ['id' => 'root-a', 'children' => []],
    ], $allowedIds),
    'Duplicate menu IDs must be rejected.'
);

expectInvalid(
    fn () => $normalizer->normalize([
        [
            'id' => 'root-a',
            'children' => [[
                'id' => 'child-a',
                'children' => [[
                    'id' => 'grandchild-a',
                    'children' => [['id' => 'child-b', 'children' => []]],
                ]],
            ]],
        ],
    ], $allowedIds),
    'Trees deeper than three levels must be rejected.'
);

expectInvalid(
    fn () => $normalizer->normalize([
        ['id' => 'unknown', 'children' => []],
    ], $allowedIds),
    'Unknown menu IDs must be rejected.'
);

expectInvalid(
    fn () => $normalizer->normalize([
        ['id' => 'root-a', 'children' => []],
    ], $allowedIds),
    'Incomplete menu trees must be rejected.'
);

echo "MenuTreeNormalizer tests passed.\n";
