<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Presentation;

use App\Account\Facade\AccountFacadeInterface;
use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\DockerManagement\Facade\DockerManagementFacadeInterface;
use App\McpInstancesConfiguration\Facade\Service\InstanceTypesConfigFacadeInterface;
use App\McpInstancesManagement\Domain\Entity\McpInstance;
use App\McpInstancesManagement\Domain\Service\McpInstancesDomainServiceInterface;
use App\McpInstancesManagement\Facade\Dto\InstanceStatusDto;
use App\McpInstancesManagement\Facade\Dto\ProcessStatusDto;
use App\McpInstancesManagement\Facade\InstanceType;
use App\McpInstancesManagement\Facade\McpInstancesManagementFacadeInterface;
use App\McpInstancesManagement\Presentation\Dto\AdminAccountDto;
use App\McpInstancesManagement\Presentation\Dto\AdminOverviewDto;
use App\McpInstancesManagement\Presentation\Dto\DashboardDataDto;
use App\McpInstancesManagement\Presentation\Dto\McpInstanceInfoDto;
use Exception;
use ValueError;

final readonly class McpInstancesPresentationService
{
    public function __construct(
        private McpInstancesDomainServiceInterface    $domainService,
        private AccountFacadeInterface                $accountFacade,
        private DockerManagementFacadeInterface       $dockerFacade,
        private InstanceTypesConfigFacadeInterface    $typesConfig,
        private McpInstancesManagementFacadeInterface $instancesFacade,
    ) {
    }

    /**
     * Get dashboard data for a specific account.
     */
    public function getDashboardData(AccountCoreInfoDto $accountCoreInfoDto): DashboardDataDto
    {
        $instances = $this->domainService->getMcpInstanceInfosForAccount($accountCoreInfoDto);
        $instance  = $instances[0] ?? null;

        $instanceDto   = null;
        $processStatus = null;
        $genericStatus = null;

        if ($instance) {
            $instanceDto = $this->mapMcpInstanceToDto($instance);

            try {
                $processStatus = $this->getProcessStatusForInstance($instance->getId() ?? '');
            } catch (Exception) {
                // If there's an error getting process status, we'll show the instance without status
            }

            try {
                $genericStatus = $this->getInstanceStatusForInstance($instance->getId() ?? '');
            } catch (Exception) {
                $genericStatus = null;
            }
        }

        $availableTypes = $this->getAvailableTypes();
        /** @var array<int,array{value:string,display:string}> $reducedTypes */
        $reducedTypes = [];
        foreach ($availableTypes as $t) {
            $reducedTypes[] = ['value' => (string)$t['value'], 'display' => (string)$t['display']];
        }

        return new DashboardDataDto(
            $instanceDto,
            $processStatus,
            $genericStatus,
            $reducedTypes,
        );
    }

    /**
     * @return array<array{value:string,display:string,description:string}>
     */
    public function getAvailableTypes(): array
    {
        return array_values(array_filter(
            array_map(function (InstanceType $t): array {
                $cfg = $this->typesConfig->getTypeConfig($t);

                return [
                    'value'       => $t->value,
                    'display'     => ($cfg !== null) ? $cfg->displayName : $t->value,
                    'description' => ($cfg !== null) ? $cfg->description : '',
                ];
            }, InstanceType::cases()),
            static fn (array $x): bool => $x['value'] !== InstanceType::_LEGACY->value
        ));
    }

    /**
     * @return array<McpInstanceInfoDto>
     */
    public function getInstancesForAccount(AccountCoreInfoDto $accountCoreInfoDto): array
    {
        $instances = $this->domainService->getMcpInstanceInfosForAccount($accountCoreInfoDto);

        return array_map(fn (McpInstance $i): McpInstanceInfoDto => $this->mapMcpInstanceToDto($i), $instances);
    }

    /**
     * Get process status for a specific MCP instance.
     */
    public function getProcessStatusForInstance(string $instanceId): ProcessStatusDto
    {
        return $this->domainService->getProcessStatusForInstance($instanceId);
    }

    /**
     * Get generic instance status with dynamic endpoints for a specific MCP instance.
     */
    public function getInstanceStatusForInstance(string $instanceId): ?InstanceStatusDto
    {
        $instance = $this->domainService->getMcpInstanceById($instanceId);
        if ($instance === null) {
            return null;
        }

        return $this->dockerFacade->getInstanceStatus($this->instancesFacade->toDto($instance));
    }

    /**
     * Get admin overview data for all MCP instances.
     *
     * @return array<AdminOverviewDto>
     */
    public function getAdminOverviewData(): array
    {
        // Get all MCP instances from domain
        $instances = $this->domainService->getAllMcpInstances();

        if (empty($instances)) {
            return [];
        }

        $overviewData = [];
        foreach ($instances as $instance) {
            // Get the account info via Account Facade
            $account = $this->accountFacade->getAccountById($instance->getAccountCoreId());

            if ($account === null) {
                continue; // Skip if account not found
            }

            // Get container status for health check
            try {
                $containerStatus = $this->dockerFacade->getContainerStatus($this->instancesFacade->toDto($instance));
                $isHealthy       = $containerStatus->healthy;
                $mcpEndpoint     = $containerStatus->mcpEndpoint;
                $vncEndpoint     = $containerStatus->vncEndpoint;
            } catch (Exception $e) {
                // If Docker facade fails, use default values
                $isHealthy   = false;
                $mcpEndpoint = null;
                $vncEndpoint = null;
            }

            $overviewData[] = new AdminOverviewDto(
                $this->mapMcpInstanceToDto($instance),
                new AdminAccountDto(
                    $account->id,
                    $account->email,
                    $account->roles,
                    $account->createdAt,
                ),
                $isHealthy,
                $mcpEndpoint,
                $vncEndpoint,
            );
        }

        // Sort by account creation date (newest first), then instance creation date
        usort($overviewData, function (AdminOverviewDto $a, AdminOverviewDto $b) {
            $accountCompare = $b->account->createdAt <=> $a->account->createdAt;
            if ($accountCompare !== 0) {
                return $accountCompare;
            }

            return $b->instance->createdAt <=> $a->instance->createdAt;
        });

        return $overviewData;
    }

    /**
     * Get MCP instance info by ID.
     */
    public function getMcpInstanceInfoById(string $id): ?McpInstanceInfoDto
    {
        $instance = $this->domainService->getMcpInstanceById($id);

        return $instance ? $this->mapMcpInstanceToDto($instance) : null;
    }

    /**
     * Map McpInstance domain entity to presentation DTO.
     */
    private function mapMcpInstanceToDto(McpInstance $instance): McpInstanceInfoDto
    {
        $typeCfg  = $this->typesConfig->getTypeConfig(InstanceType::from($instance->getInstanceType()->value));
        $display  = ($typeCfg !== null) ? $typeCfg->displayName : $instance->getInstanceType()->value;
        $vncPaths = [];
        $mcpPaths = [];

        if ($typeCfg !== null) {
            if (array_key_exists('vnc', $typeCfg->endpoints)) {
                $vncPaths = $typeCfg->endpoints['vnc']->externalPaths;
            }
            if (array_key_exists('mcp', $typeCfg->endpoints)) {
                $mcpPaths = $typeCfg->endpoints['mcp']->externalPaths;
            }
        }

        if (!array_is_list($vncPaths)) {
            throw new ValueError('vncPaths must be a list');
        }
        if (!array_is_list($mcpPaths)) {
            throw new ValueError('mcpPaths must be a list');
        }

        return new McpInstanceInfoDto(
            $instance->getId() ?? '',
            $instance->getCreatedAt(),
            $instance->getAccountCoreId(),
            $instance->getInstanceSlug(),
            $instance->getContainerName(),
            $instance->getContainerState()->value,
            $instance->getInstanceType()->value,
            $display,
            $instance->getScreenWidth(),
            $instance->getScreenHeight(),
            $instance->getColorDepth(),
            $instance->getVncPassword(),
            $instance->getMcpBearer(),
            $instance->getMcpSubdomain(),
            $instance->getVncSubdomain(),
            $vncPaths,
            $mcpPaths,
        );
    }

    // Account mapping now happens via AccountFacade DTO
}
