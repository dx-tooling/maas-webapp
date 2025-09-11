<?php

declare(strict_types=1);

namespace App\McpInstancesConfiguration\Domain\Service;

use App\McpInstancesConfiguration\Domain\Dto\InstanceTypeConfig;
use App\McpInstancesManagement\Domain\Enum\InstanceType;

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
