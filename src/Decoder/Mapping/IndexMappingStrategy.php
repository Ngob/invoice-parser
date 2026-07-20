<?php

declare(strict_types=1);

namespace App\Decoder\Mapping;

use App\Config\IngestionProfileConfig;

/**
 * Cles positionnelles (entieres) : le fieldMap donne propriete => index de
 * colonne. C'est la forme d'un CSV sans en-tete.
 */
class IndexMappingStrategy implements MappingStrategy
{
    /**
     * @param array<int|string, mixed> $row
     * @return array<string, mixed>
     */
    public function toCanonical(array $row, IngestionProfileConfig $profile): array
    {
        $canonical = [];
        foreach ($profile->fieldMap as $property => $index) {
            if (array_key_exists($index, $row)) {
                $canonical[(string) $property] = $row[$index];
            }
        }

        return $canonical;
    }
}
