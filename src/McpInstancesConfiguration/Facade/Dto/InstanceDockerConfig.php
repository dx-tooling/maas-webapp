<?php

declare(strict_types=1);

namespace App\McpInstancesConfiguration\Facade\Dto;

final readonly class InstanceDockerConfig
{
    /**
     * @param array<string,string> $env
     */
    public function __construct(
        public string $image,
        public array  $env = [],
    ) {
    }
}
