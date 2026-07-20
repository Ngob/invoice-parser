<?php

declare(strict_types=1);

namespace App\UseCase;

use App\Entity\ImportAttemptEntity;
use App\Enum\IngestionOutcomeEnum;

class IngestionReportFactory
{
    public function unchanged(): IngestionReportUseCase
    {
        return new IngestionReportUseCase(IngestionOutcomeEnum::Unchanged, 0);
    }

    public function accepted(ImportAttemptEntity $attempt): IngestionReportUseCase
    {
        return new IngestionReportUseCase(IngestionOutcomeEnum::Accepted, $attempt->invoiceCount());
    }
}
