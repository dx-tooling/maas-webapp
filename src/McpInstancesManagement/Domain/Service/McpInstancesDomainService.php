<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Domain\Service;

use App\DockerManagement\Facade\DockerManagementFacadeInterface;
use App\McpInstancesConfiguration\Facade\Service\InstanceTypesConfigFacadeInterface;
use App\McpInstancesManagement\Domain\Entity\McpInstance;
use App\McpInstancesManagement\Facade\Dto\McpInstanceDto;
use App\McpInstancesManagement\Facade\Dto\ProcessStatusContainerDto;
use App\McpInstancesManagement\Facade\Dto\ProcessStatusDto;
use App\McpInstancesManagement\Facade\Dto\ServiceStatusDto;
use App\McpInstancesManagement\Facade\Enum\ContainerState;
use App\McpInstancesManagement\Facade\Enum\InstanceType;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use LogicException;

final readonly class McpInstancesDomainService implements McpInstancesDomainServiceInterface
{
    public function __construct(
        private EntityManagerInterface             $entityManager,
        private DockerManagementFacadeInterface    $dockerFacade,
        private InstanceTypesConfigFacadeInterface $typesConfig,
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

    /**
     * @throws Exception
     */
    public function createMcpInstance(
        string        $accountCoreId,
        ?InstanceType $instanceType = null
    ): McpInstance {
        // Check running instances count for this account against limit
        $repo  = $this->entityManager->getRepository(McpInstance::class);
        $count = $repo->count(['accountCoreId' => $accountCoreId]);
        $max   = \App\McpInstancesManagement\Domain\Enum\UsageLimits::MAX_RUNNING_INSTANCES->value;
        if ($count >= $max) {
            throw new LogicException('Maximum number of MCP instances reached for this account.');
        }

        // Create instance with default screen settings and generated secrets
        $screenWidth  = 1920;
        $screenHeight = 1200;
        $colorDepth   = 24;
        $vncPassword  = McpInstance::generateRandomPassword();
        $mcpBearer    = McpInstance::generateRandomBearer();

        $instance = new McpInstance(
            $accountCoreId,
            $instanceType !== null ? InstanceType::from($instanceType->value) : InstanceType::PLAYWRIGHT_V1,
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
        if (!$this->dockerFacade->createAndStartContainer($this->createMcpInstanceDtoForDocker($instance))) {
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
        $this->dockerFacade->stopAndRemoveContainer($this->createMcpInstanceDtoForDocker($instance));

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

        $success = $this->dockerFacade->restartContainer($this->createMcpInstanceDtoForDocker($instance));

        if ($success) {
            $instance->setContainerState(ContainerState::RUNNING);
        } else {
            $instance->setContainerState(ContainerState::ERROR);
        }

        $this->entityManager->flush();

        return $success;
    }

    /**
     * Stop and remove the existing container, then create and start a new one
     * with the exact same instance attributes (IDs, slugs, passwords).
     */
    public function recreateMcpInstanceContainer(string $instanceId): bool
    {
        $repo     = $this->entityManager->getRepository(McpInstance::class);
        $instance = $repo->find($instanceId);
        if (!$instance) {
            return false;
        }

        // Stop and remove old container (ignore result; it may not exist)
        $this->dockerFacade->stopAndRemoveContainer($this->createMcpInstanceDtoForDocker($instance));

        // Ensure derived fields exist (container name/slug) before recreation
        if ($instance->getContainerName() === null || $instance->getInstanceSlug() === null) {
            $rootDomain = getenv('APP_ROOT_DOMAIN') ?: 'mcp-as-a-service.com';
            $instance->generateDerivedFields($rootDomain);
            $this->entityManager->flush();
        }

        // Create and start new container using the same instance data
        $success = $this->dockerFacade->createAndStartContainer($this->createMcpInstanceDtoForDocker($instance));
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

    public function getMcpInstanceById(string $id): ?McpInstance
    {
        $repo = $this->entityManager->getRepository(McpInstance::class);

        return $repo->find($id);
    }

    /** @return array<McpInstance> */
    public function getMcpInstanceInfosForAccount(string $accountId): array
    {
        $repo = $this->entityManager->getRepository(McpInstance::class);

        return $repo->findBy(['accountCoreId' => $accountId]);
    }

    /**
     * @throws Exception
     */
    public function createMcpInstanceForAccount(
        string        $accountId,
        ?InstanceType $instanceType = null
    ): McpInstance {
        return $this->createMcpInstance($accountId, $instanceType);
    }

    public function stopAndRemoveMcpInstanceForAccount(
        string $accountId
    ): void {
        $this->stopAndRemoveMcpInstance($accountId);
    }

    public function stopAndRemoveMcpInstanceById(string $instanceId): void
    {
        $repo     = $this->entityManager->getRepository(McpInstance::class);
        $instance = $repo->find($instanceId);
        if (!$instance) {
            throw new LogicException('MCP instance not found.');
        }

        $this->dockerFacade->stopAndRemoveContainer($this->createMcpInstanceDtoForDocker($instance));
        $this->entityManager->remove($instance);
        $this->entityManager->flush();
    }

    /**
     * Get process status for a specific MCP instance.
     */
    public function getProcessStatusForInstance(string $instanceId): ProcessStatusDto
    {
        $repo     = $this->entityManager->getRepository(McpInstance::class);
        $instance = $repo->find($instanceId);

        if (!$instance) {
            throw new LogicException('MCP instance not found.');
        }

        // Get Docker container status with partial endpoint checks
        $containerStatus = $this->dockerFacade->getContainerStatus($this->createMcpInstanceDtoForDocker($instance));

        $running     = $containerStatus->state === 'running';
        $xvfbUp      = $running; // container running implies Xvfb supervisor started
        $mcpUp       = $containerStatus->mcpUp;
        $noVncUp     = $containerStatus->noVncUp;
        $websocketUp = $noVncUp; // web client served by noVNC/websockify

        $allRunning = $xvfbUp && $mcpUp && $noVncUp && $websocketUp;

        $processes = new ServiceStatusDto(
            $xvfbUp ? 'running' : null,
            $mcpUp ? 'running' : null,
            $noVncUp ? 'running' : null,
            $websocketUp ? 'running' : null,
        );

        $containerStatusDto = new ProcessStatusContainerDto(
            $containerStatus->containerName,
            $containerStatus->state,
            $containerStatus->healthy,
            $mcpUp,
            $noVncUp,
            $containerStatus->mcpEndpoint,
            $containerStatus->vncEndpoint,
        );

        return new ProcessStatusDto(
            $instance->getId() ?? '',
            $processes,
            $allRunning,
            $containerStatusDto,
        );
    }

    /**
     * @param array<string,string> $environmentVariables
     */
    public function updateEnvironmentVariables(string $accountCoreId, string $instanceId, array $environmentVariables): bool
    {
        $repo     = $this->entityManager->getRepository(McpInstance::class);
        $instance = $repo->findOneBy(['id' => $instanceId, 'accountCoreId' => $accountCoreId]);

        if ($instance === null) {
            return false;
        }

        $instance->setUserEnvironmentVariables($environmentVariables);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Recreate all MCP instances by stopping and recreating their containers.
     * Returns true if all instances were successfully recreated, false otherwise.
     */
    public function recreateAllMcpInstances(): bool
    {
        $instances = $this->getAllMcpInstances();

        if (empty($instances)) {
            return true; // No instances to recreate
        }

        $allSuccessful = true;

        foreach ($instances as $instance) {
            $instanceId = $instance->getId();
            if ($instanceId === null) {
                $allSuccessful = false;
                continue;
            }

            // Reuse the existing recreateMcpInstanceContainer method
            $success = $this->recreateMcpInstanceContainer($instanceId);
            if (!$success) {
                $allSuccessful = false;
            }
        }

        return $allSuccessful;
    }

    /**
     * Create a complete McpInstanceDto with all required data including required environment variables.
     */
    public function createMcpInstanceDto(McpInstance $instance): McpInstanceDto
    {
        $typeCfg             = $this->typesConfig->getTypeConfig(InstanceType::from($instance->getInstanceType()->value));
        $requiredUserEnvVars = $typeCfg !== null ? $typeCfg->requiredUserEnvVars : [];

        return new McpInstanceDto(
            $instance->getId() ?? '',
            $instance->getCreatedAt(),
            $instance->getAccountCoreId(),
            $instance->getInstanceSlug(),
            $instance->getContainerName(),
            ContainerState::from($instance->getContainerState()->value),
            InstanceType::from($instance->getInstanceType()->value),
            $instance->getScreenWidth(),
            $instance->getScreenHeight(),
            $instance->getColorDepth(),
            $instance->getVncPassword(),
            $instance->getMcpBearer(),
            $instance->getMcpSubdomain(),
            $instance->getVncSubdomain(),
            $instance->getUserEnvironmentVariablesAsArray(),
            $requiredUserEnvVars,
        );
    }

    /**
     * Create a McpInstanceDto for Docker operations (without required environment variables).
     */
    public function createMcpInstanceDtoForDocker(McpInstance $instance): McpInstanceDto
    {
        return new McpInstanceDto(
            $instance->getId() ?? '',
            $instance->getCreatedAt(),
            $instance->getAccountCoreId(),
            $instance->getInstanceSlug(),
            $instance->getContainerName(),
            ContainerState::from($instance->getContainerState()->value),
            InstanceType::from($instance->getInstanceType()->value),
            $instance->getScreenWidth(),
            $instance->getScreenHeight(),
            $instance->getColorDepth(),
            $instance->getVncPassword(),
            $instance->getMcpBearer(),
            $instance->getMcpSubdomain(),
            $instance->getVncSubdomain(),
            $instance->getUserEnvironmentVariablesAsArray(),
            [], // No required environment variables needed for Docker operations
        );
    }
}
