<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class MenuTreeNormalizer
{
    private const MAX_DEPTH = 3;

    /**
     * @param  array<int, array{id: string, children?: array}>  $tree
     * @param  array<int, string>  $allowedIds
     * @return array<int, array{id: string, parent_id: ?string, level_sidebar: int, order_number: int}>
     */
    public function normalize(array $tree, array $allowedIds): array
    {
        $allowed = array_fill_keys($allowedIds, true);
        $seen = [];
        $positions = [];

        $walk = function (array $nodes, ?string $parentId, int $depth) use (&$walk, &$seen, &$positions, $allowed): void {
            if ($depth > self::MAX_DEPTH) {
                throw new InvalidArgumentException('Menu hierarchy cannot exceed three levels.');
            }

            foreach (array_values($nodes) as $index => $node) {
                $id = $node['id'] ?? null;

                if (! is_string($id) || $id === '' || ! isset($allowed[$id])) {
                    throw new InvalidArgumentException('One or more menu IDs are invalid.');
                }

                if (isset($seen[$id])) {
                    throw new InvalidArgumentException('A menu cannot appear more than once in the hierarchy.');
                }

                $seen[$id] = true;
                $positions[] = [
                    'id' => $id,
                    'parent_id' => $parentId,
                    'level_sidebar' => $depth,
                    'order_number' => $index + 1,
                ];

                $children = $node['children'] ?? [];

                if (! is_array($children)) {
                    throw new InvalidArgumentException('Menu children must be an array.');
                }

                if ($children !== []) {
                    $walk($children, $id, $depth + 1);
                }
            }
        };

        $walk($tree, null, 1);

        if (count($seen) !== count($allowed)) {
            throw new InvalidArgumentException('The complete menu hierarchy must be submitted.');
        }

        return $positions;
    }
}
