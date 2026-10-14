<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Issue;
use App\Enum\IssueStatus;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'faultline:issues:list', description: 'List issues (metadata only, no event payloads) for a quick review from the shell')]
final class IssuesListCommand extends Command
{
    private const int TITLE_WIDTH = 70;

    public function __construct(
        private readonly IssueRepository $issues,
        private readonly ProjectRepository $projects,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('project', 'p', InputOption::VALUE_REQUIRED, 'Project slug')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Only issues seen since a date (ISO 8601) or a relative age such as 90m, 24h, 7d')
            ->addOption('new', null, InputOption::VALUE_NONE, 'Make --since apply to first seen instead of last seen')
            ->addOption('status', 's', InputOption::VALUE_REQUIRED, 'unresolved, resolved or ignored')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of issues', '50')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'table or json', 'table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $slug = $input->getOption('project');
        $project = null;
        if (\is_string($slug) && '' !== $slug) {
            $project = $this->projects->findOneBySlug($slug);
            if (null === $project) {
                $io->error('Unknown project.');

                return Command::FAILURE;
            }
        }

        $statusOption = $input->getOption('status');
        $status = null;
        if (\is_string($statusOption) && '' !== $statusOption) {
            $status = IssueStatus::tryFrom($statusOption);
            if (null === $status) {
                $io->error('Status must be unresolved, resolved or ignored.');

                return Command::FAILURE;
            }
        }

        $sinceOption = $input->getOption('since');
        $since = null;
        if (\is_string($sinceOption) && '' !== $sinceOption) {
            $since = self::parseSince($sinceOption, $this->clock->now());
            if (null === $since) {
                $io->error('Invalid --since, use an ISO 8601 date or an age such as 90m, 24h, 7d.');

                return Command::FAILURE;
            }
        }

        $limit = filter_var($input->getOption('limit'), \FILTER_VALIDATE_INT);
        if (false === $limit || $limit < 1 || $limit > 1000) {
            $io->error('Limit must be between 1 and 1000.');

            return Command::FAILURE;
        }

        $format = $input->getOption('format');
        if ('table' !== $format && 'json' !== $format) {
            $io->error('Format must be table or json.');

            return Command::FAILURE;
        }

        $rows = array_map(self::row(...), $this->issues->findForListing($project, $since, (bool) $input->getOption('new'), $status, $limit));

        if ('json' === $format) {
            $output->writeln(json_encode($rows, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        $io->table(
            ['Project', 'Id', 'Title', 'Culprit', 'Level', 'First seen', 'Last seen', 'Events', 'Status'],
            array_map(static fn (array $row): array => [
                $row['project'],
                $row['id'],
                self::cell($row['title']),
                self::cell($row['culprit'] ?? ''),
                $row['level'],
                $row['firstSeen'],
                $row['lastSeen'],
                $row['events'],
                $row['status'],
            ], $rows),
        );

        return Command::SUCCESS;
    }

    public static function parseSince(string $value, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        if (1 === preg_match('/^(\d{1,5})([mhdw])$/', $value, $matches)) {
            $unit = ['m' => 'minutes', 'h' => 'hours', 'd' => 'days', 'w' => 'weeks'][$matches[2]];

            return $now->modify(\sprintf('-%d %s', (int) $matches[1], $unit));
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private static function cell(string $text): string
    {
        $text = (string) preg_replace('/[\x00-\x1F\x7F\x{80}-\x{9F}]/u', ' ', $text);

        return OutputFormatter::escape(mb_strimwidth($text, 0, self::TITLE_WIDTH, '...'));
    }

    /**
     * @return array{project: string, id: int, title: string, culprit: ?string, level: string, firstSeen: string, lastSeen: string, events: int, status: string}
     */
    private static function row(Issue $issue): array
    {
        return [
            'project' => $issue->getProject()->getSlug(),
            'id' => $issue->getId() ?? 0,
            'title' => $issue->getTitle(),
            'culprit' => $issue->getCulprit(),
            'level' => $issue->getLevel()->value,
            'firstSeen' => $issue->getFirstSeen()->format(\DATE_ATOM),
            'lastSeen' => $issue->getLastSeen()->format(\DATE_ATOM),
            'events' => $issue->getEventCount(),
            'status' => $issue->getStatus()->value,
        ];
    }
}
