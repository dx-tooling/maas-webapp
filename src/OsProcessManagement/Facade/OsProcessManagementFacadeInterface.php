<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Facade;

interface OsProcessManagementFacadeInterface
{
    public function launchPlaywrightSetup(
        int    $displayNumber,
        int    $screenWidth,
        int    $screenHeight,
        int    $colorDepth,
        int    $mcpPort,
        int    $vncPort,
        int    $websocketPort,
        string $vncPassword
    ): void;

    public function stopPlaywrightSetup(
        int $displayNumber,
        int $mcpPort,
        int $vncPort,
        int $websocketPort
    ): void;
}
