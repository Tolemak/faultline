<?php

declare(strict_types=1);

namespace App\Project;

use App\Entity\Project;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class Dsn
{
    public function __construct(
        #[Autowire(env: 'DEFAULT_URI')]
        private string $baseUri,
    ) {
    }

    public function for(Project $project): string
    {
        $parts = parse_url($this->baseUri);
        $scheme = \is_array($parts) && isset($parts['scheme']) ? $parts['scheme'] : 'https';
        $host = \is_array($parts) && isset($parts['host']) ? $parts['host'] : 'localhost';
        $port = \is_array($parts) && isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = \is_array($parts) && isset($parts['path']) ? rtrim($parts['path'], '/') : '';

        return \sprintf('%s://%s@%s%s%s/%d', $scheme, $project->getPublicKey(), $host, $port, $path, $project->getId() ?? 0);
    }
}
