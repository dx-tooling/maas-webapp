<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Service;

use App\Account\Domain\Entity\AccountCore;
use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\DockerManagement\Facade\DockerManagementFacadeInterface;
use App\McpInstances\Domain\Entity\McpInstance;
use App\McpInstances\Domain\Enum\ContainerState;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use LogicException;

readonly class McpInstancesDomainService
{
    public function __construct(
        private EntityManagerInterface          $entityManager,
        private DockerManagementFacadeInterface $dockerFacade,
    ) {
    }

    /**
     * @return array<McpInstance>
     */
    public function getAllMcpInstances(): array
    {
        $repo = $this->entityManager->getRepository(McpInstance::class);

        return $repo->findAll();
    }

    public function createMcpInstance(string $accountCoreId): McpInstance
    {
        // Check if instance already exists
        $repo     = $this->entityManager->getRepository(McpInstance::class);
        $existing = $repo->findOneBy(['accountCoreId' => $accountCoreId]);
        if ($existing) {
            throw new LogicException('Account already has an MCP instance.');
        }

        // Create instance with default screen settings and generated secrets
        $screenWidth  = 1280;
        $screenHeight = 720;
        $colorDepth   = 24;
        $vncPassword  = McpInstance::generateRandomPassword();
        $mcpBearer    = McpInstance::generateRandomBearer();

        $instance = new McpInstance(
            $accountCoreId,
            $screenWidth,
            $screenHeight,
            $colorDepth,
            $vncPassword,
            $mcpBearer
        );

        $this->entityManager->persist($instance);
        $this->entityManager->flush();

        // Generate derived fields after ID is available
        $rootDomain = getenv('APP_ROOT_DOMAIN') ?: 'mcp-as-a-service.com';
        $instance->generateDerivedFields($rootDomain);
        $this->entityManager->flush();

        // Create and start Docker container
        if (!$this->dockerFacade->createAndStartContainer($instance)) {
            // If container creation fails, remove the database entry
            $this->entityManager->remove($instance);
            $this->entityManager->flush();
            throw new LogicException('Failed to create Docker container for MCP instance.');
        }

        // Update container state
        $instance->setContainerState(ContainerState::RUNNING);
        $this->entityManager->flush();

        return $instance;
    }

    public function stopAndRemoveMcpInstance(string $accountCoreId): void
    {
        $repo     = $this->entityManager->getRepository(McpInstance::class);
        $instance = $repo->findOneBy(['accountCoreId' => $accountCoreId]);
        if (!$instance) {
            throw new LogicException('No MCP instance for this account.');
        }

        // Stop and remove Docker container
        $this->dockerFacade->stopAndRemoveContainer($instance);

        // Remove database entry
        $this->entityManager->remove($instance);
        $this->entityManager->flush();
    }

    public function restartMcpInstance(string $instanceId): bool
    {
        $repo     = $this->entityManager->getRepository(McpInstance::class);
        $instance = $repo->find($instanceId);
        if (!$instance) {
            return false;
        }

        $success = $this->dockerFacade->restartContainer($instance);

        if ($success) {
            $instance->setContainerState(ContainerState::RUNNING);
        } else {
            $instance->setContainerState(ContainerState::ERROR);
        }

        $this->entityManager->flush();

        return $success;
    }

    /** @return array<McpInstance> */
    public function getMcpInstanceInfos(): array
    {
        return $this->getAllMcpInstances();
    }

    /** @return array<McpInstance> */
    public function getMcpInstanceInfosForAccount(AccountCoreInfoDto $accountCoreInfoDto): array
    {
        $repo      = $this->entityManager->getRepository(McpInstance::class);
        $instances = $repo->findBy(['accountCoreId' => $accountCoreInfoDto->id]);

        return $instances;
    }

    public function createMcpInstanceForAccount(AccountCoreInfoDto $accountCoreInfoDto): McpInstance
    {
        return $this->createMcpInstance($accountCoreInfoDto->id);
    }

    public function stopAndRemoveMcpInstanceForAccount(AccountCoreInfoDto $accountCoreInfoDto): void
    {
        $this->stopAndRemoveMcpInstance($accountCoreInfoDto->id);
    }

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

    /**
     * Get comprehensive admin overview of all MCP instances with account information.
     * This method requires ROLE_ADMIN access.
     *
     * @return array<array{
     *   instance: McpInstance,
     *   account: AccountCore,
     *   isHealthy: bool,
     *   mcpEndpoint: string|null,
     *   vncEndpoint: string|null
     * }>
     */
    public function getMcpInstanceAdminOverview(): array
    {
        // Get all MCP instances first
        $instances = $this->getAllMcpInstances();

        if (empty($instances)) {
            return [];
        }

        $overviewData = [];
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

            $overviewData[] = [
                'instance'    => $instance,
                'account'     => $account,
                'isHealthy'   => $isHealthy,
                'mcpEndpoint' => $mcpEndpoint,
                'vncEndpoint' => $vncEndpoint,
            ];
        }

        // Sort by account creation date (newest first), then instance creation date
        usort($overviewData, function (array $a, array $b) {
            $accountCompare = $b['account']->getCreatedAt() <=> $a['account']->getCreatedAt();
            if ($accountCompare !== 0) {
                return $accountCompare;
            }

            return $b['instance']->getCreatedAt() <=> $a['instance']->getCreatedAt();
        });

        return $overviewData;
    }
}
