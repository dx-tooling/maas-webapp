<?php

declare(strict_types=1);

namespace App\McpInstanceDataRegistry\Facade;

use App\McpInstanceDataRegistry\Domain\Service\RegistryDomainServiceInterface;
use App\McpInstancesManagement\Facade\McpInstancesManagementFacadeInterface;
use Psr\Log\LoggerInterface;

final readonly class McpInstanceDataRegistryFacade implements McpInstanceDataRegistryFacadeInterface
{
    public function __construct(
        private RegistryDomainServiceInterface        $domainService,
        private McpInstancesManagementFacadeInterface $instancesFacade,
        private LoggerInterface                       $logger
    ) {
    }

    public function getValueWithAuth(string $instanceId, string $bearerToken, string $key): ?string
    {
        // Validate the bearer token against the instance
        $instance = $this->instancesFacade->getMcpInstanceById($instanceId);

        if (!$instance) {
            $this->logger->warning('[RegistryFacade] Instance not found', [
                'instanceId' => $instanceId
            ]);

            return null;
        }

        // Constant-time comparison of bearer tokens
        if (!hash_equals($instance->mcpBearer, $bearerToken)) {
            $this->logger->warning('[RegistryFacade] Invalid bearer token', [
                'instanceId' => $instanceId
            ]);

            return null;
        }

        return $this->domainService->getValue($instanceId, $key);
    }

    public function setValue(string $instanceId, string $key, string $value): void
    {
        $this->domainService->setValue($instanceId, $key, $value);
    }

    public function deleteValue(string $instanceId, string $key): bool
    {
        return $this->domainService->deleteValue($instanceId, $key);
    }

    public function getAllValues(string $instanceId): array
    {
        return $this->domainService->getAllValues($instanceId);
    }
}
