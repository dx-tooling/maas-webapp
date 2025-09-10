<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Config\Dto;

readonly class InstanceTypeConfig
{
    /**
     * @param array<string,EndpointConfig> $endpoints
     */
    public function __construct(
        public string               $displayName,
        public string               $description,
        public InstanceDockerConfig $docker,
        public array                $endpoints,
    ) {
    }
}
