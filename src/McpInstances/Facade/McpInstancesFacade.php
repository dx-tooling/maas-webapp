<?php

declare(strict_types=1);

namespace App\McpInstances\Facade;

use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\McpInstances\Domain\Entity\McpInstance;
use App\McpInstances\Domain\Service\McpInstancesDomainService;
use App\McpInstances\Facade\Dto\McpInstanceInfoDto;
use Doctrine\ORM\EntityManagerInterface;

readonly class McpInstancesFacade implements McpInstancesFacadeInterface
{
    public function __construct(
        private McpInstancesDomainService $domainService,
        private EntityManagerInterface    $entityManager
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
}
