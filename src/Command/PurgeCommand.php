<?php

declare(strict_types=1);

namespace App\Command;

use App\Maintenance\Purger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'faultline:purge', description: 'Delete events past each project retention and issues left without events')]
final class PurgeCommand extends Command
{
    public function __construct(private readonly Purger $purger)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->purger->purge();

        (new SymfonyStyle($input, $output))->success(\sprintf('Deleted %d events and %d issues.', $result->events, $result->issues));

        return Command::SUCCESS;
    }
}
