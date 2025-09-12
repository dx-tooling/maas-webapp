<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Facade;

enum InstanceType: string
{
    case _LEGACY           = '_legacy';
    case PLAYWRIGHT_V1     = 'playwright-v1';
    case LINUX_CMD_LINE_V1 = 'linux-cmd-line-v1';
}
