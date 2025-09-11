<?php

declare(strict_types=1);

namespace App\McpInstancesConfiguration\Domain\Service;

use App\McpInstancesConfiguration\Domain\Dto\InstanceTypeConfig;
use App\McpInstancesConfiguration\Infrastructure\InstanceTypesConfigProviderInterface;
use App\McpInstancesManagement\Domain\Enum\InstanceType;

final readonly class InstanceTypesConfigService implements InstanceTypesConfigServiceInterface
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
