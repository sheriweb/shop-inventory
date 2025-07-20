<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CategoryResource extends Resource
{
    protected static ?string $model = \App\Models\Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Inventory Management';

    protected static function getFormTabs(): array
    {
        return [
            Forms\Components\Tabs\Tab::make('English')
                ->schema([
                    Forms\Components\TextInput::make('en.name')
                        ->label('Name (English)')
                        ->required(fn ($get) => $get('active_tab') === 'en')
                        ->maxLength(255)
                        ->afterStateHydrated(fn ($component, $record) => $component->state($record?->translate('en')?->name ?? '')),
                    Forms\Components\Textarea::make('en.description')
                        ->label('Description (English)')
                        ->maxLength(65535)
                        ->afterStateHydrated(fn ($component, $record) => $component->state($record?->translate('en')?->description ?? '')),
                ]),
            Forms\Components\Tabs\Tab::make('Urdu')
                ->schema([
                    Forms\Components\TextInput::make('ur.name')
                        ->label('Name (Urdu)')
                        ->required(fn ($get) => $get('active_tab') === 'ur')
                        ->maxLength(255)
                        ->afterStateHydrated(fn ($component, $record) => $component->state($record?->translate('ur')?->name ?? '')),
                    Forms\Components\Textarea::make('ur.description')
                        ->label('Description (Urdu)')
                        ->maxLength(65535)
                        ->afterStateHydrated(fn ($component, $record) => $component->state($record?->translate('ur')?->description ?? '')),
                ])
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('active_tab')
                    ->default('en')
                    ->dehydrated(false),

                Forms\Components\Tabs::make('Translations')
                    ->tabs([
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
                                    }),
                                Forms\Components\Textarea::make('en.description')
                                    ->label('Description (English)')
                                    ->maxLength(65535)
                                    ->default(fn ($record) => $record ? $record->translate('en')?->description : '')
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record && $record->hasTranslation('en')) {
                                            $component->state($record->translate('en')->description);
                                        }
                                    }),
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
                                    }),
                                Forms\Components\Textarea::make('ur.description')
                                    ->label('Description (Urdu)')
                                    ->maxLength(65535)
                                    ->default(fn ($record) => $record ? $record->translate('ur')?->description : '')
                                    ->extraInputAttributes(['dir' => 'rtl', 'style' => 'text-align: right;'])
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record && $record->hasTranslation('ur')) {
                                            $component->state($record->translate('ur')->description);
                                        }
                                    }),
                            ]),
                    ])
                    ->afterStateUpdated(function ($state, $set) {
                        $set('active_tab', $state);
                    })
                    ->reactive()
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->required()
                    ->default(true),
            ]);
    }

    public static function create(Form $form): Form
    {
        return parent::create($form)
            ->model(\App\Models\Category::class);
    }

    public static function edit(Form $form): Form
    {
        return parent::edit($form)
            ->model(\App\Models\Category::class);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Allow viewing categories for all authenticated users
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
                Tables\Columns\TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Products')
                    ->sortable(),
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
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('edit_categories')),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('delete_categories'))
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => auth()->user()->can('create_categories')),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
