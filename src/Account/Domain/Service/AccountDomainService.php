<?php

declare(strict_types=1);

namespace App\Account\Domain\Service;

use App\Account\Domain\Entity\AccountCore;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class AccountDomainService
{
    public function __construct(
        private EntityManagerInterface      $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function register(string $email, string $plainPassword): AccountCore
    {
        // Prevent duplicate accounts by email
        $existing = $this->findByEmail($email);
        if ($existing !== null) {
            throw new LogicException('An account with this email already exists.');
        }

        // Create a temporary AccountCore to satisfy the hasher's interface
        $tempAccount    = new AccountCore($email, '');
        $hashedPassword = $this->passwordHasher->hashPassword($tempAccount, $plainPassword);
        $account        = new AccountCore($email, $hashedPassword);

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
    }

    public function findByEmail(string $email): ?AccountCore
    {
        return $this->entityManager->getRepository(AccountCore::class)->findOneBy(['email' => $email]);
    }

    public function verifyPassword(AccountCore $account, string $plainPassword): bool
    {
        return $this->passwordHasher->isPasswordValid($account, $plainPassword);
    }
}
