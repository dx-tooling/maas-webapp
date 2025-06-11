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
            new McpInstanceInfoDto('instance1', 101, 11111, 9101, 22222, 33333, 'password_for_instance1'),
            new McpInstanceInfoDto('instance2', 102, 11112, 9102, 22223, 33334, 'foobar'),
            new McpInstanceInfoDto('instance3', 103, 11113, 9103, 22224, 33335, 'password_for_instance3'),
        ];
    }
}
