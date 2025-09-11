<?php

declare(strict_types=1);

namespace App\McpInstancesConfiguration\Domain\Dto;

readonly class EndpointHealthConfig
{
    public function __construct(
        public ?EndpointHealthHttpConfig $http = null
    ) {
    }
}
