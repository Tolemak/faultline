<?php

declare(strict_types=1);

namespace App\Demo;

use App\Entity\Issue;
use App\Entity\User;
use App\Ingest\EventId;
use App\Processing\EventNormalizer;
use App\Processing\EventRecorder;
use App\Processing\Grouper;
use App\Processing\IssueSummary;
use App\Processing\Scrubber;
use App\Project\ProjectManager;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @phpstan-type Frame array{0: string, 1: string, 2: int, 3: bool, 4?: string}
 * @phpstan-type IssueSpec array{type?: string, value: string, level: string, perDay: int, status?: 'resolved'|'ignored'|'regressed', frames?: list<Frame>}
 * @phpstan-type ProjectSpec array{name: string, platform: string, releases: list<string>, tags: array<string, list<string>>, url?: string, issues: list<IssueSpec>}
 */
final readonly class DemoSeeder
{
    public const int DAYS = 14;
    private const int SEED = 2026;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectManager $projects,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
        private EventNormalizer $normalizer,
        private Scrubber $scrubber,
        private Grouper $grouper,
        private EventRecorder $recorder,
        private ClockInterface $clock,
    ) {
    }

    public function seed(): int
    {
        $this->wipe();
        $this->createUser();

        $random = new Randomizer(new Mt19937(self::SEED));
        $now = $this->clock->now();
        $recorded = 0;

        foreach (self::projects() as $spec) {
            $project = $this->projects->create($spec['name']);

            $events = [];
            foreach ($spec['issues'] as $index => $issue) {
                foreach ($this->schedule($issue, $random) as $ageSeconds) {
                    $events[] = [$ageSeconds, $index];
                }
            }
            usort($events, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

            /** @var array<int, Issue> $issues */
            $issues = [];
            /** @var array<int, true> $resolved */
            $resolved = [];
            foreach ($events as [$ageSeconds, $index]) {
                $issueSpec = $spec['issues'][$index];
                if ('regressed' === ($issueSpec['status'] ?? null) && $ageSeconds < 2 * 86400 && isset($issues[$index]) && !isset($resolved[$index])) {
                    $issues[$index]->resolve();
                    $this->entityManager->flush();
                    $resolved[$index] = true;
                }

                $at = $now->modify(\sprintf('-%d seconds', $ageSeconds));
                $event = $this->scrubber->scrubEvent($this->normalizer->normalize(EventId::generate(), $this->payload($spec, $issueSpec, $ageSeconds, $at, $random), $at));
                $result = $this->recorder->record($project, $event, $this->grouper->fingerprint($event), IssueSummary::of($event), $at);
                if (null !== $result) {
                    $issues[$index] = $result->issue;
                    ++$recorded;
                }
            }

            foreach ($issues as $index => $issue) {
                $status = $spec['issues'][$index]['status'] ?? null;
                if ('resolved' === $status) {
                    $issue->resolve();
                } elseif ('ignored' === $status) {
                    $issue->ignore();
                }
            }
            $this->entityManager->flush();
        }

        $this->entityManager->clear();

        return $recorded;
    }

    private function wipe(): void
    {
        $this->entityManager->getConnection()->executeStatement('TRUNCATE event, issue_daily_count, issue, project, app_user, messenger_messages RESTART IDENTITY CASCADE');
        $this->entityManager->clear();
    }

    private function createUser(): void
    {
        $user = new User(DemoMode::USERNAME);
        $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(16))));
        $this->users->save($user);
    }

    /**
     * @param IssueSpec $issue
     *
     * @return list<int>
     */
    private function schedule(array $issue, Randomizer $random): array
    {
        $status = $issue['status'] ?? null;
        $ages = [];
        for ($day = self::DAYS - 1; $day >= 0; --$day) {
            $quiet = match ($status) {
                'resolved' => $day < 4,
                'regressed' => $day >= 2 && $day < 5,
                default => false,
            };
            if ($quiet) {
                continue;
            }

            $count = $random->getInt(0, 2 * $issue['perDay']);
            for ($i = 0; $i < $count; ++$i) {
                $ages[] = $day * 86400 + $random->getInt(60, 86399);
            }
        }

        return $ages;
    }

    /**
     * @param ProjectSpec $project
     * @param IssueSpec   $issue
     *
     * @return array<string, mixed>
     */
    private function payload(array $project, array $issue, int $ageSeconds, \DateTimeImmutable $at, Randomizer $random): array
    {
        $releases = $project['releases'];
        $releaseIndex = min(\count($releases) - 1, intdiv((self::DAYS * 86400 - $ageSeconds) * \count($releases), self::DAYS * 86400));

        $tags = [];
        foreach ($project['tags'] as $name => $values) {
            $tags[$name] = $values[$random->getInt(0, \count($values) - 1)];
        }

        $payload = [
            'timestamp' => $at->getTimestamp(),
            'level' => $issue['level'],
            'platform' => $project['platform'],
            'environment' => 'production',
            'release' => $releases[$releaseIndex],
            'tags' => $tags,
            'user' => ['id' => 'user-'.$random->getInt(1, 400)],
        ];

        if (isset($project['url'])) {
            $payload['request'] = ['method' => 'GET', 'url' => $project['url']];
        }

        if (!isset($issue['type'])) {
            return $payload + ['message' => $issue['value']];
        }

        $frames = array_map(static fn (array $frame): array => [
            'filename' => $frame[0],
            'function' => $frame[1],
            'lineno' => $frame[2],
            'in_app' => $frame[3],
            'context_line' => $frame[4] ?? null,
        ], $issue['frames'] ?? []);

        return $payload + ['exception' => ['values' => [[
            'type' => $issue['type'],
            'value' => $issue['value'],
            'stacktrace' => ['frames' => $frames],
        ]]]];
    }

    /**
     * @return list<ProjectSpec>
     */
    private static function projects(): array
    {
        return [
            [
                'name' => 'Shop API',
                'platform' => 'php',
                'releases' => ['shop-api@2.13.0', 'shop-api@2.14.0', 'shop-api@2.14.1'],
                'tags' => ['server_name' => ['api-1', 'api-2'], 'php' => ['8.4.12']],
                'url' => 'https://shop.example.test/api/orders',
                'issues' => [
                    ['type' => 'Doctrine\DBAL\Exception\UniqueConstraintViolationException', 'value' => 'Duplicate key value violates unique constraint "order_number_unique"', 'level' => 'error', 'perDay' => 6, 'frames' => [
                        ['vendor/symfony/http-kernel/HttpKernel.php', 'handleRaw', 183, false],
                        ['src/Controller/OrderController.php', 'create', 48, true, '$order = $this->orders->place($cart);'],
                        ['src/Order/OrderNumberGenerator.php', 'next', 31, true, '$this->connection->insert(\'order_number\', [\'value\' => $next]);'],
                    ]],
                    ['type' => 'TypeError', 'value' => 'App\Payment\PaymentGateway::charge(): Argument #2 ($amount) must be of type int, float given', 'level' => 'error', 'perDay' => 3, 'status' => 'regressed', 'frames' => [
                        ['vendor/symfony/messenger/Middleware/HandleMessageMiddleware.php', 'callHandler', 157, false],
                        ['src/Payment/ChargeOrderHandler.php', '__invoke', 27, true, '$this->gateway->charge($order->getId(), $order->getTotal() * 100);'],
                        ['src/Payment/PaymentGateway.php', 'charge', 19, true, 'public function charge(int $orderId, int $amount): Charge'],
                    ]],
                    ['type' => 'Symfony\Component\HttpClient\Exception\TimeoutException', 'value' => 'Idle timeout reached for "https://courier.example.test/v2/labels".', 'level' => 'warning', 'perDay' => 9, 'frames' => [
                        ['vendor/symfony/http-client/Response/CurlResponse.php', 'perform', 312, false],
                        ['src/Shipping/CourierClient.php', 'createLabel', 54, true, '$response = $this->http->request(\'POST\', \'/v2/labels\', [\'json\' => $label]);'],
                    ]],
                    ['type' => 'RuntimeException', 'value' => 'Stock reservation expired before checkout finished', 'level' => 'warning', 'perDay' => 2, 'status' => 'resolved', 'frames' => [
                        ['src/Checkout/CheckoutService.php', 'complete', 88, true, 'throw new \RuntimeException(\'Stock reservation expired before checkout finished\');'],
                    ]],
                    ['value' => 'Slow query on product search took more than 2 s', 'level' => 'info', 'perDay' => 4, 'status' => 'ignored'],
                ],
            ],
            [
                'name' => 'Storefront',
                'platform' => 'javascript',
                'releases' => ['storefront@5.7.2', 'storefront@5.8.0'],
                'tags' => ['browser' => ['Chrome 141', 'Firefox 143', 'Safari 26', 'Edge 141'], 'os' => ['Windows 11', 'macOS 26', 'Android 16', 'iOS 26']],
                'url' => 'https://shop.example.test/products',
                'issues' => [
                    ['type' => 'TypeError', 'value' => "Cannot read properties of undefined (reading 'price')", 'level' => 'error', 'perDay' => 12, 'frames' => [
                        ['assets/vendor/react-dom.js', 'renderWithHooks', 11021, false],
                        ['assets/components/ProductList.tsx', 'ProductList', 22, true, '{products.map((product) => <ProductCard key={product.id} product={product} />)}'],
                        ['assets/components/ProductCard.tsx', 'ProductCard', 14, true, 'const total = formatPrice(product.variant.price);'],
                    ]],
                    ['type' => 'ChunkLoadError', 'value' => 'Loading chunk 412 failed.', 'level' => 'warning', 'perDay' => 4, 'status' => 'ignored', 'frames' => [
                        ['assets/runtime.js', 'requireEnsure', 118, false],
                    ]],
                    ['type' => 'Error', 'value' => 'Hydration failed because the server rendered HTML did not match the client.', 'level' => 'error', 'perDay' => 5, 'frames' => [
                        ['assets/vendor/react-dom.js', 'throwOnHydrationMismatch', 4410, false],
                        ['assets/components/CartBadge.tsx', 'CartBadge', 9, true, 'return <span>{new Date().toLocaleTimeString()}</span>;'],
                    ]],
                    ['type' => 'SecurityError', 'value' => "Failed to read the 'localStorage' property from 'Window': Access is denied for this document.", 'level' => 'warning', 'perDay' => 2, 'status' => 'resolved', 'frames' => [
                        ['assets/preferences.ts', 'loadTheme', 6, true, 'const theme = window.localStorage.getItem(\'theme\');'],
                    ]],
                ],
            ],
            [
                'name' => 'Billing worker',
                'platform' => 'python',
                'releases' => ['billing@1.4.0', 'billing@1.5.0'],
                'tags' => ['server_name' => ['worker-1'], 'runtime' => ['CPython 3.13.7']],
                'issues' => [
                    ['type' => 'KeyError', 'value' => "'vat_rate'", 'level' => 'error', 'perDay' => 3, 'status' => 'regressed', 'frames' => [
                        ['billing/tasks.py', 'run_monthly', 41, true, 'invoice = build_invoice(customer, period)'],
                        ['billing/invoices.py', 'build_invoice', 77, true, 'rate = TAX_RATES[customer.country]["vat_rate"]'],
                    ]],
                    ['type' => 'DeadlockDetected', 'value' => 'deadlock detected', 'level' => 'fatal', 'perDay' => 1, 'frames' => [
                        ['psycopg/cursor.py', 'execute', 732, false],
                        ['billing/ledger.py', 'post_entries', 58, true, 'cur.execute(INSERT_ENTRY, entry)'],
                    ]],
                    ['type' => 'ConnectionError', 'value' => "HTTPSConnectionPool(host='rates.example.test', port=443): Max retries exceeded", 'level' => 'warning', 'perDay' => 5, 'frames' => [
                        ['requests/adapters.py', 'send', 700, false],
                        ['billing/fx.py', 'fetch_rates', 23, true, 'response = session.get(RATES_URL, timeout=5)'],
                    ]],
                ],
            ],
        ];
    }
}
