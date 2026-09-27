<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function findOneBySlug(string $slug): ?Project
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @return list<Project>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    public function save(Project $project): void
    {
        $this->getEntityManager()->persist($project);
        $this->getEntityManager()->flush();
    }
}
