<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<ForwardLog>
 */
#[AsRepository(entityClass: ForwardLog::class)]
class ForwardLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForwardLog::class);
    }

    /**
     * @return array<ForwardLog>
     */
    public function findRecentLogs(int $limit = 100): array
    {
        /** @var array<ForwardLog> */
        return $this->createQueryBuilder('l')
            ->orderBy('l.requestTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<ForwardLog>
     */
    public function findByRule(ForwardRule $rule, int $limit = 100): array
    {
        /** @var array<ForwardLog> */
        return $this->createQueryBuilder('l')
            ->where('l.rule = :rule')
            ->setParameter('rule', $rule)
            ->orderBy('l.requestTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<ForwardLog>
     */
    public function findErrorLogs(int $limit = 100): array
    {
        /** @var array<ForwardLog> */
        return $this->createQueryBuilder('l')
            ->where('l.responseStatus >= 400 OR l.errorMessage IS NOT NULL')
            ->orderBy('l.requestTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatsByRule(ForwardRule $rule): array
    {
        $qb = $this->createQueryBuilder('l');

        /** @var array<string, mixed> */
        return $qb->select([
            'COUNT(l.id) as totalRequests',
            'AVG(l.durationMs) as avgDuration',
            'MAX(l.durationMs) as maxDuration',
            'MIN(l.durationMs) as minDuration',
            'SUM(CASE WHEN l.responseStatus >= 200 AND l.responseStatus < 300 THEN 1 ELSE 0 END) as successCount',
            'SUM(CASE WHEN l.responseStatus >= 400 THEN 1 ELSE 0 END) as errorCount',
            'SUM(CASE WHEN l.fallbackUsed = true THEN 1 ELSE 0 END) as fallbackCount',
            'SUM(l.retryCountUsed) as totalRetries',
        ])
            ->where('l.rule = :rule')
            ->setParameter('rule', $rule)
            ->getQuery()
            ->getSingleResult()
        ;
    }

    public function save(ForwardLog $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function update(ForwardLog $log, bool $flush = true): void
    {
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ForwardLog $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function cleanOldLogs(\DateTimeImmutable $before): int
    {
        /** @var int */
        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.requestTime < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute()
        ;
    }
}
