<?php

namespace App\Services\Ai\Actions;

class SaleDraftCalculator
{
    /**
     * @param  array<string, mixed>  $draft
     * @param  array{
     *   variant_id: string,
     *   product_id: string,
     *   unit_id: string,
     *   sku: string,
     *   label: string,
     *   unit_price: float,
     *   quantity: float,
     *   stock: float
     * }  $item
     * @return array<string, mixed>
     */
    public function addItem(array $draft, array $item): array
    {
        $items = $draft['items'] ?? [];
        $merged = false;

        foreach ($items as $index => $existing) {
            if (($existing['variant_id'] ?? '') !== $item['variant_id']) {
                continue;
            }

            $quantity = (float) $existing['quantity'] + (float) $item['quantity'];
            $items[$index] = $this->line($item, $quantity);
            $merged = true;
            break;
        }

        if (! $merged) {
            $items[] = $this->line($item, (float) $item['quantity']);
        }

        $next = $draft;
        $next['items'] = array_values($items);
        $next['confirmation_token'] = null;
        $next['subtotal'] = $this->subtotal($next['items']);

        return $next;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function withCustomer(array $draft, ?string $customerId, string $customerName): array
    {
        $next = $draft;
        $next['customer_id'] = $customerId;
        $next['customer_name'] = $customerName;
        $next['confirmation_token'] = null;

        return $next;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function withPayment(array $draft, string $paymentMethodId, string $paymentMethodName, string $paymentCode): array
    {
        $next = $draft;
        $next['payment_method_id'] = $paymentMethodId;
        $next['payment_method_name'] = $paymentMethodName;
        $next['payment_code'] = $paymentCode;
        $next['confirmation_token'] = null;

        return $next;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function withConfirmationToken(array $draft, string $token): array
    {
        $next = $draft;
        $next['confirmation_token'] = $token;

        return $next;
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    public function tokenMatches(array $draft, string $token): bool
    {
        $stored = (string) ($draft['confirmation_token'] ?? '');

        return $stored !== '' && hash_equals($stored, $token);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function subtotal(array $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $total += (float) ($item['line_total'] ?? 0);
        }

        return round($total, 2);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function line(array $item, float $quantity): array
    {
        $unitPrice = (float) $item['unit_price'];

        return [
            'variant_id' => $item['variant_id'],
            'product_id' => $item['product_id'],
            'unit_id' => $item['unit_id'],
            'sku' => $item['sku'],
            'label' => $item['label'],
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'stock' => (float) ($item['stock'] ?? 0),
            'line_total' => round($quantity * $unitPrice, 2),
        ];
    }
}
