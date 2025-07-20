<?php

namespace App\Models;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable as BaseTranslatable;

trait Translatable
{
    use BaseTranslatable;

    /**
     * Get the translation for the current locale.
     *
     * @param  string|null  $locale
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function translateOrDefault($locale = null)
    {
        $translation = $this->translate($locale);
        
        if (!$translation) {
            $translation = $this->translate(config('app.fallback_locale'));
        }
        
        return $translation;
    }

    /**
     * Get the name attribute with fallback.
     *
     * @return string
     */
    public function getNameAttribute()
    {
        $translation = $this->translateOrDefault();
        return $translation ? $translation->name : '';
    }

    /**
     * Get the description attribute with fallback.
     *
     * @return string
     */
    public function getDescriptionAttribute()
    {
        $translation = $this->translateOrDefault();
        return $translation ? $translation->description : '';
    }
}
