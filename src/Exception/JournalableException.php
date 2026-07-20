<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Un rejet qui sait se rendre en lignes de journal.
 *
 * \JsonSerializable declare `jsonSerialize(): mixed`, ce qui ne dit rien de la
 * forme attendue par import_attempt.errors — colonne jsonb interrogee en SQL
 * (`errors->0->>'reason'`). Cette interface fige cette forme, et c'est elle que
 * ImportAttemptEntity::fail() exige : un throwable qui ne l'implemente pas
 * (DriverException, bug PHP) n'a pas de lignes a donner et ne peut pas etre
 * journalise.
 */
interface JournalableException extends \JsonSerializable
{
    /**
     * @return list<array{line: int, field: string, rawValue: string|null, reason: string, expected: string}>
     */
    public function jsonSerialize(): array;
}
