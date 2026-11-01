<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    use HasRoleAccess;

    public static function permissionPrefix(): string
    {
        return 'payments';
    }

    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Keuangan';

    protected static ?string $modelLabel = 'Pembayaran';

    protected static ?string $pluralModelLabel = 'Pembayaran & Piutang';

    protected static ?string $slug = 'pembayaran';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('invoice_id')->label('Invoice')
                ->options(fn (?Payment $record) => Invoice::with('customer')
                    ->where(fn ($q) => $q->open()->when($record, fn ($x) => $x->orWhere('id', $record->invoice_id)))->get()
                    ->mapWithKeys(fn (Invoice $i) => [$i->id => $i->number.' · '.$i->customer->name.' · sisa Rp '.number_format($i->outstanding, 0, ',', '.')]))
                ->searchable()->required()->live(),
            Forms\Components\DatePicker::make('paid_at')->label('Tanggal bayar')->default(now())->required()->native(false),
            Forms\Components\TextInput::make('amount')->label('Jumlah')->numeric()->prefix('Rp')->required()
                ->minValue(1)
                ->maxValue(fn (Get $get, ?Payment $record) => Payment::maxAmount(Invoice::find($get('invoice_id')), $record))
                ->validationMessages(['max' => 'Melebihi sisa tagihan invoice (Rp :max).'])
                ->placeholder(fn (Get $get) => $get('invoice_id') ? number_format(Invoice::find($get('invoice_id'))?->outstanding ?? 0, 0, ',', '.') : null),
            Forms\Components\Select::make('method')->label('Metode')->options(Payment::METHODS)->default('transfer')->required(),
            Forms\Components\TextInput::make('reference')->label('No referensi / bukti'),
            Forms\Components\Textarea::make('notes')->label('Catatan')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('paid_at')->label('Tanggal')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('invoice.number')->label('Invoice')->searchable(),
                Tables\Columns\TextColumn::make('invoice.customer.name')->label('Customer')->wrap(),
                Tables\Columns\TextColumn::make('amount')->label('Jumlah')->money('IDR', locale: 'id')->alignEnd()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR', locale: 'id')),
                Tables\Columns\TextColumn::make('method')->label('Metode')->formatStateUsing(fn ($state) => Payment::METHODS[$state] ?? $state),
                Tables\Columns\TextColumn::make('reference')->label('Referensi')->toggleable(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePayments::route('/')];
    }
}
