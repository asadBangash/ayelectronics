<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallmentGuarantor extends Model
{
    protected $guarded = ['id'];
    public function plan() { return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id'); }
}
