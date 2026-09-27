<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ui;

use App\Entity\Issue;
use App\Entity\Project;
use App\Enum\IssueStatus;
use App\Repository\IssueRepository;
use App\Tests\Support\AdminFixtures;
use App\Tests\Support\EventSeeder;
use App\Tests\Support\ProjectFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class IssuePagesTest extends WebTestCase
{
    use AdminFixtures;
    use EventSeeder;
    use ProjectFixtures;

    private KernelBrowser $client;
    private Project $project;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->loginUser(self::createAdmin(self::getContainer()));
        $this->project = self::createProject(self::getContainer(), 'Shop');
    }

    public function testListsUnresolvedIssuesByDefault(): void
    {
        $this->seedIssues();
        $this->issue('Disk full')->resolve();
        $this->flush();

        $crawler = $this->client->request('GET', '/projects/shop/issues');

        self::assertResponseIsSuccessful();
        self::assertSame(['Queue full', 'TimeoutError: Gateway timeout', 'PaymentError: Card declined'], $this->titles($crawler));
        self::assertSelectorTextContains('.page-head .muted', '3 issues');
        self::assertCount(3, $crawler->filter('.issue .trace polyline'));
    }

    public function testFiltersByStatusLevelEnvironmentAndRelease(): void
    {
        $this->seedIssues();
        $this->issue('Disk full')->ignore();
        $this->flush();

        self::assertSame(['Disk full'], $this->titles($this->client->request('GET', '/projects/shop/issues?status=ignored')));
        self::assertCount(4, $this->titles($this->client->request('GET', '/projects/shop/issues?status=all')));
        self::assertSame(['Queue full'], $this->titles($this->client->request('GET', '/projects/shop/issues?level=warning')));
        self::assertSame(['TimeoutError: Gateway timeout'], $this->titles($this->client->request('GET', '/projects/shop/issues?environment=staging')));
        self::assertSame(['PaymentError: Card declined'], $this->titles($this->client->request('GET', '/projects/shop/issues?release=shop%402.0.0')));

        $crawler = $this->client->request('GET', '/projects/shop/issues?environment=staging');
        self::assertSame(['', 'production', 'staging'], $crawler->filter('select[name=environment] option')->extract(['value']));
        self::assertSame('staging', $crawler->filter('select[name=environment] option[selected]')->attr('value'));
    }

    public function testSearchesTitlesAndCulprits(): void
    {
        $this->seedIssues();

        self::assertSame(['PaymentError: Card declined'], $this->titles($this->client->request('GET', '/projects/shop/issues?q=CARD')));
        self::assertSame(['TimeoutError: Gateway timeout'], $this->titles($this->client->request('GET', '/projects/shop/issues?q=callGateway')));
        self::assertSame([], $this->titles($this->client->request('GET', '/projects/shop/issues?q=100%25')));
        self::assertSelectorTextContains('.empty', 'No issues match');
    }

    public function testSortsByEventsAndFirstSeen(): void
    {
        $this->seedIssues();
        self::seedEvent(self::getContainer(), $this->project, self::exceptionEvent('PaymentError', 'Card declined', 'pay'));

        self::assertSame('PaymentError: Card declined', $this->titles($this->client->request('GET', '/projects/shop/issues?sort=events'))[0]);
        self::assertSame('Queue full', $this->titles($this->client->request('GET', '/projects/shop/issues?sort=first_seen'))[0]);
        self::assertSame('PaymentError: Card declined', $this->titles($this->client->request('GET', '/projects/shop/issues?sort=bogus&status=bogus'))[0]);
    }

    public function testPaginates(): void
    {
        for ($i = 1; $i <= 27; ++$i) {
            self::seedEvent(self::getContainer(), $this->project, ['message' => 'Failure in job number-'.str_repeat('x', $i)]);
        }

        $crawler = $this->client->request('GET', '/projects/shop/issues');
        self::assertCount(25, $this->titles($crawler));
        self::assertSelectorTextContains('.pager', 'Page 1 of 2');

        $crawler = $this->client->click($crawler->selectLink('Older')->link());
        self::assertCount(2, $this->titles($crawler));
        self::assertSelectorExists('.pager a[rel=prev]');

        self::assertCount(2, $this->titles($this->client->request('GET', '/projects/shop/issues?page=99')));
    }

    public function testShowsIssueDetails(): void
    {
        $this->seedIssues();
        $issue = $this->issue('PaymentError: Card declined');
        self::seedEvent(self::getContainer(), $this->project, self::exceptionEvent('PaymentError', 'Card declined', 'pay', [
            'tags' => ['browser' => 'Firefox'],
            'request' => ['url' => 'https://shop.example.com/pay', 'method' => 'POST', 'headers' => ['Accept' => 'text/html'], 'data' => ['amount' => 5]],
            'user' => ['id' => '9'],
            'contexts' => ['runtime' => ['name' => 'php']],
            'extra' => ['attempt' => 2],
            'release' => 'shop@2.0.0',
        ]), new \DateTimeImmutable('+1 second'));

        $crawler = $this->client->request('GET', '/projects/shop/issues/'.$issue->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'PaymentError: Card declined');
        self::assertSelectorTextContains('.status__label', 'Unresolved');
        self::assertSelectorTextContains('.event__head .pager', '1 of 2');
        self::assertSelectorTextContains('.frame--app .frame__fn', 'pay');
        self::assertSelectorTextContains('.code__current', '$order->pay();');
        self::assertSelectorTextContains('.frames-hidden summary', '2 library frames');
        self::assertSelectorTextContains('.frame__vars', '[filtered]');
        self::assertSelectorTextContains('.request-line', 'POST https://shop.example.com/pay');
        self::assertSelectorTextContains('.kv', 'Accept');
        self::assertStringContainsString('"attempt": 2', $crawler->filter('.event')->text(normalizeWhitespace: false));
        self::assertSelectorTextContains('.tags', 'Firefox');
        self::assertSelectorTextContains('.tags', '100%');

        $this->client->click($crawler->selectLink('Older')->link());
        self::assertSelectorTextContains('.event__head .pager', '2 of 2');
        self::assertSelectorExists('.event__head .pager a[rel=prev]');
    }

    public function testShowsMessageOnlyEvents(): void
    {
        self::seedEvent(self::getContainer(), $this->project, ['message' => 'Plain log line']);

        $this->client->request('GET', '/projects/shop/issues/'.$this->issue('Plain log line')->getId());

        self::assertSelectorTextContains('.message', 'Plain log line');
        self::assertSelectorNotExists('.exception');
    }

    public function testChangesStatusThroughTurboStreams(): void
    {
        $this->seedIssues();
        $issue = $this->issue('Queue full');
        $crawler = $this->client->request('GET', '/projects/shop/issues/'.$issue->getId());
        $form = $crawler->selectButton('Resolve')->form();

        $this->client->submit($form, [], ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html, text/html']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/vnd.turbo-stream.html; charset=UTF-8');
        self::assertStringContainsString('<turbo-stream action="replace" target="issue-status">', (string) $this->client->getResponse()->getContent());
        self::assertSelectorTextContains('.status__label', 'Resolved');
        self::assertSame(IssueStatus::Resolved, $this->reload($issue)->getStatus());
    }

    public function testChangesStatusWithoutJavascript(): void
    {
        $this->seedIssues();
        $issue = $this->issue('Queue full');

        $this->client->request('GET', '/projects/shop/issues/'.$issue->getId());
        $this->client->submitForm('Ignore');
        self::assertResponseRedirects('/projects/shop/issues/'.$issue->getId());
        self::assertSame(IssueStatus::Ignored, $this->reload($issue)->getStatus());

        $this->client->followRedirect();
        $this->client->submitForm('Reopen');
        self::assertSame(IssueStatus::Unresolved, $this->reload($issue)->getStatus());
    }

    public function testRejectsForgedStatusChanges(): void
    {
        $this->seedIssues();
        $issue = $this->issue('Queue full');

        $this->client->request('POST', '/projects/shop/issues/'.$issue->getId().'/status', ['action' => 'resolve', '_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(IssueStatus::Unresolved, $this->reload($issue)->getStatus());
    }

    public function testRejectsUnknownActions(): void
    {
        $this->seedIssues();
        $issue = $this->issue('Queue full');
        $crawler = $this->client->request('GET', '/projects/shop/issues/'.$issue->getId());
        $token = $crawler->filter('#issue-status input[name=_token]')->attr('value');

        $this->client->request('POST', '/projects/shop/issues/'.$issue->getId().'/status', ['action' => 'delete', '_token' => $token]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testHidesIssuesOfOtherProjects(): void
    {
        $other = self::createProject(self::getContainer(), 'Other');
        self::seedEvent(self::getContainer(), $other, ['message' => 'Elsewhere']);
        $issue = $this->issue('Elsewhere');

        $this->client->request('GET', '/projects/shop/issues/'.$issue->getId());
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/projects/shop/issues/999999');
        self::assertResponseStatusCodeSame(404);
    }

    private function seedIssues(): void
    {
        $container = self::getContainer();
        self::seedEvent($container, $this->project, self::exceptionEvent('PaymentError', 'Card declined', 'pay', ['environment' => 'production', 'release' => 'shop@2.0.0']), new \DateTimeImmutable('-4 hours'));
        self::seedEvent($container, $this->project, ['message' => 'Disk full', 'level' => 'fatal', 'environment' => 'production'], new \DateTimeImmutable('-3 hours'));
        self::seedEvent($container, $this->project, self::exceptionEvent('TimeoutError', 'Gateway timeout', 'callGateway', ['environment' => 'staging', 'release' => 'shop@1.9.0']), new \DateTimeImmutable('-2 hours'));
        self::seedEvent($container, $this->project, ['message' => 'Queue full', 'level' => 'warning'], new \DateTimeImmutable('-1 hour'));
    }

    /**
     * @return list<string>
     */
    private function titles(\Symfony\Component\DomCrawler\Crawler $crawler): array
    {
        return $crawler->filter('.issue__title')->each(static fn ($node): string => $node->text());
    }

    private function issue(string $title): Issue
    {
        return self::getContainer()->get(IssueRepository::class)->findOneBy(['title' => $title]) ?? throw new \LogicException('Issue not found.');
    }

    private function reload(Issue $issue): Issue
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(IssueRepository::class)->find($issue->getId()) ?? throw new \LogicException('Issue not found.');
    }

    private function flush(): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->flush();
    }
}
