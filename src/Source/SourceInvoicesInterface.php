<?php

declare(strict_types=1);

namespace App\Source;

use App\Dto\InvoiceRowDto;

interface SourceInvoicesInterface
{
    /**
     * @return list<InvoiceRowDto>
     */
    public function getInvoices(): array;
}
