<?php

declare(strict_types=1);

namespace App\McpInstances\Facade;

use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\DockerManagement\Facade\DockerManagementFacadeInterface;
use App\McpInstances\Domain\Entity\McpInstance;
use App\McpInstances\Domain\Service\McpInstancesDomainService;
use App\McpInstances\Facade\Dto\McpInstanceInfoDto;
use Doctrine\ORM\EntityManagerInterface;
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

        // Get Docker container status
        $containerStatus = $this->dockerFacade->getContainerStatus($instance);

        // Map to legacy format for UI compatibility
        $allRunning = $containerStatus['healthy'];

        return [
            'instanceId' => $instance->getId() ?? '',
            'processes'  => [
                'xvfb'      => $allRunning ? ['status' => 'running'] : null,
                'mcp'       => $allRunning ? ['status' => 'running'] : null,
                'vnc'       => $allRunning ? ['status' => 'running'] : null,
                'websocket' => $allRunning ? ['status' => 'running'] : null,
            ],
            'allRunning'      => $allRunning,
            'containerStatus' => $containerStatus
        ];
    }

    public function restartProcessesForInstance(string $instanceId): bool
    {
        return $this->domainService->restartMcpInstance($instanceId);
    }
}
