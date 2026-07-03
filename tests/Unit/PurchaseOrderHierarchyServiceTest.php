<?php

namespace Tests\Unit;

use App\Models\ProductPurchaseOrder;
use App\Services\PurchaseOrderHierarchyService;
use Tests\TestCase;

class PurchaseOrderHierarchyServiceTest extends TestCase
{
    public function test_item_key_normalizes_null_variant(): void
    {
        $this->assertSame(
            'prod-1|null|unit-1',
            PurchaseOrderHierarchyService::itemKey('prod-1', null, 'unit-1')
        );
        $this->assertSame(
            'prod-1|var-1|unit-1',
            PurchaseOrderHierarchyService::itemKey('prod-1', 'var-1', 'unit-1')
        );
    }

    public function test_master_po_is_not_editable_even_when_draft(): void
    {
        $purchase = new ProductPurchaseOrder([
            'po_kind' => PurchaseOrderHierarchyService::KIND_MASTER,
            'status' => 'draft',
        ]);

        $this->assertTrue(PurchaseOrderHierarchyService::isMaster($purchase));
        $this->assertFalse(PurchaseOrderHierarchyService::isEditable($purchase));
        $this->assertFalse(PurchaseOrderHierarchyService::canReceive($purchase));
    }

    public function test_sub_and_standalone_draft_are_editable(): void
    {
        $sub = new ProductPurchaseOrder([
            'po_kind' => PurchaseOrderHierarchyService::KIND_SUB,
            'status' => 'draft',
        ]);
        $standalone = new ProductPurchaseOrder([
            'po_kind' => PurchaseOrderHierarchyService::KIND_STANDALONE,
            'status' => 'draft',
        ]);

        $this->assertTrue(PurchaseOrderHierarchyService::isEditable($sub));
        $this->assertTrue(PurchaseOrderHierarchyService::isEditable($standalone));
        $this->assertTrue(PurchaseOrderHierarchyService::canReceive($sub));
        $this->assertTrue(PurchaseOrderHierarchyService::canReceive($standalone));
    }

    public function test_resolve_parent_item_id_by_product_key(): void
    {
        $parentItem = new \App\Models\ProductPurchaseOrderItem;
        $parentItem->forceFill([
            'id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'product_id' => 'prod-1',
            'variant_id' => null,
            'unit_id' => 'unit-1',
        ]);

        $master = new ProductPurchaseOrder([
            'po_kind' => PurchaseOrderHierarchyService::KIND_MASTER,
        ]);
        $master->setRelation('items', collect([$parentItem]));

        $resolved = PurchaseOrderHierarchyService::resolveParentItemId($master, [
            'product_id' => 'prod-1',
            'variant_id' => null,
            'unit_id' => 'unit-1',
        ]);

        $this->assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $resolved);
    }
}
