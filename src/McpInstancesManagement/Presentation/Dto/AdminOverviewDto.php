<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Presentation\Dto;

readonly class AdminOverviewDto
{
    public function __construct(
        public McpInstanceInfoDto $instance,
        public AdminAccountDto    $account,
        public bool               $isHealthy,
        public ?string            $mcpEndpoint,
        public ?string            $vncEndpoint,
    ) {
    }
}
