<?php

declare(strict_types=1);

namespace App\Common\Presentation\Controller;

use App\Account\Domain\Entity\AccountCore;
use App\Account\Facade\Dto\AccountCoreInfoDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class AbstractAccountAwareController extends AbstractController
{
    final protected function getAuthenticatedAccountCoreInfo(): AccountCoreInfoDto
    {
        $user = $this->getUser();
        if (!$user instanceof AccountCore) {
            throw $this->createAccessDeniedException();
        }

        $accountId = $user->getId();
        if ($accountId === null || $accountId === '') {
            throw $this->createAccessDeniedException();
        }

        return new AccountCoreInfoDto($accountId);
    }
}
