<?php

declare(strict_types=1);

namespace App\McpInstances\Facade;

use App\OsProcessManagement\Domain\Dto\PlaywrightMcpProcessInfoDto;

interface McpInstancesFacadeInterface
{
    /** @return PlaywrightMcpProcessInfoDto[] */
    public function getMcpInstanceInfos(): array;
}
