<?php

declare(strict_types=1);

namespace App\Ingest;

use App\Entity\Project;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final readonly class IngestRateLimiter
{
    public function __construct(
        #[Target('ingest')]
        private RateLimiterFactoryInterface $ingestLimiter,
    ) {
    }

    public function consume(Project $project, int $tokens = 1): void
    {
        $projectId = $project->getId() ?? throw new \LogicException('Project is not persisted.');

        try {
            $limit = $this->ingestLimiter->create('project-'.$projectId)->consume($tokens);
        } catch (\InvalidArgumentException) {
            throw IngestException::invalidPayload('Too many events in one request.');
        }

        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            throw IngestException::rateLimited($retryAfter);
        }
    }
}
