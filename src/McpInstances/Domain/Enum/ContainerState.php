<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Enum;

enum ContainerState: string
{
    case CREATED = 'created';
    case RUNNING = 'running';
    case STOPPED = 'stopped';
    case ERROR   = 'error';
}
