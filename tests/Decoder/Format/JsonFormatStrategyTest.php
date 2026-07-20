<?php

declare(strict_types=1);

namespace App\Tests\Decoder\Format;

use App\Config\IngestionProfileConfig;
use App\Decoder\Format\JsonFormatStrategy;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class JsonFormatStrategyTest extends KernelTestCase
{
    private function strategy(): JsonFormatStrategy
    {
        /** @var JsonFormatStrategy $strategy */
        $strategy = static::getContainer()->get(JsonFormatStrategy::class);

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

    public function testFormatIsJson(): void
    {
        self::assertSame('json', $this->strategy()->format());
    }

    public function testParseNamedJsonToCanonicalRows(): void
    {
        $bytes = '[{"montant":"670.43","devise":"EUR","nom":"Frank Green","date":"2025-02-03"}]';
        $profile = new IngestionProfileConfig('json', null, ['amount' => 'montant', 'currency' => 'devise', 'name' => 'nom', 'issuedAt' => 'date']);

        self::assertSame([[
            'amount' => '670.43',
            'currency' => 'EUR',
            'name' => 'Frank Green',
            'issuedAt' => '2025-02-03',
        ]], $this->strategy()->parse($this->file($bytes, 'json'), $profile));
    }
}
