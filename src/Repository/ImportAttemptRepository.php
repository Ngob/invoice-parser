<?php

declare(strict_types=1);

namespace App\Repository;

use App\Enum\AttemptStatusEnum;
use App\Entity\ImportAttemptEntity;
use App\Entity\InvoiceEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImportAttemptEntity>
 */
class ImportAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportAttemptEntity::class);
    }

    /**
     * Intention metier : on ne retente que si aucune tentative n'est active
     * (PENDING) ni aboutie (DONE) pour la cle.
     */
    public function canAttempt(string $client, string $sourceFile): bool
    {
        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.client = :client')
            ->andWhere('a.sourceFile = :file')
            ->andWhere('a.status IN (:active)')
            ->setParameter('client', $client)
            ->setParameter('file', $sourceFile)
            ->setParameter('active', [AttemptStatusEnum::Pending, AttemptStatusEnum::Done])
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count === 0;
    }
}
