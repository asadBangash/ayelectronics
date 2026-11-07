<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientGuarantor extends Model
{
    protected $fillable = ['client_id', 'guarantor_id', 'position', 'relationship'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function guarantor(): BelongsTo
    {
        return $this->belongsTo(Guarantor::class);
    }
}
