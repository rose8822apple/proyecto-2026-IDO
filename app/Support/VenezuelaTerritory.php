<?php

namespace App\Support;

use RuntimeException;

final class VenezuelaTerritory
{
    private static ?array $entities = null;

    public static function entities(): array
    {
        if (self::$entities === null) {
            $path = resource_path('data/venezuela-territory.json');
            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new RuntimeException('No se pudo leer el catálogo territorial de Venezuela.');
            }

            $catalog = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            self::$entities = $catalog['entities'];
        }

        return self::$entities;
    }

    public static function states(): array
    {
        return array_keys(self::entities());
    }

    public static function municipalitiesFor(?string $state): array
    {
        return array_keys(self::entities()[$state] ?? []);
    }

    public static function parishesFor(?string $state, ?string $municipality): array
    {
        return self::entities()[$state][$municipality] ?? [];
    }

    public static function catalog(): array
    {
        return self::entities();
    }
}
