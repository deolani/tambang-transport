<?php

namespace App\Filament\Resources\BreakdownResource\Pages;

use App\Filament\Resources\BreakdownResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBreakdowns extends ManageRecords
{
    protected static string $resource = BreakdownResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Lapor Breakdown')->icon('heroicon-o-plus')->slideOver()];
    }
}
