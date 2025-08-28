<?php

declare(strict_types=1);

namespace App\DockerManagement\Facade;

use App\DockerManagement\Facade\Dto\ContainerStatusDto;
use App\McpInstances\Domain\Entity\McpInstance;

interface DockerManagementFacadeInterface
{
    /**
     * Create and start a Docker container for the given MCP instance.
     */
    public function createAndStartContainer(McpInstance $instance): bool;

    /**
     * Stop and remove the Docker container for the given MCP instance.
     */
    public function stopAndRemoveContainer(McpInstance $instance): bool;

    /**
     * Restart the Docker container for the given MCP instance.
     */
    public function restartContainer(McpInstance $instance): bool;

    /**
     * Check if the container is healthy (endpoints responding).
     */
    public function isContainerHealthy(McpInstance $instance): bool;

    /**
     * Get comprehensive status information for the container.
     */
    public function getContainerStatus(McpInstance $instance): ContainerStatusDto;
}
