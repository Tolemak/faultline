<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\Event;
use App\Entity\Issue;
use App\Entity\IssueDailyCount;
use App\Repository\ProjectRepository;
use App\Tests\Support\EventSeeder;
use App\Tests\Support\ProjectFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class MaintenanceCommandsTest extends KernelTestCase
{
    use EventSeeder;
    use ProjectFixtures;

    public function testPurgesEventsPastRetentionAndEmptyIssues(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $short = self::createProject($container, 'Short');
        $short->setRetentionDays(7);
        $long = self::createProject($container, 'Long');
        $container->get(ProjectRepository::class)->save($short);

        self::seedEvent($container, $short, ['message' => 'Old only'], new \DateTimeImmutable('-10 days'));
        self::seedEvent($container, $short, ['message' => 'Mixed'], new \DateTimeImmutable('-10 days'));
        self::seedEvent($container, $short, ['message' => 'Mixed'], new \DateTimeImmutable('-1 day'));
        self::seedEvent($container, $long, ['message' => 'Kept'], new \DateTimeImmutable('-10 days'));

        $tester = new CommandTester((new Application(self::$kernel ?? throw new \LogicException()))->find('faultline:purge'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Deleted 2 events and 1 issues.', $tester->getDisplay());

        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->clear();
        $titles = array_map(static fn (Issue $issue): string => $issue->getTitle(), $entityManager->getRepository(Issue::class)->findBy([], ['title' => 'ASC']));
        self::assertSame(['Kept', 'Mixed'], $titles);
        self::assertSame(2, $entityManager->getRepository(Event::class)->count([]));
        self::assertSame(2, $entityManager->getRepository(IssueDailyCount::class)->count([]));
    }

    public function testRotatesProjectKeys(): void
    {
        self::bootKernel();
        $project = self::createProject(self::getContainer(), 'Shop');
        $oldKey = $project->getPublicKey();
        $tester = new CommandTester((new Application(self::$kernel ?? throw new \LogicException()))->find('faultline:project:rotate-key'));

        $tester->execute(['slug' => 'shop']);

        $tester->assertCommandIsSuccessful();
        self::assertNotSame($oldKey, $project->getPublicKey());
        self::assertStringContainsString($project->getPublicKey().'@localhost/'.$project->getId(), $tester->getDisplay());

        self::assertSame(Command::FAILURE, $tester->execute(['slug' => 'missing']));
    }
}
