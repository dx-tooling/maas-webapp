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

    public function restartVirtualFramebuffer(int $displayNumber): bool;

    public function restartPlaywrightMcp(int $port): bool;

    public function restartVncServer(int $port): bool;

    public function restartVncWebsocket(int $httpPort): bool;

    public function restartAllProcessesForInstance(string $instanceId): bool;

    /**
     * Get all running processes.
     *
     * @return array{
     *   virtualFramebuffers: array<array{proc: array<string, mixed>, instanceId: string|null}>,
     *   playwrightMcps: array<array{proc: array<string, mixed>, instanceId: string|null}>,
     *   vncServers: array<array{proc: array<string, mixed>, instanceId: string|null}>,
     *   vncWebsockets: array<array{proc: array<string, mixed>, instanceId: string|null}>
     * }
     */
    public function getAllProcesses(): array;
}
