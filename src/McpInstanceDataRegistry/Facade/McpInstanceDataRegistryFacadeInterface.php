<?php

declare(strict_types=1);

namespace App\McpInstanceDataRegistry\Facade;

interface McpInstanceDataRegistryFacadeInterface
{
    /**
     * Get a value from the registry for a specific instance and key.
     * Validates the bearer token before returning the value.
     * Returns null if the key doesn't exist or authentication fails.
     */
    public function getValueWithAuth(string $instanceId, string $bearerToken, string $key): ?string;

    /**
     * Set a value in the registry (admin operation, no bearer validation).
     */
    public function setValue(string $instanceId, string $key, string $value): void;

    /**
     * Delete a value from the registry (admin operation).
     */
    public function deleteValue(string $instanceId, string $key): bool;

    /**
     * Get all values for an instance (admin operation).
     *
     * @return array<string, string>
     */
    public function getAllValues(string $instanceId): array;

    /**
     * Get the base registry endpoint URL for a given instance.
     * This URL can be used by containers to access the registry API.
     */
    public function getRegistryEndpointUrl(string $instanceId): string;
}
