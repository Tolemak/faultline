<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'project')]
class Project
{
    public const int DEFAULT_RETENTION_DAYS = 30;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 100, unique: true)]
    private string $slug;

    #[ORM\Column(length: 32, unique: true)]
    private string $publicKey;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $allowedOrigins = [];

    #[ORM\Column]
    private int $retentionDays = self::DEFAULT_RETENTION_DAYS;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, string $slug, string $publicKey, \DateTimeImmutable $createdAt)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->publicKey = $publicKey;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function rotateKey(string $publicKey): void
    {
        $this->publicKey = $publicKey;
    }

    /**
     * @return list<string>
     */
    public function getAllowedOrigins(): array
    {
        return $this->allowedOrigins;
    }

    /**
     * @param list<string> $origins
     */
    public function setAllowedOrigins(array $origins): void
    {
        $this->allowedOrigins = array_values(array_unique($origins));
    }

    public function allowsOrigin(string $origin): bool
    {
        return \in_array('*', $this->allowedOrigins, true) || \in_array($origin, $this->allowedOrigins, true);
    }

    public function getRetentionDays(): int
    {
        return $this->retentionDays;
    }

    public function setRetentionDays(int $days): void
    {
        $this->retentionDays = $days;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
