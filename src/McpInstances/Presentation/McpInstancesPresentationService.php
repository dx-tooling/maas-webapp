<?php

declare(strict_types=1);

namespace App\McpInstances\Presentation;

use App\Account\Domain\Entity\AccountCore;
use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\DockerManagement\Facade\DockerManagementFacadeInterface;
use App\McpInstances\Domain\Dto\ProcessStatusDto;
use App\McpInstances\Domain\Enum\InstanceType;
use App\McpInstances\Domain\Service\McpInstancesDomainServiceInterface;
use App\McpInstances\Presentation\Dto\AdminAccountDto;
use App\McpInstances\Presentation\Dto\AdminOverviewDto;
use App\McpInstances\Presentation\Dto\DashboardDataDto;
use App\McpInstances\Presentation\Dto\McpInstanceInfoDto;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

readonly class McpInstancesPresentationService
{
    public function __construct(
        private McpInstancesDomainServiceInterface $domainService,
        private EntityManagerInterface             $entityManager,
        private DockerManagementFacadeInterface    $dockerFacade,
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

        if ($instance) {
            $instanceDto = $this->mapMcpInstanceToDto($instance);

            try {
                $processStatus = $this->getProcessStatusForInstance($instance->getId() ?? '');
            } catch (Exception) {
                // If there's an error getting process status, we'll show the instance without status
            }
        }

        $availableTypes = array_filter(
            InstanceType::cases(),
            static fn (InstanceType $t) => $t !== InstanceType::_LEGACY
        );

        return new DashboardDataDto(
            $instanceDto,
            $processStatus,
            $availableTypes,
        );
    }

    /**
     * Get process status for a specific MCP instance.
     */
    public function getProcessStatusForInstance(string $instanceId): ProcessStatusDto
    {
        return $this->domainService->getProcessStatusForInstance($instanceId);
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
            // Get the account for this instance
            $accountRepo = $this->entityManager->getRepository(AccountCore::class);
            $account     = $accountRepo->find($instance->getAccountCoreId());

            if (!$account) {
                continue; // Skip if account not found
            }

            // Get container status for health check
            try {
                $containerStatus = $this->dockerFacade->getContainerStatus($instance);
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
                $this->mapAccountToDto($account),
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
    private function mapMcpInstanceToDto(\App\McpInstances\Domain\Entity\McpInstance $instance): McpInstanceInfoDto
    {
        return new McpInstanceInfoDto(
            $instance->getId() ?? '',
            $instance->getCreatedAt(),
            $instance->getAccountCoreId(),
            $instance->getInstanceSlug(),
            $instance->getContainerName(),
            $instance->getContainerState()->value,
            $instance->getInstanceType()->value,
            $instance->getScreenWidth(),
            $instance->getScreenHeight(),
            $instance->getColorDepth(),
            $instance->getVncPassword(),
            $instance->getMcpBearer(),
            $instance->getMcpSubdomain(),
            $instance->getVncSubdomain(),
        );
    }

    /**
     * Map AccountCore domain entity to presentation DTO.
     */
    private function mapAccountToDto(AccountCore $account): AdminAccountDto
    {
        return new AdminAccountDto(
            $account->getId() ?? '',
            $account->getEmail(),
            $account->getRoles(),
            $account->getCreatedAt(),
        );
    }
}
