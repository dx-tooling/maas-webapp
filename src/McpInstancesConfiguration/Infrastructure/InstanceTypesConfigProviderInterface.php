<?php

declare(strict_types=1);

namespace App\McpInstancesConfiguration\Infrastructure;

use App\McpInstancesConfiguration\Domain\Dto\McpInstanceTypesConfig;

interface InstanceTypesConfigProviderInterface
{
    public function getConfig(): McpInstanceTypesConfig;
}
