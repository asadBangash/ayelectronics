<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientDocument extends Model
{
    protected $fillable = ['client_id', 'type', 'label', 'path', 'mime', 'uploaded_by'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
