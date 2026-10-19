<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class InstallmentSetting extends Model
{
    protected $fillable = [
        'require_guarantors_for_installment', 'guarantors_required', 'due_day_of_month',
        'default_markup_percent', 'rating_thresholds',
        'whatsapp_enabled', 'whatsapp_test_mode', 'whatsapp_api_url', 'whatsapp_access_token',
        'whatsapp_phone_number_id', 'whatsapp_business_account_id', 'whatsapp_webhook_verify_token',
        'whatsapp_reminder_rules',
    ];

    protected $casts = [
        'require_guarantors_for_installment' => 'boolean',
        'whatsapp_enabled' => 'boolean',
        'whatsapp_test_mode' => 'boolean',
        'rating_thresholds' => 'array',
        'whatsapp_reminder_rules' => 'array',
        'default_markup_percent' => 'float',
    ];

    protected $hidden = ['whatsapp_access_token'];

    public static function current(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        return static::create([
            'require_guarantors_for_installment' => true,
            'guarantors_required' => 2,
            'due_day_of_month' => 1,
            'default_markup_percent' => 0,
            'whatsapp_test_mode' => true,
        ]);
    }

    public function setWhatsappAccessTokenAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['whatsapp_access_token'] = null;

            return;
        }
        $this->attributes['whatsapp_access_token'] = Crypt::encryptString($value);
    }

    public function getWhatsappAccessTokenDecrypted(): ?string
    {
        $raw = $this->attributes['whatsapp_access_token'] ?? null;
        if (! $raw) {
            return null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
