<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\InvalidFileException;
use App\Exception\PayloadUnreadableException;
use App\Exception\UnknownClientException;
use App\UseCase\IngestInvoicesUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:parse', description: 'Importe les fichiers de démonstration (acme CSV + globex JSON)')]
class ParseInvoicesCommand extends Command
{
    /** @var array<string, string> client => chemin du fichier de demo */
    private const DEMO_FILES = [
        'acme' => 'data/invoices.csv',
        'globex' => 'data/invoices.json',
    ];

    public function __construct(private readonly IngestInvoicesUseCase $ingest)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $exit = Command::SUCCESS;

        foreach (self::DEMO_FILES as $client => $path) {
            try {
                $report = $this->ingest->execute($client, new \SplFileInfo($path));
            } catch (UnknownClientException|PayloadUnreadableException $e) {
                $io->error(sprintf('%s: %s', $client, $e->getMessage()));
                $exit = Command::INVALID;

                continue;
            } catch (InvalidFileException $e) {
                $io->error(sprintf('%s: %s', $client, $e->summary()));
                if ($exit === Command::SUCCESS) {
                    $exit = Command::FAILURE;
                }

                continue;
            } catch (\Throwable $e) {
                // Meme filet que ImportInvoicesCommand : garder le code de
                // sortie dans le contrat fige a trois valeurs. FAILURE, pas
                // INVALID — voir la justification la-bas.
                $io->error(sprintf('%s: erreur interne — le fichier n\'a pas ete importe : %s', $client, $e->getMessage()));
                if ($exit === Command::SUCCESS) {
                    $exit = Command::FAILURE;
                }

                continue;
            }

            $io->success(sprintf('%s: %s', $client, $report->summary()));
        }

        return $exit;
    }
}
