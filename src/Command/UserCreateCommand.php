<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'faultline:user:create', description: 'Create the admin user or reset its password')]
final class UserCreateCommand extends Command
{
    public const int MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, 'Login name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = trim((string) $input->getArgument('username'));
        if (1 !== preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username)) {
            $io->error('Username must be 3 to 64 characters: letters, digits, dot, dash or underscore.');

            return Command::INVALID;
        }

        $password = $io->askHidden('Password');
        $repeat = $io->askHidden('Repeat password');
        if (!\is_string($password) || mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $io->error(\sprintf('Password must be at least %d characters long.', self::MIN_PASSWORD_LENGTH));

            return Command::INVALID;
        }
        if ($password !== $repeat) {
            $io->error('Passwords do not match.');

            return Command::INVALID;
        }

        $user = $this->users->findOneByUsername($username);
        $created = null === $user;
        $user ??= new User($username);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->users->save($user);

        $io->success($created ? \sprintf('User "%s" created.', $username) : \sprintf('Password of "%s" updated.', $username));

        return Command::SUCCESS;
    }
}
