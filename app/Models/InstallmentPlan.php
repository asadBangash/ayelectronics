<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallmentPlan extends Model
{
    protected $guarded = ['id'];

    public function sale() { return $this->belongsTo(Sale::class); }
    public function installments() { return $this->hasMany(Installment::class)->orderBy('number'); }
    public function guarantors() { return $this->hasMany(InstallmentGuarantor::class)->orderBy('number'); }
}
