<?php

declare(strict_types=1);

namespace App\DockerManagement\Facade;

use App\DockerManagement\Facade\Dto\ContainerStatusDto;
use App\DockerManagement\Infrastructure\Service\ContainerManagementService;
use App\McpInstances\Domain\Entity\McpInstance;
use App\McpInstances\Domain\Enum\ContainerState;

readonly class DockerManagementFacade implements DockerManagementFacadeInterface
{
    public function __construct(
        private ContainerManagementService $dockerDomainService
    ) {
    }

    public function createAndStartContainer(McpInstance $instance): bool
    {
        // Create the container
        if (!$this->dockerDomainService->createContainer($instance)) {
            return false;
        }

        // Start the container
        if (!$this->dockerDomainService->startContainer($instance)) {
            // If start fails, clean up the created container
            $this->dockerDomainService->removeContainer($instance);

            return false;
        }

        return true;
    }

    public function stopAndRemoveContainer(McpInstance $instance): bool
    {
        $stopped = $this->dockerDomainService->stopContainer($instance);
        $removed = $this->dockerDomainService->removeContainer($instance);

        // Return true if either operation succeeded (container might not exist)
        return $stopped || $removed;
    }

    public function restartContainer(McpInstance $instance): bool
    {
        return $this->dockerDomainService->restartContainer($instance);
    }

    public function getContainerState(McpInstance $instance): ContainerState
    {
        return $this->dockerDomainService->getContainerState($instance);
    }

    public function isContainerHealthy(McpInstance $instance): bool
    {
        return $this->dockerDomainService->isContainerHealthy($instance);
    }

    public function getContainerStatus(McpInstance $instance): ContainerStatusDto
    {
        $state   = $this->getContainerState($instance);
        $running = $state === ContainerState::RUNNING;
        $mcpUp   = $running && $this->dockerDomainService->isMcpEndpointUp($instance);
        $noVncUp = $running && $this->dockerDomainService->isNoVncEndpointUp($instance);
        $healthy = $running && $mcpUp && $noVncUp;

        return new ContainerStatusDto(
            $instance->getContainerName() ?? '',
            $state->value,
            $healthy,
            $instance->getMcpSubdomain() ? 'https://' . $instance->getMcpSubdomain() . '/mcp' : null,
            $instance->getVncSubdomain() ? 'https://' . $instance->getVncSubdomain() : null,
            $mcpUp,
            $noVncUp
        );
    }
}
