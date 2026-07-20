<?php

declare(strict_types=1);

namespace App\UseCase;

use App\Enum\IngestionOutcomeEnum;

readonly class IngestionReportUseCase
{
    public function __construct(
        public IngestionOutcomeEnum $outcome,
        public int $imported,
    ) {
    }

    public function summary(): string
    {
        return match ($this->outcome) {
            IngestionOutcomeEnum::Unchanged => 'inchangé (hash identique) — aucune écriture',
            IngestionOutcomeEnum::Accepted => sprintf('%d importées', $this->imported),
        };
    }
}
