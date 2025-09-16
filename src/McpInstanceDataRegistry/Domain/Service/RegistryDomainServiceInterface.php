<?php

declare(strict_types=1);

namespace App\McpInstanceDataRegistry\Domain\Service;

interface RegistryDomainServiceInterface
{
    /**
     * Get a value from the registry for a specific instance and key.
     * Returns null if the key doesn't exist.
     */
    public function getValue(string $instanceId, string $key): ?string;

    /**
     * Set a value in the registry for a specific instance and key.
     * Creates a new entry or updates an existing one.
     */
    public function setValue(string $instanceId, string $key, string $value): void;

    /**
     * Delete a specific key from the registry for an instance.
     * Returns true if the key existed and was deleted, false otherwise.
     */
    public function deleteValue(string $instanceId, string $key): bool;

    /**
     * Get all key-value pairs for a specific instance.
     *
     * @return array<string, string>
     */
    public function getAllValues(string $instanceId): array;

    /**
     * Delete all registry entries for a specific instance.
     * Returns the number of entries deleted.
     */
    public function deleteAllValues(string $instanceId): int;
}
