<?php

namespace App\Filament\Resources\HaulRouteResource\Pages;

use App\Filament\Resources\HaulRouteResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageHaulRoutes extends ManageRecords
{
    protected static string $resource = HaulRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Tambah Rute')->slideOver()];
    }
}
