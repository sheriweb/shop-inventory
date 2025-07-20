<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class ProductResource extends Resource
{
    protected static ?string $model = \App\Models\Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Inventory Management';

    protected static ?string $recordTitleAttribute = 'name';

    protected static function getFormTabs(): array
    {
        return [
            Forms\Components\Tabs\Tab::make('English')
                ->schema([
                    Forms\Components\TextInput::make('en.name')
                        ->label('Name (English)')
                        ->required(fn ($get) => $get('active_tab') === 'en')
                        ->maxLength(255)
                        ->default(fn ($record) => $record ? $record->translate('en')?->name : '')
                        ->afterStateHydrated(function ($component, $record) {
                            if ($record && $record->hasTranslation('en')) {
                                $component->state($record->translate('en')->name);
                            }
                        })
                        ->dehydrateStateUsing(fn ($state) => $state ?? ''),
                    Forms\Components\Textarea::make('en.description')
                        ->label('Description (English)')
                        ->maxLength(65535)
                        ->default(fn ($record) => $record ? $record->translate('en')?->description : '')
                        ->afterStateHydrated(function ($component, $record) {
                            if ($record && $record->hasTranslation('en')) {
                                $component->state($record->translate('en')->description);
                            }
                        })
                        ->dehydrateStateUsing(fn ($state) => $state ?? ''),
                ]),
            Forms\Components\Tabs\Tab::make('Urdu')
                ->schema([
                    Forms\Components\TextInput::make('ur.name')
                        ->label('Name (Urdu)')
                        ->required(fn ($get) => $get('active_tab') === 'ur')
                        ->maxLength(255)
                        ->default(fn ($record) => $record ? $record->translate('ur')?->name : '')
                        ->extraInputAttributes(['dir' => 'rtl', 'style' => 'text-align: right;'])
                        ->afterStateHydrated(function ($component, $record) {
                            if ($record && $record->hasTranslation('ur')) {
                                $component->state($record->translate('ur')->name);
                            }
                        })
                        ->dehydrateStateUsing(fn ($state) => $state ?? ''),
                    Forms\Components\Textarea::make('ur.description')
                        ->label('Description (Urdu)')
                        ->maxLength(65535)
                        ->default(fn ($record) => $record ? $record->translate('ur')?->description : '')
                        ->extraInputAttributes(['dir' => 'rtl', 'style' => 'text-align: right;'])
                        ->afterStateHydrated(function ($component, $record) {
                            if ($record && $record->hasTranslation('ur')) {
                                $component->state($record->translate('ur')->description);
                            }
                        })
                        ->dehydrateStateUsing(fn ($state) => $state ?? ''),
                ]),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Hidden field to track active tab
                Forms\Components\Hidden::make('active_tab')
                    ->dehydrated(false)
                    ->default('en'),

                Forms\Components\Tabs::make('Product Translations')
                    ->tabs(self::getFormTabs())
                    ->afterStateUpdated(function ($state, $set) {
                        $set('active_tab', $state);
                    })
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),

                Forms\Components\Select::make('category_id')
                    ->relationship(
                        name: 'category',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query) {
                            $locale = app()->getLocale();
                            $fallbackLocale = config('app.fallback_locale', 'en');

                            return $query->join('category_translations', function($join) use ($locale, $fallbackLocale) {
                                    $join->on('categories.id', '=', 'category_translations.category_id')
                                        ->where('category_translations.locale', $locale)
                                        ->orWhere('category_translations.locale', $fallbackLocale);
                                })
                                ->select('categories.*', 'category_translations.name as name', 'category_translations.locale as locale')
                                ->orderBy('category_translations.name')
                                ->distinct('categories.id');
                        }
                    )
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->name ?? 'Untitled')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('price')
                    ->label('Selling Price (PKR)')
                    ->required()
                    ->numeric()
                    ->prefix('₨')
                    ->maxValue(42949672.95)
                    ->step(0.01)
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record) {
                            $component->state(number_format($record->price, 2, '.', ''));
                        } else {
                            $component->state('0.00');
                        }
                    })
                    ->dehydrateStateUsing(function ($state) {
                        // Convert to float without any formatting
                        $cleanValue = str_replace(',', '', $state);
                        return is_numeric($cleanValue) ? (float) $cleanValue : 0;
                    }),

                Forms\Components\TextInput::make('cost_price')
                    ->label('Cost Price (PKR)')
                    ->numeric()
                    ->prefix('₨')
                    ->step(0.01)
                    ->maxValue(42949672.95)
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record && $record->cost_price) {
                            $component->state(number_format($record->cost_price, 2, '.', ''));
                        } else {
                            $component->state('0.00');
                        }
                    })
                    ->dehydrateStateUsing(function ($state) {
                        // Convert to float without any formatting
                        $cleanValue = str_replace(',', '', $state);
                        return is_numeric($cleanValue) ? (float) $cleanValue : 0;
                    }),

                Forms\Components\TextInput::make('quantity_in_gaz')
                    ->label('Quantity (Gaz)')
                    ->numeric()
                    ->default(0),

                Forms\Components\TextInput::make('quantity_in_meter')
                    ->label('Quantity (Meter)')
                    ->numeric()
                    ->default(0),

                Forms\Components\TextInput::make('min_stock_level')
                    ->label('Min Stock Level')
                    ->numeric()
                    ->default(0),

                Forms\Components\Select::make('unit_type')
                    ->options([
                        'gaz' => 'Gaz',
                        'meter' => 'Meter',
                    ])
                    ->default('gaz')
                    ->required(),

                Forms\Components\Toggle::make('is_active')
                    ->required()
                    ->default(true),
            ]);
    }

    public static function create(Form $form): Form
    {
        return parent::create($form)
            ->model(Product::class);
    }

    public static function edit(Form $form): Form
    {
        return parent::edit($form)
            ->model(Product::class);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Allow viewing products for all authenticated users
                // Additional filtering can be added here if needed
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('translations', function($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByTranslation('name', $direction);
                    }),
                Tables\Columns\TextColumn::make('sku')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->formatStateUsing(function (Model $record) {
                        if (!$record->category) return 'N/A';
                        return $record->category->translate('en')?->name ?? 'N/A';
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->money('PKR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Current Stock')
                    ->formatStateUsing(function ($record) {
                        $stock = $record->current_stock;
                        $unitType = $record->unit_type;
                        $formattedStock = number_format($stock, 2);

                        return "{$formattedStock} {$unitType}";
                    })
                    ->color(fn ($record) => $record->is_low_stock ? 'danger' : 'success')
                    ->description(fn ($record) => $record->is_low_stock ? 'Low stock' : 'In stock')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_in_gaz')
                    ->label('Stock (Gaz)')
                    ->formatStateUsing(fn ($record) => number_format($record->quantity_in_gaz, 2) . ' gaz')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('quantity_in_meter')
                    ->label('Stock (Meter)')
                    ->formatStateUsing(fn ($record) => number_format($record->quantity_in_meter, 2) . ' m')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(function () {
                        return \App\Models\Category::where('is_active', true)
                            ->with('translations')
                            ->get()
                            ->mapWithKeys(function ($category) {
                                return [$category->id => $category->translate('en')?->name ?? 'N/A'];
                            });
                    }),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Filters\TernaryFilter::make('low_stock')
                    ->label('Low Stock')
                    ->queries(
                        true: fn ($query) => $query->whereColumn('quantity_in_gaz', '<=', 'min_stock_level')
                            ->orWhereColumn('quantity_in_meter', '<=', 'min_stock_level'),
                        false: fn ($query) => $query->whereColumn('quantity_in_gaz', '>', 'min_stock_level')
                            ->whereColumn('quantity_in_meter', '>', 'min_stock_level')
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('edit_products')),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('delete_products'))
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
