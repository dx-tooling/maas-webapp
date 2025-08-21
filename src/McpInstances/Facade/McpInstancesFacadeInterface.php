<?php

declare(strict_types=1);

namespace App\McpInstances\Facade;

use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\McpInstances\Facade\Dto\McpInstanceInfoDto;

interface McpInstancesFacadeInterface
{
    /** @return array<McpInstanceInfoDto> */
    public function getMcpInstanceInfos(): array;

    /** @return array<McpInstanceInfoDto> */
    public function getMcpInstanceInfosForAccount(
        AccountCoreInfoDto $accountCoreInfoDto
    ): array;

    public function createMcpInstance(
        AccountCoreInfoDto $accountCoreInfoDto,
    ): McpInstanceInfoDto;

    public function stopAndRemoveMcpInstance(
        AccountCoreInfoDto $accountCoreInfoDto,
    ): void;

    /**
     * Get process status for a specific MCP instance.
     *
     * @return array{
     *   instanceId: string,
     *   processes: array{
     *     xvfb: array<string, mixed>|null,
     *     mcp: array<string, mixed>|null,
     *     vnc: array<string, mixed>|null,
     *     websocket: array<string, mixed>|null
     *   },
     *   allRunning: bool,
     *   containerStatus: array{
     *     containerName: string,
     *     state: string,
     *     healthy: bool,
     *     mcpUp: bool,
     *     noVncUp: bool,
     *     mcpEndpoint: string|null,
     *     vncEndpoint: string|null
     *   }
     * }
     */
    public function getProcessStatusForInstance(string $instanceId): array;

    /**
     * Restart all processes for a specific MCP instance.
     */
    public function restartProcessesForInstance(string $instanceId): bool;
}
