<?php

declare(strict_types=1);

namespace App\McpInstances\Facade;

use App\McpInstances\TestHarness\Service\DemoDataService;

readonly class McpInstancesFacade implements McpInstancesFacadeInterface
{
    public function __construct(
        private DemoDataService $demoDataService
    ) {
    }

    public function getMcpInstanceInfos(): array
    {
        return $this->demoDataService->getFakeMcpInstanceInfoDtos();
    }
}
