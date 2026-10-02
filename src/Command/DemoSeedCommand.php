<?php

declare(strict_types=1);

namespace App\Command;

use App\Demo\DemoMode;
use App\Demo\DemoSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'faultline:demo:seed', description: 'Wipe the database and fill it with synthetic demo data (demo instances only)')]
final class DemoSeedCommand extends Command
{
    public function __construct(
        private readonly DemoMode $demo,
        private readonly DemoSeeder $seeder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->demo->isEnabled()) {
            $io->error('This command wipes the database. It only runs with FAULTLINE_DEMO=1.');

            return Command::FAILURE;
        }

        $events = $this->seeder->seed();
        $io->success(\sprintf('Demo data ready: %d events.', $events));

        return Command::SUCCESS;
    }
}
