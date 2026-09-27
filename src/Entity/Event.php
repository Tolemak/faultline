<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Level;
use App\Processing\NormalizedEvent;
use App\Repository\EventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
#[ORM\UniqueConstraint(name: 'event_project_event_id', columns: ['project_id', 'event_id'])]
#[ORM\Index(name: 'event_issue_received_at', columns: ['issue_id', 'received_at'])]
#[ORM\Index(name: 'event_received_at', columns: ['received_at'])]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Issue $issue;

    #[ORM\Column(length: 32)]
    private string $eventId;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(length: 16, enumType: Level::class)]
    private Level $level;

    #[ORM\Column(length: 64)]
    private string $platform;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $environment;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $release;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $transaction;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message;

    /**
     * @var list<array<string, mixed>>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $exception;

    /**
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $tags;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $contexts;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $request;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'user_data', type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $user;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $extra;

    public function __construct(Project $project, Issue $issue, NormalizedEvent $event, \DateTimeImmutable $receivedAt)
    {
        $this->project = $project;
        $this->issue = $issue;
        $this->eventId = $event->eventId;
        $this->receivedAt = $receivedAt;
        $this->occurredAt = $event->occurredAt;
        $this->level = $event->level;
        $this->platform = $event->platform;
        $this->environment = $event->environment;
        $this->release = $event->release;
        $this->transaction = $event->transaction;
        $this->message = $event->message;
        $this->exception = $event->exceptions;
        $this->tags = $event->tags;
        $this->contexts = $event->contexts;
        $this->request = $event->request;
        $this->user = $event->user;
        $this->extra = $event->extra;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getIssue(): Issue
    {
        return $this->issue;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getLevel(): Level
    {
        return $this->level;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function getRelease(): ?string
    {
        return $this->release;
    }

    public function getTransaction(): ?string
    {
        return $this->transaction;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getException(): array
    {
        return $this->exception;
    }

    /**
     * @return array<string, string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContexts(): array
    {
        return $this->contexts;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRequest(): ?array
    {
        return $this->request;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUser(): ?array
    {
        return $this->user;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtra(): array
    {
        return $this->extra;
    }
}
