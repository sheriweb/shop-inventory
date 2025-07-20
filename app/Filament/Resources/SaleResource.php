<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Filament\Resources\SaleResource\RelationManagers;
use App\Models\Sale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SaleResource extends Resource
{
    protected static ?string $model = \App\Models\Sale::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    
    protected static ?string $navigationGroup = 'Sales Management';
    
    protected static ?string $recordTitleAttribute = 'invoice_number';
    
    protected static ?int $navigationSort = 1;

    protected static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['subtotal'] = collect($data['items'] ?? [])->sum('total_price');
        $data['tax_amount'] = 0;
        $data['total_amount'] = $data['subtotal'] - ($data['discount_amount'] ?? 0);
        $data['due_amount'] = max(0, $data['total_amount'] - ($data['paid_amount'] ?? 0));
        $data['status'] = $data['due_amount'] <= 0 ? 'completed' : 'pending';
        
        return $data;
    }
    
    protected function updateAmounts($get, $set): void
    {
        try {
            // Helper function to safely convert to float with debug logging
            $toFloat = function($value) {
                $originalValue = $value;
                $type = gettype($value);
                
                if (is_numeric($value)) {
                    $result = (float) $value;
                    \Log::debug("Numeric conversion", ['original' => $originalValue, 'type' => $type, 'result' => $result]);
                    return $result;
                }
                
                if (is_string($value) && $value !== '') {
                    $cleaned = preg_replace('/[^0-9.-]/', '', $value);
                    $result = is_numeric($cleaned) ? (float) $cleaned : 0.0;
                    \Log::debug("String conversion", ['original' => $originalValue, 'cleaned' => $cleaned, 'result' => $result]);
                    return $result;
                }
                
                \Log::debug("Default conversion", ['original' => $originalValue, 'type' => $type, 'result' => 0.0]);
                return 0.0;
            };

            // Debug log all inputs
            $items = $get('items') ?? [];
            $discount = $get('discount_amount');
            $paid = $get('paid_amount');
            
            \Log::debug('Input values', [
                'items' => $items,
                'discount_amount' => ['value' => $discount, 'type' => gettype($discount)],
                'paid_amount' => ['value' => $paid, 'type' => gettype($paid)],
            ]);

            // Ensure all values are treated as floats for calculations
            $subtotal = $toFloat(collect($items)->sum('total_price'));
            $taxAmount = $subtotal * 0.16;
            
            // Get values safely
            $discountAmount = $toFloat($discount);
            $paidAmount = $toFloat($paid);
            
            // Debug log before calculations
            \Log::debug('Before calculations', [
                'subtotal' => $subtotal,
                'taxAmount' => $taxAmount,
                'discountAmount' => $discountAmount,
                'paidAmount' => $paidAmount,
                'types' => [
                    'subtotal' => gettype($subtotal),
                    'taxAmount' => gettype($taxAmount),
                    'discountAmount' => gettype($discountAmount),
                    'paidAmount' => gettype($paidAmount),
                ]
            ]);
            
            // Calculate amounts (without tax)
            $totalAmount = $subtotal - $discountAmount;
            $dueAmount = max(0, $totalAmount - $paidAmount);
            
            // Format and set values
            $format = fn($num) => is_numeric($num) ? number_format($num, 2, '.', '') : '0.00';
            
            $set('subtotal', $format($subtotal));
            $set('total_amount', $format($totalAmount));
            $set('due_amount', $format($dueAmount));
            $set('status', $dueAmount <= 0 ? 'completed' : 'pending');
            
            // Debug log after calculations
            \Log::debug('After calculations', [
                'totalAmount' => $totalAmount,
                'dueAmount' => $dueAmount,
                'status' => $dueAmount <= 0 ? 'completed' : 'pending'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in updateAmounts', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => [
                    'items' => $get('items') ?? [],
                    'discount' => $get('discount_amount'),
                    'paid' => $get('paid_amount')
                ]
            ]);
        }

        // Calculate other amounts (no tax)
        $discountAmount = $toFloat($get('discount_amount'));
        $totalAmount = $subtotal - $discountAmount;
        $paidAmount = $toFloat($get('paid_amount'));
        $dueAmount = max(0, $totalAmount - $paidAmount);
        
        // Format helper
        $format = fn($num) => number_format($num, 2, '.', '');
        
        // Update the form fields
        $set('subtotal', $format($subtotal));
        $set('total_amount', $format($totalAmount));
        $set('due_amount', $format($dueAmount));
        $set('status', $dueAmount <= 0 ? 'completed' : 'pending');
        
        // Log the calculation
        \Illuminate\Support\Facades\Log::info('Amounts updated:', [
            'subtotal' => $subtotal,
            'discount' => $discountAmount,
            'total' => $totalAmount,
            'paid' => $paidAmount,
            'due' => $dueAmount
        ]);
    }
    
    protected static function mutateFormDataBeforeSave(array $data): array
    {
        // Helper function to safely convert to float
        $toFloat = function($value) {
            if (is_numeric($value)) {
                return (float) $value;
            }
            if (is_string($value)) {
                $cleaned = preg_replace('/[^0-9.-]/', '', $value);
                return is_numeric($cleaned) ? (float) $cleaned : 0.0;
            }
            return 0.0;
        };

        // Calculate subtotal from items
        $subtotal = 0;
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                if (isset($item['total_price'])) {
                    $subtotal += $toFloat($item['total_price']);
                }
            }
        }

        // Calculate other amounts (no tax)
        $discountAmount = $toFloat($data['discount_amount'] ?? 0);
        $totalAmount = $subtotal - $discountAmount;
        $paidAmount = $toFloat($data['paid_amount'] ?? 0);
        $dueAmount = max(0, $totalAmount - $paidAmount);
        
        // Update data with calculated values
        $data['subtotal'] = $subtotal;
        $data['tax_amount'] = 0; // No tax
        $data['total_amount'] = $totalAmount;
        $data['discount_amount'] = $discountAmount;
        $data['paid_amount'] = $paidAmount;
        $data['due_amount'] = $dueAmount;
        $data['status'] = $dueAmount <= 0 ? 'completed' : 'pending';
        
        return $data;
    }
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('user_id')
                    ->default(auth()->id()),
                    
                Forms\Components\Hidden::make('subtotal')
                    ->default(0),
                    
                Forms\Components\Hidden::make('tax_amount')
                    ->default(0),
                    
                Forms\Components\Hidden::make('total_amount')
                    ->default(0),
                    
                Forms\Components\Hidden::make('due_amount')
                    ->default(0),
                    
                Forms\Components\Hidden::make('status')
                    ->default('pending'),
                    
                Forms\Components\Fieldset::make('Customer & Payment')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->searchDebounce(500)
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->unique('users', 'email')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('address')
                                    ->maxLength(500),
                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->required()
                                    ->default('password')
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                return \App\Models\User::create($data)->id;
                            }),
                        Forms\Components\TextInput::make('paid_amount')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function ($get, $set) {
                                // Helper function to safely convert to float
                                $toFloat = function($value) {
                                    if (is_numeric($value)) {
                                        return (float) $value;
                                    }
                                    if (is_string($value) && $value !== '') {
                                        $cleaned = preg_replace('/[^0-9.-]/', '', $value);
                                        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
                                    }
                                    return 0.0;
                                };

                                // Convert all values to float and handle empty values
                                $subtotal = $toFloat(collect($get('items') ?? [])->sum('total_price'));
                                $taxAmount = $subtotal * 0.16;
                                $discountAmount = $toFloat($get('discount_amount'));
                                $totalAmount = $subtotal + $taxAmount - $discountAmount;
                                $paidAmount = $toFloat($get('paid_amount'));
                                $dueAmount = max(0, $totalAmount - $paidAmount);
                                
                                // Format helper
                                $format = fn($num) => number_format($num, 2, '.', '');
                                
                                $set('subtotal', $format($subtotal));
                                $set('tax_amount', $format($taxAmount));
                                $set('total_amount', $format($totalAmount));
                                $set('due_amount', $format($dueAmount));
                                $set('status', $dueAmount <= 0 ? 'completed' : 'pending');
                            })
                            ->prefix('Rs. '),
                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Discount')
                            ->numeric()
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function ($get, $set) {
                                $subtotal = (float)collect($get('items') ?? [])->sum('total_price');
                                $discount = (float)($get('discount_amount') ?? 0);
                                $total = $subtotal - $discount;
                                $paid = (float)($get('paid_amount') ?? 0);
                                $due = max(0, $total - $paid);
                                
                                $set('total_amount', number_format($total, 2, '.', ''));
                                $set('due_amount', number_format($due, 2, '.', ''));
                                $set('status', $due <= 0 ? 'completed' : 'pending');
                            })
                            ->prefix('Rs. '),
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ])->columns(2),
                
                Forms\Components\Fieldset::make('Sale Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->options(function () {
                                        return \App\Models\Product::with(['translations' => function($query) {
                                            $query->where('locale', app()->getLocale());
                                        }])
                                        ->get()
                                        ->mapWithKeys(function ($product) {
                                            return [$product->id => $product->name . ' (' . $product->unit_type . ')' . ' - Stock: ' . $product->current_stock . ' ' . $product->unit_type];
                                        });
                                    })
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $product = \App\Models\Product::find($state);
                                        if ($product) {
                                            $set('unit_price', $product->price);
                                            $set('unit_type', $product->unit_type);
                                            $set('quantity', 1);
                                            $set('total_price', $product->price);
                                            
                                            // Log the product selection
                                            \Illuminate\Support\Facades\Log::info('Product selected in sale:', [
                                                'product_id' => $product->id,
                                                'product_name' => $product->name,
                                                'unit_type' => $product->unit_type,
                                                'price' => $product->price,
                                                'current_stock' => $product->current_stock,
                                                'quantity_in_gaz' => $product->quantity_in_gaz,
                                                'quantity_in_meter' => $product->quantity_in_meter
                                            ]);
                                        }
                                    })
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($get, $set) {
                                        $quantity = $get('quantity') ?? 0;
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $set('total_price', (float)$quantity * (float)$unitPrice);
                                    }),
                                Forms\Components\TextInput::make('unit_type')
                                    ->disabled()
                                    ->dehydrated()
                                    ->required(),
                                Forms\Components\TextInput::make('unit_price')
                                    ->numeric()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($get, $set) {
                                        $quantity = $get('quantity') ?? 0;
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $set('total_price', (float)$quantity * (float)$unitPrice);
                                    })
                                    ->prefix('Rs. '),
                                Forms\Components\TextInput::make('total_price')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->prefix('Rs. '),
                            ])
                            ->columns(5)
                            ->columnSpanFull()
                            ->createItemButtonLabel('Add Product')
                    ]),
                    
                // Summary Section
                Forms\Components\Section::make('Order Summary')
                    ->schema([
                        Forms\Components\Placeholder::make('subtotal_amount')
                            ->label('Subtotal')
                            ->content(function ($get) {
                                $subtotal = collect($get('items') ?? [])->sum('total_price');
                                return 'Rs. ' . number_format($subtotal, 2);
                            })
                            ->columnSpan(1),
                            
                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Discount')
                            ->numeric()
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function ($get, $set) {
                                $this->updateAmounts($get, $set);
                            })
                            ->prefix('Rs. ')
                            ->columnSpan(1),
                            

                        Forms\Components\Placeholder::make('total_amount')
                            ->label('Total Amount')
                            ->content(function ($get) {
                                $subtotal = (float) collect($get('items') ?? [])->sum('total_price');
                                $discount = is_numeric($get('discount_amount')) ? (float) $get('discount_amount') : 0;
                                $total = $subtotal - $discount;
                                return 'Rs. ' . number_format($total, 2);
                            })
                            ->columnSpan(1)
                            ->extraAttributes(['class' => 'font-bold']),
                            
                        Forms\Components\TextInput::make('paid_amount')
                            ->label('Amount Paid')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function ($get, $set) {
                                $this->updateAmounts($get, $set);
                            })
                            ->prefix('Rs. ')
                            ->columnSpan(1),
                            
                        Forms\Components\Placeholder::make('due_amount')
                            ->label('Amount Due')
                            ->content(function ($get) {
                                $subtotal = (float) collect($get('items') ?? [])->sum('total_price');
                                $discount = is_numeric($get('discount_amount')) ? (float) $get('discount_amount') : 0;
                                $total = $subtotal - $discount;
                                $paid = is_numeric($get('paid_amount')) ? (float) $get('paid_amount') : 0;
                                $due = max(0, $total - $paid);
                                return 'Rs. ' . number_format($due, 2);
                            })
                            ->columnSpan(1)
                            ->extraAttributes(['class' => 'font-bold']),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->formatStateUsing(fn ($state) => 'Rs. ' . number_format($state / 100, 2))
                    ->sortable()
                    ->alignRight(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid Amount')
                    ->formatStateUsing(fn ($state) => 'Rs. ' . number_format($state / 100, 2))
                    ->sortable()
                    ->alignRight(),
                Tables\Columns\TextColumn::make('due_amount')
                    ->label('Due Amount')
                    ->formatStateUsing(fn ($state) => 'Rs. ' . number_format($state / 100, 2))
                    ->sortable()
                    ->alignRight()
                    ->color(fn (string $state): string => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'completed',
                        'warning' => 'pending',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn ($query, $date) => $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['created_until'],
                                fn ($query, $date) => $query->whereDate('created_at', '<=', $date)
                            );
                    })
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }
}
