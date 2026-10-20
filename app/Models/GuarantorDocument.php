<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuarantorDocument extends Model
{
    protected $fillable = ['guarantor_id', 'label', 'path', 'mime', 'uploaded_by'];

    public function guarantor(): BelongsTo
    {
        return $this->belongsTo(Guarantor::class);
    }
}
