<?php

declare(strict_types=1);

namespace App\McpInstances\Facade;

use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\McpInstances\Domain\Entity\McpInstance;
use App\McpInstances\Domain\Service\McpInstancesDomainService;
use App\McpInstances\Facade\Dto\McpInstanceInfoDto;
use App\OsProcessManagement\Facade\OsProcessManagementFacadeInterface;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

readonly class McpInstancesFacade implements McpInstancesFacadeInterface
{
    public function __construct(
        private McpInstancesDomainService          $domainService,
        private EntityManagerInterface             $entityManager,
        private OsProcessManagementFacadeInterface $osProcessMgmtFacade
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
                $i->getDisplayNumber(),
                $i->getMcpPort(),
                $i->getMcpProxyPort(),
                $i->getVncPort(),
                $i->getWebsocketPort(),
                $i->getVncPassword()
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

        return array_map(
            fn (McpInstance $i) => new McpInstanceInfoDto(
                $i->getId(),
                $i->getDisplayNumber(),
                $i->getMcpPort(),
                $i->getMcpProxyPort(),
                $i->getVncPort(),
                $i->getWebsocketPort(),
                $i->getVncPassword()
            ),
            $instances
        );
    }

    public function createMcpInstance(AccountCoreInfoDto $accountCoreInfoDto): McpInstanceInfoDto
    {
        $instance = $this->domainService->createMcpInstance($accountCoreInfoDto->id);

        return new McpInstanceInfoDto(
            $instance->getId(),
            $instance->getDisplayNumber(),
            $instance->getMcpPort(),
            $instance->getMcpProxyPort(),
            $instance->getVncPort(),
            $instance->getWebsocketPort(),
            $instance->getVncPassword()
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

        // Get all process statuses
        $allProcesses = $this->osProcessMgmtFacade->getAllProcesses();

        // Filter processes for this specific instance
        $instanceProcesses = [
            'xvfb'      => null,
            'mcp'       => null,
            'vnc'       => null,
            'websocket' => null
        ];

        // Find Xvfb process
        foreach ($allProcesses['virtualFramebuffers'] as $xvfb) {
            if ($xvfb['proc']['displayNumber'] === $instance->getDisplayNumber()) {
                $instanceProcesses['xvfb'] = $xvfb;
                break;
            }
        }

        // Find MCP process
        foreach ($allProcesses['playwrightMcps'] as $mcp) {
            if ($mcp['proc']['mcpPort'] === $instance->getMcpPort()) {
                $instanceProcesses['mcp'] = $mcp;
                break;
            }
        }

        // Find VNC server process
        foreach ($allProcesses['vncServers'] as $vnc) {
            if ($vnc['proc']['port'] === $instance->getVncPort()) {
                $instanceProcesses['vnc'] = $vnc;
                break;
            }
        }

        // Find VNC websocket process
        foreach ($allProcesses['vncWebsockets'] as $ws) {
            if ($ws['proc']['httpPort'] === $instance->getWebsocketPort()) {
                $instanceProcesses['websocket'] = $ws;
                break;
            }
        }

        return [
            'instanceId' => $instance->getId() ?? '',
            'processes'  => $instanceProcesses,
            'allRunning' => !in_array(null, $instanceProcesses, true)
        ];
    }

    public function restartProcessesForInstance(string $instanceId): bool
    {
        $repo     = $this->entityManager->getRepository(McpInstance::class);
        $instance = $repo->find($instanceId);

        if (!$instance) {
            throw new LogicException('MCP instance not found.');
        }

        return $this->osProcessMgmtFacade->restartAllProcessesForInstance($instanceId);
    }
}
