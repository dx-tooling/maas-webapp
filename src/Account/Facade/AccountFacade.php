<?php

declare(strict_types=1);

namespace App\Account\Facade;

use App\Account\Domain\Entity\AccountCore;
use App\Account\Facade\Dto\AccountPublicInfoDto;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AccountFacade implements AccountFacadeInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function getAccountById(string $id): ?AccountPublicInfoDto
    {
        $repo    = $this->entityManager->getRepository(AccountCore::class);
        $account = $repo->find($id);
        if (!$account instanceof AccountCore) {
            return null;
        }

        return new AccountPublicInfoDto(
            $account->getId() ?? '',
            $account->getEmail(),
            $account->getRoles(),
            $account->getCreatedAt(),
        );
    }
}
