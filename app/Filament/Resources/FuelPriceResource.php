<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\FuelPriceResource\Pages;
use App\Models\FuelPrice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FuelPriceResource extends Resource
{
    use HasRoleAccess;

    public static function permissionPrefix(): string
    {
        return 'fuel_prices';
    }

    protected static ?string $model = FuelPrice::class;

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $modelLabel = 'Harga Solar';

    protected static ?string $pluralModelLabel = 'Harga Solar';

    protected static ?string $slug = 'harga-solar';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('valid_from')->label('Berlaku mulai')->required()
                ->unique(ignoreRecord: true)->native(false),
            Forms\Components\TextInput::make('price_per_liter')->label('Harga per liter')->numeric()->prefix('Rp')->required(),
            Forms\Components\TextInput::make('vendor')->label('Vendor / SPBU'),
            Forms\Components\Textarea::make('notes')->label('Catatan')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('valid_from', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('valid_from')->label('Berlaku mulai')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('price_per_liter')->label('Harga / liter')->money('IDR', locale: 'id'),
                Tables\Columns\TextColumn::make('vendor')->label('Vendor'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->slideOver(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageFuelPrices::route('/')];
    }
}
