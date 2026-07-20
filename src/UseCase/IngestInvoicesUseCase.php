<?php

declare(strict_types=1);

namespace App\UseCase;

use App\Config\IngestionProfilesConfig;
use App\Decoder\Format\FormatStrategyInterface;
use App\Entity\ImportAttemptEntity;
use App\Entity\InvoiceEntity;
use App\Exception\JournalableException;
use App\Exception\PayloadUnreadableException;
use App\Repository\ImportAttemptRepository;
use App\Source\SourceInvoicesFile;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class IngestInvoicesUseCase
{
    public function __construct(
        private readonly IngestionProfilesConfig $profiles,
        private readonly ImportAttemptRepository $attempts,
        private readonly IngestionReportFactory $reports,
        // This could be done using a factory for SourceInvoicesFile
        private readonly DenormalizerInterface $serializer,
        #[AutowireLocator('app.format_strategy')]
        private readonly ContainerInterface $formatStrategies,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @throws \App\Exception\UnknownClientException client inconnu
     * @throws PayloadUnreadableException            fichier illisible
     * @throws \App\Exception\InvalidFileException   fichier lisible, lignes fautives
     */
    public function execute(string $client, \SplFileInfo $file): IngestionReportUseCase
    {
        $profile = $this->profiles->byClient($client);

        $path = $file->getPathname();
        if (!$file->isFile() || !$file->isReadable()) {
            throw new PayloadUnreadableException(sprintf('Cannot read payload at "%s".', $path));
        }

        $sourceFile = realpath($path) ?: $path;

        if (!$this->attempts->canAttempt($client, $sourceFile)) {
            // Deja fait (DONE) ou deja en cours (PENDING).
            return $this->reports->unchanged();
        }

        $attempt = new ImportAttemptEntity($client, $sourceFile, new \DateTimeImmutable());
        $attempt->pending();

        try {
            $this->em->persist($attempt);
            $this->em->flush();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            // ACTUELLEMENT INATTEIGNABLE : aucune contrainte UNIQUE n'existe
            // sur la cle (client, source_file). L'index
            // uniq_attempt_active est partiel et NON-UNIQUE (decision C3, cf.
            // le docblock de ImportAttemptRepository::canAttempt()), donc deux
            // INSERT PENDING concurrents passent tous les deux. Ce catch n'est
            // PAS une preuve de securite anti-course cote base : il est garde
            // parce qu'il revit tel quel le jour ou l'index sera promu UNIQUE,
            // ce qui est la seule facon de fermer la fenetre TOCTOU acceptee.
            // Course perdue : un INSERT PENDING concurrent a gagne. Traiter
            // comme "deja en cours".
            return $this->reports->unchanged();
        }

        $this->em->beginTransaction();
        try {
            // This could be done using a factory for SourceInvoicesFile
            /** @var FormatStrategyInterface $strategy */
            $strategy = $this->formatStrategies->get($profile->format);
            $source = new SourceInvoicesFile($profile, $file, $strategy, $this->serializer, $this->validator);
            $invoiceCount = 0;
            foreach ($source->getInvoices() as $row) {

                $invoice = new InvoiceEntity($row->name, $row->amount, $row->currency, $row->issuedAt);
                $invoice->setAttemptEntity($attempt);
                $this->em->persist($invoice);
                $invoiceCount++;
            }

            $attempt->done($invoiceCount);
            $this->em->flush();
            $this->em->commit();

            return $this->reports->accepted($attempt);
        } catch (\Throwable $e) {
            $this->em->rollBack();

            // Might want to reset the entityManager
            if (!$this->em->isOpen()) {
                throw $e;
            }

            if ($e instanceof JournalableException) {
                $attempt->fail($e);
                $this->em->flush();
            }

            throw $e;
        }
    }
}
