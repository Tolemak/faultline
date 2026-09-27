<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\IssueStatus;
use App\Enum\Level;
use App\Repository\IssueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IssueRepository::class)]
#[ORM\Table(name: 'issue')]
#[ORM\UniqueConstraint(name: 'issue_project_fingerprint', columns: ['project_id', 'fingerprint'])]
#[ORM\Index(name: 'issue_project_status_last_seen', columns: ['project_id', 'status', 'last_seen'])]
class Issue
{
    public const int TITLE_LENGTH = 255;
    public const int CULPRIT_LENGTH = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\Column(length: 64)]
    private string $fingerprint;

    #[ORM\Column(length: self::TITLE_LENGTH)]
    private string $title;

    #[ORM\Column(length: self::CULPRIT_LENGTH, nullable: true)]
    private ?string $culprit;

    #[ORM\Column(length: 16, enumType: Level::class)]
    private Level $level;

    #[ORM\Column(length: 16, enumType: IssueStatus::class)]
    private IssueStatus $status = IssueStatus::Unresolved;

    #[ORM\Column]
    private \DateTimeImmutable $firstSeen;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeen;

    #[ORM\Column]
    private int $eventCount = 0;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $lastRelease = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastNotifiedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $regressedAt = null;

    public function __construct(Project $project, string $fingerprint, string $title, ?string $culprit, Level $level, \DateTimeImmutable $seenAt)
    {
        $this->project = $project;
        $this->fingerprint = $fingerprint;
        $this->title = $title;
        $this->culprit = $culprit;
        $this->level = $level;
        $this->firstSeen = $seenAt;
        $this->lastSeen = $seenAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCulprit(): ?string
    {
        return $this->culprit;
    }

    public function getLevel(): Level
    {
        return $this->level;
    }

    public function getStatus(): IssueStatus
    {
        return $this->status;
    }

    public function getFirstSeen(): \DateTimeImmutable
    {
        return $this->firstSeen;
    }

    public function getLastSeen(): \DateTimeImmutable
    {
        return $this->lastSeen;
    }

    public function getEventCount(): int
    {
        return $this->eventCount;
    }

    public function getLastRelease(): ?string
    {
        return $this->lastRelease;
    }

    public function getLastNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->lastNotifiedAt;
    }

    public function getRegressedAt(): ?\DateTimeImmutable
    {
        return $this->regressedAt;
    }

    public function markNotified(\DateTimeImmutable $at): void
    {
        $this->lastNotifiedAt = $at;
    }

    public function recordEvent(string $title, ?string $culprit, Level $level, ?string $release, \DateTimeImmutable $seenAt): bool
    {
        $this->title = $title;
        $this->culprit = $culprit;
        $this->level = $level;
        ++$this->eventCount;

        if ($seenAt > $this->lastSeen) {
            $this->lastSeen = $seenAt;
        }
        if ($seenAt < $this->firstSeen) {
            $this->firstSeen = $seenAt;
        }
        if (null !== $release) {
            $this->lastRelease = $release;
        }

        if (IssueStatus::Resolved === $this->status) {
            $this->status = IssueStatus::Unresolved;
            $this->regressedAt = $seenAt;

            return true;
        }

        return false;
    }

    public function resolve(): void
    {
        $this->status = IssueStatus::Resolved;
    }

    public function ignore(): void
    {
        $this->status = IssueStatus::Ignored;
    }

    public function reopen(): void
    {
        $this->status = IssueStatus::Unresolved;
    }
}
