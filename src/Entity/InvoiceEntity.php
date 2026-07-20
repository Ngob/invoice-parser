<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\ORM\Mapping as ORM;

// Index nomme explicitement : sans lui, Doctrine attend l'index auto-genere
// pour le FK (IDX_...) plutot que celui, descriptif, cree par la migration.
// Nom de table explicite : la table s'appelle 'invoice' (cf. migration), pas
// 'invoice_entity'. Sans ce mapping, le suffixe Entity du nom de classe
// deviendrait le nom de table par defaut et divergerait du schema reel.
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoice')]
#[ORM\Index(name: 'idx_invoice_attempt', columns: ['attempt_id'])]
class InvoiceEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string')]
    private string $name;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $amount;

    #[ORM\Column(type: 'string')]
    private string $currency;

    #[ORM\Column(name: 'issued_at', type: 'date_immutable')]
    private \DateTimeImmutable $issuedAt;

    #[ORM\ManyToOne(targetEntity: ImportAttemptEntity::class)]
    #[ORM\JoinColumn(name: 'attempt_id', referencedColumnName: 'id', nullable: true)]
    private ?ImportAttemptEntity $attempt = null;

    public function __construct(string $name, string $amount, string $currency, \DateTimeImmutable $issuedAt)
    {
        $this->name = $name;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->issuedAt = $issuedAt;
    }

    public function setAttemptEntity(ImportAttemptEntity $attempt): void
    {
        $this->attempt = $attempt;
    }

}
