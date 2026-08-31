<?php

namespace App\Services;

use App\Models\ProductPurchaseOrder;
use App\Models\PurchaseKontrabon;
use App\Models\PurchaseKontrabonItem;
use App\Models\PurchaseKontrabonPayment;
use App\Models\Supplier;
use App\Support\KontrabonStatus;
use App\Support\PurchaseOrderStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KontrabonService
{
    /**
     * @param  array<int, string>  $branchIds
     * @param  'received'|'unreceived'|null  $receiveScope
     * @return Collection<int, ProductPurchaseOrder>
     */
    public static function eligiblePurchaseOrders(
        string $supplierId,
        array $branchIds,
        ?string $excludeKontrabonId = null,
        ?string $receiveScope = null
    ): Collection {
        return ProductPurchaseOrder::query()
            ->with(['items.product', 'items.unit', 'items.variant', 'items.receiveItems'])
            ->where('supplier_id', $supplierId)
            ->where(function ($query) {
                $query->whereNull('po_kind')
                    ->orWhereIn('po_kind', ['standalone', 'sub']);
            })
            ->whereIn('status', ['process', 'receiving', 'received', 'payment'])
            ->whereNull('deleted_at')
            ->when(! empty($branchIds), fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->orderByDesc('purchase_date')
            ->orderByDesc('created_at')
            ->get()
            ->filter(function (ProductPurchaseOrder $po) use ($excludeKontrabonId, $receiveScope) {
                if (self::purchaseOrderRemainingInvoiceAmount($po, $excludeKontrabonId) <= 0.000001) {
                    return false;
                }

                $hasReceive = self::purchaseOrderHasReceive($po);
                $status = $po->status_key ?? $po->status;

                return match ($receiveScope) {
                    'unreceived' => ! $hasReceive && $status === 'process',
                    'received' => $hasReceive,
                    default => $hasReceive || $status === 'process',
                };
            })
            ->values();
    }

    public static function purchaseOrderHasReceive(ProductPurchaseOrder $purchase): bool
    {
        $purchase->loadMissing('items.receiveItems');

        return $purchase->items->contains(
            fn ($item) => (float) $item->receiveItems->sum('quantity_received') > 0
        );
    }

    public static function purchaseOrderIsFullyReceived(ProductPurchaseOrder $purchase): bool
    {
        $purchase->loadMissing('items.receiveItems');

        if ($purchase->items->isEmpty()) {
            return false;
        }

        return $purchase->items->every(function ($item) {
            $received = (float) $item->receiveItems->sum('quantity_received');

            return $received + 1e-6 >= (float) $item->quantity;
        });
    }

    public static function purchaseOrderTotals(ProductPurchaseOrder $purchase): array
    {
        return [
            'subtotal' => (float) $purchase->subtotal,
            'tax_amount' => (float) $purchase->tax_amount,
            'discount_amount' => (float) $purchase->discount_amount,
            'other_cost_amount' => (float) ($purchase->other_cost_amount ?? 0),
            'total' => (float) $purchase->total,
        ];
    }

    public static function purchaseOrderInvoicedAmount(string $poId, ?string $excludeKontrabonId = null): float
    {
        return (float) PurchaseKontrabonItem::query()
            ->where('purchase_order_id', $poId)
            ->whereNull('deleted_at')
            ->whereHas('kontrabon', function ($query) use ($excludeKontrabonId) {
                $query->whereNull('deleted_at')
                    ->whereIn('status', [
                        KontrabonStatus::DRAFT,
                        KontrabonStatus::SUBMITTED,
                        KontrabonStatus::PARTIAL_PAID,
                        KontrabonStatus::PAID,
                    ]);
                if ($excludeKontrabonId) {
                    $query->where('id', '!=', $excludeKontrabonId);
                }
            })
            ->sum('total');
    }

    public static function purchaseOrderRemainingInvoiceAmount(
        ProductPurchaseOrder $purchase,
        ?string $excludeKontrabonId = null
    ): float {
        $poTotal = (float) $purchase->total;
        $invoiced = self::purchaseOrderInvoicedAmount($purchase->id, $excludeKontrabonId);

        return max(0, $poTotal - $invoiced);
    }

    /**
     * @return array{key: string, label: string, badge: string}
     */
    public static function purchaseOrderPaymentStatus(ProductPurchaseOrder $purchase): array
    {
        $activeStatuses = [
            KontrabonStatus::DRAFT,
            KontrabonStatus::SUBMITTED,
            KontrabonStatus::PARTIAL_PAID,
            KontrabonStatus::PAID,
        ];

        $purchase->loadMissing(['kontrabonItems.kontrabon']);

        $items = $purchase->kontrabonItems->filter(function ($item) use ($activeStatuses) {
            $kontrabon = $item->kontrabon;

            return $kontrabon
                && ! $kontrabon->trashed()
                && in_array($kontrabon->status, $activeStatuses, true);
        });

        $poTotal = (float) $purchase->total;
        $invoiced = (float) $items->sum('total');
        $paid = 0.0;

        foreach ($items as $item) {
            $kontrabon = $item->kontrabon;
            $kontrabonTotal = (float) $kontrabon->total;
            if ($kontrabonTotal <= 0) {
                continue;
            }

            $ratio = min(1, (float) ($kontrabon->paid_amount ?? 0) / $kontrabonTotal);
            $paid += (float) $item->total * $ratio;
        }

        if ($poTotal > 0 && $paid + 1e-6 >= $poTotal) {
            $key = 'paid';
        } elseif ($paid > 1e-6) {
            $key = 'partial_paid';
        } elseif ($invoiced > 1e-6) {
            $key = 'invoiced';
        } else {
            $key = 'unpaid';
        }

        $label = match ($key) {
            'paid' => 'Paid',
            'partial_paid' => 'Partial Paid',
            'invoiced' => 'Invoiced',
            default => 'Unpaid',
        };

        $tone = match ($key) {
            'paid' => 'success',
            'partial_paid' => 'info',
            'invoiced' => 'warning',
            default => 'secondary',
        };

        return [
            'key' => $key,
            'label' => $label,
            'badge' => '<span class="badge bg-label-'.$tone.'">'.e($label).'</span>',
        ];
    }

    public static function purchaseOrderHasOpenKontrabon(string $poId): bool
    {
        return PurchaseKontrabonItem::query()
            ->where('purchase_order_id', $poId)
            ->whereNull('deleted_at')
            ->whereHas('kontrabon', function ($query) {
                $query->whereNull('deleted_at')
                    ->whereIn('status', [
                        KontrabonStatus::DRAFT,
                        KontrabonStatus::SUBMITTED,
                        KontrabonStatus::PARTIAL_PAID,
                    ]);
            })
            ->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function purchaseOrderItemsPayload(ProductPurchaseOrder $purchase): array
    {
        $purchase->loadMissing(['items.product', 'items.unit', 'items.variant', 'items.receiveItems']);

        return $purchase->items->map(function ($item) {
            $received = (float) $item->receiveItems->sum('quantity_received');
            $productLabel = $item->product?->name ?? '-';
            if ($item->variant?->sku) {
                $productLabel .= ' — '.$item->variant->sku;
            }

            return [
                'product_name' => $item->product?->name,
                'product_code' => $item->product?->code,
                'product_label' => $productLabel,
                'variant_sku' => $item->variant?->sku,
                'unit_label' => $item->unit?->name ?: ($item->unit?->symbol ?: '-'),
                'batch_number' => $item->batch_number,
                'expiry_date' => $item->expiry_date?->format('d/m/Y'),
                'quantity' => (float) $item->quantity,
                'quantity_received' => $received,
                'quantity_remaining' => max(0, (float) $item->quantity - $received),
                'unit_price' => (float) $item->unit_price,
                'discount_amount' => (float) $item->discount_amount,
                'subtotal' => (float) $item->subtotal,
                'carton_display' => $item->carton_display_label,
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function createKontrabon(array $payload, array $items, string $userId): PurchaseKontrabon
    {
        return DB::transaction(function () use ($payload, $items, $userId) {
            $supplier = Supplier::findOrFail($payload['supplier_id']);
            $normalizedItems = self::normalizeItems(
                $supplier->id,
                $items,
                $payload['branch_id'] ?? null
            );

            $totals = self::sumItemTotals($normalizedItems);

            $kontrabon = PurchaseKontrabon::create([
                'kontrabon_number' => self::generateNumber(),
                'kontrabon_date' => $payload['kontrabon_date'],
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'company_id' => $payload['company_id'] ?? null,
                'branch_id' => $payload['branch_id'] ?? null,
                'status' => KontrabonStatus::DRAFT,
                'notes' => $payload['notes'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'discount_amount' => $totals['discount_amount'],
                'total' => $totals['total'],
                'paid_amount' => 0,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            self::syncItems($kontrabon, $normalizedItems, $userId);

            if (! empty($payload['submit'])) {
                self::submitKontrabon($kontrabon);
            }

            return $kontrabon->fresh(['items.purchaseOrder', 'payments']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function updateKontrabon(PurchaseKontrabon $kontrabon, array $payload, array $items, string $userId): PurchaseKontrabon
    {
        if (! KontrabonStatus::canEdit($kontrabon)) {
            throw new \RuntimeException('Kontrabon hanya dapat diedit saat status Draft.');
        }

        return DB::transaction(function () use ($kontrabon, $payload, $items, $userId) {
            $supplier = Supplier::findOrFail($payload['supplier_id']);
            $normalizedItems = self::normalizeItems(
                $supplier->id,
                $items,
                $payload['branch_id'] ?? $kontrabon->branch_id,
                $kontrabon->id
            );
            $totals = self::sumItemTotals($normalizedItems);

            $updateData = [
                'kontrabon_date' => $payload['kontrabon_date'],
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'notes' => $payload['notes'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'discount_amount' => $totals['discount_amount'],
                'total' => $totals['total'],
                'updated_by' => $userId,
            ];

            $kontrabon->update($updateData);

            $kontrabon->items()->delete();
            self::syncItems($kontrabon, $normalizedItems, $userId);

            if (! empty($payload['submit'])) {
                self::submitKontrabon($kontrabon);
            }

            return $kontrabon->fresh(['items.purchaseOrder', 'payments']);
        });
    }

    public static function submitKontrabon(PurchaseKontrabon $kontrabon): PurchaseKontrabon
    {
        $kontrabon->loadMissing('items');

        if ($kontrabon->items->isEmpty()) {
            throw new \RuntimeException('Kontrabon minimal harus memiliki 1 Purchase Order.');
        }

        foreach ($kontrabon->items as $item) {
            if (blank($item->supplier_invoice_number)) {
                throw new \RuntimeException('Nomor faktur supplier wajib diisi untuk semua PO.');
            }
        }

        $error = KontrabonStatus::validateTransition($kontrabon, KontrabonStatus::SUBMITTED);
        if ($error) {
            throw new \RuntimeException($error);
        }

        $kontrabon->update(['status' => KontrabonStatus::SUBMITTED]);

        return $kontrabon->fresh(['items.purchaseOrder', 'payments']);
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public static function recordPayment(PurchaseKontrabon $kontrabon, array $paymentData, string $userId): PurchaseKontrabon
    {
        if (! KontrabonStatus::canPay($kontrabon)) {
            throw new \RuntimeException('Pembayaran tidak dapat dilakukan untuk kontrabon ini.');
        }

        $balance = KontrabonStatus::paymentBalance($kontrabon);
        $payAmount = (float) ($paymentData['amount'] ?? 0);

        if ($payAmount <= 0) {
            throw new \RuntimeException('Nominal pembayaran harus lebih dari 0.');
        }

        if ($payAmount > $balance + 0.000001) {
            throw new \RuntimeException('Nominal pembayaran melebihi sisa tagihan (maks: '.format_number($balance, 2, true).').');
        }

        return DB::transaction(function () use ($kontrabon, $paymentData, $payAmount, $userId) {
            $kontrabon->loadMissing('items.purchaseOrder.items.receiveItems');

            PurchaseKontrabonPayment::create([
                'kontrabon_id' => $kontrabon->id,
                'payment_date' => $paymentData['payment_date'],
                'amount' => $payAmount,
                'payment_reference' => $paymentData['payment_reference'] ?? null,
                'payment_method' => $paymentData['payment_method'] ?? null,
                'payment_notes' => $paymentData['payment_notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $newPaidAmount = (float) $kontrabon->paid_amount + $payAmount;
            $isFullyPaid = $newPaidAmount >= (float) $kontrabon->total - 0.000001;
            $newStatus = $isFullyPaid ? KontrabonStatus::PAID : KontrabonStatus::PARTIAL_PAID;

            if ($isFullyPaid) {
                foreach ($kontrabon->items as $item) {
                    $purchase = $item->purchaseOrder;
                    if (! $purchase) {
                        continue;
                    }

                    if (self::purchaseOrderRemainingInvoiceAmount($purchase) > 0.000001) {
                        continue;
                    }

                    if (self::purchaseOrderHasOpenKontrabon($purchase->id)) {
                        continue;
                    }

                    if (! self::purchaseOrderIsFullyReceived($purchase)) {
                        continue;
                    }

                    if (($purchase->status ?? '') === 'payment') {
                        continue;
                    }

                    $paymentError = PurchaseOrderStatus::validateTransition($purchase, 'payment');
                    if ($paymentError) {
                        throw new \RuntimeException($purchase->purchase_number.': '.$paymentError);
                    }

                    $purchase->update([
                        'status' => 'payment',
                        'updated_by' => $userId,
                    ]);
                }
            }

            $kontrabon->update([
                'status' => $newStatus,
                'paid_amount' => $newPaidAmount,
                'payment_date' => $paymentData['payment_date'],
                'payment_reference' => $paymentData['payment_reference'] ?? null,
                'payment_method' => $paymentData['payment_method'] ?? null,
                'payment_notes' => $paymentData['payment_notes'] ?? null,
                'updated_by' => $userId,
            ]);

            return $kontrabon->fresh(['items.purchaseOrder', 'payments']);
        });
    }

    public static function cancelKontrabon(PurchaseKontrabon $kontrabon, string $userId): PurchaseKontrabon
    {
        if (! KontrabonStatus::canCancel($kontrabon)) {
            throw new \RuntimeException('Kontrabon tidak dapat dibatalkan.');
        }

        $error = KontrabonStatus::validateTransition($kontrabon, KontrabonStatus::CANCELLED);
        if ($error) {
            throw new \RuntimeException($error);
        }

        $kontrabon->update([
            'status' => KontrabonStatus::CANCELLED,
            'updated_by' => $userId,
        ]);

        return $kontrabon->fresh(['items.purchaseOrder', 'payments']);
    }

    public static function generateNumber(): string
    {
        $prefix = 'KB-'.date('Ym').'-';
        $last = PurchaseKontrabon::withTrashed()
            ->where('kontrabon_number', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(kontrabon_number) DESC, kontrabon_number DESC')
            ->value('kontrabon_number');

        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected static function normalizeItems(
        string $supplierId,
        array $items,
        ?string $branchId,
        ?string $excludeKontrabonId = null
    ): array {
        if (empty($items)) {
            throw new \RuntimeException('Minimal 1 Purchase Order harus dipilih.');
        }

        $branchIds = $branchId ? [$branchId] : [];
        $eligible = self::eligiblePurchaseOrders($supplierId, $branchIds, $excludeKontrabonId)
            ->keyBy('id');

        $normalized = [];
        $usedPoIds = [];

        foreach ($items as $row) {
            $poId = $row['purchase_order_id'] ?? null;
            if (! $poId || isset($usedPoIds[$poId])) {
                continue;
            }

            /** @var ProductPurchaseOrder|null $purchase */
            $purchase = $eligible->get($poId);
            if (! $purchase) {
                throw new \RuntimeException('Purchase Order tidak valid atau sudah tidak memiliki sisa tagihan.');
            }

            $amounts = self::purchaseOrderTotals($purchase);
            $poTotal = $amounts['total'];
            $remaining = self::purchaseOrderRemainingInvoiceAmount($purchase, $excludeKontrabonId);
            $invoiceTotal = isset($row['total']) && $row['total'] !== ''
                ? (float) $row['total']
                : $remaining;

            if ($invoiceTotal <= 0) {
                throw new \RuntimeException('Nominal invoice harus lebih dari 0.');
            }

            if ($invoiceTotal > $remaining + 0.000001) {
                throw new \RuntimeException(
                    'Nominal invoice PO '.$purchase->purchase_number.' melebihi sisa tagihan (maks: '.format_number($remaining, 2, true).').'
                );
            }

            $ratio = $poTotal > 0 ? ($invoiceTotal / $poTotal) : 1;

            $normalized[] = [
                'purchase_order_id' => $purchase->id,
                'po_total' => $poTotal,
                'supplier_invoice_number' => trim((string) ($row['supplier_invoice_number'] ?? '')),
                'supplier_invoice_date' => self::toDatabaseDate($row['supplier_invoice_date'] ?? null),
                'subtotal' => round($amounts['subtotal'] * $ratio, 4),
                'tax_amount' => round($amounts['tax_amount'] * $ratio, 4),
                'discount_amount' => round($amounts['discount_amount'] * $ratio, 4),
                'other_cost_amount' => round($amounts['other_cost_amount'] * $ratio, 4),
                'total' => round($invoiceTotal, 4),
                'notes' => $row['notes'] ?? null,
                'attachment_path' => $row['attachment_path'] ?? null,
                'attachment_name' => $row['attachment_name'] ?? null,
                'attachment_mime' => $row['attachment_mime'] ?? null,
            ];
            $usedPoIds[$poId] = true;
        }

        if (empty($normalized)) {
            throw new \RuntimeException('Minimal 1 Purchase Order harus dipilih.');
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected static function syncItems(PurchaseKontrabon $kontrabon, array $items, string $userId): void
    {
        foreach ($items as $row) {
            PurchaseKontrabonItem::create([
                'kontrabon_id' => $kontrabon->id,
                'purchase_order_id' => $row['purchase_order_id'],
                'po_total' => $row['po_total'],
                'supplier_invoice_number' => $row['supplier_invoice_number'] ?: null,
                'supplier_invoice_date' => $row['supplier_invoice_date'] ?: null,
                'subtotal' => $row['subtotal'],
                'tax_amount' => $row['tax_amount'],
                'discount_amount' => $row['discount_amount'],
                'other_cost_amount' => $row['other_cost_amount'],
                'total' => $row['total'],
                'notes' => $row['notes'] ?? null,
                'attachment_path' => $row['attachment_path'] ?? null,
                'attachment_name' => $row['attachment_name'] ?? null,
                'attachment_mime' => $row['attachment_mime'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    /**
     * Convert display/request date (d/m/Y) to DB date (Y-m-d).
     */
    protected static function toDatabaseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value))->format('Y-m-d');
        }

        $value = trim((string) $value);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
                if ($parsed !== false) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: float, tax_amount: float, discount_amount: float, total: float}
     */
    protected static function sumItemTotals(array $items): array
    {
        $subtotal = 0.0;
        $tax = 0.0;
        $discount = 0.0;
        $total = 0.0;

        foreach ($items as $row) {
            $subtotal += (float) $row['subtotal'];
            $tax += (float) $row['tax_amount'];
            $discount += (float) $row['discount_amount'];
            $total += (float) $row['total'];
        }

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'discount_amount' => $discount,
            'total' => $total,
        ];
    }
}
