<?php

declare(strict_types=1);

namespace App\DockerManagement\Facade\Dto;

readonly class ContainerStatusDto
{
    public function __construct(
        public string  $containerName,
        public string  $state,
        public bool    $healthy,
        public ?string $mcpEndpoint,
        public ?string $vncEndpoint,
        public bool    $mcpUp = false,
        public bool    $noVncUp = false,
    ) {
    }
}
