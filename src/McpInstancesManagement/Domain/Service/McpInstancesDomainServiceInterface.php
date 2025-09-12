<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Domain\Service;

use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\McpInstancesManagement\Domain\Entity\McpInstance;
use App\McpInstancesManagement\Facade\Dto\ProcessStatusDto;
use App\McpInstancesManagement\Facade\InstanceType;
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

    public function stopAndRemoveMcpInstanceById(string $instanceId): void;

    /**
     * Get process status for a specific MCP instance.
     */
    public function getProcessStatusForInstance(string $instanceId): ProcessStatusDto;
}
