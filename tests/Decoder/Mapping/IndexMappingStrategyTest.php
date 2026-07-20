<?php

declare(strict_types=1);

namespace App\Tests\Decoder\Mapping;

use App\Config\IngestionProfileConfig;
use App\Decoder\Mapping\IndexMappingStrategy;
use PHPUnit\Framework\TestCase;

class IndexMappingStrategyTest extends TestCase
{
    /**
     * @param array<string, int> $fieldMap
     */
    private function profile(array $fieldMap): IngestionProfileConfig
    {
        return new IngestionProfileConfig('csv', "\t", $fieldMap);
    }

    public function testMapsPositionalColumnsToCanonicalProperties(): void
    {
        $strategy = new IndexMappingStrategy();
        $row = ['670.43', 'EUR', 'Frank Green', '2025-02-03'];

        $canonical = $strategy->toCanonical($row, $this->profile(['amount' => 0, 'currency' => 1, 'name' => 2, 'issuedAt' => 3]));

        self::assertSame([
            'amount' => '670.43',
            'currency' => 'EUR',
            'name' => 'Frank Green',
            'issuedAt' => '2025-02-03',
        ], $canonical);
    }

    public function testAMissingIndexBecomesNull(): void
    {
        $strategy = new IndexMappingStrategy();

        $canonical = $strategy->toCanonical(['670.43'], $this->profile(['amount' => 0, 'name' => 2]));

        self::assertSame('670.43', $canonical['amount']);
        self::assertArrayNotHasKey('name', $canonical);
    }
}
