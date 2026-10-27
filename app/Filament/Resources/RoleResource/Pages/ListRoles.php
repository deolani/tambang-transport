<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected ?string $subheading = 'Super Admin selalu punya semua akses. Role lain diatur dengan mencentang hak akses per menu.';

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Tambah Role')->icon('heroicon-o-plus')];
    }
}
