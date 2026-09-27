<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ProjectCreateCommandTest extends KernelTestCase
{
    public function testCreatesAProjectAndPrintsItsDsn(): void
    {
        $tester = $this->tester();

        $tester->execute(['name' => 'Home Dashboard', '--origin' => ['https://home.example.com'], '--retention' => '14']);

        $tester->assertCommandIsSuccessful();
        $project = $this->projects()->findOneBySlug('home-dashboard');
        self::assertNotNull($project);
        self::assertSame(['https://home.example.com'], $project->getAllowedOrigins());
        self::assertSame(14, $project->getRetentionDays());
        self::assertStringContainsString($project->getPublicKey().'@localhost/'.$project->getId(), $tester->getDisplay());
    }

    public function testMakesSlugsUnique(): void
    {
        $this->tester()->execute(['name' => 'Tracker']);
        $this->tester()->execute(['name' => 'Tracker']);
        $this->tester()->execute(['name' => '???']);

        self::assertNotNull($this->projects()->findOneBySlug('tracker-2'));
        self::assertNotNull($this->projects()->findOneBySlug('project'));
    }

    public function testRejectsInvalidInput(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['name' => 'X', '--retention' => 'ten']));
        self::assertSame(Command::INVALID, $tester->execute(['name' => 'X', '--retention' => '0']));
        self::assertSame(Command::INVALID, $tester->execute(['name' => '   ']));
        self::assertSame(Command::INVALID, $tester->execute(['name' => 'X', '--origin' => ['not-an-origin']]));
        self::assertStringContainsString('not a valid origin', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        return new CommandTester((new Application(self::bootKernel()))->find('faultline:project:create'));
    }

    private function projects(): ProjectRepository
    {
        return self::getContainer()->get(ProjectRepository::class);
    }
}
