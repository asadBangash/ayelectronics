<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallmentAuditLog extends Model
{
    protected $fillable = [
        'auditable_type', 'auditable_id', 'action', 'old_values', 'new_values', 'user_id',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];
}
