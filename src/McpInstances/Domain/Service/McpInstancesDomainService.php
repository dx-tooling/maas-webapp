<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Service;

use App\DockerManagement\Facade\DockerManagementFacadeInterface;
use App\McpInstances\Domain\Entity\McpInstance;
use App\McpInstances\Domain\Enum\ContainerState;
use Doctrine\ORM\EntityManagerInterface;
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
}
