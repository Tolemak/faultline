<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Issue;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Issue>
 */
class IssueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Issue::class);
    }

    public function findOneByFingerprint(Project $project, string $fingerprint): ?Issue
    {
        return $this->findOneBy(['project' => $project, 'fingerprint' => $fingerprint]);
    }

    public function markNotified(Issue $issue, \DateTimeImmutable $at): void
    {
        $this->createQueryBuilder('i')
            ->update()
            ->set('i.lastNotifiedAt', ':at')
            ->where('i = :issue')
            ->setParameter('at', $at, Types::DATETIME_IMMUTABLE)
            ->setParameter('issue', $issue)
            ->getQuery()
            ->execute();

        $issue->markNotified($at);
    }
}
