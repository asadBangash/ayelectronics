<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Translation extends Model
{
    use HasFactory;
    
    protected $fillable = ['locale', 'group', 'key', 'value'];

    // Get Default Language (Cached)
    public static function getTrnaslactionsByLocale(string $locale)
    {
        $cacheKey = 'translations_by_locale_' . $locale;

        return Cache::rememberForever($cacheKey, function () use ($locale) {
            return self::where('locale', $locale)
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->group . '.' . $item->key => $item->value];
                })
                ->toArray();
        });
    }

    public static function forgetCachedTranslations(?string $locale = null): void
    {
        Cache::forget('translations_by_locale');

        if ($locale !== null) {
            Cache::forget('translations_by_locale_' . $locale);
            Cache::forget('translations_' . $locale);

            return;
        }

        try {
            if (Schema::hasTable('languages')) {
                foreach (DB::table('languages')->pluck('language') as $code) {
                    Cache::forget('translations_by_locale_' . $code);
                    Cache::forget('translations_' . $code);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        Cache::forget('languages_list');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'locale');
    }
}
