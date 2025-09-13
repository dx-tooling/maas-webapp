<?php

declare(strict_types=1);

namespace App\McpInstancesConfiguration\Facade\Service;

use App\McpInstancesConfiguration\Facade\Dto\InstanceTypeConfig;
use App\McpInstancesConfiguration\Infrastructure\InstanceTypesConfigProviderInterface;
use App\McpInstancesManagement\Domain\Enum\InstanceType;

final readonly class InstanceTypesConfigFacade implements InstanceTypesConfigFacadeInterface
{
    public function __construct(
        private InstanceTypesConfigProviderInterface $provider
    ) {
    }

    public function getTypeConfig(InstanceType $type): ?InstanceTypeConfig
    {
        $config = $this->provider->getConfig();

        return $config->types[$type->value] ?? null;
    }
}
