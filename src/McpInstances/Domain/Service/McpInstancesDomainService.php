<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Service;

use App\McpInstances\Domain\Entity\McpInstance;
use App\McpInstances\Facade\McpInstancesFacade;
use App\OsProcessManagement\Facade\OsProcessManagementFacadeInterface;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use RuntimeException;

readonly class McpInstancesDomainService
{
    public function __construct(
        private EntityManagerInterface             $entityManager,
        private OsProcessManagementFacadeInterface $osProcessMgmtFacade,
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

        // Generate unique display/port numbers
        $usedDisplays       = array_map(fn (McpInstance $i): int => $i->getDisplayNumber(), $repo->findAll());
        $usedMcpPorts       = array_map(fn (McpInstance $i): int => $i->getMcpPort(), $repo->findAll());
        $usedMcpProxyPorts  = array_map(fn (McpInstance $i): int => $i->getMcpProxyPort(), $repo->findAll());
        $usedVncPorts       = array_map(fn (McpInstance $i): int => $i->getVncPort(), $repo->findAll());
        $usedWebsocketPorts = array_map(fn (McpInstance $i): int => $i->getWebsocketPort(), $repo->findAll());

        $displayNumber = self::findFreeNumber(100, 199, $usedDisplays);
        $mcpPort       = self::findFreeNumber(11111, 11200, $usedMcpPorts);
        $mcpProxyPort  = self::findFreeNumber(9100, 9199, $usedMcpProxyPorts);
        $vncPort       = self::findFreeNumber(22222, 22300, $usedVncPorts);
        $websocketPort = self::findFreeNumber(33333, 33400, $usedWebsocketPorts);
        $screenWidth   = 1280;
        $screenHeight  = 720;
        $colorDepth    = 24;
        $vncPassword   = McpInstance::generateRandomPassword();

        $instance = new McpInstance(
            $accountCoreId,
            $displayNumber,
            $screenWidth,
            $screenHeight,
            $colorDepth,
            $mcpPort,
            $mcpProxyPort,
            $vncPort,
            $websocketPort,
            $vncPassword
        );
        $this->entityManager->persist($instance);
        $this->entityManager->flush();

        $this->osProcessMgmtFacade->launchPlaywrightSetup(
            McpInstancesFacade::mcpInstancesToMcpInstanceInfoDtos($this->getAllMcpInstances()),
            $displayNumber,
            $screenWidth,
            $screenHeight,
            $colorDepth,
            $mcpPort,
            $vncPort,
            $websocketPort,
            $vncPassword
        );

        return $instance;
    }

    public function stopAndRemoveMcpInstance(string $accountCoreId): void
    {
        $repo     = $this->entityManager->getRepository(McpInstance::class);
        $instance = $repo->findOneBy(['accountCoreId' => $accountCoreId]);
        if (!$instance) {
            throw new LogicException('No MCP instance for this account.');
        }
        $this->osProcessMgmtFacade->stopPlaywrightSetup(
            McpInstancesFacade::mcpInstancesToMcpInstanceInfoDtos($this->getAllMcpInstances()),
            $instance->getDisplayNumber(),
            $instance->getMcpPort(),
            $instance->getVncPort(),
            $instance->getWebsocketPort()
        );
        $this->entityManager->remove($instance);
        $this->entityManager->flush();
    }

    /**
     * @param int[] $used
     */
    private static function findFreeNumber(int $min, int $max, array $used): int
    {
        for ($i = $min; $i <= $max; ++$i) {
            if (!in_array($i, $used, true)) {
                return $i;
            }
        }
        throw new RuntimeException('No free number in range.');
    }
}
