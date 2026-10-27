<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\TariffResource\Pages;
use App\Models\Tariff;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TariffResource extends Resource
{
    use HasRoleAccess;

    public static function permissionPrefix(): string
    {
        return 'tariffs';
    }

    protected static ?string $model = Tariff::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $modelLabel = 'Tarif';

    protected static ?string $pluralModelLabel = 'Tarif';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\Select::make('customer_id')->label('Customer')
                    ->relationship('customer', 'name')->searchable()->preload()->required(),
                Forms\Components\Select::make('haul_route_id')->label('Rute')
                    ->relationship('haulRoute', 'code')->searchable()->preload()->required(),
                Forms\Components\Select::make('unit_basis')->label('Satuan')
                    ->options(['ton' => 'Per ton', 'rit' => 'Per rit'])->default('ton')->required(),
                Forms\Components\TextInput::make('rate')->label('Tarif')->numeric()->prefix('Rp')->required(),
                Forms\Components\DatePicker::make('valid_from')->label('Berlaku mulai')->required()->native(false)
                    ->rules([
                        fn (Get $get, ?Tariff $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                            $clash = Tariff::query()
                                ->where('customer_id', $get('customer_id'))
                                ->where('haul_route_id', $get('haul_route_id'))
                                ->whereDate('valid_from', $value)
                                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                ->exists();
                            if ($clash) {
                                $fail('Sudah ada tarif untuk customer + rute ini dengan tanggal mulai yang sama.');
                            }
                        },
                    ]),
                Forms\Components\DatePicker::make('valid_to')->label('Berlaku sampai')->native(false)
                    ->helperText('Kosongkan bila masih berlaku.')->afterOrEqual('valid_from'),
                Forms\Components\TextInput::make('contract_number')->label('No kontrak'),
                Forms\Components\Textarea::make('notes')->label('Catatan')->rows(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('valid_from', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')->label('Customer')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('haulRoute.code')->label('Rute'),
                Tables\Columns\TextColumn::make('rate')->label('Tarif')->money('IDR', locale: 'id')
                    ->description(fn (Tariff $record) => 'per '.$record->unit_basis),
                Tables\Columns\TextColumn::make('valid_from')->label('Mulai')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('valid_to')->label('Sampai')->date('d M Y')->placeholder('Masih berlaku'),
                Tables\Columns\TextColumn::make('contract_number')->label('Kontrak')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->slideOver(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageTariffs::route('/')];
    }
}
