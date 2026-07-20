<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Tout ce qui varie d'un client a l'autre, et rien d'autre.
 */
readonly class IngestionProfileConfig
{
    /**
     * @param string                    $format   nom de la FormatStrategy a employer
     *                                            ('fileExtension', 'csv', 'json')
     * @param array<string, string|int> $fieldMap propriete d'InvoiceRowDto => cle dans la source
     */
    public function __construct(
        public string $format,
        public ?string $delimiter,
        public array $fieldMap,
    ) {
    }
}
