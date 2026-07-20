<?php

declare(strict_types=1);

namespace App\Command;

use App\UseCase\IngestInvoicesUseCase;
use App\Exception\InvalidFileException;
use App\Exception\PayloadUnreadableException;
use App\Exception\UnknownClientException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:invoice:import', description: 'Importe les factures d\'un client depuis un fichier')]
class ImportInvoicesCommand extends Command
{
    public function __construct(private readonly IngestInvoicesUseCase $ingest)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('client', InputArgument::REQUIRED, 'Le client configure dans invoice_ingestion.yaml')
            ->addArgument('path', InputArgument::REQUIRED, 'Chemin du fichier a importer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $client = $input->getArgument('client');
        $path = $input->getArgument('path');
        if (!is_string($client) || !is_string($path)) {
            // Les arguments sont declares REQUIRED et non-tableau : Console
            // les rend toujours en string. Cette branche protege le typage,
            // elle ne s'execute jamais en pratique.
            throw new \LogicException('Les arguments "client" et "path" doivent etre des chaines.');
        }

        try {
            $report = $this->ingest->execute($client, new \SplFileInfo($path));
        } catch (UnknownClientException|PayloadUnreadableException $e) {
            // INVALID : on a mal appele la commande. Distinct de FAILURE, qui
            // dit que le client a envoye n'importe quoi. Le cron a besoin des deux.
            $io->error($e->getMessage());

            return Command::INVALID;
        } catch (InvalidFileException $e) {
            // Le client a envoye n'importe quoi : c'est FAILURE, et l'operateur
            // a besoin du detail ligne par ligne, pas d'une trace de pile.
            $io->error($e->summary());

            return Command::FAILURE;
        } catch (\Throwable $e) {
            // Le contrat de sortie est fige a trois valeurs. Sans ce filet,
            // Application::run() rend le getCode() de l'exception : un
            // SQLSTATE 22001 sortait en 7, hors contrat, et le cron lisait une
            // valeur qu'aucune de ses branches ne connait.
            //
            // FAILURE et non INVALID : INVALID veut dire « tu m'as mal
            // appelee » et invite l'operateur a corriger son invocation. Une
            // erreur interne ne se corrige pas en rappelant autrement — le
            // fichier n'a simplement pas ete importe, ce qui est exactement ce
            // que FAILURE signifie. Le journal de la tentative dit `failure`
            // lui aussi : code de sortie et journal racontent la meme chose.
            $io->error(sprintf('erreur interne — le fichier n\'a pas ete importe : %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success($report->summary());

        return Command::SUCCESS;
    }
}
