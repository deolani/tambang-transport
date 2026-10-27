<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $guarded = [];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Permissions::flush());
        static::deleted(fn () => Permissions::flush());
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'key');
    }

    public function isSuperAdmin(): bool
    {
        return $this->key === Permissions::SUPER_ADMIN;
    }

    /** Pilihan role untuk form user: key => nama. */
    public static function options(bool $includeSuperAdmin = false): array
    {
        return static::query()
            ->when(! $includeSuperAdmin, fn ($q) => $q->where('key', '!=', Permissions::SUPER_ADMIN))
            ->orderBy('id')
            ->pluck('name', 'key')
            ->all();
    }
}
