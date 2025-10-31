<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<Backend>
 */
#[AsRepository(entityClass: Backend::class)]
class BackendRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Backend::class);
    }

    public function save(Backend $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Backend $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function update(Backend $entity, bool $flush = true): void
    {
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<Backend>
     */
    public function findEnabledBackends(): array
    {
        /** @var array<Backend> */
        return $this->createQueryBuilder('b')
            ->where('b.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('b.weight', 'DESC')
            ->addOrderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<Backend>
     */
    public function findHealthyBackends(): array
    {
        /** @var array<Backend> */
        return $this->createQueryBuilder('b')
            ->where('b.enabled = :enabled')
            ->andWhere('b.status = :status')
            ->setParameter('enabled', true)
            ->setParameter('status', BackendStatus::ACTIVE->value)
            ->orderBy('b.weight', 'DESC')
            ->addOrderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<Backend>
     */
    public function findBackendsForHealthCheck(): array
    {
        /** @var array<Backend> */
        return $this->createQueryBuilder('b')
            ->where('b.enabled = :enabled')
            ->andWhere('b.healthCheckPath IS NOT NULL')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param array<int> $ids
     * @return array<Backend>
     */
    public function findByIds(array $ids): array
    {
        /** @var array<Backend> */
        return $this->createQueryBuilder('b')
            ->where('b.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<Backend>
     */
    public function findUnhealthyBackends(): array
    {
        /** @var array<Backend> */
        return $this->createQueryBuilder('b')
            ->where('b.status = :status')
            ->orWhere('b.lastHealthStatus = :healthStatus')
            ->setParameter('status', BackendStatus::UNHEALTHY->value)
            ->setParameter('healthStatus', false)
            ->orderBy('b.lastHealthCheck', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
