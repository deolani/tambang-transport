<?php

namespace App\Filament\Resources\TariffResource\Pages;

use App\Filament\Resources\TariffResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTariffs extends ManageRecords
{
    protected static string $resource = TariffResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Tambah Tarif')->slideOver()];
    }
}
