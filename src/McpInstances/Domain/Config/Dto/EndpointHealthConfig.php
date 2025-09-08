<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Config\Dto;

readonly class EndpointHealthConfig
{
    public function __construct(
        public ?EndpointHealthHttpConfig $http = null
    ) {
    }
}
