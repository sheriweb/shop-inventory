<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Sale extends Model
{
    protected $fillable = [
        'user_id',
        'customer_id',
        'invoice_number',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'due_amount',
        'notes',
        'status'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $sale) {
            $sale->invoice_number = 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            $sale->calculateTotals();

            // Log the sale creation
            \Illuminate\Support\Facades\Log::info('Creating new sale', [
                'invoice_number' => $sale->invoice_number,
                'status' => $sale->status,
                'total_amount' => $sale->total_amount
            ]);
        });

        static::updating(function (self $sale) {
            $sale->calculateTotals();

            // Log the sale update
            \Illuminate\Support\Facades\Log::info('Updating sale', [
                'sale_id' => $sale->id,
                'status' => $sale->status,
                'total_amount' => $sale->total_amount,
                'original_status' => $sale->getOriginal('status')
            ]);
        });

        // Update stock after sale items are created
        static::created(function (self $sale) {
            // We'll handle stock updates in the saved event to ensure all items are created
            // This is a workaround for the issue where items are created after the sale
        });

        // Handle stock updates when sale is saved (after items are created)
        static::saved(function (self $sale) {
            // Only process if this is a new sale (not an update)
            if ($sale->wasRecentlyCreated) {
                \DB::beginTransaction();

                try {
                    // Reload the sale with items and their products
                    $sale->load(['items' => function($query) {
                        $query->with(['product']);
                    }]);

                    // Log the sale creation
                    \Illuminate\Support\Facades\Log::info('Processing stock update for new sale', [
                        'sale_id' => $sale->id,
                        'invoice_number' => $sale->invoice_number,
                        'status' => $sale->status,
                        'items_count' => $sale->items->count(),
                        'items' => $sale->items->map(function($item) {
                            return [
                                'id' => $item->id,
                                'product_id' => $item->product_id,
                                'quantity' => $item->quantity,
                                'unit_type' => $item->unit_type,
                                'product' => $item->product ? [
                                    'id' => $item->product->id,
                                    'name' => $item->product->name,
                                    'unit_type' => $item->product->unit_type,
                                    'quantity_in_gaz' => $item->product->quantity_in_gaz,
                                    'quantity_in_meter' => $item->product->quantity_in_meter
                                ] : null
                            ];
                        })
                    ]);

                    // Lock the products for update
                    $productIds = $sale->items->pluck('product_id')->filter()->toArray();
                    if (!empty($productIds)) {
                        \App\Models\Product::whereIn('id', $productIds)->lockForUpdate()->get();
                    }

                    // Update stock for each item
                    foreach ($sale->items as $item) {
                        if ($item->product) {
                            $product = $item->product;
                            $quantity = (float) $item->quantity;
                            $unitType = $item->unit_type;
                            $field = "quantity_in_{$unitType}";

                            // Get current stock
                            $currentStock = (float)($product->$field ?? 0);

                            if ($currentStock < $quantity) {
                                throw new \Exception("Insufficient stock for product: {$product->name}. Available: {$currentStock} {$unitType}, Requested: {$quantity} {$unitType}");
                            }

                            $newStock = $currentStock - $quantity;

                            // Log before update
                            \Illuminate\Support\Facades\Log::info('Updating stock for product in sale', [
                                'sale_id' => $sale->id,
                                'product_id' => $product->id,
                                'product_name' => $product->name,
                                'field_updated' => $field,
                                'quantity' => $quantity,
                                'unit_type' => $unitType,
                                'previous_stock' => $currentStock,
                                'new_stock' => $newStock
                            ]);

                            // Update the stock directly in the database
                            \DB::table('products')
                                ->where('id', $product->id)
                                ->update([
                                    $field => $newStock,
                                    'updated_at' => now()
                                ]);

                            // Log after update
                            $product->refresh();
                            \Illuminate\Support\Facades\Log::info('Stock updated for product in sale', [
                                'sale_id' => $sale->id,
                                'product_id' => $product->id,
                                'current_quantity_in_gaz' => $product->quantity_in_gaz,
                                'current_quantity_in_meter' => $product->quantity_in_meter,
                                'product_unit_type' => $product->unit_type
                            ]);
                        }
                    }

                    \DB::commit();
                    \Illuminate\Support\Facades\Log::info('Stock updated successfully for new sale', [
                        'sale_id' => $sale->id,
                        'invoice_number' => $sale->invoice_number
                    ]);

                } catch (\Exception $e) {
                    \DB::rollBack();
                    \Illuminate\Support\Facades\Log::error('Error updating stock for new sale', [
                        'sale_id' => $sale->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            }
        });

        // Handle status changes (e.g., cancelling a sale)
        static::updated(function (self $sale) {
            // If status changed to cancelled, increase stock
            if ($sale->wasChanged('status') && $sale->status === 'cancelled') {
                try {
                    $sale->load('items.product');
                    \Illuminate\Support\Facades\Log::info('Processing stock increase for cancelled sale', [
                        'sale_id' => $sale->id,
                        'previous_status' => $sale->getOriginal('status')
                    ]);
                    $sale->updateStock('increase');
                    \Illuminate\Support\Facades\Log::info('Stock increased successfully for cancelled sale', [
                        'sale_id' => $sale->id
                    ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Error updating stock for cancelled sale', [
                        'sale_id' => $sale->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            }
        });

        // Handle sale deletion - return stock
        static::deleting(function (self $sale) {
            \DB::beginTransaction();

            try {
                // Load the sale with items and their products
                $sale->load(['items' => function($query) {
                    $query->with(['product']);
                }]);

                // Lock the products for update
                $productIds = $sale->items->pluck('product_id')->filter()->toArray();
                if (!empty($productIds)) {
                    \App\Models\Product::whereIn('id', $productIds)->lockForUpdate()->get();
                }

                \Illuminate\Support\Facades\Log::info('Processing stock increase for deleted sale', [
                    'sale_id' => $sale->id,
                    'status' => $sale->status,
                    'items_count' => $sale->items->count()
                ]);

                // Update stock for each item
                foreach ($sale->items as $item) {
                    if ($item->product) {
                        $product = $item->product;
                        $quantity = (float) $item->quantity;
                        $unitType = $item->unit_type;
                        $field = "quantity_in_{$unitType}";

                        // Get current stock
                        $currentStock = (float)($product->$field ?? 0);
                        $newStock = $currentStock + $quantity;

                        // Log before update
                        \Illuminate\Support\Facades\Log::info('Restoring stock for product in deleted sale', [
                            'sale_id' => $sale->id,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity' => $quantity,
                            'unit_type' => $unitType,
                            'previous_stock' => $currentStock,
                            'new_stock' => $newStock
                        ]);

                        // Update the stock directly in the database
                        \DB::table('products')
                            ->where('id', $product->id)
                            ->update([
                                $field => $newStock,
                                'updated_at' => now()
                            ]);

                        // Log after update
                        $product->refresh();
                        \Illuminate\Support\Facades\Log::info('Stock restored for product in deleted sale', [
                            'sale_id' => $sale->id,
                            'product_id' => $product->id,
                            'current_quantity_in_gaz' => $product->quantity_in_gaz,
                            'current_quantity_in_meter' => $product->quantity_in_meter
                        ]);
                    }
                }

                \DB::commit();
                \Illuminate\Support\Facades\Log::info('Stock increased successfully for deleted sale', [
                    'sale_id' => $sale->id
                ]);

            } catch (\Exception $e) {
                \DB::rollBack();
                \Illuminate\Support\Facades\Log::error('Error updating stock for deleted sale', [
                    'sale_id' => $sale->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    public function updateStock(string $action = 'decrease'): void
    {
        $this->load(['items' => function($query) {
            $query->with(['product' => function($q) {
                $q->withTrashed();
            }]);
        }]);

        \DB::beginTransaction();

        try {
            foreach ($this->items as $item) {
                if (!$item->product) {
                    \Log::warning("Product not found for sale item", [
                        'sale_id' => $this->id,
                        'sale_item_id' => $item->id,
                        'product_id' => $item->product_id
                    ]);
                    continue;
                }

                try {
                    $quantity = (float) $item->quantity;
                    $unitType = $item->unit_type;

                    // Log before updating stock
                    \Log::info("Updating stock for product:", [
                        'sale_id' => $this->id,
                        'sale_status' => $this->status,
                        'product_id' => $item->product->id,
                        'product_name' => $item->product->name,
                        'quantity' => $quantity,
                        'unit_type' => $unitType,
                        'operation' => $action,
                        'current_stock_gaz' => $item->product->quantity_in_gaz,
                        'current_stock_meter' => $item->product->quantity_in_meter,
                        'product_unit_type' => $item->product->unit_type
                    ]);

                    // Update the stock
                    $item->product->updateStock(
                        $quantity,
                        $unitType,
                        $action
                    );

                    // Refresh the product to get updated stock values
                    $item->product->refresh();

                    // Log the stock update for debugging
                    \Log::info("Stock updated successfully:", [
                        'sale_id' => $this->id,
                        'product_id' => $item->product->id,
                        'operation' => $action,
                        'new_stock_gaz' => $item->product->quantity_in_gaz,
                        'new_stock_meter' => $item->product->quantity_in_meter
                    ]);

                } catch (\Exception $e) {
                    \Log::error("Error updating stock:", [
                        'error' => $e->getMessage(),
                        'sale_id' => $this->id,
                        'sale_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'unit_type' => $item->unit_type,
                        'action' => $action,
                        'trace' => $e->getTraceAsString()
                    ]);

                    throw $e; // Re-throw to trigger rollback
                }
            }

            // Commit the transaction if all updates were successful
            \DB::commit();

        } catch (\Exception $e) {
            // Rollback the transaction on error
            \DB::rollBack();
            \Log::error("Transaction rolled back:", [
                'sale_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * @return void
     */
    public function calculateTotals(): void
    {
        $this->load('items');
        $this->subtotal = round($this->items->sum('total_price') * 100);
        $this->tax_amount = round($this->subtotal * 0.16);
        $this->total_amount = $this->subtotal + $this->tax_amount - ($this->discount_amount ?? 0);
        $this->due_amount = max(0, $this->total_amount - ($this->paid_amount ?? 0));
        $this->status = $this->due_amount <= 0 ? 'completed' : 'pending';
    }

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
