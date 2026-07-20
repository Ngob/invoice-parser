<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Le fichier est syntaxiquement mort : ni ligne valide, ni ligne invalide,
 * rien a lire. Distinct d'une violation de ligne, qui localise un probleme
 * dans un fichier par ailleurs lisible.
 *
 * JsonSerializable pour la meme raison qu'InvalidFileException : c'est un REJET,
 * donc il doit se journaliser dans import_attempt.errors, interroge en SQL
 * (`errors->0->>'reason'`). Sans ca, ImportAttemptEntity::fail() ne l'accepte
 * pas, la tentative reste PENDING et ces octets ne sont plus re-importables.
 */
class DecodeFailedException extends \RuntimeException implements JournalableException
{
    /**
     * @return list<array{line: int, field: string, rawValue: string|null, reason: string, expected: string}>
     */
    public function jsonSerialize(): array
    {
        return [[
            'line' => 0,
            'field' => '(row)',
            'rawValue' => null,
            'reason' => $this->getMessage(),
            'expected' => 'a decodable payload',
        ]];
    }
}
