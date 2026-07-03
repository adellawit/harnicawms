<?php

namespace App\Support;

use App\Models\PurchaseKontrabon;

class KontrabonStatus
{
    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const PARTIAL_PAID = 'partial_paid';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['key' => self::DRAFT, 'label' => 'Draft'],
            ['key' => self::SUBMITTED, 'label' => 'Submitted'],
            ['key' => self::PARTIAL_PAID, 'label' => 'Partial Paid'],
            ['key' => self::PAID, 'label' => 'Paid'],
            ['key' => self::CANCELLED, 'label' => 'Cancelled'],
        ];
    }

    public static function label(?string $status): string
    {
        return match ($status) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::PARTIAL_PAID => 'Partial Paid',
            self::PAID => 'Paid',
            self::CANCELLED => 'Cancelled',
            default => ucfirst($status ?? '-'),
        };
    }

    public static function badgeClass(?string $status): string
    {
        return match ($status) {
            self::DRAFT => 'secondary',
            self::SUBMITTED => 'warning',
            self::PARTIAL_PAID => 'info',
            self::PAID => 'success',
            self::CANCELLED => 'danger',
            default => 'secondary',
        };
    }

    public static function canEdit(PurchaseKontrabon $kontrabon): bool
    {
        return ! $kontrabon->trashed() && $kontrabon->status === self::DRAFT;
    }

    public static function canSubmit(PurchaseKontrabon $kontrabon): bool
    {
        return ! $kontrabon->trashed() && $kontrabon->status === self::DRAFT;
    }

    public static function canPay(PurchaseKontrabon $kontrabon): bool
    {
        if ($kontrabon->trashed()) {
            return false;
        }

        return in_array($kontrabon->status, [self::SUBMITTED, self::PARTIAL_PAID], true)
            && self::paymentBalance($kontrabon) > 0.000001;
    }

    public static function canCancel(PurchaseKontrabon $kontrabon): bool
    {
        return ! $kontrabon->trashed()
            && in_array($kontrabon->status, [self::DRAFT, self::SUBMITTED], true);
    }

    public static function paymentBalance(PurchaseKontrabon $kontrabon): float
    {
        return max(0, (float) $kontrabon->total - (float) ($kontrabon->paid_amount ?? 0));
    }

    public static function validateTransition(PurchaseKontrabon $kontrabon, string $newStatus): ?string
    {
        $current = $kontrabon->status;

        if ($current === $newStatus) {
            return null;
        }

        $allowed = [
            self::DRAFT => [self::SUBMITTED, self::CANCELLED],
            self::SUBMITTED => [self::PARTIAL_PAID, self::PAID, self::CANCELLED, self::DRAFT],
            self::PARTIAL_PAID => [self::PAID, self::SUBMITTED],
            self::PAID => [],
            self::CANCELLED => [self::DRAFT],
        ];

        if (! in_array($newStatus, $allowed[$current] ?? [], true)) {
            return 'Tidak dapat mengubah status dari '.self::label($current).' ke '.self::label($newStatus).'.';
        }

        return null;
    }
}
