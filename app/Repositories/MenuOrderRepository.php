<?php

namespace App\Repositories;

use App\Models\Menu;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MenuOrderRepository
{
    public function transaction(Closure $callback): mixed
    {
        return DB::connection((new Menu)->getConnectionName())->transaction($callback);
    }

    /**
     * @return Collection<int, Menu>
     */
    public function lockActive(): Collection
    {
        DB::connection((new Menu)->getConnectionName())
            ->statement('LOCK TABLE master_data.menus IN SHARE ROW EXCLUSIVE MODE');

        return Menu::query()
            ->lockForUpdate()
            ->get(['id', 'parent_id', 'level_sidebar', 'order_number']);
    }

    /**
     * @param  array<int, array{id: string, parent_id: ?string, level_sidebar: int, order_number: int}>  $positions
     */
    public function updatePositions(array $positions, ?string $userId): void
    {
        foreach ($positions as $position) {
            Menu::query()
                ->whereKey($position['id'])
                ->update([
                    'parent_id' => $position['parent_id'],
                    'level_sidebar' => $position['level_sidebar'],
                    'order_number' => $position['order_number'],
                    'updated_by' => $userId,
                ]);
        }
    }
}
