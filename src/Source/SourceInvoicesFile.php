<?php

declare(strict_types=1);

namespace App\Source;

use App\Config\IngestionProfileConfig;
use App\Decoder\Format\FormatStrategyInterface;
use App\Dto\InvoiceRowDto;
use App\Exception\DecodeFailedException;
use App\Exception\InvalidFileException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SourceInvoicesFile implements SourceInvoicesInterface
{
    public function __construct(
        private readonly IngestionProfileConfig $profile,
        private readonly \SplFileInfo $splInfo,
        private readonly FormatStrategyInterface $strategy,
        private readonly DenormalizerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     *
     * @return list<InvoiceRowDto>
     *
     */
    public function getInvoices(): array
    {
        try {
            $parsed = $this->strategy->parse($this->splInfo, $this->profile);
        } catch (NotEncodableValueException $e) {
            throw new DecodeFailedException(sprintf('Cannot decode payload: %s', $e->getMessage()), 0, $e);
        }

        $violations = [];

        try {
            /** @var list<InvoiceRowDto> $rows */
            $rows = $this->serializer->denormalize($parsed, InvoiceRowDto::class.'[]', null, $this->context());
        } catch (PartialDenormalizationException $e) {

            // Rendues en ConstraintViolation comme celles du validator : le
            // journal n'a qu'une seule forme d'erreur a connaitre. getPath()
            // rend deja "[1].amount", soit exactement le propertyPath que
            // produit une validation de collection.
            foreach ($e->getErrors() as $error) {
                $violations[] = new ConstraintViolation(
                    $error->getMessage(),
                    null,
                    [],
                    $parsed,
                    (string) $error->getPath(),
                    null,
                );
            }
            throw new InvalidFileException($violations);
        } catch (NotNormalizableValueException $e) {
            throw new DecodeFailedException(sprintf('Cannot decode payload: %s', $e->getMessage()), 0, $e);
        }

        foreach ($this->validator->validate($rows) as $violation) {
            $violations[] = $violation;
        }

        if ($violations !== []) {
            throw new InvalidFileException($violations);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            // Le '|' remet l'heure a 00:00:00 ET rejette tout ce qui n'est pas
            // strictement Y-m-d (createFromFormat rentre en mode strict) :
            // sans lui, DateTimeNormalizer retombe sur new DateTimeImmutable()
            // / strtotime, qui accepte "tomorrow", "now", un ISO-datetime avec
            // heure (silencieusement ecrasee par le callback ci-dessous), etc.
            DateTimeNormalizer::FORMAT_KEY => 'Y-m-d|',
            // Le montant JSON arrive en float (670.43) et InvoiceRowDto le veut
            // en string. La validation reelle du montant se fait plus bas.
            AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true,
            // Rend TOUTES les erreurs du fichier, pas seulement la premiere.
            DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            AbstractNormalizer::CALLBACKS => [
                'issuedAt' => static function (mixed $value): \DateTimeImmutable {
                    if (!$value instanceof \DateTimeImmutable) {
                        throw new \InvalidArgumentException('issuedAt must be a date.');
                    }

                    // DateTimeNormalizer parse la date a 00:00 ; on la fixe
                    // ensuite a 04:00 pour eviter les bascules DST et le
                    // glissement de date sous offset UTC negatif.
                    return $value->setTime(4, 0);
                },
            ],
        ];
    }

}
