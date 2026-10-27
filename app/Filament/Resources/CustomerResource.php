<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    use HasRoleAccess;

    public static function permissionPrefix(): string
    {
        return 'customers';
    }

    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $modelLabel = 'Customer';

    protected static ?string $pluralModelLabel = 'Customer';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Perusahaan')->columns(2)->schema([
                Forms\Components\TextInput::make('code')->label('Kode customer')->required()
                    ->unique(ignoreRecord: true)->maxLength(20),
                Forms\Components\TextInput::make('name')->label('Nama perusahaan')->required()->maxLength(150),
                Forms\Components\TextInput::make('npwp')->label('NPWP'),
                Forms\Components\Toggle::make('is_pkp')->label('PKP')->default(true)->inline(false),
                Forms\Components\Textarea::make('address')->label('Alamat')->rows(2)->columnSpanFull(),
            ]),
            Forms\Components\Section::make('Kontak & penagihan')->columns(2)->schema([
                Forms\Components\TextInput::make('pic_name')->label('Nama PIC'),
                Forms\Components\TextInput::make('pic_phone')->label('No HP PIC')->tel(),
                Forms\Components\TextInput::make('billing_email')->label('Email tagihan')->email(),
                Forms\Components\TextInput::make('payment_terms_days')->label('Termin bayar')
                    ->numeric()->suffix('hari')->default(30)->required(),
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
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('pic_name')->label('PIC')->toggleable(),
                Tables\Columns\TextColumn::make('payment_terms_days')->label('Termin')->suffix(' hari'),
                Tables\Columns\IconColumn::make('is_pkp')->label('PKP')->boolean(),
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
        return ['index' => Pages\ManageCustomers::route('/')];
    }
}
