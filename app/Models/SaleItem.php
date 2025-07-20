<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_type',
        'unit_price',
        'total_price'
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($saleItem) {
            // Ensure quantity is a valid number
            $saleItem->quantity = (float)$saleItem->quantity;

            // Ensure unit_price is a valid number
            $saleItem->unit_price = (float)$saleItem->unit_price;

            // Calculate total price
            $saleItem->total_price = $saleItem->quantity * $saleItem->unit_price;

            // Log the creation of the sale item
            \Illuminate\Support\Facades\Log::info('Creating sale item:', [
                'sale_id' => $saleItem->sale_id,
                'product_id' => $saleItem->product_id,
                'quantity' => $saleItem->quantity,
                'unit_type' => $saleItem->unit_type,
                'unit_price' => $saleItem->unit_price,
                'total_price' => $saleItem->total_price
            ]);
        });

        static::created(function ($saleItem) {
            // Log when a sale item is successfully created
            \Illuminate\Support\Facades\Log::info('Sale item created:', [
                'id' => $saleItem->id,
                'sale_id' => $saleItem->sale_id,
                'product_id' => $saleItem->product_id,
                'quantity' => $saleItem->quantity,
                'unit_type' => $saleItem->unit_type,
                'total_price' => $saleItem->total_price
            ]);

            // Update product stock
            try {
                $product = $saleItem->product;
                if ($product) {
                    $field = 'quantity_in_' . $saleItem->unit_type;
                    $currentStock = (float) $product->$field;
                    $newStock = $currentStock - (float) $saleItem->quantity;

                    // Log before update
                    \Illuminate\Support\Facades\Log::info('Updating stock from sale item:', [
                        'sale_item_id' => $saleItem->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $saleItem->quantity,
                        'unit_type' => $saleItem->unit_type,
                        'current_stock' => $currentStock,
                        'new_stock' => $newStock
                    ]);

                    // Update stock directly in database
                    \DB::table('products')
                        ->where('id', $product->id)
                        ->update([
                            $field => $newStock,
                            'updated_at' => now()
                        ]);

                    // Log after update
                    $product->refresh();
                    \Illuminate\Support\Facades\Log::info('Stock updated from sale item:', [
                        'product_id' => $product->id,
                        'quantity_in_gaz' => $product->quantity_in_gaz,
                        'quantity_in_meter' => $product->quantity_in_meter
                    ]);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error updating stock from sale item:', [
                    'sale_item_id' => $saleItem->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Don't throw exception to prevent rollback of the sale item creation
            }
        });
    }

    /**
     * @return BelongsTo
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
