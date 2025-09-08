<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Config\Dto;

readonly class EndpointConfig
{
    /**
     * @param string[] $externalPaths
     */
    public function __construct(
        public int                   $port,
        public ?string               $auth,
        public array                 $externalPaths,
        public ?EndpointHealthConfig $health,
    ) {
    }
}
