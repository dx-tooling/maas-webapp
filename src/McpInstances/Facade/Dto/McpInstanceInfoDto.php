<?php

declare(strict_types=1);

namespace App\McpInstances\Facade\Dto;

readonly class McpInstanceInfoDto
{
    public function __construct(
        public ?string $id,
        public ?string $instanceSlug,
        public ?string $containerName,
        public string  $containerState,
        public int     $screenWidth,
        public int     $screenHeight,
        public int     $colorDepth,
        public string  $vncPassword,
        public string  $mcpBearer,
        public ?string $mcpSubdomain,
        public ?string $vncSubdomain,
    ) {
    }
}
