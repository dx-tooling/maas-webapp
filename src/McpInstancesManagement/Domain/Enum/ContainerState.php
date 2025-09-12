<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Domain\Enum;

// Moved to Facade namespace. Keep for BC and deprecation window.
/** @deprecated Use App\McpInstancesManagement\Facade\ContainerState */
enum ContainerState: string
{
    case CREATED = 'created';
    case RUNNING = 'running';
    case STOPPED = 'stopped';
    case ERROR   = 'error';
}
