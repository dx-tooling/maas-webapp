<?php

declare(strict_types=1);

namespace App\McpInstancesConfiguration\Facade\Dto;

readonly class EndpointHealthHttpConfig
{
    public function __construct(
        public string $path,
        public int    $acceptStatusLt = 500,
    ) {
    }
}
