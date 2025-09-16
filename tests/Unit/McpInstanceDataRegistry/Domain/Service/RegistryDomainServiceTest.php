<?php

declare(strict_types=1);

namespace App\Tests\Unit\McpInstanceDataRegistry\Domain\Service;

use App\McpInstanceDataRegistry\Domain\Entity\RegistryEntry;
use App\McpInstanceDataRegistry\Domain\Service\RegistryDomainService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RegistryDomainServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private RegistryDomainService $service;
    /** @var EntityRepository<RegistryEntry>&MockObject */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger        = $this->createMock(LoggerInterface::class);
        $this->repository    = $this->createMock(EntityRepository::class);

        $this->entityManager->method('getRepository')
            ->with(RegistryEntry::class)
            ->willReturn($this->repository);

        $this->service = new RegistryDomainService(
            $this->entityManager,
            $this->logger
        );
    }

    public function testGetValueReturnsValueWhenEntryExists(): void
    {
        $instanceId = 'test-instance';
        $key        = 'test-key';
        $value      = 'test-value';

        $entry = $this->createMock(RegistryEntry::class);
        $entry->method('getRegistryValue')->willReturn($value);

        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'instanceId'  => $instanceId,
                'registryKey' => $key
            ])
            ->willReturn($entry);

        $result = $this->service->getValue($instanceId, $key);

        $this->assertSame($value, $result);
    }

    public function testGetValueReturnsNullWhenEntryDoesNotExist(): void
    {
        $instanceId = 'test-instance';
        $key        = 'non-existent-key';

        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'instanceId'  => $instanceId,
                'registryKey' => $key
            ])
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('[RegistryDomainService] Key not found', [
                'instanceId' => $instanceId,
                'key'        => $key
            ]);

        $result = $this->service->getValue($instanceId, $key);

        $this->assertNull($result);
    }

    public function testSetValueUpdatesExistingEntry(): void
    {
        $instanceId = 'test-instance';
        $key        = 'test-key';
        $value      = 'new-value';

        $entry = $this->createMock(RegistryEntry::class);
        $entry->expects($this->once())
            ->method('setRegistryValue')
            ->with($value);

        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'instanceId'  => $instanceId,
                'registryKey' => $key
            ])
            ->willReturn($entry);

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('[RegistryDomainService] Updated existing entry', [
                'instanceId' => $instanceId,
                'key'        => $key
            ]);

        $this->service->setValue($instanceId, $key, $value);
    }

    public function testSetValueCreatesNewEntry(): void
    {
        $instanceId = 'test-instance';
        $key        = 'test-key';
        $value      = 'test-value';

        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'instanceId'  => $instanceId,
                'registryKey' => $key
            ])
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(RegistryEntry::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('[RegistryDomainService] Created new entry', [
                'instanceId' => $instanceId,
                'key'        => $key
            ]);

        $this->service->setValue($instanceId, $key, $value);
    }

    public function testDeleteValueRemovesExistingEntry(): void
    {
        $instanceId = 'test-instance';
        $key        = 'test-key';

        $entry = $this->createMock(RegistryEntry::class);

        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'instanceId'  => $instanceId,
                'registryKey' => $key
            ])
            ->willReturn($entry);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($entry);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('[RegistryDomainService] Deleted entry', [
                'instanceId' => $instanceId,
                'key'        => $key
            ]);

        $result = $this->service->deleteValue($instanceId, $key);

        $this->assertTrue($result);
    }

    public function testDeleteValueReturnsFalseWhenEntryDoesNotExist(): void
    {
        $instanceId = 'test-instance';
        $key        = 'non-existent-key';

        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'instanceId'  => $instanceId,
                'registryKey' => $key
            ])
            ->willReturn(null);

        $this->entityManager->expects($this->never())
            ->method('remove');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $result = $this->service->deleteValue($instanceId, $key);

        $this->assertFalse($result);
    }

    public function testGetAllValuesReturnsArrayOfKeyValuePairs(): void
    {
        $instanceId = 'test-instance';

        $entry1 = $this->createMock(RegistryEntry::class);
        $entry1->method('getRegistryKey')->willReturn('key1');
        $entry1->method('getRegistryValue')->willReturn('value1');

        $entry2 = $this->createMock(RegistryEntry::class);
        $entry2->method('getRegistryKey')->willReturn('key2');
        $entry2->method('getRegistryValue')->willReturn('value2');

        $entries = [$entry1, $entry2];

        $this->repository->expects($this->once())
            ->method('findBy')
            ->with(['instanceId' => $instanceId])
            ->willReturn($entries);

        $result = $this->service->getAllValues($instanceId);

        $this->assertSame([
            'key1' => 'value1',
            'key2' => 'value2'
        ], $result);
    }

    public function testDeleteAllValuesRemovesAllEntries(): void
    {
        $instanceId = 'test-instance';

        $entry1  = $this->createMock(RegistryEntry::class);
        $entry2  = $this->createMock(RegistryEntry::class);
        $entries = [$entry1, $entry2];

        $this->repository->expects($this->once())
            ->method('findBy')
            ->with(['instanceId' => $instanceId])
            ->willReturn($entries);

        $this->entityManager->expects($this->exactly(2))
            ->method('remove')
            ->willReturnCallback(function (mixed $arg) use ($entry1, $entry2): void {
                $this->assertContains($arg, [$entry1, $entry2]);
            });

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('[RegistryDomainService] Deleted all entries for instance', [
                'instanceId' => $instanceId,
                'count'      => 2
            ]);

        $result = $this->service->deleteAllValues($instanceId);

        $this->assertSame(2, $result);
    }
}
