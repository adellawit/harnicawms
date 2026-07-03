<?php

namespace Tests\Unit;

use App\Models\ProductPurchaseOrder;
use App\Models\ProductPurchaseOrderItem;
use App\Support\PurchaseOrderStatus;
use Tests\TestCase;

class PurchaseOrderStatusTest extends TestCase
{
    public function test_draft_can_move_to_process(): void
    {
        $po = new ProductPurchaseOrder(['status' => 'draft']);
        $po->setRelation('items', collect());

        $this->assertNull(PurchaseOrderStatus::validateTransition($po, 'process'));
    }

    public function test_cannot_cancel_po_with_receives(): void
    {
        $po = new ProductPurchaseOrder(['status' => 'process']);
        $item = new ProductPurchaseOrderItem;
        $item->setRelation('receiveItems', collect([(object) ['quantity_received' => 1]]));
        $po->setRelation('items', collect([$item]));

        $this->assertSame(
            'PO yang sudah memiliki penerimaan barang tidak dapat dibatalkan.',
            PurchaseOrderStatus::validateTransition($po, 'cancelled')
        );
    }

    public function test_received_status_cannot_be_updated_manually(): void
    {
        $po = new ProductPurchaseOrder(['status' => 'received']);

        $this->assertFalse(PurchaseOrderStatus::canUpdate($po));
    }

    public function test_process_status_cannot_show_update_action(): void
    {
        $po = new ProductPurchaseOrder(['status' => 'process']);

        $this->assertFalse(PurchaseOrderStatus::canUpdate($po));
    }

    public function test_draft_can_show_update_action(): void
    {
        $po = new ProductPurchaseOrder(['status' => 'draft']);

        $this->assertTrue(PurchaseOrderStatus::canUpdate($po));
    }
}
