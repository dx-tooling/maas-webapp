<?php

declare(strict_types=1);

namespace App\DockerManagement\Facade;

use App\DockerManagement\Domain\Service\ContainerManagementDomainService;
use App\DockerManagement\Facade\Dto\ContainerStatusDto;
use App\McpInstancesConfiguration\Domain\Service\InstanceTypesConfigServiceInterface;
use App\McpInstancesManagement\Domain\Dto\EndpointStatusDto;
use App\McpInstancesManagement\Domain\Dto\InstanceStatusDto;
use App\McpInstancesManagement\Domain\Entity\McpInstance;
use App\McpInstancesManagement\Domain\Enum\ContainerState;

readonly class DockerManagementFacade implements DockerManagementFacadeInterface
{
    public function __construct(
        private ContainerManagementDomainService    $domainService,
        private InstanceTypesConfigServiceInterface $configService
    ) {
    }

    public function createAndStartContainer(McpInstance $instance): bool
    {
        // Create the container
        if (!$this->domainService->createContainer($instance)) {
            return false;
        }

        // Start the container
        if (!$this->domainService->startContainer($instance)) {
            // If start fails, clean up the created container
            $this->domainService->removeContainer($instance);

            return false;
        }

        return true;
    }

    public function stopAndRemoveContainer(McpInstance $instance): bool
    {
        $stopped = $this->domainService->stopContainer($instance);
        $removed = $this->domainService->removeContainer($instance);

        // Return true if either operation succeeded (container might not exist)
        return $stopped || $removed;
    }

    public function restartContainer(McpInstance $instance): bool
    {
        return $this->domainService->restartContainer($instance);
    }

    public function isContainerHealthy(McpInstance $instance): bool
    {
        return $this->domainService->isContainerHealthy($instance);
    }

    public function getContainerStatus(McpInstance $instance): ContainerStatusDto
    {
        $state   = $this->domainService->getContainerState($instance);
        $running = $state === ContainerState::RUNNING;
        $mcpUp   = $running && $this->domainService->isMcpEndpointUp($instance);
        $noVncUp = $running && $this->domainService->isNoVncEndpointUp($instance);
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

    public function getInstanceStatus(McpInstance $instance): InstanceStatusDto
    {
        $containerState = $this->domainService->getContainerState($instance);
        $running        = $containerState === ContainerState::RUNNING;

        $rootDomain = getenv('APP_ROOT_DOMAIN') ?: 'mcp-as-a-service.com';
        $typeCfg    = $this->configService->getTypeConfig($instance->getInstanceType());
        $endpoints  = [];

        if ($typeCfg !== null) {
            foreach ($typeCfg->endpoints as $endpointId => $epCfg) {
                $isUp = false;
                if ($running && $epCfg->health !== null && $epCfg->health->http !== null) {
                    // Probe via docker exec curl http://localhost:{port}{path}
                    $path   = $epCfg->health->http->path;
                    $status = $this->domainService->execCurlStatus($instance, 'http://localhost:' . $epCfg->port . $path);
                    $isUp   = $status > 0 && $status < $epCfg->health->http->acceptStatusLt;
                }

                // Build external URLs from external_paths and host pattern
                $host         = $endpointId . '-' . ($instance->getInstanceSlug() ?? '') . '.' . $rootDomain;
                $externalUrls = [];
                foreach ($epCfg->externalPaths as $p) {
                    $externalUrls[] = 'https://' . $host . $p;
                }

                $requiresAuth = ($epCfg->auth === 'bearer') || ($endpointId === 'mcp');
                $hasHealth    = $epCfg->health !== null && $epCfg->health->http !== null;

                $endpoints[] = new EndpointStatusDto($endpointId, $isUp, $externalUrls, $requiresAuth, $hasHealth);
            }
        }

        return new InstanceStatusDto(
            $instance->getId()            ?? '',
            $instance->getContainerName() ?? '',
            $containerState->value,
            $running,
            $endpoints
        );
    }
}
