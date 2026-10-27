<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\DriverResource\Pages;
use App\Models\Driver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DriverResource extends Resource
{
    use HasRoleAccess;

    public static function permissionPrefix(): string
    {
        return 'drivers';
    }

    protected static ?string $model = Driver::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $modelLabel = 'Sopir';

    protected static ?string $pluralModelLabel = 'Sopir';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Data sopir')->columns(2)->schema([
                Forms\Components\TextInput::make('name')->label('Nama')->required()->maxLength(100),
                Forms\Components\TextInput::make('nik')->label('NIK')->length(16)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('phone')->label('No HP')->tel()->required(),
                Forms\Components\Select::make('default_unit_id')->label('Unit default')
                    ->relationship('defaultUnit', 'code')->searchable()->preload(),
                Forms\Components\DatePicker::make('joined_at')->label('Tanggal masuk')->native(false),
                Forms\Components\Select::make('status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])
                    ->default('aktif')->required(),
            ]),
            Forms\Components\Section::make('SIM')->columns(3)->schema([
                Forms\Components\TextInput::make('license_number')->label('No SIM')->required(),
                Forms\Components\Select::make('license_type')->label('Jenis SIM')
                    ->options(['B1 Umum' => 'B1 Umum', 'B2 Umum' => 'B2 Umum'])->default('B2 Umum')->required(),
                Forms\Components\DatePicker::make('license_expiry')->label('Berlaku sampai')->required()->native(false),
            ]),
            Forms\Components\Section::make('Premi')->columns(2)->schema([
                Forms\Components\Select::make('bonus_scheme')->label('Skema premi')
                    ->options(['rit' => 'Per rit', 'ton' => 'Per ton'])->default('rit')->required(),
                Forms\Components\TextInput::make('bonus_rate')->label('Nilai premi')->numeric()->prefix('Rp')->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('phone')->label('No HP'),
                Tables\Columns\TextColumn::make('defaultUnit.code')->label('Unit'),
                Tables\Columns\TextColumn::make('license_type')->label('SIM')->toggleable(),
                Tables\Columns\TextColumn::make('license_expiry')->label('SIM berlaku')->date('d M Y')
                    ->color(fn ($state) => $state && $state->lte(now()->addDays(30)) ? 'danger' : null),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn ($state) => $state === 'aktif' ? 'success' : 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->slideOver(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDrivers::route('/')];
    }
}
