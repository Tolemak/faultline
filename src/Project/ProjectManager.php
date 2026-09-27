<?php

declare(strict_types=1);

namespace App\Project;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class ProjectManager
{
    public const int MAX_RETENTION_DAYS = 365;

    public function __construct(
        private ProjectRepository $projects,
        private SluggerInterface $slugger,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string> $allowedOrigins
     */
    public function create(string $name, array $allowedOrigins = [], int $retentionDays = Project::DEFAULT_RETENTION_DAYS): Project
    {
        $name = self::validName($name);
        self::assertRetention($retentionDays);

        $project = new Project($name, $this->uniqueSlug($name), self::newKey(), $this->clock->now());
        $project->setAllowedOrigins($allowedOrigins);
        $project->setRetentionDays($retentionDays);
        $this->projects->save($project);

        return $project;
    }

    public function rotateKey(Project $project): void
    {
        $project->rotateKey(self::newKey());
        $this->projects->save($project);
    }

    public static function validName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > 100) {
            throw new \InvalidArgumentException('Project name must be 1 to 100 characters long.');
        }

        return $name;
    }

    public static function assertRetention(int $days): void
    {
        if ($days < 1 || $days > self::MAX_RETENTION_DAYS) {
            throw new \InvalidArgumentException(\sprintf('Retention must be between 1 and %d days.', self::MAX_RETENTION_DAYS));
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = mb_substr($this->slugger->slug($name)->lower()->toString(), 0, 90);
        if ('' === $base) {
            $base = 'project';
        }

        $slug = $base;
        for ($i = 2; null !== $this->projects->findOneBySlug($slug); ++$i) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private static function newKey(): string
    {
        return bin2hex(random_bytes(16));
    }
}
