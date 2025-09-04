<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Enum;

enum InstanceType: string
{
    case _LEGACY           = '_legacy';
    case PLAYWRIGHT_V1     = 'playwright_v1';
    case LINUX_CMD_LINE_V1 = 'linux_cmd_line_v1';
}
