<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<ForwardRule>
 */
#[AsRepository(entityClass: ForwardRule::class)]
class ForwardRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForwardRule::class);
    }

    /**
     * @return array<ForwardRule>
     */
    public function findEnabledRulesOrderedByPriority(): array
    {
        /** @var array<ForwardRule> */
        return $this->createQueryBuilder('r')
            ->where('r.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('r.priority', 'DESC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<ForwardRule>
     */
    public function findByPath(string $path): array
    {
        /** @var array<ForwardRule> */
        return $this->createQueryBuilder('r')
            ->where('r.enabled = :enabled')
            ->andWhere(':path LIKE CONCAT(r.sourcePath, \'%\') OR :path REGEXP r.sourcePath')
            ->setParameter('enabled', true)
            ->setParameter('path', $path)
            ->orderBy('r.priority', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<ForwardRule>
     */
    public function findEnabledRules(): array
    {
        /** @var array<ForwardRule> */
        return $this->createQueryBuilder('r')
            ->where('r.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('r.priority', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<ForwardRule>
     */
    public function findByPathPattern(string $pattern): array
    {
        /** @var array<ForwardRule> */
        return $this->createQueryBuilder('r')
            ->where('r.sourcePath LIKE :pattern')
            ->setParameter('pattern', '%' . $pattern . '%')
            ->orderBy('r.priority', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<ForwardRule>
     */
    public function findByHttpMethod(string $method): array
    {
        /** @var array<ForwardRule> */
        return $this->createQueryBuilder('r')
            ->where('r.httpMethods LIKE :method')
            ->setParameter('method', '%"' . $method . '"%')
            ->orderBy('r.priority', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function save(ForwardRule $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ForwardRule $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function update(ForwardRule $entity, bool $flush = true): void
    {
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
