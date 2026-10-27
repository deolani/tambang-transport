<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TripsRelationManager extends RelationManager
{
    protected static string $relationship = 'trips';

    protected static ?string $title = 'Rincian trip';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->defaultSort('number')
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('No trip')->searchable(),
                Tables\Columns\TextColumn::make('trip_date')->label('Tanggal')->date('d M Y'),
                Tables\Columns\TextColumn::make('unit.code')->label('Unit'),
                Tables\Columns\TextColumn::make('ticket_number')->label('No tiket')->searchable(),
                Tables\Columns\TextColumn::make('tonnage')->label('Tonase')->numeric(2, ',', '.')->suffix(' t')->alignEnd()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->numeric(2, ',', '.')->suffix(' t')),
                Tables\Columns\TextColumn::make('amount')->label('Nilai')->money('IDR', locale: 'id')->alignEnd()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR', locale: 'id')),
            ]);
    }
}
