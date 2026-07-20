<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Enum\AttemptStatusEnum;
use App\Entity\ImportAttemptEntity;
use App\Exception\InvalidFileException;
use Symfony\Component\Validator\ConstraintViolation;
use App\Repository\ImportAttemptRepository;
use App\Tests\DatabaseTestCase;

class ImportAttemptRepositoryTest extends DatabaseTestCase
{
    private ImportAttemptRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = static::getContainer()->get(ImportAttemptRepository::class);
    }

    private function attempt(AttemptStatusEnum $status): ImportAttemptEntity
    {
        $attempt = new ImportAttemptEntity('acme', '/inbound/invoices.csv', new \DateTimeImmutable('2026-07-18 10:00:00'));
        match ($status) {
            AttemptStatusEnum::Created => null,
            AttemptStatusEnum::Pending => $attempt->pending(),
            AttemptStatusEnum::Done => $attempt->done(0),
            AttemptStatusEnum::Failure => $attempt->fail(new InvalidFileException([new ConstraintViolation('bad', 'bad', [], null, '[0]', null)])),
        };

        return $attempt;
    }

    private function persist(ImportAttemptEntity $attempt): void
    {
        $this->em->persist($attempt);
        $this->em->flush();
    }

    public function testCanAttemptFalseForPendingOrDone(): void
    {
        $this->persist($this->attempt(AttemptStatusEnum::Pending));
        self::assertFalse($this->repository->canAttempt('acme', '/inbound/invoices.csv'));
    }

    public function testCanAttemptTrueForFailureOnly(): void
    {
        $this->persist($this->attempt(AttemptStatusEnum::Failure));
        self::assertTrue($this->repository->canAttempt('acme', '/inbound/invoices.csv'));
    }

    public function testRejectedAttemptStoresItsErrorsAsQueryableJson(): void
    {
        $attempt = $this->attempt(AttemptStatusEnum::Failure);
        $attempt->fail(new InvalidFileException([new ConstraintViolation('bad', 'bad', [], null, '[3].amount', '1 234,56')]));
        $this->persist($attempt);

        $field = $this->conn->fetchOne("SELECT errors->0->>'field' FROM import_attempt");

        self::assertSame('amount', $field);
    }
}
