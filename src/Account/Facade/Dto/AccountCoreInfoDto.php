<?php

declare(strict_types=1);

namespace App\Account\Facade\Dto;

readonly class AccountCoreInfoDto
{
    public function __construct(
        public string $id,
    ) {}
}
