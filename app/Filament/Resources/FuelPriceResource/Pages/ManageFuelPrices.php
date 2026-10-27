<?php

namespace App\Filament\Resources\FuelPriceResource\Pages;

use App\Filament\Resources\FuelPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageFuelPrices extends ManageRecords
{
    protected static string $resource = FuelPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Tambah Harga')->slideOver()];
    }
}
