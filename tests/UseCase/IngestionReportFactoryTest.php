<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Entity\ImportAttemptEntity;
use App\Enum\IngestionOutcomeEnum;
use App\UseCase\IngestionReportFactory;
use PHPUnit\Framework\TestCase;

class IngestionReportFactoryTest extends TestCase
{
    private function attempt(): ImportAttemptEntity
    {
        return new ImportAttemptEntity('acme', '/f.csv', new \DateTimeImmutable('2026-07-18'));
    }

    public function testUnchangedNeedsNoAttempt(): void
    {
        $report = (new IngestionReportFactory())->unchanged();

        self::assertSame(IngestionOutcomeEnum::Unchanged, $report->outcome);
        self::assertSame(0, $report->imported);
    }

    /**
     * La fabrique ne fait QUE lire : la transition done() appartient a
     * IngestInvoicesUseCase, qui possede la transaction. Ce test pin cette
     * separation — un `accepted()` qui muterait la tentative ferait passer un
     * attempt encore Created pour Done.
     */
    public function testAcceptedReadsTheAttemptWithoutTransitioningIt(): void
    {
        $attempt = $this->attempt();
        $attempt->done(10);

        $report = (new IngestionReportFactory())->accepted($attempt);

        self::assertSame(IngestionOutcomeEnum::Accepted, $report->outcome);
        self::assertSame(10, $report->imported);
    }
}
