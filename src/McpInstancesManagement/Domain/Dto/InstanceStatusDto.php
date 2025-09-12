<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Domain\Dto;

final readonly class InstanceStatusDto
{
    /**
     * @param array<int,EndpointStatusDto> $endpoints
     */
    public function __construct(
        public string $instanceId,
        public string $containerName,
        public string $containerState,
        public bool   $containerRunning,
        public array  $endpoints,
    ) {
    }
}
