<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class DatabaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;
    protected Connection $conn;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->conn = $this->em->getConnection();
        $this->conn->executeStatement('TRUNCATE TABLE invoice, import_attempt RESTART IDENTITY CASCADE');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    /**
     * DBAL rend fetchOne() en mixed : ce test-suite ne l'utilise jamais que
     * pour lire un entier (COUNT, id, invoice_count). Le narrowing local vaut
     * mieux qu'un cast aveugle disperse dans chaque test.
     */
    protected function fetchInt(string $sql): int
    {
        $value = $this->conn->fetchOne($sql);
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            throw new \LogicException(sprintf('Expected a numeric scalar from "%s", got %s.', $sql, get_debug_type($value)));
        }

        return (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchRow(string $sql): array
    {
        $row = $this->conn->fetchAssociative($sql);
        if ($row === false) {
            throw new \LogicException(sprintf('Expected a row from "%s".', $sql));
        }

        return $row;
    }
}
