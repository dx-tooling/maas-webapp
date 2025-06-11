<?php

declare(strict_types=1);

namespace App\McpInstances\Facade;

use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\McpInstances\Facade\Dto\McpInstanceInfoDto;

interface McpInstancesFacadeInterface
{
    /** @return McpInstanceInfoDto[] */
    public function getMcpInstanceInfos(): array;

    public function getMcpInstanceInfosForAccount(
        AccountCoreInfoDto $accountCoreInfoDto
    ): array;

    public function createMcpInstance(
        AccountCoreInfoDto $accountCoreInfoDto,
    ): void;
}
