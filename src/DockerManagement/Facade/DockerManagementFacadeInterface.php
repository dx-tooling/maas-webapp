<?php

declare(strict_types=1);

namespace App\DockerManagement\Facade;

use App\DockerManagement\Facade\Dto\ContainerStatusDto;
use App\McpInstancesManagement\Facade\Dto\InstanceStatusDto;
use App\McpInstancesManagement\Facade\Dto\McpInstanceDto;

interface DockerManagementFacadeInterface
{
    /**
     * Create and start a Docker container for the given MCP instance.
     */
    public function createAndStartContainer(McpInstanceDto $instance): bool;

    /**
     * Stop and remove the Docker container for the given MCP instance.
     */
    public function stopAndRemoveContainer(McpInstanceDto $instance): bool;

    /**
     * Restart the Docker container for the given MCP instance.
     */
    public function restartContainer(McpInstanceDto $instance): bool;

    /**
     * Check if the container is healthy (endpoints responding).
     */
    public function isContainerHealthy(McpInstanceDto $instance): bool;

    /**
     * Get comprehensive status information for the container.
     */
    public function getContainerStatus(McpInstanceDto $instance): ContainerStatusDto;

    /**
     * Get generic instance status including dynamic endpoints derived from configuration.
     */
    public function getInstanceStatus(McpInstanceDto $instance): InstanceStatusDto;
}
