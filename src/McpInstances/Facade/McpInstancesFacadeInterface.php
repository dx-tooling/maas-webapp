<?php

declare(strict_types=1);

namespace App\McpInstances\Facade;

use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\McpInstances\Facade\Dto\McpInstanceInfoDto;

interface McpInstancesFacadeInterface
{
    /** @return array<McpInstanceInfoDto> */
    public function getMcpInstanceInfos(): array;

    /** @return array<McpInstanceInfoDto> */
    public function getMcpInstanceInfosForAccount(
        AccountCoreInfoDto $accountCoreInfoDto
    ): array;

    public function createMcpInstance(
        AccountCoreInfoDto $accountCoreInfoDto,
    ): McpInstanceInfoDto;

    public function stopAndRemoveMcpInstance(
        AccountCoreInfoDto $accountCoreInfoDto,
    ): void;
}
