<?php

declare(strict_types=1);

namespace App\Tests\Decoder\Mapping;

use App\Config\IngestionProfileConfig;
use App\Decoder\Mapping\KeyMappingStrategy;
use PHPUnit\Framework\TestCase;

class KeyMappingStrategyTest extends TestCase
{
    /**
     * @param array<string, string> $fieldMap
     */
    private function profile(array $fieldMap): IngestionProfileConfig
    {
        return new IngestionProfileConfig('json', null, $fieldMap);
    }

    public function testMapsNamedKeysToCanonicalProperties(): void
    {
        $strategy = new KeyMappingStrategy();
        $row = ['montant' => '670.43', 'devise' => 'EUR', 'nom' => 'Frank Green', 'date' => '2025-02-03'];

        $canonical = $strategy->toCanonical($row, $this->profile([
            'amount' => 'montant',
            'currency' => 'devise',
            'name' => 'nom',
            'issuedAt' => 'date',
        ]));

        self::assertSame([
            'amount' => '670.43',
            'currency' => 'EUR',
            'name' => 'Frank Green',
            'issuedAt' => '2025-02-03',
        ], $canonical);
    }

    public function testAnEmptyFieldMapThrows(): void
    {
        $strategy = new KeyMappingStrategy();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('at least one field mapping');

        $strategy->toCanonical(['name' => 'Frank Green'], $this->profile([]));
    }

    public function testAMissingSourceKeyBecomesNull(): void
    {
        $strategy = new KeyMappingStrategy();

        $canonical = $strategy->toCanonical(['montant' => '670.43'], $this->profile(['amount' => 'montant', 'name' => 'nom']));

        self::assertSame('670.43', $canonical['amount']);
        self::assertArrayNotHasKey('name', $canonical);
    }
}
