<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, RoleResource::splitPermissions((array) ($data['permissions'] ?? [])));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return RoleResource::mergePermissions($data);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Hak akses role '.$this->getRecord()->name.' disimpan';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
