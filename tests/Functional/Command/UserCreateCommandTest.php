<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserCreateCommandTest extends KernelTestCase
{
    public function testCreatesAndUpdatesTheAdmin(): void
    {
        $tester = $this->tester();
        $tester->setInputs(['a long enough secret', 'a long enough secret']);
        $tester->execute(['username' => 'admin']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('User "admin" created.', $tester->getDisplay());
        $user = self::getContainer()->get(UserRepository::class)->findOneByUsername('admin');
        self::assertNotNull($user);
        self::assertSame(['ROLE_ADMIN'], $user->getRoles());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'a long enough secret'));

        $tester->setInputs(['another long secret', 'another long secret']);
        $tester->execute(['username' => 'admin']);
        self::assertStringContainsString('Password of "admin" updated.', $tester->getDisplay());
    }

    public function testRejectsBadInput(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['username' => 'a b']));

        $tester->setInputs(['short', 'short']);
        self::assertSame(Command::INVALID, $tester->execute(['username' => 'admin']));
        self::assertStringContainsString('at least 12 characters', $tester->getDisplay());

        $tester->setInputs(['a long enough secret', 'something else entirely']);
        self::assertSame(Command::INVALID, $tester->execute(['username' => 'admin']));
        self::assertStringContainsString('do not match', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        return new CommandTester((new Application(self::bootKernel()))->find('faultline:user:create'));
    }
}
