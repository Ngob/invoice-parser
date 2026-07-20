<?php

declare(strict_types=1);

namespace App\Decoder\Format;

use App\Config\IngestionProfileConfig;
use App\Decoder\Mapping\KeyMappingStrategy;
use App\Exception\DecodeFailedException;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

/**
 * Decode le JSON et mappe par cles nommees. Le meme KeyMappingStrategy sert
 * json, csv-a-en-tete et (demain) xml : le mapping ne depend pas du format.
 */
#[AsTaggedItem('json')]
class JsonFormatStrategy implements FormatStrategyInterface
{
    public function __construct(
        private readonly DecoderInterface $serializer,
        private readonly KeyMappingStrategy $keyMapping,
    ) {
    }

    public function format(): string
    {
        return 'json';
    }

    // This does not handle edge case like 1e5 or 0.30000000000000004
    // Better to handle "float" as string directly in the imported json
    public function parse(SplFileInfo $file, IngestionProfileConfig $profile): array
    {
        $bytes = file_get_contents($file->getPathname());
        if ($bytes === false) {
            throw new DecodeFailedException(sprintf('Cannot read %s payload at "%s".', $this->format(), $file->getPathname()));
        }

        $decoded = $this->serializer->decode($bytes, $this->format(), []);

        // JSON valide mais scalaire (null, "a string", 42) decode tel quel,
        // et n'est pas une collection.
        if (!is_array($decoded)) {
            throw new DecodeFailedException(sprintf('Cannot decode %s payload: expected a collection, got %s', $this->format(), get_debug_type($decoded)));
        }

        return array_map(
            fn (mixed $row): mixed => is_array($row) ? $this->keyMapping->toCanonical($row, $profile) : $row,
            array_values($decoded),
        );
    }
}
