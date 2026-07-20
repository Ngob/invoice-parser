<?php

declare(strict_types=1);

namespace App\Decoder\Mapping;

use App\Config\IngestionProfileConfig;

/**
 * Traduit une ligne brute (telle que l'encoder l'a rendue) en ligne aux cles
 * canoniques (== proprietes d'InvoiceRowDto)
 */
interface MappingStrategy
{
    /**
     * @param array<int|string, mixed> $row
     * @return array<string, mixed>
     */
    public function toCanonical(array $row, IngestionProfileConfig $profile): array;
}
