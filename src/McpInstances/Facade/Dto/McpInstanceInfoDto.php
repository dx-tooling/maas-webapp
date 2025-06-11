<?php

declare(strict_types=1);

namespace App\McpInstances\Facade\Dto;

readonly class McpInstanceInfoDto
{
    public function __construct(
        public string $id,
        public int    $displayNumber,
        public int    $mcpPort,
        public int    $vncPort,
        public int    $websocketPort,
        public string $websocketPassword,
    ) {
    }
}
