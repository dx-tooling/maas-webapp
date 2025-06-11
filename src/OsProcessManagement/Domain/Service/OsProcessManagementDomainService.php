<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Domain\Service;

class OsProcessManagementDomainService
{
    public function launchVirtualFramebuffer(
        int $displayNumber,
        int $screenWidth,
        int $screenHeight,
        int $colorDepth
    ): bool {
        return false;
    }

    public function launchPlaywrightMcp(
        int $port,
        int $displayNumber
    ): bool {
        return false;
    }

    public function launchVncServer(
        int    $port,
        int    $displayNumber,
        string $password
    ): bool {
        return false;
    }

    public function launchVncWebsocket(
        int $httpPort,
        int $vncPort
    ): bool {
        return false;
    }
}
