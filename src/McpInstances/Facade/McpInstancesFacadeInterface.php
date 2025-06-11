<?php

declare(strict_types=1);

namespace App\McpInstances\Facade;

use App\McpInstances\Facade\Dto\McpInstanceInfoDto;

interface McpInstancesFacadeInterface
{
    /** @return McpInstanceInfoDto[] */
    public function getMcpInstanceInfos(): array;
}
