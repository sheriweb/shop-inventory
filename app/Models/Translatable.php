<?php

namespace App\Models;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable as BaseTranslatable;
use Illuminate\Database\Eloquent\Model;

trait Translatable
{
    use BaseTranslatable;

    /**
     * @param $locale
     * @return Model|null
     */
    public function translateOrDefault($locale = null): ?Model
    {
        $translation = $this->translate($locale);

        if (!$translation) {
            $translation = $this->translate(config('app.fallback_locale'));
        }

        return $translation;
    }

    /**
     * @return mixed|string
     */
    public function getNameAttribute(): mixed
    {
        $translation = $this->translateOrDefault();
        return $translation ? $translation->name : '';
    }

    /**
     * @return mixed|string
     */
    public function getDescriptionAttribute(): mixed
    {
        $translation = $this->translateOrDefault();
        return $translation ? $translation->description : '';
    }
}
