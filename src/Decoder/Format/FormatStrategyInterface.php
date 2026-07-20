<?php

declare(strict_types=1);

namespace App\Decoder\Format;

use App\Config\IngestionProfileConfig;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.format_strategy')]
interface FormatStrategyInterface
{
    public function parse(SplFileInfo $file, IngestionProfileConfig $profile): array;
}
