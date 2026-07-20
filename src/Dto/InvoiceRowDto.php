<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class InvoiceRowDto
{
    /**
     * `max: 255` reflete la largeur de InvoiceEntity::$name / $currency
     * (#[ORM\Column(type: 'string')] = VARCHAR(255) implicite). Defense en
     * profondeur, pas la seule garde : IngestInvoicesUseCase rattrape de toute
     * facon tout throwable et fait echouer la tentative proprement. Mais sans
     * ces contraintes, une valeur de 300 caracteres n'etait signalee que par
     * un SQLSTATE 22001 sans numero de ligne ; avec elles, l'operateur lit
     * « ligne 4 · name — trop long », le cas normal d'une donnee client fausse.
     *
     * Couplage assume : si la largeur de la colonne change, ces deux `max`
     * doivent suivre.
     */
    private const COLUMN_MAX = 255;

    #[Assert\NotBlank]
    #[Assert\Length(max: self::COLUMN_MAX)]
    public string $name;

    // Regex seul laisserait passer '' : RegexValidator court-circuite
    // volontairement sur null/'' (voir Symfony\Component\Validator\
    // Constraints\RegexValidator::validate()), alors que
    // isCanonicalDecimal('') rendait false. NotBlank restaure ce cas.
    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^-?\d{1,15}(\.\d{1,4})?\z/',
        message: 'not a canonical decimal',
    )]
    public string $amount;

    #[Assert\NotBlank]
    #[Assert\Length(max: self::COLUMN_MAX)]
    public string $currency;

    #[Assert\NotNull]
    public \DateTimeImmutable $issuedAt;
}
