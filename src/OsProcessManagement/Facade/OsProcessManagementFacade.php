<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Facade;

use App\OsProcessManagement\Domain\Service\OsProcessManagementDomainService;

readonly class OsProcessManagementFacade implements OsProcessManagementFacadeInterface
{
    public function __construct(
        private OsProcessManagementDomainService $processMgmtService
    ) {
    }

    public function launchPlaywrightSetup(
        int    $displayNumber,
        int    $screenWidth,
        int    $screenHeight,
        int    $colorDepth,
        int    $mcpPort,
        int    $vncPort,
        int    $websocketPort,
        string $vncPassword
    ): void {
        $this->processMgmtService->launchVirtualFramebuffer(
            $displayNumber,
            $screenWidth,
            $screenHeight,
            $colorDepth
        );

        $this->processMgmtService->launchPlaywrightMcp(
            $mcpPort,
            $displayNumber
        );

        $this->processMgmtService->launchVncServer(
            $vncPort,
            $displayNumber,
            $vncPassword
        );

        $this->processMgmtService->launchVncWebsocket(
            $websocketPort,
            $vncPort
        );
    }

    public function stopPlaywrightSetup(
        int $displayNumber,
        int $mcpPort,
        int $vncPort,
        int $websocketPort
    ): void {
        $this->processMgmtService->stopVncWebsocket($websocketPort);
        $this->processMgmtService->stopVncServer($vncPort, $displayNumber);
        $this->processMgmtService->stopPlaywrightMcp($mcpPort);
        $this->processMgmtService->stopVirtualFramebuffer($displayNumber);
    }
}
