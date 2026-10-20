<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentPlan extends Model
{
    protected $fillable = [
        'sale_id', 'client_id', 'total_amount', 'down_payment', 'financed_amount',
        'markup_percent', 'markup_amount', 'total_payable', 'duration_months',
        'monthly_installment', 'due_day_of_month', 'first_due_date', 'next_due_date',
        'total_paid', 'outstanding_balance', 'overdue_days', 'overdue_amount',
        'status', 'internal_notes', 'created_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'financed_amount' => 'decimal:2',
        'markup_amount' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'monthly_installment' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'overdue_amount' => 'decimal:2',
        'first_due_date' => 'date',
        'next_due_date' => 'date',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function scheduleItems(): HasMany
    {
        return $this->hasMany(InstallmentScheduleItem::class)->orderBy('installment_number');
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(InstallmentWhatsappMessage::class);
    }
}
