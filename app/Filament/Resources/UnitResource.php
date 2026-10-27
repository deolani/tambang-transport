<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\UnitResource\Pages;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UnitResource extends Resource
{
    use HasRoleAccess;

    public static function permissionPrefix(): string
    {
        return 'units';
    }

    protected static ?string $model = Unit::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $modelLabel = 'Unit';

    protected static ?string $pluralModelLabel = 'Unit / Armada';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identitas unit')->columns(2)->schema([
                Forms\Components\TextInput::make('code')->label('Kode unit')
                    ->placeholder('DT 1501')->required()->unique(ignoreRecord: true)->maxLength(20),
                Forms\Components\TextInput::make('plate_number')->label('No polisi')
                    ->placeholder('BE 1234 XX')->required()->unique(ignoreRecord: true)->maxLength(20),
                Forms\Components\TextInput::make('type')->label('Jenis')->default('Dump Truck')->required(),
                Forms\Components\TextInput::make('brand')->label('Merk / tipe'),
                Forms\Components\TextInput::make('year')->label('Tahun')->numeric()->minValue(1990)->maxValue(2100),
                Forms\Components\TextInput::make('capacity_ton')->label('Kapasitas')->numeric()->suffix('ton')->required(),
                Forms\Components\TextInput::make('chassis_number')->label('No rangka'),
                Forms\Components\TextInput::make('engine_number')->label('No mesin'),
            ]),
            Forms\Components\Section::make('Dokumen & status')->columns(3)->schema([
                Forms\Components\DatePicker::make('stnk_expiry')->label('Masa berlaku STNK')->native(false),
                Forms\Components\DatePicker::make('kir_expiry')->label('Masa berlaku KIR')->native(false),
                Forms\Components\Select::make('status')->options(Unit::STATUSES)->default('aktif')->required()
                    ->helperText('Otomatis jadi Breakdown saat ada laporan kerusakan terbuka.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('plate_number')->label('No polisi')->searchable(),
                Tables\Columns\TextColumn::make('type')->label('Jenis')->toggleable(),
                Tables\Columns\TextColumn::make('capacity_ton')->label('Kapasitas')->suffix(' t')->numeric(2, ',', '.'),
                Tables\Columns\TextColumn::make('stnk_expiry')->label('STNK')->date('d M Y')
                    ->color(fn ($state) => $state && $state->lte(now()->addDays(30)) ? 'danger' : null)->toggleable(),
                Tables\Columns\TextColumn::make('kir_expiry')->label('KIR')->date('d M Y')
                    ->color(fn ($state) => $state && $state->lte(now()->addDays(30)) ? 'danger' : null)->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => Unit::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'aktif' => 'success', 'breakdown' => 'danger', default => 'gray' }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(Unit::STATUSES),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->slideOver(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUnits::route('/')];
    }
}
