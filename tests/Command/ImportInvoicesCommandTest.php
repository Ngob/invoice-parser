<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ImportInvoicesCommand;
use App\Tests\DatabaseTestCase;
use App\UseCase\IngestInvoicesUseCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ImportInvoicesCommandTest extends DatabaseTestCase
{
    private function tester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:invoice:import'));
    }

    public function testExecuteReturnsSuccessOnAcceptedImport(): void
    {
        $tester = $this->tester();

        $exit = $tester->execute(['client' => 'acme', 'path' => (string) realpath('data/invoices.csv')]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('10 importées', $tester->getDisplay());
        self::assertSame(10, $this->fetchInt('SELECT COUNT(*) FROM invoice'));
    }

    public function testExecuteReturnsSuccessAndWritesNothingOnUnchangedFile(): void
    {
        $path = (string) realpath('data/invoices.csv');
        $this->tester()->execute(['client' => 'acme', 'path' => $path]);

        $tester = $this->tester();
        $exit = $tester->execute(['client' => 'acme', 'path' => $path]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('inchangé', $tester->getDisplay());
        self::assertSame(10, $this->fetchInt('SELECT COUNT(*) FROM invoice'));
    }

    public function testExecuteReturnsInvalidOnUnknownClient(): void
    {
        $tester = $this->tester();

        $exit = $tester->execute(['client' => 'acmee', 'path' => (string) realpath('data/invoices.csv')]);

        self::assertSame(Command::INVALID, $exit);
        self::assertStringContainsString('acmee', $tester->getDisplay());
        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM invoice'));
    }

    public function testExecuteReturnsInvalidOnUnreadableFile(): void
    {
        $tester = $this->tester();

        $exit = $tester->execute(['client' => 'acme', 'path' => '/nope/missing.csv']);

        self::assertSame(Command::INVALID, $exit);
    }
}
