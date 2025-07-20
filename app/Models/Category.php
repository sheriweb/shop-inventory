<?php

namespace App\Models;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model implements TranslatableContract
{
    use Translatable;

    public $translationModel = 'App\Models\CategoryTranslation';
    public $translatedAttributes = ['name', 'description'];

    protected $fillable = [
        'is_active',
    ];

    protected $appends = [
        'name_en', 'name_ur', 'description_en', 'description_ur'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];


    /**
     * @return mixed|null
     */
    public function getNameEnAttribute(): mixed
    {
        return $this->translate('en')?->name;
    }

    /**
     * @return mixed|null
     */
    public function getNameUrAttribute(): mixed
    {
        return $this->translate('ur')?->name;
    }

    /**
     * @return mixed|null
     */
    public function getDescriptionEnAttribute(): mixed
    {
        return $this->translate('en')?->description;
    }

    /**
     * @return mixed|null
     */
    public function getDescriptionUrAttribute(): mixed
    {
        return $this->translate('ur')?->description;
    }

    /**
     * @return void
     */
    protected static function booted(): void
    {
        parent::booted();

        static::saving(function ($model) {
            $data = request()->all();

            // Debug: Log the incoming request data
            \Illuminate\Support\Facades\Log::info('Category Save Data:', $data);

            // Get the raw form data from the request
            $formData = [];

            // Process form data from Livewire/Filament format
            if (isset($data['components'][0]['updates']) && is_array($data['components'][0]['updates'])) {
                foreach ($data['components'][0]['updates'] as $key => $value) {
                    if (str_starts_with($key, 'data.')) {
                        $formKey = str_replace('data.', '', $key);
                        $formData[$formKey] = $value;
                    }
                }
            }

            // Process English translation
            if (isset($formData['en.name']) || isset($formData['en.description'])) {
                $model->translateOrNew('en')->name = $formData['en.name'] ?? null;
                $model->translateOrNew('en')->description = $formData['en.description'] ?? null;
            }

            // Process Urdu translation
            if (isset($formData['ur.name']) || isset($formData['ur.description'])) {
                $model->translateOrNew('ur')->name = $formData['ur.name'] ?? null;
                $model->translateOrNew('ur')->description = $formData['ur.description'] ?? null;

                // If Urdu name is provided but English is empty, copy Urdu to English
                if (!empty($formData['ur.name']) && empty($formData['en.name'] ?? null)) {
                    $model->translateOrNew('en')->name = $formData['ur.name'];
                }
            }

            // If English name is empty but Urdu has value, use Urdu name for English
            if (empty($formData['en.name'] ?? null) && !empty($formData['ur.name'] ?? null)) {
                $model->translateOrNew('en')->name = $formData['ur.name'];
            }

            // If Urdu name is empty but English has value, use English name for Urdu
            if (empty($formData['ur.name'] ?? null) && !empty($formData['en.name'] ?? null)) {
                $model->translateOrNew('ur')->name = $formData['en.name'];
            }
        });
    }

    public $useTranslationFallback = true;

    /**
     * @return HasMany
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Order by translated field
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $field
     * @param string $direction
     * @param string|null $relation
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeOrderByTranslation($query, string $field, string $direction = 'asc', string $relation = null)
    {
        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');

        $table = $relation ? $relation . '_translations' : 'category_translations';
        $foreignKey = $relation ? $relation . '_id' : 'category_id';

        return $query->orderBy(function ($q) use ($field, $locale, $fallbackLocale, $table, $foreignKey, $relation) {
            $q->select("$field")
                ->from($table)
                ->whereColumn("$table.$foreignKey", $relation ? "$relation.id" : 'categories.id')
                ->where(function ($q) use ($table, $locale, $fallbackLocale) {
                    $q->where("$table.locale", $locale)
                        ->orWhere("$table.locale", $fallbackLocale);
                })
                ->orderByRaw("FIELD(locale, '{$locale}', '{$fallbackLocale}')")
                ->limit(1);
        }, $direction);
    }
}
