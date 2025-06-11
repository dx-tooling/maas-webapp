<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Facade;

use App\McpInstances\Facade\Dto\McpInstanceInfoDto;

interface OsProcessManagementFacadeInterface
{
    /**
     * @param array<McpInstanceInfoDto> $mcpInstanceInfos
     */
    public function launchPlaywrightSetup(
        array  $mcpInstanceInfos,
        int    $displayNumber,
        int    $screenWidth,
        int    $screenHeight,
        int    $colorDepth,
        int    $mcpPort,
        int    $vncPort,
        int    $websocketPort,
        string $vncPassword
    ): void;

    /**
     * @param array<McpInstanceInfoDto> $mcpInstanceInfos
     */
    public function stopPlaywrightSetup(
        array $mcpInstanceInfos,
        int   $displayNumber,
        int   $mcpPort,
        int   $vncPort,
        int   $websocketPort
    ): void;
}
