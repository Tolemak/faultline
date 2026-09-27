<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\IssueDailyCountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IssueDailyCountRepository::class)]
#[ORM\Table(name: 'issue_daily_count')]
#[ORM\UniqueConstraint(name: 'issue_daily_count_issue_day', columns: ['issue_id', 'day'])]
#[ORM\Index(name: 'issue_daily_count_day', columns: ['day'])]
class IssueDailyCount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Issue $issue;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $day;

    #[ORM\Column]
    private int $count;

    public function __construct(Issue $issue, \DateTimeImmutable $day, int $count)
    {
        $this->issue = $issue;
        $this->day = $day;
        $this->count = $count;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIssue(): Issue
    {
        return $this->issue;
    }

    public function getDay(): \DateTimeImmutable
    {
        return $this->day;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
