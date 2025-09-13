<?php

declare(strict_types=1);

namespace App\McpInstancesConfiguration\Infrastructure;

use App\McpInstancesConfiguration\Facade\Dto\McpInstanceTypesConfig;

interface InstanceTypesConfigProviderInterface
{
    public function getConfig(): McpInstanceTypesConfig;
}
