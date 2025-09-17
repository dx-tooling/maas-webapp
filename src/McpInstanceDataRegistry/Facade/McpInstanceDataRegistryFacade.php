<?php

declare(strict_types=1);

namespace App\McpInstanceDataRegistry\Facade;

use App\McpInstanceDataRegistry\Domain\Service\RegistryDomainServiceInterface;
use App\McpInstancesManagement\Facade\McpInstancesManagementFacadeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final readonly class McpInstanceDataRegistryFacade implements McpInstanceDataRegistryFacadeInterface
{
    public function __construct(
        private RegistryDomainServiceInterface        $domainService,
        private McpInstancesManagementFacadeInterface $instancesFacade,
        private LoggerInterface                       $logger,
        private RouterInterface                       $router
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

        // Constant-time comparison of bearer tokens (using registryBearer, not mcpBearer)
        if (!hash_equals($instance->registryBearer, $bearerToken)) {
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

    public function getRegistryEndpointUrl(string $instanceId): string
    {
        // Generate the absolute URL for the registry API
        return $this->router->generate(
            'mcp_instance_data_registry.api.get_value',
            [
                'instanceId' => $instanceId,
                'key'        => 'KEY_PLACEHOLDER'
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        // The URL will have the format: https://app.domain.com/api/instance-data-registry/{instanceId}/KEY_PLACEHOLDER
        // The container will replace KEY_PLACEHOLDER with the actual key when making requests
    }
}
