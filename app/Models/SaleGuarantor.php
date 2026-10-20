<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleGuarantor extends Model
{
    protected $fillable = ['sale_id', 'guarantor_id', 'position', 'relationship'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function guarantor(): BelongsTo
    {
        return $this->belongsTo(Guarantor::class);
    }
}
