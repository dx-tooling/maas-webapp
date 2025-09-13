<?php

declare(strict_types=1);

namespace App\McpInstancesConfiguration\Facade\Dto;

final readonly class EndpointHealthConfig
{
    public function __construct(
        public ?EndpointHealthHttpConfig $http = null
    ) {
    }
}
