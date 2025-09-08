<?php

declare(strict_types=1);

namespace App\McpInstances\Infrastructure\Config;

use App\McpInstances\Domain\Config\Dto\McpInstanceTypesConfig;

interface InstanceTypesConfigProviderInterface
{
    public function getConfig(): McpInstanceTypesConfig;
}
