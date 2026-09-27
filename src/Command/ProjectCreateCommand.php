<?php

declare(strict_types=1);

namespace App\Command;

use App\Project\Dsn;
use App\Project\OriginList;
use App\Project\ProjectManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'faultline:project:create', description: 'Create a project and print its DSN')]
final class ProjectCreateCommand extends Command
{
    public function __construct(
        private readonly ProjectManager $projects,
        private readonly Dsn $dsn,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Project name')
            ->addOption('origin', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Allowed browser origin (repeatable)')
            ->addOption('retention', null, InputOption::VALUE_REQUIRED, 'Retention in days', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $retention = $input->getOption('retention');
        if (!\is_string($retention) || !ctype_digit($retention)) {
            $io->error('Retention must be a whole number of days.');

            return Command::INVALID;
        }

        try {
            $project = $this->projects->create(
                (string) $input->getArgument('name'),
                OriginList::parse((array) $input->getOption('origin')),
                (int) $retention,
            );
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->success(\sprintf('Project "%s" created.', $project->getName()));
        $io->writeln($this->dsn->for($project));

        return Command::SUCCESS;
    }
}
