<?php

declare(strict_types=1);

namespace App\Tests;

class SmokeDatabaseTest extends DatabaseTestCase
{
    public function testDatabaseIsReachableAndInvoiceTableIsEmpty(): void
    {
        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM invoice'));
    }
}
