<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Exception\DecodeFailedException;
use App\Exception\InvalidFileException;
use App\Exception\PayloadUnreadableException;
use App\Exception\UnknownClientException;
use App\Tests\DatabaseTestCase;
use App\UseCase\IngestInvoicesUseCase;
use App\Enum\IngestionOutcomeEnum;

class IngestInvoicesUseCaseTest extends DatabaseTestCase
{
    private IngestInvoicesUseCase $ingest;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ingest = static::getContainer()->get(IngestInvoicesUseCase::class);
        $this->dir = sys_get_temp_dir().'/ingest-'.uniqid('', true);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function write(string $name, string $contents): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * Le rejet n'est plus un rapport, c'est une levee : ce helper rend
     * l'exception pour que les tests assertent SUR elle, et echoue bruyamment
     * si le fichier passe — un import silencieusement accepte est exactement
     * la maladie d'origine.
     */
    private function reject(string $client, string $path): InvalidFileException
    {
        try {
            $this->ingest->execute($client, new \SplFileInfo($path));
        } catch (InvalidFileException $e) {
            return $e;
        }

        self::fail(sprintf('Expected "%s" to be rejected.', $path));
    }

    public function testExecuteInsertsTenInvoicesFromValidCsv(): void
    {
        $report = $this->ingest->execute('acme', new \SplFileInfo((string) realpath('data/invoices.csv')));

        self::assertSame(IngestionOutcomeEnum::Accepted, $report->outcome);
        self::assertSame(10, $report->imported);
        self::assertSame(10, $this->fetchInt('SELECT COUNT(*) FROM invoice'));
        self::assertSame(
            ['name' => 'Frank Green', 'amount' => '670.4300', 'currency' => 'EUR', 'issued_at' => '2025-02-03'],
            $this->fetchRow('SELECT name, amount, currency, issued_at FROM invoice ORDER BY id LIMIT 1')
        );
    }

    public function testExecuteInsertsTenInvoicesFromValidJson(): void
    {
        $report = $this->ingest->execute('globex', new \SplFileInfo((string) realpath('data/invoices.json')));

        self::assertSame(IngestionOutcomeEnum::Accepted, $report->outcome);
        self::assertSame(10, $report->imported);

        // Le bug d'origine : WHERE name = '"Frank Green"', guillemets inclus.
        self::assertSame(
            'Frank Green',
            $this->conn->fetchOne("SELECT name FROM invoice WHERE name = 'Frank Green' LIMIT 1")
        );
    }

    public function testExecuteReturnsUnchangedOnReimportOfTheSamePath(): void
    {
        $path = (string) realpath('data/invoices.csv');
        $this->ingest->execute('acme', new \SplFileInfo($path));

        $report = $this->ingest->execute('acme', new \SplFileInfo($path));

        self::assertSame(IngestionOutcomeEnum::Unchanged, $report->outcome);
        self::assertSame(10, $this->fetchInt('SELECT COUNT(*) FROM invoice'));
        self::assertSame(1, $this->fetchInt('SELECT COUNT(*) FROM import_attempt'));
    }

    public function testExecuteThrowsOnUnknownClient(): void
    {
        $this->expectException(UnknownClientException::class);

        $this->ingest->execute('acmee', new \SplFileInfo((string) realpath('data/invoices.csv')));
    }

    public function testExecuteThrowsOnUnreadableFile(): void
    {
        $this->expectException(PayloadUnreadableException::class);

        $this->ingest->execute('acme', new \SplFileInfo($this->dir.'/nope.csv'));
    }

    /**
     * Un JSON tronque n'a ni ligne valide ni ligne invalide : rien a lire.
     * C'est DecodeFailedException, pas InvalidFileException — la distinction
     * est ce qui permet au journal de dire « fichier mort » plutot que
     * « 0 erreur de ligne ».
     */
    public function testExecuteRejectsFileOnMalformedJson(): void
    {
        $path = $this->write('broken.json', '[{"montant": 670.4');

        try {
            $this->ingest->execute('globex', new \SplFileInfo($path));
            self::fail('Expected a malformed JSON payload to be rejected.');
        } catch (DecodeFailedException) {
            // attendu
        }

        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM invoice'));
        self::assertSame('failure', $this->fetchRow('SELECT status FROM import_attempt')['status']);
    }

    public function testExecuteRejectsWholeFileOnSingleBadRow(): void
    {
        $good = "670.43\tEUR\tFrank Green\t2025-02-03\n";
        $path = $this->write('mixed.csv', str_repeat($good, 9)."\tEUR\tNo Amount\t2025-02-03\n");

        $this->reject('acme', $path);

        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM invoice'), '9 bonnes lignes ne sauvent pas le fichier');
        self::assertSame(1, $this->fetchInt("SELECT COUNT(*) FROM import_attempt WHERE status = 'failure'"));
    }

    public function testExecuteCollectsAllRowErrorsNotOnlyFirst(): void
    {
        $path = $this->write('three-bad.json', json_encode([
            ['devise' => 'EUR', 'nom' => 'No Amount', 'date' => '2025-02-03'],
            ['montant' => 12.0, 'devise' => 'EUR', 'nom' => 'Bad Date', 'date' => 'not-a-date'],
            ['montant' => 99.0, 'nom' => 'No Currency', 'date' => '2025-02-03'],
        ], JSON_THROW_ON_ERROR));

        $lines = array_unique(array_column($this->reject('globex', $path)->jsonSerialize(), 'line'));
        sort($lines);

        self::assertSame([1, 2, 3], $lines);
    }

    public function testExecuteRecordsRejectedAttemptWithQueryableErrors(): void
    {
        $path = $this->write('bad.csv', "\tEUR\tNo Amount\t2025-02-03\n");

        $this->reject('acme', $path);

        $row = $this->fetchRow('SELECT status, invoice_count, errors FROM import_attempt');
        self::assertSame('failure', $row['status']);

        $invoiceCount = $row['invoice_count'];
        if (!is_int($invoiceCount) && !is_string($invoiceCount)) {
            self::fail('invoice_count devrait etre un entier ou une chaine numerique.');
        }
        self::assertSame(0, (int) $invoiceCount);

        $rawErrors = $row['errors'];
        if (!is_string($rawErrors)) {
            self::fail('errors devrait etre une chaine JSON.');
        }

        $errors = json_decode($rawErrors, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($errors);
        self::assertNotEmpty($errors);

        $firstError = $errors[0];
        self::assertIsArray($firstError);
        self::assertArrayHasKey('field', $firstError);
        self::assertArrayHasKey('line', $firstError);
    }

    public function testExecuteReRejectsIdenticalFileAfterPriorRejection(): void
    {
        $path = $this->write('bad.csv', "\tEUR\tNo Amount\t2025-02-03\n");
        $this->reject('acme', $path);

        // Le piege : sans le filtre status='done' dans canAttempt(),
        // ce second appel repondrait Unchanged et le cron sortirait en SUCCESS
        // sur un import qui n'a jamais eu lieu.
        $this->reject('acme', $path);

        self::assertSame(2, $this->fetchInt('SELECT COUNT(*) FROM import_attempt'));
    }

    public function testExecuteLinksEveryImportedInvoiceToItsAttempt(): void
    {
        $this->ingest->execute('acme', new \SplFileInfo((string) realpath('data/invoices.csv')));

        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM invoice WHERE attempt_id IS NULL'));
        self::assertSame(10, $this->fetchInt("SELECT invoice_count FROM import_attempt WHERE status = 'done'"));
    }
}
