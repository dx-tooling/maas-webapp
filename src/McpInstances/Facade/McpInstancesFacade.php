<?php

declare(strict_types=1);

namespace App\McpInstances\Facade;

use App\Account\Domain\Entity\AccountCore;
use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\DockerManagement\Facade\DockerManagementFacadeInterface;
use App\McpInstances\Domain\Entity\McpInstance;
use App\McpInstances\Domain\Service\McpInstancesDomainService;
use App\McpInstances\Facade\Dto\McpInstanceAdminOverviewDto;
use App\McpInstances\Facade\Dto\McpInstanceInfoDto;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use LogicException;

readonly class McpInstancesFacade implements McpInstancesFacadeInterface
{
    public function __construct(
        private McpInstancesDomainService       $domainService,
        private EntityManagerInterface          $entityManager,
        private DockerManagementFacadeInterface $dockerFacade
    ) {
    }

    /**
     * @param array<McpInstance> $mcpInstances
     *
     * @return array<McpInstanceInfoDto>
     */
    public static function mcpInstancesToMcpInstanceInfoDtos(array $mcpInstances): array
    {
        return array_map(
            fn (McpInstance $i) => new McpInstanceInfoDto(
                $i->getId(),
                $i->getInstanceSlug(),
                $i->getContainerName(),
                $i->getContainerState()->value,
                $i->getScreenWidth(),
                $i->getScreenHeight(),
                $i->getColorDepth(),
                $i->getVncPassword(),
                $i->getMcpBearer(),
                $i->getMcpSubdomain(),
                $i->getVncSubdomain()
            ),
            $mcpInstances
        );
    }

    /** @return array<McpInstanceInfoDto> */
    public function getMcpInstanceInfos(): array
    {
        return self::mcpInstancesToMcpInstanceInfoDtos($this->domainService->getAllMcpInstances());
    }

    /** @return array<McpInstanceInfoDto> */
    public function getMcpInstanceInfosForAccount(AccountCoreInfoDto $accountCoreInfoDto): array
    {
        $repo      = $this->entityManager->getRepository(McpInstance::class);
        $instances = $repo->findBy(['accountCoreId' => $accountCoreInfoDto->id]);

        return self::mcpInstancesToMcpInstanceInfoDtos($instances);
    }

    public function createMcpInstance(AccountCoreInfoDto $accountCoreInfoDto): McpInstanceInfoDto
    {
        $instance = $this->domainService->createMcpInstance($accountCoreInfoDto->id);

        return new McpInstanceInfoDto(
            $instance->getId(),
            $instance->getInstanceSlug(),
            $instance->getContainerName(),
            $instance->getContainerState()->value,
            $instance->getScreenWidth(),
            $instance->getScreenHeight(),
            $instance->getColorDepth(),
            $instance->getVncPassword(),
            $instance->getMcpBearer(),
            $instance->getMcpSubdomain(),
            $instance->getVncSubdomain()
        );
    }

    public function stopAndRemoveMcpInstance(AccountCoreInfoDto $accountCoreInfoDto): void
    {
        $this->domainService->stopAndRemoveMcpInstance($accountCoreInfoDto->id);
    }

    public function getProcessStatusForInstance(string $instanceId): array
    {
        $repo     = $this->entityManager->getRepository(McpInstance::class);
        $instance = $repo->find($instanceId);

        if (!$instance) {
            throw new LogicException('MCP instance not found.');
        }

        // Get Docker container status with partial endpoint checks
        $containerStatus = $this->dockerFacade->getContainerStatus($instance);

        $running     = $containerStatus->state === 'running';
        $xvfbUp      = $running; // container running implies Xvfb supervisor started
        $mcpUp       = $containerStatus->mcpUp;
        $noVncUp     = $containerStatus->noVncUp;
        $websocketUp = $noVncUp; // web client served by noVNC/websockify

        $allRunning = $xvfbUp && $mcpUp && $noVncUp && $websocketUp;

        return [
            'instanceId' => $instance->getId() ?? '',
            'processes'  => [
                'xvfb'      => $xvfbUp ? ['status' => 'running'] : null,
                'mcp'       => $mcpUp ? ['status' => 'running'] : null,
                'vnc'       => $noVncUp ? ['status' => 'running'] : null,
                'websocket' => $websocketUp ? ['status' => 'running'] : null,
            ],
            'allRunning'      => $allRunning,
            'containerStatus' => [
                'containerName' => $containerStatus->containerName,
                'state'         => $containerStatus->state,
                'healthy'       => $containerStatus->healthy,
                'mcpUp'         => $mcpUp,
                'noVncUp'       => $noVncUp,
                'mcpEndpoint'   => $containerStatus->mcpEndpoint,
                'vncEndpoint'   => $containerStatus->vncEndpoint,
            ]
        ];
    }

    public function restartProcessesForInstance(string $instanceId): bool
    {
        return $this->domainService->restartMcpInstance($instanceId);
    }

    /**
     * Get comprehensive admin overview of all MCP instances with account information.
     * This method requires ROLE_ADMIN access.
     *
     * @return array<McpInstanceAdminOverviewDto>
     */
    public function getMcpInstanceAdminOverview(): array
    {
        // Get all MCP instances first
        $instances = $this->domainService->getAllMcpInstances();

        if (empty($instances)) {
            return [];
        }

        $overviewDtos = [];
        foreach ($instances as $instance) {
            // Get the account for this instance
            $accountRepo = $this->entityManager->getRepository(AccountCore::class);
            $account     = $accountRepo->find($instance->getAccountCoreId());

            if (!$account) {
                continue; // Skip if account not found
            }

            // Get container status for health check
            try {
                $containerStatus = $this->dockerFacade->getContainerStatus($instance);
                $isHealthy       = $containerStatus->healthy;
                $mcpEndpoint     = $containerStatus->mcpEndpoint;
                $vncEndpoint     = $containerStatus->vncEndpoint;
            } catch (Exception $e) {
                // If Docker facade fails, use default values
                $isHealthy   = false;
                $mcpEndpoint = null;
                $vncEndpoint = null;
            }

            $overviewDtos[] = new McpInstanceAdminOverviewDto(
                $instance->getId()            ?? '',
                $instance->getInstanceSlug()  ?? '',
                $instance->getContainerName() ?? '',
                $instance->getContainerState()->value,
                $instance->getCreatedAt(),
                $account->getId() ?? '',
                $account->getEmail(),
                $account->getCreatedAt(),
                $account->getRoles(),
                $isHealthy,
                $mcpEndpoint,
                $vncEndpoint,
                $instance->getScreenWidth(),
                $instance->getScreenHeight(),
                $instance->getColorDepth()
            );
        }

        // Sort by account creation date (newest first), then instance creation date
        usort($overviewDtos, function (McpInstanceAdminOverviewDto $a, McpInstanceAdminOverviewDto $b) {
            $accountCompare = $b->accountCreatedAt <=> $a->accountCreatedAt;
            if ($accountCompare !== 0) {
                return $accountCompare;
            }

            return $b->instanceCreatedAt <=> $a->instanceCreatedAt;
        });

        return $overviewDtos;
    }
}
