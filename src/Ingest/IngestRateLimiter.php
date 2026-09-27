<?php

declare(strict_types=1);

namespace App\Ingest;

use App\Entity\Project;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final readonly class IngestRateLimiter
{
    public function __construct(
        private RateLimiterFactoryInterface $ingestLimiter,
    ) {
    }

    public function consume(Project $project): void
    {
        $limit = $this->ingestLimiter->create($project->getPublicKey())->consume();

        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            throw IngestException::rateLimited($retryAfter);
        }
    }
}
