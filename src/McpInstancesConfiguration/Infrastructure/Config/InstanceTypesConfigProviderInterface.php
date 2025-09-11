<?php

declare(strict_types=1);

namespace App\McpInstancesConfiguration\Infrastructure\Config;

use App\McpInstancesConfiguration\Domain\Config\Dto\McpInstanceTypesConfig;

interface InstanceTypesConfigProviderInterface
{
    public function getConfig(): McpInstanceTypesConfig;
}
