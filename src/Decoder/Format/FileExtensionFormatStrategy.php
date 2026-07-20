<?php

declare(strict_types=1);

namespace App\Decoder\Format;

use App\Config\IngestionProfileConfig;
use App\Exception\DecodeFailedException;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem('fileExtension')]
class FileExtensionFormatStrategy implements FormatStrategyInterface
{
    public function __construct(
        private readonly JsonFormatStrategy $jsonStrategy,
        private readonly CsvFormatStrategy $csvStrategy,
    ) {
    }

    public function parse(SplFileInfo $file, IngestionProfileConfig $profile): array
    {
        return match (strtolower($file->getExtension())) {
            'json' => $this->jsonStrategy->parse($file, $profile),
            'csv' => $this->csvStrategy->parse($file, $profile),
            // DecodeFailedException et non une exception interne : une
            // extension inconnue est un probleme de FICHIER, il doit rejeter
            // la tentative avec une raison lisible, pas exploser en erreur 500.
            default => throw new DecodeFailedException(sprintf('No format strategy handles extension "%s".', $file->getExtension())),
        };
    }
}
