<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentWhatsappMessage extends Model
{
    protected $fillable = [
        'client_id', 'sale_id', 'installment_plan_id', 'template_key', 'to_number',
        'body', 'provider_message_id', 'status', 'provider_response', 'sent_at', 'delivered_at', 'sent_by',
    ];

    protected $casts = [
        'provider_response' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }
}
