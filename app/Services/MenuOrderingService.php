<?php

namespace App\Services;

use App\Repositories\MenuOrderRepository;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MenuOrderingService
{
    public function __construct(
        private readonly MenuOrderRepository $repository,
        private readonly MenuTreeNormalizer $normalizer,
    ) {}

    /**
     * @param  array<int, array{id: string, children: array}>  $tree
     * @return array<int, array{id: string, parent_id: ?string, level_sidebar: int, order_number: int}>
     */
    public function reorder(array $tree, ?string $userId): array
    {
        return $this->repository->transaction(function () use ($tree, $userId): array {
            $menus = $this->repository->lockActive();

            try {
                $positions = $this->normalizer->normalize($tree, $menus->modelKeys());
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    'menus' => $exception->getMessage(),
                ]);
            }

            $this->repository->updatePositions($positions, $userId);

            return $positions;
        });
    }
}
