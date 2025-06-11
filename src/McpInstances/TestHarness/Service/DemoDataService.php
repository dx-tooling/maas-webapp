<?php

declare(strict_types=1);

namespace App\McpInstances\TestHarness\Service;

use App\McpInstances\Facade\Dto\McpInstanceInfoDto;

readonly class DemoDataService
{
    /** @return McpInstanceInfoDto[] */
    public function getFakeMcpInstanceInfoDtos(): array
    {
        return [
            new McpInstanceInfoDto('instance-1', 99, 11111, 22222, 33333, 'secret1'),
            new McpInstanceInfoDto('instance-2', 100, 11112, 22223, 33334, 'secret2'),
            new McpInstanceInfoDto('instance-3', 101, 11113, 22224, 33335, 'secret3'),
        ];
    }
}
