<?php

declare(strict_types=1);

namespace App\Config;

use App\Exception\UnknownClientException;

class IngestionProfilesConfig
{
    /**
     * @param array<string, array{format: string, delimiter: string|null, fields: array<string, string|int>}> $clients
     */
    public function __construct(private readonly array $clients)
    {
    }

    public function byClient(string $client): IngestionProfileConfig
    {
        $config = $this->clients[$client]
            ?? throw UnknownClientException::named($client, array_keys($this->clients));

        return new IngestionProfileConfig($config['format'], $config['delimiter'], $config['fields']);
    }
}
