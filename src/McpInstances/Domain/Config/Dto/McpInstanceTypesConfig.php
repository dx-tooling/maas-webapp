<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Config\Dto;

readonly class McpInstanceTypesConfig
{
    /**
     * @param array<string,InstanceTypeConfig> $types
     */
    public function __construct(
        public array $types
    ) {
    }
}
