<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\HaulRouteResource\Pages;
use App\Models\HaulRoute;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HaulRouteResource extends Resource
{
    use HasRoleAccess;

    public static function permissionPrefix(): string
    {
        return 'routes';
    }

    protected static ?string $model = HaulRoute::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $modelLabel = 'Rute';

    protected static ?string $pluralModelLabel = 'Rute';

    protected static ?string $slug = 'rute';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Rute')->columns(3)->schema([
                Forms\Components\TextInput::make('code')->label('Kode rute')->placeholder('BSR - 107')
                    ->required()->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('origin')->label('Asal (pit)')->required(),
                Forms\Components\TextInput::make('destination')->label('Tujuan (jetty / stockpile)')->required(),
            ]),
            Forms\Components\Section::make('Standar per rit')
                ->description('Dipakai otomatis untuk menghitung HPP setiap trip.')
                ->columns(2)->schema([
                    Forms\Components\TextInput::make('distance_km')->label('Jarak')->numeric()->suffix('km')->required(),
                    Forms\Components\TextInput::make('std_fuel_liters')->label('Solar standar')->numeric()->suffix('liter')->required(),
                    Forms\Components\TextInput::make('allowance')->label('Uang jalan')->numeric()->prefix('Rp')->required(),
                    Forms\Components\TextInput::make('std_travel_hours')->label('Waktu tempuh')->numeric()->suffix('jam'),
                    Forms\Components\Select::make('status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])
                        ->default('aktif')->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('origin')->label('Asal')->wrap(),
                Tables\Columns\TextColumn::make('destination')->label('Tujuan')->wrap(),
                Tables\Columns\TextColumn::make('distance_km')->label('Jarak')->suffix(' km')->numeric(0),
                Tables\Columns\TextColumn::make('std_fuel_liters')->label('Solar')->suffix(' L')->numeric(0),
                Tables\Columns\TextColumn::make('allowance')->label('Uang jalan')->money('IDR', locale: 'id'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn ($state) => $state === 'aktif' ? 'success' : 'gray'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->slideOver(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageHaulRoutes::route('/')];
    }
}
