<?php

declare(strict_types=1);

namespace App\DockerManagement\Facade;

use App\DockerManagement\Domain\Service\DockerDomainService;
use App\McpInstances\Domain\Entity\McpInstance;
use App\McpInstances\Domain\Enum\ContainerState;

readonly class DockerManagementFacade implements DockerManagementFacadeInterface
{
    public function __construct(
        private DockerDomainService $dockerDomainService
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

    public function getContainerStatus(McpInstance $instance): array
    {
        $state   = $this->getContainerState($instance);
        $healthy = $state === ContainerState::RUNNING ? $this->isContainerHealthy($instance) : false;

        return [
            'containerName' => $instance->getContainerName(),
            'state'         => $state->value,
            'healthy'       => $healthy,
            'mcpEndpoint'   => $instance->getMcpSubdomain() ? 'https://' . $instance->getMcpSubdomain() . '/mcp' : null,
            'vncEndpoint'   => $instance->getVncSubdomain() ? 'https://' . $instance->getVncSubdomain() : null
        ];
    }
}
