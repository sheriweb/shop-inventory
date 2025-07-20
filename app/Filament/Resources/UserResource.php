<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Role;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('User Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Account')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->required(fn (string $context): bool => $context === 'create'),
                                Forms\Components\Select::make('roles')
                                    ->label('Roles')
                                    ->multiple()
                                    ->relationship('roles', 'name')
                                    ->preload()
                                    ->required()
                                    ->afterStateUpdated(function ($state, $set) {
                                        if (in_array('admin', $state)) {
                                            $set('is_customer', false);
                                        }
                                    }),
                                Forms\Components\Toggle::make('is_customer')
                                    ->label('Is Customer?')
                                    ->default(false)
                                    ->disabled(fn ($get) => in_array('admin', $get('roles') ?? []))
                                    ->dehydrated(false)
                                    ->hiddenOn('edit'),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Roles & Permissions')
                            ->schema([
                                Forms\Components\Select::make('roles')
                                    ->relationship('roles', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->columnSpanFull()
                                    ->helperText('Select roles to assign to this user.'),
                                    
                                Forms\Components\CheckboxList::make('permissions')
                                    ->relationship('permissions', 'name')
                                    ->gridDirection('row')
                                    ->columns(3)
                                    ->searchable()
                                    ->hiddenOn('create')
                                    ->helperText('Additional permissions that override role permissions.'),
                            ])
                            ->columns(1)
                            ->hidden(fn ($record) => $record === null),
                        Forms\Components\Tabs\Tab::make('Profile')
                            ->schema([
                                Forms\Components\TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(20),
                                Forms\Components\Textarea::make('address')
                                    ->maxLength(65535)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['roles', 'permissions']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->formatStateUsing(function ($record) {
                        if (!$record->relationLoaded('roles')) {
                            $record->load('roles');
                        }
                        return $record->roles->isNotEmpty() ? $record->roles->pluck('name')->join(', ') : 'N/A';
                    })
                    ->color(function ($record) {
                        if (!$record->relationLoaded('roles')) {
                            $record->load('roles');
                        }
                        
                        if ($record->roles->contains('name', 'admin')) {
                            return 'success';
                        }
                        if ($record->roles->contains('name', 'customer')) {
                            return 'primary';
                        }
                        return 'gray';
                    }),
                Tables\Columns\IconColumn::make('is_customer')
                    ->boolean()
                    ->label('Customer'),
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
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple(),
                Tables\Filters\TernaryFilter::make('is_customer')
                    ->label('Customer Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
    
    public static function create(Form $form): Form
    {
        return $form
            ->schema(self::form($form)->getSchema())
            ->model(static::getModel())
            ->statePath('data')
            ->saveRelationshipsUsing(function (User $record, array $data) {
                if (isset($data['roles'])) {
                    $record->roles()->sync($data['roles']);
                    unset($data['roles']);
                }
                $record->fill($data)->save();
            });
    }
    
    public static function edit(Form $form): Form
    {
        return $form
            ->schema(self::form($form)->getSchema())
            ->model(static::getModel())
            ->statePath('data')
            ->saveRelationshipsUsing(function (User $record, array $data) {
                if (isset($data['roles'])) {
                    $record->roles()->sync($data['roles']);
                    unset($data['roles']);
                }
                $record->update($data);
            });
    }
}
