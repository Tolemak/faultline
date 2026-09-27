<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

trait AdminFixtures
{
    private const string ADMIN_PASSWORD = 'correct horse battery';

    private static function createAdmin(ContainerInterface $container, string $username = 'admin'): User
    {
        $user = new User($username);
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, self::ADMIN_PASSWORD));
        $container->get(UserRepository::class)->save($user);

        return $user;
    }
}
