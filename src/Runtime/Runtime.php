<?php

declare(strict_types=1);

namespace App\Runtime;

use Symfony\Component\Runtime\SymfonyRuntime;

final class Runtime extends SymfonyRuntime
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        $projectDir = $options['project_dir'] ?? null;

        if (\is_string($projectDir) && true !== ($options['disable_dotenv'] ?? false)) {
            EnvFiles::load($projectDir);
            $options['disable_dotenv'] = true;
        }

        parent::__construct($options);
    }
}
