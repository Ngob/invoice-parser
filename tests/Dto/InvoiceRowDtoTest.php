<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\InvoiceRowDto;
use PHPUnit\Framework\TestCase;

class InvoiceRowDtoTest extends TestCase
{
    public function testInvoiceRowKeepsAmountAsAnExactString(): void
    {
        $row = new InvoiceRowDto();
        $row->name = 'Frank Green';
        $row->amount = '670.43';
        $row->currency = 'EUR';
        $row->issuedAt = new \DateTimeImmutable('2025-02-03');

        self::assertSame('Frank Green', $row->name);
        self::assertSame('670.43', $row->amount);
        self::assertSame('EUR', $row->currency);
        self::assertSame('2025-02-03', $row->issuedAt->format('Y-m-d'));
    }
}
