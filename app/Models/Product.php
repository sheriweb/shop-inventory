<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;

class Product extends Model implements TranslatableContract
{
    use Translatable;

    public $translationModel = 'App\Models\ProductTranslation';
    public $translatedAttributes = ['name', 'description'];
    public $useTranslationFallback = true;

    /**
     * @var string[]
     */
    protected $fillable = [
        'category_id',
        'sku',
        'price',
        'cost_price',
        'quantity_in_gaz',
        'quantity_in_meter',
        'min_stock_level',
        'unit_type',
        'is_active',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'price' => 'integer', // Stored in paisa
        'cost_price' => 'integer', // Stored in paisa
        'quantity_in_meter' => 'decimal:2',
        'quantity_in_gaz' => 'decimal:2',
        'min_stock_level' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @var string[]
     */
    protected $appends = [
        'name_en', 'name_ur', 'description_en', 'description_ur',
        'formatted_price', 'current_stock', 'is_low_stock', 'stock_quantity'
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
     * @return float|int
     */
    public function getCurrentStockAttribute(): float|int
    {
        if ($this->unit_type === 'gaz') {
            return (float) $this->quantity_in_gaz;
        } elseif ($this->unit_type === 'meter') {
            return (float) $this->quantity_in_meter;
        }
        return 0;
    }

    /**
     * @return bool
     */
    public function getIsLowStockAttribute(): bool
    {
        if ($this->min_stock_level <= 0) {
            return false;
        }

        return $this->unit_type === 'gaz'
            ? $this->quantity_in_gaz <= $this->min_stock_level
            : $this->quantity_in_meter <= $this->min_stock_level;
    }

    /**
     * @return void
     */
    protected static function booted(): void
    {
        parent::booted();

        static::saving(function ($model) {
            $data = request()->all();

            \Illuminate\Support\Facades\Log::info('Product Save Data:', $data);

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

            // Handle price - store directly as entered
            if (isset($formData['price'])) {
                $model->price = (float) $formData['price'];
            }

            if (isset($formData['cost_price'])) {
                $model->cost_price = $formData['cost_price'] ? (float) $formData['cost_price'] : null;
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

    /**
     * @return string
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'Rs. ' . number_format($this->price, 0);
    }

    /**
     * @param $value
     * @return void
     */
    public function setPriceAttribute($value): void
    {
        $this->attributes['price'] = (float) $value;
    }

    /**
     * @param $value
     * @return void
     */
    public function setCostPriceAttribute($value): void
    {
        $this->attributes['cost_price'] = $value ? (float) $value : null;
    }

    /**
     * @return string|null
     */
    public function getFormattedCostPriceAttribute(): ?string
    {
        return $this->cost_price ? 'Rs. ' . number_format($this->cost_price, 0) : null;
    }

    /**
     * @return BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function getStockQuantityAttribute()
    {
        return $this->unit_type === 'gaz' ? $this->quantity_in_gaz : $this->quantity_in_meter;
    }

    /**
     * @param $query
     * @param string $field
     * @param string $direction
     * @param string|null $relation
     * @return mixed
     */
    public function scopeOrderByTranslation($query, string $field, string $direction = 'asc', string $relation = null): mixed
    {
        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');

        $table = $relation ? $relation . '_translations' : 'product_translations';
        $foreignKey = $relation ? $relation . '_id' : 'product_id';

        return $query->orderBy(function($q) use ($field, $locale, $fallbackLocale, $table, $foreignKey, $relation) {
            $q->select("$field")
                ->from($table)
                ->whereColumn("$table.$foreignKey", $relation ? "$relation.id" : 'products.id')
                ->where(function($q) use ($table, $locale, $fallbackLocale) {
                    $q->where("$table.locale", $locale)
                      ->orWhere("$table.locale", $fallbackLocale);
                })
                ->orderByRaw("FIELD(locale, '{$locale}', '{$fallbackLocale}')")
                ->limit(1);
        }, $direction);
    }

    /**
     * @param float $quantity
     * @param string $unitType
     * @param string $operation
     * @return void
     * @throws \Exception
     */
    public function updateStock(float $quantity, string $unitType, string $operation = 'decrease'): void
    {
        // Validate unit type
        if (!in_array($unitType, ['meter', 'gaz'])) {
            throw new \Exception("Invalid unit type: {$unitType}");
        }

        $field = "quantity_in_{$unitType}";

        // Get current stock before any changes
        $currentStock = (float)($this->$field ?? 0);
        $quantity = (float)$quantity;

        // Log before making any changes
        Log::info("Starting stock update:", [
            'product_id' => $this->id,
            'product_name' => $this->name,
            'product_unit_type' => $this->unit_type,
            'operation' => $operation,
            'quantity' => $quantity,
            'unit_type' => $unitType,
            'current_stock' => $currentStock,
            'current_stock_gaz' => $this->quantity_in_gaz,
            'current_stock_meter' => $this->quantity_in_meter
        ]);

        // Calculate new stock value
        if ($operation === 'decrease') {
            // Check if we have enough stock before decreasing
            if ($currentStock < $quantity) {
                $error = "Insufficient stock for product: {$this->name}. Available: {$currentStock} {$unitType}, Requested: {$quantity} {$unitType}";
                Log::error($error);
                throw new \Exception($error);
            }

            $newStock = $currentStock - $quantity;
        } else {
            // For increase operation
            $newStock = $currentStock + $quantity;
        }

        // Update the stock
        $this->$field = max(0, $newStock);

        // Save the changes without triggering events to prevent loops
        $saved = $this->saveQuietly();

        if (!$saved) {
            $error = "Failed to save stock update for product: {$this->id}";
            Log::error($error);
            throw new \Exception($error);
        }

        // Refresh the model to get the latest values
        $this->refresh();

        // Log the stock update
        Log::info("Stock update completed:", [
            'product_id' => $this->id,
            'product_name' => $this->name,
            'operation' => $operation,
            'unit_type' => $unitType,
            'quantity' => $quantity,
            'previous_stock' => $currentStock,
            'new_stock' => $this->$field,
            'current_stock_gaz' => $this->quantity_in_gaz,
            'current_stock_meter' => $this->quantity_in_meter,
            'saved' => $saved
        ]);
    }
}

class ProductTranslation extends Model
{
    public $timestamps = false;
    protected $fillable = ['name', 'description'];
}
