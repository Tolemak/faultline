<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Demo\DemoMode;
use App\Entity\Issue;
use App\Entity\Project;
use App\Enum\IssueStatus;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Tests\Support\AdminFixtures;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DemoSeedCommandTest extends KernelTestCase
{
    use AdminFixtures;

    protected function tearDown(): void
    {
        unset($_SERVER['FAULTLINE_DEMO'], $_ENV['FAULTLINE_DEMO']);
        parent::tearDown();
    }

    public function testRefusesToRunOutsideDemoMode(): void
    {
        self::createAdmin(self::getContainer());

        $tester = $this->tester();
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertNotNull(self::getContainer()->get(UserRepository::class)->findOneByUsername('admin'));
    }

    public function testReplacesEverythingWithSyntheticData(): void
    {
        $_SERVER['FAULTLINE_DEMO'] = $_ENV['FAULTLINE_DEMO'] = '1';
        self::createAdmin(self::getContainer());

        $tester = $this->tester();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $container = self::getContainer();
        self::assertNull($container->get(UserRepository::class)->findOneByUsername('admin'));
        self::assertNotNull($container->get(UserRepository::class)->findOneByUsername(DemoMode::USERNAME));
        self::assertSame(['Billing worker', 'Shop API', 'Storefront'], array_map(static fn (Project $project): string => $project->getName(), $container->get(ProjectRepository::class)->findAllOrdered()));

        $issues = $container->get(IssueRepository::class)->findAll();
        self::assertCount(12, $issues);
        $statuses = array_map(static fn (Issue $issue): IssueStatus => $issue->getStatus(), $issues);
        self::assertContains(IssueStatus::Resolved, $statuses);
        self::assertContains(IssueStatus::Ignored, $statuses);
        self::assertNotEmpty(array_filter($issues, static fn (Issue $issue): bool => null !== $issue->getRegressedAt()));
        self::assertMatchesRegularExpression('/Demo data ready: \d+ events\./', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        return new CommandTester((new Application(self::bootKernel()))->find('faultline:demo:seed'));
    }
}
