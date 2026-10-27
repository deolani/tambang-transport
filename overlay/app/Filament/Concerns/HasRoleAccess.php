<?php

namespace App\Filament\Concerns;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;

/**
 * Hak akses menu dari database (Pengaturan › Role & Hak Akses).
 * Resource cukup mendefinisikan permissionPrefix(), misalnya 'units',
 * lalu dicek "units.view", "units.create", "units.edit", "units.delete".
 */
trait HasRoleAccess
{
    abstract public static function permissionPrefix(): string;

    public static function allows(string $action): bool
    {
        return Permissions::can(auth()->user(), static::permissionPrefix().'.'.$action);
    }

    /** Dipertahankan agar kode lama tetap jalan: cek nama role langsung. */
    protected static function userHas(array $roles): bool
    {
        $user = auth()->user();

        return $user && (Permissions::isSuperAdmin($user) || in_array($user->role, $roles, true));
    }

    public static function canViewAny(): bool
    {
        return static::allows('view');
    }

    public static function canCreate(): bool
    {
        return static::allows('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::allows('edit');
    }

    public static function canDelete(Model $record): bool
    {
        return static::allows('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::allows('delete');
    }
}
