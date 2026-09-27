<?php

declare(strict_types=1);

namespace App\Query;

use App\Enum\IssueStatus;
use App\Enum\Level;
use Symfony\Component\HttpFoundation\Request;

final readonly class IssueCriteria
{
    public const int PER_PAGE = 25;
    public const array SORTS = ['last_seen', 'events', 'first_seen'];

    public function __construct(
        public ?IssueStatus $status = IssueStatus::Unresolved,
        public ?Level $level = null,
        public ?string $environment = null,
        public ?string $release = null,
        public ?string $search = null,
        public string $sort = 'last_seen',
        public int $page = 1,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $query = $request->query;
        $status = self::string($query->all()['status'] ?? null);

        return new self(
            status: 'all' === $status ? null : (IssueStatus::tryFrom($status ?? '') ?? IssueStatus::Unresolved),
            level: Level::tryFrom(self::string($query->all()['level'] ?? null) ?? ''),
            environment: self::limited($query->all()['environment'] ?? null, 64),
            release: self::limited($query->all()['release'] ?? null, 200),
            search: self::limited($query->all()['q'] ?? null, 200),
            sort: \in_array($query->all()['sort'] ?? null, self::SORTS, true) ? (string) $query->all()['sort'] : 'last_seen',
            page: max(1, min(10_000, (int) (self::string($query->all()['page'] ?? null) ?? 1))),
        );
    }

    /**
     * @return array<string, string|int>
     */
    public function toQuery(): array
    {
        return array_filter([
            'status' => $this->status->value ?? 'all',
            'level' => $this->level?->value,
            'environment' => $this->environment,
            'release' => $this->release,
            'q' => $this->search,
            'sort' => 'last_seen' === $this->sort ? null : $this->sort,
        ], static fn (mixed $value): bool => null !== $value);
    }

    private static function string(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    private static function limited(mixed $value, int $length): ?string
    {
        $text = self::string($value);

        return null === $text ? null : mb_substr($text, 0, $length);
    }
}
