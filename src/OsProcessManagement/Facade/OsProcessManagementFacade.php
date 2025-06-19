<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Facade;

use App\McpInstances\Domain\Entity\McpInstance;
use App\OsProcessManagement\Domain\Service\NginxManagementDomainService;
use App\OsProcessManagement\Domain\Service\OsProcessManagementDomainService;
use Doctrine\ORM\EntityManagerInterface;

readonly class OsProcessManagementFacade implements OsProcessManagementFacadeInterface
{
    public function __construct(
        private OsProcessManagementDomainService $processMgmtService,
        private NginxManagementDomainService     $nginxMgmtService,
        private EntityManagerInterface           $entityManager
    ) {
    }

    public function launchPlaywrightSetup(
        int    $displayNumber,
        int    $screenWidth,
        int    $screenHeight,
        int    $colorDepth,
        int    $mcpPort,
        int    $vncPort,
        int    $websocketPort,
        string $vncPassword
    ): void {
        $this->processMgmtService->launchVirtualFramebuffer(
            $displayNumber,
            $screenWidth,
            $screenHeight,
            $colorDepth
        );

        $this->processMgmtService->launchPlaywrightMcp(
            $mcpPort,
            $displayNumber
        );

        $this->processMgmtService->launchVncServer(
            $vncPort,
            $displayNumber,
            $vncPassword
        );

        $this->processMgmtService->launchVncWebsocket(
            $websocketPort,
            $vncPort
        );

        $this->nginxMgmtService->reconfigureAndRestartNginx();
    }

    public function stopPlaywrightSetup(
        int $displayNumber,
        int $mcpPort,
        int $vncPort,
        int $websocketPort
    ): void {
        $this->processMgmtService->stopVncWebsocket($websocketPort);
        $this->processMgmtService->stopVncServer($vncPort, $displayNumber);
        $this->processMgmtService->stopPlaywrightMcp($mcpPort);
        $this->processMgmtService->stopVirtualFramebuffer($displayNumber);

        $this->nginxMgmtService->reconfigureAndRestartNginx();
    }

    public function restartVirtualFramebuffer(int $displayNumber): bool
    {
        return $this->processMgmtService->restartVirtualFramebuffer($displayNumber);
    }

    public function restartPlaywrightMcp(int $port): bool
    {
        return $this->processMgmtService->restartPlaywrightMcp($port);
    }

    public function restartVncServer(int $port): bool
    {
        return $this->processMgmtService->restartVncServer($port);
    }

    public function restartVncWebsocket(int $httpPort): bool
    {
        return $this->processMgmtService->restartVncWebsocket($httpPort);
    }

    public function restartAllProcessesForInstance(string $instanceId): bool
    {
        $repo        = $this->entityManager->getRepository(McpInstance::class);
        $mcpInstance = $repo->find($instanceId);

        if (!$mcpInstance) {
            return false;
        }

        return $this->processMgmtService->restartAllProcessesForInstance($mcpInstance);
    }

    public function getAllProcesses(): array
    {
        $mcpInstances = $this->entityManager->getRepository(McpInstance::class)->findAll();

        // Build lookup tables for fast matching
        $displayToInstance       = [];
        $mcpPortToInstance       = [];
        $vncPortToInstance       = [];
        $websocketPortToInstance = [];
        foreach ($mcpInstances as $instance) {
            $displayToInstance[$instance->getDisplayNumber()]       = $instance->getId();
            $mcpPortToInstance[$instance->getMcpPort()]             = $instance->getId();
            $vncPortToInstance[$instance->getVncPort()]             = $instance->getId();
            $websocketPortToInstance[$instance->getWebsocketPort()] = $instance->getId();
        }

        // Helper to convert DTOs to arrays and add instance mapping
        $convertToArray = function (array $processes, string $type) use ($displayToInstance, $mcpPortToInstance, $vncPortToInstance, $websocketPortToInstance) {
            $result = [];
            foreach ($processes as $proc) {
                $instanceId = null;
                if ($type === 'xvfb' && array_key_exists($proc->displayNumber, $displayToInstance)) {
                    $instanceId = $displayToInstance[$proc->displayNumber];
                } elseif ($type === 'mcp' && array_key_exists($proc->mcpPort, $mcpPortToInstance)) {
                    $instanceId = $mcpPortToInstance[$proc->mcpPort];
                } elseif ($type === 'vnc' && array_key_exists($proc->port, $vncPortToInstance)) {
                    $instanceId = $vncPortToInstance[$proc->port];
                } elseif ($type === 'ws' && array_key_exists($proc->httpPort, $websocketPortToInstance)) {
                    $instanceId = $websocketPortToInstance[$proc->httpPort];
                }

                $result[] = [
                    'proc' => [
                        'pid'                => $proc->pid,
                        'percentCpuUsage'    => $proc->percentCpuUsage,
                        'percentMemoryUsage' => $proc->percentMemoryUsage,
                        'commandLine'        => $proc->commandLine,
                        'displayNumber'      => $proc->displayNumber ?? null,
                        'mcpPort'            => $proc->mcpPort       ?? null,
                        'port'               => $proc->port          ?? null,
                        'httpPort'           => $proc->httpPort      ?? null,
                        'vncPort'            => $proc->vncPort       ?? null,
                    ],
                    'instanceId' => $instanceId,
                ];
            }

            return $result;
        };

        return [
            'virtualFramebuffers' => $convertToArray($this->processMgmtService->getRunningVirtualFramebuffers(), 'xvfb'),
            'playwrightMcps'      => $convertToArray($this->processMgmtService->getRunningPlaywrightMcps(), 'mcp'),
            'vncServers'          => $convertToArray($this->processMgmtService->getRunningVncServers(), 'vnc'),
            'vncWebsockets'       => $convertToArray($this->processMgmtService->getRunningVncWebsockets(), 'ws'),
        ];
    }
}
