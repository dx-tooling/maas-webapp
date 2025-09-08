<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Service;

use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\McpInstances\Domain\Dto\ProcessStatusDto;
use App\McpInstances\Domain\Entity\McpInstance;
use App\McpInstances\Domain\Enum\InstanceType;
use Exception;

interface McpInstancesDomainServiceInterface
{
    /**
     * @return array<McpInstance>
     */
    public function getAllMcpInstances(): array;

    public function createMcpInstance(
        string        $accountCoreId,
        ?InstanceType $instanceType = null
    ): McpInstance;

    public function stopAndRemoveMcpInstance(string $accountCoreId): void;

    public function restartMcpInstance(string $instanceId): bool;

    /**
     * Stop and remove the existing container, then create and start a new one
     * with the exact same instance attributes (IDs, slugs, passwords).
     */
    public function recreateMcpInstanceContainer(string $instanceId): bool;

    /** @return array<McpInstance> */
    public function getMcpInstanceInfos(): array;

    public function getMcpInstanceById(string $id): ?McpInstance;

    /** @return array<McpInstance> */
    public function getMcpInstanceInfosForAccount(AccountCoreInfoDto $accountCoreInfoDto): array;

    /**
     * @throws Exception
     */
    public function createMcpInstanceForAccount(
        AccountCoreInfoDto $accountCoreInfoDto,
        ?InstanceType      $instanceType = null
    ): McpInstance;

    public function stopAndRemoveMcpInstanceForAccount(
        AccountCoreInfoDto $accountCoreInfoDto
    ): void;

    /**
     * Get process status for a specific MCP instance.
     */
    public function getProcessStatusForInstance(string $instanceId): ProcessStatusDto;
}
