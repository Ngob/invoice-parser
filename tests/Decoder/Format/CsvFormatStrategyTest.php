<?php

declare(strict_types=1);

namespace App\Tests\Decoder\Format;

use App\Config\IngestionProfileConfig;
use App\Decoder\Format\CsvFormatStrategy;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CsvFormatStrategyTest extends KernelTestCase
{
    private function strategy(): CsvFormatStrategy
    {
        /** @var CsvFormatStrategy $strategy */
        $strategy = static::getContainer()->get(CsvFormatStrategy::class);

        return $strategy;
    }

    /** parse() prend un SplFileInfo depuis B6 : les octets doivent atterrir sur disque. */
    private function file(string $bytes, string $extension): \SplFileInfo
    {
        $path = sys_get_temp_dir().'/strategy-'.uniqid('', true).'.'.$extension;
        file_put_contents($path, $bytes);
        $this->paths[] = $path;

        return new \SplFileInfo($path);
    }

    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        array_map(unlink(...), $this->paths);
        $this->paths = [];
        parent::tearDown();
    }

    public function testFormatIsCsv(): void
    {
        self::assertSame('csv', $this->strategy()->format());
    }

    public function testParsePositionalCsvToCanonicalRows(): void
    {
        $bytes = "670.43\tEUR\tFrank Green\t2025-02-03\n";
        $profile = new IngestionProfileConfig('csv', "\t", ['amount' => 0, 'currency' => 1, 'name' => 2, 'issuedAt' => 3]);

        self::assertSame([[
            'amount' => '670.43',
            'currency' => 'EUR',
            'name' => 'Frank Green',
            'issuedAt' => '2025-02-03',
        ]], $this->strategy()->parse($this->file($bytes, 'csv'), $profile));
    }
}
