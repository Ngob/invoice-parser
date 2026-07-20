<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Entity\ImportAttemptEntity;
use App\Enum\IngestionOutcomeEnum;
use App\UseCase\IngestionReportFactory;
use PHPUnit\Framework\TestCase;

class IngestionReportUseCaseTest extends TestCase
{
    private function attempt(): ImportAttemptEntity
    {
        return new ImportAttemptEntity('acme', '/f.csv', new \DateTimeImmutable('2026-07-18'));
    }

    public function testSummaryRendersAcceptedCount(): void
    {
        $attempt = $this->attempt();
        $attempt->done(10);

        $report = (new IngestionReportFactory())->accepted($attempt);

        self::assertSame(IngestionOutcomeEnum::Accepted, $report->outcome);
        self::assertStringContainsString('10 importées', $report->summary());
    }

    public function testSummaryRendersUnchanged(): void
    {
        $report = (new IngestionReportFactory())->unchanged();

        self::assertStringContainsString('inchangé (hash identique)', $report->summary());
    }
}
