<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Config\Service;

use App\McpInstances\Domain\Config\Dto\InstanceTypeConfig;
use App\McpInstances\Domain\Enum\InstanceType;

interface InstanceTypesConfigServiceInterface
{
    public function getTypeConfig(InstanceType $type): ?InstanceTypeConfig;

    /**
     * Build Traefik labels based on endpoints; forwardauth if auth == 'bearer'.
     *
     * @return string[]
     */
    public function buildTraefikLabels(
        InstanceType $type,
        string       $instanceSlug,
        string       $rootDomain,
        string       $forwardAuthUrl
    ): array;
}
