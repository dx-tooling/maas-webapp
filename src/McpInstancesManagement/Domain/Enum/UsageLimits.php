<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Domain\Enum;

enum UsageLimits: int
{
    case MAX_RUNNING_INSTANCES = 5;
}
