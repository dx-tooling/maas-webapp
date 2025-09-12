<?php

declare(strict_types=1);

namespace App\Account\Facade;

use App\Account\Facade\Dto\AccountPublicInfoDto;

interface AccountFacadeInterface
{
    public function getAccountById(string $id): ?AccountPublicInfoDto;
}
