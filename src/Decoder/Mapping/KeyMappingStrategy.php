<?php

declare(strict_types=1);

namespace App\Decoder\Mapping;

use App\Config\IngestionProfileConfig;

/**
 * Cles nommees (string) : le fieldMap donne propriete => cle source
 */
class KeyMappingStrategy implements MappingStrategy
{
    /**
     * @param array<int|string, mixed> $row
     * @return array<string, mixed>
     */
    public function toCanonical(array $row, IngestionProfileConfig $profile): array
    {
        if ($profile->fieldMap === []) {
            // Le noeud `fields` du bundle est isRequired() +
            // requiresAtLeastOneElement() : un fieldMap vide ne peut pas
            // venir de la config. Y arriver veut dire qu'un profil a ete
            // construit a la main, et l'identite silencieuse d'avant faisait
            // passer les cles SOURCE pour des proprietes canoniques — donc un
            // fichier decode en zero champ reconnu, sans un mot.
            throw new \LogicException('An ingestion profile must declare at least one field mapping.');
        }

        $canonical = [];
        foreach ($profile->fieldMap as $property => $sourceKey) {
            if (array_key_exists($sourceKey, $row)) {
                $canonical[(string) $property] = $row[$sourceKey];
            }
        }

        return $canonical;
    }
}
