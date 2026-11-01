<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Pembayaran';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('paid_at')->label('Tanggal bayar')->default(now())->required()->native(false),
            Forms\Components\TextInput::make('amount')->label('Jumlah')->numeric()->prefix('Rp')->required()
                ->default(fn () => $this->getOwnerRecord()->outstanding)
                ->minValue(1)
                ->maxValue(fn (?Payment $record) => Payment::maxAmount($this->getOwnerRecord(), $record))
                ->validationMessages(['max' => 'Melebihi sisa tagihan invoice (Rp :max).']),
            Forms\Components\Select::make('method')->label('Metode')->options(Payment::METHODS)->default('transfer')->required(),
            Forms\Components\TextInput::make('reference')->label('No referensi / bukti'),
            Forms\Components\Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                Tables\Columns\TextColumn::make('paid_at')->label('Tanggal')->date('d M Y'),
                Tables\Columns\TextColumn::make('amount')->label('Jumlah')->money('IDR', locale: 'id')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR', locale: 'id')),
                Tables\Columns\TextColumn::make('method')->label('Metode')->formatStateUsing(fn ($state) => Payment::METHODS[$state] ?? $state),
                Tables\Columns\TextColumn::make('reference')->label('Referensi'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Catat Pembayaran')
                    ->visible(fn () => in_array($this->getOwnerRecord()->status, ['belum_lunas', 'sebagian'], true)),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
