<?php

declare(strict_types=1);

namespace App\Decoder\Format;

use App\Config\IngestionProfileConfig;
use App\Decoder\Mapping\IndexMappingStrategy;
use App\Decoder\Mapping\KeyMappingStrategy;
use App\Exception\DecodeFailedException;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

#[AsTaggedItem('csv')]
class CsvFormatStrategy implements FormatStrategyInterface
{
    public function __construct(
        private readonly DecoderInterface $serializer,
        private readonly IndexMappingStrategy $indexMapping,
        private readonly KeyMappingStrategy $keyMapping,
    ) {
    }

    public function format(): string
    {
        return 'csv';
    }

    public function parse(SplFileInfo $file, IngestionProfileConfig $profile): array
    {
        $bytes = file_get_contents($file->getPathname());
        if ($bytes === false) {
            throw new DecodeFailedException(sprintf('Cannot read %s payload at "%s".', $this->format(), $file->getPathname()));
        }

        $context = [CsvEncoder::DELIMITER_KEY => $profile->delimiter ?? ','];

        $mapper = $this->keyMapping;
        if ($this->isPositional($profile)) {
            // Sans NO_HEADERS_KEY, CsvEncoder mange la 1re ligne comme en-tete
            // et eclate "670.43" en cle imbriquee [670][43].
            $context[CsvEncoder::NO_HEADERS_KEY] = true;
            $mapper = $this->indexMapping;
        }

        $decoded = $this->serializer->decode($bytes, $this->format(), $context);

        // decode() rend une valeur PHP quelconque, pas forcement une
        // collection. `{}` et `[]` restent des collections vides valides.
        if (!is_array($decoded)) {
            throw new DecodeFailedException(sprintf('Cannot decode %s payload: expected a collection, got %s', $this->format(), get_debug_type($decoded)));
        }

        return array_map(
            fn (mixed $row): mixed => is_array($row) ? $mapper->toCanonical($row, $profile) : $row,
            array_values($decoded),
        );
    }

    private function isPositional(IngestionProfileConfig $profile): bool
    {
        foreach ($profile->fieldMap as $sourceKey) {
            if (is_int($sourceKey)) {
                return true;
            }
        }

        return false;
    }
}
