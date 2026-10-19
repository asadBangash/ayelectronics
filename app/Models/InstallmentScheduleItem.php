<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentScheduleItem extends Model
{
    protected $fillable = [
        'installment_plan_id', 'installment_number', 'due_date',
        'amount_due', 'amount_paid', 'remaining_amount', 'status', 'paid_at',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InstallmentPaymentAllocation::class);
    }
}
