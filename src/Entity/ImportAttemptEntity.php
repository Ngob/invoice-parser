<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttemptStatusEnum;
use App\Exception\JournalableException;
use App\Repository\ImportAttemptRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportAttemptRepository::class)]
#[ORM\Table(name: 'import_attempt')]
#[ORM\Index(name: 'uniq_attempt_active', columns: ['client', 'source_file'], options: ['where' => "((status)::text = ANY ((ARRAY['pending'::character varying, 'done'::character varying])::text[]))"])]
class ImportAttemptEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 64)]
    private string $client;

    #[ORM\Column(name: 'source_file', type: 'string', length: 1024)]
    private string $sourceFile;

    #[ORM\Column(name: 'attempted_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $attemptedAt;

    #[ORM\Column(type: 'string', length: 16, enumType: AttemptStatusEnum::class)]
    private AttemptStatusEnum $status;

    #[ORM\Column(name: 'invoice_count', type: 'integer')]
    private int $invoiceCount;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $errors = [];

    public function __construct(string $client, string $sourceFile, \DateTimeImmutable $attemptedAt)
    {
        $this->client = $client;
        $this->sourceFile = $sourceFile;
        $this->attemptedAt = $attemptedAt;
        $this->status = AttemptStatusEnum::Created;
        $this->invoiceCount = 0;
    }

    public function pending(): void
    {
        $this->status = AttemptStatusEnum::Pending;
    }

    public function done(int $invoiceCount): void
    {
        $this->status = AttemptStatusEnum::Done;
        $this->invoiceCount = $invoiceCount;
        $this->errors = [];
    }

    public function fail(\Throwable&JournalableException $e): void
    {
        $this->status = AttemptStatusEnum::Failure;
        $this->invoiceCount = 0;
        $this->errors = $e->jsonSerialize();
    }

    public function id(): int
    {
        return $this->id;
    }

    public function status(): AttemptStatusEnum
    {
        return $this->status;
    }

    public function invoiceCount(): int
    {
        return $this->invoiceCount;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
