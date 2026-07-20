<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Tests\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ParseInvoicesCommandTest extends DatabaseTestCase
{
    /** @var list<string> temp demo dirs to remove in tearDown() */
    private array $tempDirs = [];

    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = (string) getcwd();
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        foreach ($this->tempDirs as $dir) {
            $this->removeDir($dir);
        }
        parent::tearDown();
    }

    public function testParseImportsBothDemoFiles(): void
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:parse'));

        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame(20, $this->fetchInt('SELECT COUNT(*) FROM invoice'));
        self::assertSame(2, $this->fetchInt("SELECT COUNT(*) FROM import_attempt WHERE status = 'done'"));
    }

    /**
     * acme (position 0, CSV) rejected, globex (position 1, JSON) accepted.
     * This is the load-bearing order: it pins BOTH the Rejected=>FAILURE
     * mapping AND the aggregation guard (§ testMixedOutcomes... below), since
     * a later SUCCESS outcome must not clobber an earlier FAILURE.
     */
    public function testRejectedDemoFileYieldsFailureExitAndPartialWrite(): void
    {
        $this->chdirToDemoDir(acmeCsv: $this->brokenCsv(), globexJson: $this->validJson(10));

        $tester = $this->runParse();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame(10, $this->fetchInt('SELECT COUNT(*) FROM invoice'));
        self::assertSame('failure', $this->fetchRow("SELECT status FROM import_attempt WHERE client = 'acme'")['status']);
        self::assertSame('done', $this->fetchRow("SELECT status FROM import_attempt WHERE client = 'globex'")['status']);
    }

    /**
     * Neither demo file exists at all: both clients hit
     * PayloadUnreadableException before any attempt row is ever created.
     * Drives the exception path WITHOUT touching the real data/ fixtures —
     * chdir() into an empty temp dir so the command's hardcoded relative
     * paths ('data/invoices.csv', 'data/invoices.json') simply don't resolve.
     */
    public function testMissingDemoFilesYieldInvalidExitAndWriteNothing(): void
    {
        $dir = $this->makeTempDir();
        chdir($dir);

        $tester = $this->runParse();

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('Cannot read payload at "data/invoices.csv"', $tester->getDisplay());
        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM invoice'));
        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM import_attempt'));
    }


    private function runParse(): CommandTester
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:parse'));
        $tester->execute([]);

        return $tester;
    }

    private function chdirToDemoDir(string $acmeCsv, string $globexJson): void
    {
        $dir = $this->makeTempDir();
        file_put_contents($dir.'/data/invoices.csv', $acmeCsv);
        file_put_contents($dir.'/data/invoices.json', $globexJson);
        chdir($dir);
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/parse-invoices-test-'.bin2hex(random_bytes(6));
        mkdir($dir.'/data', 0777, true);
        $this->tempDirs[] = $dir;

        return $dir;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/data/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir.'/data');
        @rmdir($dir);
    }

    /** One row, empty amount field: fails NotBlank, whole file rejected (all-or-nothing). */
    private function brokenCsv(): string
    {
        return "\tEUR\tNo Amount\t2025-02-03\n";
    }
    
    private function validJson(int $rows): string
    {
        $records = [];
        for ($i = 1; $i <= $rows; ++$i) {
            $records[] = ['montant' => 100 + $i, 'devise' => 'EUR', 'nom' => 'Test Person '.$i, 'date' => '2025-02-03'];
        }

        return (string) json_encode($records);
    }
}
