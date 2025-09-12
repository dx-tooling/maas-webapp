<?php

declare(strict_types=1);

namespace App\Tests\Unit\Account\Domain\Service;

use App\Account\Domain\Entity\AccountCore;
use App\Account\Domain\Service\AccountDomainService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountDomainServiceTest extends TestCase
{
    public function testRegisterThrowsWhenEmailExists(): void
    {
        $em     = $this->createMock(EntityManagerInterface::class);
        $repo   = $this->createMock(EntityRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        $em->method('getRepository')->willReturn($repo);
        $repo->method('findOneBy')->with(['email' => 'test@example.com'])->willReturn($this->createMock(AccountCore::class));

        $unitUnderTest = new AccountDomainService($em, $hasher);

        $this->expectException(LogicException::class);
        $unitUnderTest->register('test@example.com', 'secret');
    }

    public function testRegisterPersistsNewAccount(): void
    {
        $em     = $this->createMock(EntityManagerInterface::class);
        $repo   = $this->createMock(EntityRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        $em->method('getRepository')->willReturn($repo);
        $repo->method('findOneBy')->with(['email' => 'fresh@example.com'])->willReturn(null);

        $hasher->method('hashPassword')->willReturn('hashed');

        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(AccountCore::class));
        $em->expects($this->once())->method('flush');

        $unitUnderTest = new AccountDomainService($em, $hasher);
        $account       = $unitUnderTest->register('fresh@example.com', 'secret');

        $this->assertSame('fresh@example.com', $account->getEmail());
        $this->assertSame('hashed', $account->getPassword());
    }
}
