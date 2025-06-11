<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Service;

use App\McpInstances\Domain\Entity\McpInstance;
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

        // Gather all used port numbers (across all types)
        $allInstances = $repo->findAll();
        $usedPorts    = [];
        foreach ($allInstances as $i) {
            $usedPorts[] = $i->getMcpPort();
            $usedPorts[] = $i->getMcpProxyPort();
            $usedPorts[] = $i->getVncPort();
            $usedPorts[] = $i->getWebsocketPort();
        }

        $displayNumber = self::findRandomFreeNumber(100, 2147483647, array_map(fn (McpInstance $i): int => $i->getDisplayNumber(), $allInstances));
        $mcpPort       = self::findRandomFreeNumber(10000, 65000, $usedPorts);
        $usedPorts[]   = $mcpPort;
        $mcpProxyPort  = self::findRandomFreeNumber(10000, 65000, $usedPorts);
        $usedPorts[]   = $mcpProxyPort;
        $vncPort       = self::findRandomFreeNumber(10000, 65000, $usedPorts);
        $usedPorts[]   = $vncPort;
        $websocketPort = self::findRandomFreeNumber(10000, 65000, $usedPorts);
        $usedPorts[]   = $websocketPort;
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
    private static function findRandomFreeNumber(int $min, int $max, array $used): int
    {
        $used        = array_flip($used);
        $maxAttempts = 1000;
        for ($attempt = 0; $attempt < $maxAttempts; ++$attempt) {
            $candidate = random_int($min, $max);
            if (!array_key_exists($candidate, $used)) {
                return $candidate;
            }
        }
        throw new RuntimeException('No free number found in range after many attempts.');
    }
}
