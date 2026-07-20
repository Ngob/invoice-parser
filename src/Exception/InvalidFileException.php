<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Le fichier est lisible mais au moins une ligne est invalide. Agrege TOUTES
 * les violations du fichier, pas seulement la premiere : « acme nous a envoye
 * quatre fichiers casses » doit etre interrogeable ligne par ligne.
 *
 * En cas d'erreur on rejette TOUT le fichier (ADR-4 / #16) : neuf bonnes lignes
 * et une mauvaise ecrivent zero facture, mais la tentative est journalisee.
 */
class InvalidFileException extends \RuntimeException implements JournalableException
{
    /**
     * @param non-empty-list<ConstraintViolationInterface> $violations
     */
    public function __construct(public readonly array $violations)
    {
        parent::__construct(sprintf('%d invalid row(s).', count($violations)));
    }

    /**
     * Le journal est interroge en SQL (`errors->0->>'reason'`), donc chaque
     * violation est aplatie ici en une ligne de colonnes stables. La ligne
     * source vient du propertyPath, qui porte l'index de la collection
     * ("[3].amount") : c'est pour ca que la validation se fait sur $rows
     * entier et pas objet par objet.
     *
     * @return list<array{line: int, field: string, rawValue: string|null, reason: string, expected: string}>
     */
    public function jsonSerialize(): array
    {
        return array_map(function (ConstraintViolationInterface $violation): array {
            preg_match('/^\[(\d+)\](?:\.(.+))?$/', $violation->getPropertyPath(), $matches);
            $value = $violation->getInvalidValue();

            return [
                'line' => isset($matches[1]) ? (int) $matches[1] + 1 : 0,
                // '(row)' quand l'erreur porte sur la ligne entiere et non sur
                // une propriete precise. Deliberement pas un nom qui ressemble
                // a une vraie colonne (amount, currency, ...).
                'field' => $matches[2] ?? '(row)',
                'rawValue' => is_scalar($value) ? (string) $value : null,
                'reason' => (string) $violation->getMessage(),
                'expected' => $this->expectationFrom($violation),
            ];
        }, $this->violations);
    }

    /**
     * `expected` decrit ce que la ligne AURAIT du contenir. Il est derive de la
     * contrainte qui a REELLEMENT echoue, jamais d'une phrase recopiee :
     * ajouter une contrainte a InvoiceRowDto sans toucher ici rend 'unknown'
     * (honnete), pas une description empruntee a une autre regle. Ce champ
     * n'est jamais rendu par la CLI mais il EST persiste dans
     * import_attempt.errors, et ce journal est toute la raison d'etre de
     * l'entite. getConstraint() ne vit que sur la classe concrete
     * ConstraintViolation, d'ou le narrowing — une violation fabriquee a la
     * main (erreur de denormalisation) n'en porte pas et rend 'unknown'.
     */
    private function expectationFrom(ConstraintViolationInterface $violation): string
    {
        $constraint = $violation instanceof ConstraintViolation ? $violation->getConstraint() : null;

        return match (true) {
            $constraint instanceof Assert\NotBlank => 'a non-blank value',
            $constraint instanceof Assert\NotNull => 'a value',
            $constraint instanceof Assert\Length => $this->lengthExpectation($constraint),
            // Le pattern lui-meme plutot qu'une paraphrase : impossible qu'il
            // derive de la regle reellement appliquee. Les proprietes des
            // Constraint Symfony sont `mixed` pour l'analyse statique, d'ou le
            // narrowing explicite ; s'il ne tient pas, 'unknown' est la
            // reponse honnete.
            $constraint instanceof Assert\Regex && is_string($constraint->pattern) => sprintf('a value matching %s', $constraint->pattern),
            default => 'unknown',
        };
    }

    private function lengthExpectation(Assert\Length $constraint): string
    {
        return match (true) {
            is_int($constraint->max) => sprintf('at most %d characters', $constraint->max),
            is_int($constraint->min) => sprintf('at least %d characters', $constraint->min),
            default => 'unknown',
        };
    }

    /**
     * Rendu operateur, a cote du rendu machine ci-dessus : c'est ce que la CLI
     * affiche a la place d'une trace de pile. Vit ici, comme summary() vit sur
     * IngestionReportUseCase — l'objet qui porte la donnee sait la dire.
     */
    public function summary(): string
    {
        return sprintf(
            "rejeté — %d erreur(s), aucune facture écrite :\n%s",
            count($this->violations),
            implode("\n", array_map(static fn (array $row): string => $row['rawValue'] === null
                ? sprintf('  ligne %d · %s — %s', $row['line'], $row['field'], $row['reason'])
                : sprintf('  ligne %d · %s · "%s" — %s', $row['line'], $row['field'], $row['rawValue'], $row['reason']), $this->jsonSerialize())),
        );
    }
}
