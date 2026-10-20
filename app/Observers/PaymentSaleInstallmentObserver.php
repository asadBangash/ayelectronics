<?php

namespace App\Observers;

use App\Models\PaymentSale;
use App\Services\Installments\InstallmentPlanService;

class PaymentSaleInstallmentObserver
{
    public function created(PaymentSale $payment): void
    {
        app(InstallmentPlanService::class)->allocatePayment($payment);
    }
}
