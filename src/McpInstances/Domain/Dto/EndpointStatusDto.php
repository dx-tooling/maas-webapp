<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Dto;

readonly class EndpointStatusDto
{
    /**
     * @param array<int,string> $externalUrls
     */
    public function __construct(
        public string $id,
        public bool   $up,
        public array  $externalUrls,
        public bool   $requiresAuthBearer,
        public bool   $hasHealthCheck,
    ) {
    }
}
