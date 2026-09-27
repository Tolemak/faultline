<?php

declare(strict_types=1);

namespace App\Tests\Functional\Processing;

use App\Entity\Event;
use App\Entity\Issue;
use App\Entity\IssueDailyCount;
use App\Entity\Project;
use App\Enum\IssueStatus;
use App\Enum\Level;
use App\Message\ProcessEvent;
use App\Notification\IssueChange;
use App\Notification\IssueNotifierInterface;
use App\Processing\ProcessEventHandler;
use App\Processing\Scrubber;
use App\Tests\Support\NotifierSpy;
use App\Tests\Support\ProjectFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProcessEventHandlerTest extends KernelTestCase
{
    use ProjectFixtures;

    private Project $project;
    private NotifierSpy $notifier;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->notifier = new NotifierSpy();
        self::getContainer()->set(IssueNotifierInterface::class, $this->notifier);
        $this->project = self::createProject(self::getContainer());

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
    }

    public function testCreatesAnIssueEventAndDailyCount(): void
    {
        $this->process('a0000000000000000000000000000001', $this->exceptionPayload() + [
            'level' => 'fatal',
            'release' => 'shop@1.0.0',
            'environment' => 'production',
            'request' => ['url' => 'https://shop.example.com/pay?token=abc', 'headers' => ['Authorization' => 'Bearer x', 'Accept' => 'json']],
            'user' => ['id' => '1', 'ip_address' => '192.0.2.4'],
            'extra' => ['password' => 'hunter2'],
        ]);

        $issues = $this->issues();
        self::assertCount(1, $issues);
        $issue = $issues[0];
        self::assertSame('TypeError: Cannot read id', $issue->getTitle());
        self::assertSame('app/orders in load', $issue->getCulprit());
        self::assertSame(Level::Fatal, $issue->getLevel());
        self::assertSame('shop@1.0.0', $issue->getLastRelease());
        self::assertSame(1, $issue->getEventCount());
        self::assertSame(IssueStatus::Unresolved, $issue->getStatus());

        $event = $this->events()[0];
        self::assertSame('a0000000000000000000000000000001', $event->getEventId());
        self::assertSame('production', $event->getEnvironment());
        self::assertSame(['id' => '1'], $event->getUser());
        self::assertSame(['password' => Scrubber::FILTERED], $event->getExtra());
        self::assertSame(['Accept' => 'json'], ($event->getRequest() ?? [])['headers'] ?? null);
        self::assertSame('https://shop.example.com/pay?token=[filtered]', ($event->getRequest() ?? [])['url'] ?? null);

        $counts = $this->entityManager->getRepository(IssueDailyCount::class)->findAll();
        self::assertCount(1, $counts);
        self::assertSame(1, $counts[0]->getCount());
        self::assertSame('2026-09-27', $counts[0]->getDay()->format('Y-m-d'));

        self::assertCount(1, $this->notifier->calls);
        self::assertSame(IssueChange::New, $this->notifier->calls[0][1]);
    }

    public function testGroupsSimilarEventsIntoOneIssue(): void
    {
        $this->process('a0000000000000000000000000000001', $this->exceptionPayload(10));
        $this->process('a0000000000000000000000000000002', $this->exceptionPayload(99));

        $issues = $this->issues();
        self::assertCount(1, $issues);
        self::assertSame(2, $issues[0]->getEventCount());
        self::assertCount(2, $this->events());
        self::assertSame(2, $this->entityManager->getRepository(IssueDailyCount::class)->findAll()[0]->getCount());
        self::assertCount(1, $this->notifier->calls);
    }

    public function testIgnoresDuplicateEventIds(): void
    {
        $this->process('a0000000000000000000000000000001', $this->exceptionPayload());
        $this->process('a0000000000000000000000000000001', $this->exceptionPayload());

        self::assertCount(1, $this->events());
        self::assertSame(1, $this->issues()[0]->getEventCount());
    }

    public function testReopensResolvedIssuesAsRegressions(): void
    {
        $this->process('a0000000000000000000000000000001', $this->exceptionPayload());
        $issue = $this->issues()[0];
        $issue->resolve();
        $this->entityManager->flush();

        $this->process('a0000000000000000000000000000002', $this->exceptionPayload());

        $this->entityManager->refresh($issue);
        self::assertSame(IssueStatus::Unresolved, $issue->getStatus());
        self::assertSame(IssueChange::Regression, $this->notifier->calls[1][1] ?? null);
    }

    public function testSeparatesDifferentProblems(): void
    {
        $this->process('a0000000000000000000000000000001', ['message' => 'Queue full']);
        $this->process('a0000000000000000000000000000002', ['message' => 'Disk full']);

        self::assertCount(2, $this->issues());
    }

    public function testDropsEventsForMissingProjects(): void
    {
        $this->handler()(new ProcessEvent(999999, 'a0000000000000000000000000000001', '{"message":"x"}', new \DateTimeImmutable('2026-09-27 10:00')));

        self::assertSame([], $this->issues());
    }

    public function testDropsUndecodablePayloads(): void
    {
        $this->handler()(new ProcessEvent(self::projectId($this->project), 'a0000000000000000000000000000001', '[1,2]', new \DateTimeImmutable('2026-09-27 10:00')));

        self::assertSame([], $this->issues());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function process(string $eventId, array $payload): void
    {
        $this->handler()(new ProcessEvent(self::projectId($this->project), $eventId, json_encode($payload, \JSON_THROW_ON_ERROR), new \DateTimeImmutable('2026-09-27 10:00')));
    }

    private function handler(): ProcessEventHandler
    {
        $handler = self::getContainer()->get(ProcessEventHandler::class);
        \assert($handler instanceof ProcessEventHandler);

        return $handler;
    }

    /**
     * @return array<string, mixed>
     */
    private function exceptionPayload(int $line = 10): array
    {
        return ['exception' => ['values' => [[
            'type' => 'TypeError',
            'value' => 'Cannot read id',
            'stacktrace' => ['frames' => [
                ['module' => 'vendor/http', 'function' => 'handle', 'lineno' => 5, 'in_app' => false],
                ['module' => 'app/orders', 'function' => 'load', 'lineno' => $line, 'in_app' => true],
            ]],
        ]]]];
    }

    /**
     * @return list<Issue>
     */
    private function issues(): array
    {
        return array_values($this->entityManager->getRepository(Issue::class)->findBy(['project' => $this->project]));
    }

    /**
     * @return list<Event>
     */
    private function events(): array
    {
        return array_values($this->entityManager->getRepository(Event::class)->findBy(['project' => $this->project]));
    }
}
