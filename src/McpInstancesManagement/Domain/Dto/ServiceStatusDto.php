<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Domain\Dto;

readonly class ServiceStatusDto
{
    public function __construct(
        public ?string $xvfb,
        public ?string $mcp,
        public ?string $vnc,
        public ?string $websocket,
    ) {
    }
}
