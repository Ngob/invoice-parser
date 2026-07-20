<?php

declare(strict_types=1);

namespace App\Exception;

class UnknownClientException extends \InvalidArgumentException
{
    /**
     * @param list<string> $known
     */
    public static function named(string $client, array $known): self
    {
        return new self(sprintf(
            'Unknown client "%s". Configured clients: %s.',
            $client,
            $known === [] ? '(none)' : implode(', ', $known)
        ));
    }
}
