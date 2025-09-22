<?php

declare(strict_types=1);

namespace App\McpInstancesConfiguration\Facade\Dto;

final readonly class InstanceTypeConfig
{
    /**
     * @param array<string,EndpointConfig> $endpoints
     * @param array<string,string>         $requiredUserEnvVars
     */
    public function __construct(
        public string               $displayName,
        public string               $description,
        public InstanceDockerConfig $docker,
        public array                $endpoints,
        public array                $requiredUserEnvVars = [],
    ) {
    }
}
