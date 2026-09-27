<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Project;
use App\Project\ProjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait ProjectFixtures
{
    /**
     * @param list<string> $origins
     */
    private static function createProject(ContainerInterface $container, string $name = 'Shop', array $origins = []): Project
    {
        $manager = $container->get(ProjectManager::class);
        \assert($manager instanceof ProjectManager);

        return $manager->create($name, $origins);
    }

    private static function projectId(Project $project): int
    {
        return $project->getId() ?? throw new \LogicException('Project is not persisted.');
    }
}
