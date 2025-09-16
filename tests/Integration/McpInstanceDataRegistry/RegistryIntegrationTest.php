<?php

declare(strict_types=1);

namespace App\Tests\Integration\McpInstanceDataRegistry;

use App\McpInstanceDataRegistry\Domain\Entity\RegistryEntry;
use App\McpInstanceDataRegistry\Facade\McpInstanceDataRegistryFacadeInterface;
use App\McpInstancesManagement\Domain\Entity\McpInstance;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RegistryIntegrationTest extends KernelTestCase
{
    private McpInstanceDataRegistryFacadeInterface $registryFacade;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();

        $registryFacade = $container->get(McpInstanceDataRegistryFacadeInterface::class);
        if (!$registryFacade instanceof McpInstanceDataRegistryFacadeInterface) {
            throw new RuntimeException('Failed to get registry facade from container');
        }
        $this->registryFacade = $registryFacade;

        $entityManager = $container->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new RuntimeException('Failed to get entity manager from container');
        }
        $this->entityManager = $entityManager;
    }

    public function testRegistryWorkflowWithMockInstance(): void
    {
        // Create a mock instance ID and bearer token
        $instanceId  = 'test-instance-' . uniqid();
        $bearerToken = 'test-bearer-' . uniqid();
        $key         = 'test-key';
        $value       = 'test-value';

        // Set a value (admin operation)
        $this->registryFacade->setValue($instanceId, $key, $value);

        // Get all values (admin operation)
        $allValues = $this->registryFacade->getAllValues($instanceId);
        $this->assertArrayHasKey($key, $allValues);
        $this->assertSame($value, $allValues[$key]);

        // Try to get value with incorrect bearer token (should fail)
        $retrievedValue = $this->registryFacade->getValueWithAuth($instanceId, 'wrong-token', $key);
        $this->assertNull($retrievedValue);

        // Note: We can't test getValueWithAuth with correct token without a real McpInstance
        // because the facade validates against the instance's bearer token

        // Delete the value
        $deleted = $this->registryFacade->deleteValue($instanceId, $key);
        $this->assertTrue($deleted);

        // Verify it's deleted
        $allValues = $this->registryFacade->getAllValues($instanceId);
        $this->assertArrayNotHasKey($key, $allValues);
    }

    public function testMultipleEntriesForSameInstance(): void
    {
        $instanceId = 'test-instance-' . uniqid();

        // Set multiple values
        $this->registryFacade->setValue($instanceId, 'key1', 'value1');
        $this->registryFacade->setValue($instanceId, 'key2', 'value2');
        $this->registryFacade->setValue($instanceId, 'key3', 'value3');

        // Get all values
        $allValues = $this->registryFacade->getAllValues($instanceId);

        $this->assertCount(3, $allValues);
        $this->assertSame('value1', $allValues['key1']);
        $this->assertSame('value2', $allValues['key2']);
        $this->assertSame('value3', $allValues['key3']);

        // Update one value
        $this->registryFacade->setValue($instanceId, 'key2', 'updated-value2');

        $allValues = $this->registryFacade->getAllValues($instanceId);
        $this->assertSame('updated-value2', $allValues['key2']);
    }

    protected function tearDown(): void
    {
        // Clean up test data if needed
        // Remove any test entries we created
        $repo        = $this->entityManager->getRepository(RegistryEntry::class);
        $testEntries = $repo->createQueryBuilder('r')
            ->where('r.instanceId LIKE :pattern')
            ->setParameter('pattern', 'test-instance-%')
            ->getQuery()
            ->getResult();

        if (is_array($testEntries)) {
            foreach ($testEntries as $entry) {
                if (is_object($entry)) {
                    $this->entityManager->remove($entry);
                }
            }

            if (count($testEntries) > 0) {
                $this->entityManager->flush();
            }
        }

        parent::tearDown();
    }
}
