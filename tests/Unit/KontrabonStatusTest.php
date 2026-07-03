<?php

namespace Tests\Unit;

use App\Models\PurchaseKontrabon;
use App\Support\KontrabonStatus;
use PHPUnit\Framework\TestCase;

class KontrabonStatusTest extends TestCase
{
    public function test_can_pay_when_submitted_or_partial_paid_with_balance(): void
    {
        $submitted = new PurchaseKontrabon(['status' => KontrabonStatus::SUBMITTED, 'total' => 1000, 'paid_amount' => 0]);
        $partial = new PurchaseKontrabon(['status' => KontrabonStatus::PARTIAL_PAID, 'total' => 1000, 'paid_amount' => 400]);
        $paid = new PurchaseKontrabon(['status' => KontrabonStatus::PAID, 'total' => 1000, 'paid_amount' => 1000]);

        $this->assertTrue(KontrabonStatus::canPay($submitted));
        $this->assertTrue(KontrabonStatus::canPay($partial));
        $this->assertFalse(KontrabonStatus::canPay($paid));
    }

    public function test_payment_balance(): void
    {
        $kontrabon = new PurchaseKontrabon(['total' => 1000, 'paid_amount' => 350]);

        $this->assertSame(650.0, KontrabonStatus::paymentBalance($kontrabon));
    }
}
