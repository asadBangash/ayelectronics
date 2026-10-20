<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['due_date' => 'date'];
    public function plan() { return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id'); }
    public function payments() { return $this->hasMany(PaymentSale::class); }
}
