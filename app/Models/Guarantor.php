<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guarantor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'full_name', 'cnic', 'mobile', 'whatsapp', 'phone_secondary', 'address',
        'occupation', 'photo_path', 'cnic_front_path', 'cnic_back_path',
        'verification_status', 'verification_notes',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(GuarantorDocument::class);
    }

    public function saleLinks(): HasMany
    {
        return $this->hasMany(SaleGuarantor::class);
    }
}
