<?php

declare(strict_types=1);

namespace App\Command;

use App\Project\Dsn;
use App\Project\ProjectManager;
use App\Repository\ProjectRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'faultline:project:rotate-key', description: 'Replace the public key of a project and print the new DSN')]
final class ProjectRotateKeyCommand extends Command
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ProjectManager $manager,
        private readonly Dsn $dsn,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('slug', InputArgument::REQUIRED, 'Project slug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $project = $this->projects->findOneBySlug((string) $input->getArgument('slug'));

        if (null === $project) {
            $io->error('Unknown project.');

            return Command::FAILURE;
        }

        $this->manager->rotateKey($project);
        $io->success(\sprintf('Key of "%s" rotated. The old DSN no longer works.', $project->getName()));
        $io->writeln($this->dsn->for($project));

        return Command::SUCCESS;
    }
}
