<?php

namespace App\Imports;

use App\Models\User;
use App\Support\Permissions;

class ImporterRegistry
{
    /** Urutan = urutan yang disarankan saat import pertama kali. */
    public static function all(): array
    {
        return [
            Importers\UnitImporter::class,
            Importers\DriverImporter::class,
            Importers\CustomerImporter::class,
            Importers\RouteImporter::class,
            Importers\TariffImporter::class,
            Importers\FuelPriceImporter::class,
            Importers\TripImporter::class,
            Importers\BreakdownImporter::class,
        ];
    }

    /** Hak akses "import.<jenis>" diatur di Role & Hak Akses. */
    public static function allowed(?User $user, string $class): bool
    {
        return Permissions::can($user, 'import.'.$class::key());
    }

    /** Importer yang boleh dipakai user: key => label. */
    public static function optionsFor(?User $user): array
    {
        $out = [];
        foreach (static::all() as $i => $class) {
            if (static::allowed($user, $class)) {
                $out[$class::key()] = ($i + 1).'. '.$class::label();
            }
        }

        return $out;
    }

    /** @return class-string<BaseImporter>|null */
    public static function find(?string $key): ?string
    {
        foreach (static::all() as $class) {
            if ($class::key() === $key) {
                return $class;
            }
        }

        return null;
    }
}
