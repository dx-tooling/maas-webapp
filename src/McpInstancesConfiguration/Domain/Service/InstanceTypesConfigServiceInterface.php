<?php

declare(strict_types=1);

namespace App\McpInstancesConfiguration\Domain\Service;

use App\McpInstancesConfiguration\Domain\Dto\InstanceTypeConfig;
use App\McpInstancesManagement\Domain\Enum\InstanceType;

interface InstanceTypesConfigServiceInterface
{
    public function getTypeConfig(InstanceType $type): ?InstanceTypeConfig;
}
