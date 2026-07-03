<?php

namespace Tests\Unit;

use App\Models\ProductPurchaseOrder;
use App\Models\ProductPurchaseOrderItem;
use App\Services\PurchaseOrderHierarchyService;
use Tests\TestCase;

class PurchaseOrderReceiveProgressTest extends TestCase
{
    public function test_receive_progress_for_standalone_po(): void
    {
        $po = new ProductPurchaseOrder(['po_kind' => 'standalone']);
        $item = new ProductPurchaseOrderItem(['quantity' => 100]);
        $item->setRelation('receiveItems', collect([
            (object) ['quantity_received' => 25],
            (object) ['quantity_received' => 25],
        ]));
        $po->setRelation('items', collect([$item]));

        $this->assertSame(50, PurchaseOrderHierarchyService::receiveProgressPercent($po));
    }

    public function test_receive_progress_is_zero_when_no_items(): void
    {
        $po = new ProductPurchaseOrder(['po_kind' => 'standalone']);
        $po->setRelation('items', collect());

        $this->assertSame(0, PurchaseOrderHierarchyService::receiveProgressPercent($po));
    }
}
