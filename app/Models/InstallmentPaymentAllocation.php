<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentPaymentAllocation extends Model
{
    protected $fillable = [
        'installment_schedule_item_id', 'payment_sale_id', 'amount', 'transaction_reference',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function scheduleItem(): BelongsTo
    {
        return $this->belongsTo(InstallmentScheduleItem::class, 'installment_schedule_item_id');
    }

    public function paymentSale(): BelongsTo
    {
        return $this->belongsTo(PaymentSale::class, 'payment_sale_id');
    }
}
