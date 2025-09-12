<?php

declare(strict_types=1);

namespace App\McpInstancesConfiguration\Facade\Service;

use App\McpInstancesConfiguration\Facade\Dto\InstanceTypeConfig;
use App\McpInstancesManagement\Facade\InstanceType;

interface InstanceTypesConfigFacadeInterface
{
    public function getTypeConfig(InstanceType $type): ?InstanceTypeConfig;
}
