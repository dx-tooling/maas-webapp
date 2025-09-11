<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Domain\Dto;

readonly class ProcessStatusContainerDto
{
    public function __construct(
        public string  $containerName,
        public string  $state,
        public bool    $healthy,
        public bool    $mcpUp,
        public bool    $noVncUp,
        public ?string $mcpEndpoint,
        public ?string $vncEndpoint,
    ) {
    }
}
