<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Config\Service;

use App\McpInstances\Domain\Config\Dto\McpInstanceTypesConfig;

interface InstanceTypesConfigProviderInterface
{
    public function getConfig(): McpInstanceTypesConfig;
}
