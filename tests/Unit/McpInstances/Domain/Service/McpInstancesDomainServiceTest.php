<?php

declare(strict_types=1);

namespace App\Tests\Unit\McpInstances\Domain\Service;

use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\DockerManagement\Facade\DockerManagementFacadeInterface;
use App\McpInstancesManagement\Domain\Entity\McpInstance;
use App\McpInstancesManagement\Domain\Enum\ContainerState;
use App\McpInstancesManagement\Domain\Enum\InstanceType;
use App\McpInstancesManagement\Domain\Service\McpInstancesDomainService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class McpInstancesDomainServiceTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var EntityRepository<McpInstance>&MockObject */
    private EntityRepository $repo;

    /** @var DockerManagementFacadeInterface&MockObject */
    private DockerManagementFacadeInterface $dockerFacade;

    private McpInstancesDomainService $service;

    protected function setUp(): void
    {
        $this->em           = $this->createMock(EntityManagerInterface::class);
        $this->repo         = $this->createMock(EntityRepository::class);
        $this->dockerFacade = $this->createMock(DockerManagementFacadeInterface::class);

        $this->em->method('getRepository')
                 ->willReturn($this->repo);

        $this->service = new McpInstancesDomainService($this->em, $this->dockerFacade);
    }

    public function testCreateMcpInstanceSuccessCreatesContainerAndSetsRunning(): void
    {
        $accountId = 'account-uuid-123';

        // No existing instance
        $this->repo->method('findOneBy')
                   ->with(['accountCoreId' => $accountId])
                   ->willReturn(null);

        // Persist/flush are called; we do not assert exact counts to keep the test resilient
        $this->em->expects($this->atLeastOnce())
                 ->method('persist');
        $this->em->expects($this->atLeastOnce())
                 ->method('flush');

        // Docker facade returns success
        $this->dockerFacade
            ->expects($this->once())
            ->method('createAndStartContainer')
            ->with($this->isInstanceOf(McpInstance::class))
            ->willReturn(true);

        $instance = $this->service->createMcpInstance($accountId);
        $this->assertSame(ContainerState::RUNNING, $instance->getContainerState());
    }

    public function testCreateMcpInstanceFailureRemovesEntityAndThrows(): void
    {
        $accountId = 'account-uuid-456';

        $this->repo->method('findOneBy')
                   ->with(['accountCoreId' => $accountId])
                   ->willReturn(null);

        // Track that remove is called when docker creation fails
        $this->em->expects($this->atLeastOnce())
                 ->method('persist');
        $this->em->expects($this->atLeastOnce())
                 ->method('flush');
        $this->em->expects($this->once())
                 ->method('remove')
                 ->with($this->isInstanceOf(McpInstance::class));

        $this->dockerFacade
            ->expects($this->once())
            ->method('createAndStartContainer')
            ->with($this->isInstanceOf(McpInstance::class))
            ->willReturn(false);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Failed to create Docker container for MCP instance.');

        $this->service->createMcpInstance($accountId);
    }

    public function testStopAndRemoveCallsDockerAndRemovesEntity(): void
    {
        $accountId = 'account-uuid-789';
        $existing  = new McpInstance(
            $accountId,
            InstanceType::PLAYWRIGHT_V1,
            1280,
            720,
            24,
            'vncpass',
            'bearer'
        );

        $this->repo->method('findOneBy')
                   ->with(['accountCoreId' => $accountId])
                   ->willReturn($existing);

        $this->dockerFacade
            ->expects($this->once())
            ->method('stopAndRemoveContainer')
            ->with($existing)
            ->willReturn(true);

        $this->em->expects($this->once())
                 ->method('remove')
                 ->with($existing);
        $this->em->expects($this->atLeastOnce())
                 ->method('flush');

        $this->service->stopAndRemoveMcpInstance($accountId);
    }

    public function testRestartMcpInstanceUpdatesStateOnSuccess(): void
    {
        $instanceId = 'instance-uuid-1';
        $existing   = new McpInstance(
            'acc',
            InstanceType::PLAYWRIGHT_V1,
            1280,
            720,
            24,
            'vncpass',
            'bearer'
        );

        $this->repo->method('find')
                   ->with($instanceId)
                   ->willReturn($existing);

        $this->dockerFacade
            ->expects($this->once())
            ->method('restartContainer')
            ->with($existing)
            ->willReturn(true);

        $this->em->expects($this->atLeastOnce())
                 ->method('flush');

        $result = $this->service->restartMcpInstance($instanceId);

        $this->assertTrue($result);
        $this->assertSame(ContainerState::RUNNING, $existing->getContainerState());
    }

    public function testRestartMcpInstanceSetsErrorOnFailure(): void
    {
        $instanceId = 'instance-uuid-2';
        $existing   = new McpInstance(
            'acc',
            InstanceType::PLAYWRIGHT_V1,
            1280,
            720,
            24,
            'vncpass',
            'bearer'
        );

        $this->repo->method('find')
                   ->with($instanceId)
                   ->willReturn($existing);

        $this->dockerFacade
            ->expects($this->once())
            ->method('restartContainer')
            ->with($existing)
            ->willReturn(false);

        $this->em->expects($this->atLeastOnce())
                 ->method('flush');

        $result = $this->service->restartMcpInstance($instanceId);

        $this->assertFalse($result);
        $this->assertSame(ContainerState::ERROR, $existing->getContainerState());
    }

    public function testGetMcpInstanceInfosForAccountDelegatesToRepo(): void
    {
        $em          = $this->createMock(EntityManagerInterface::class);
        $repo        = $this->createMock(EntityRepository::class);
        $docker      = $this->createMock(DockerManagementFacadeInterface::class);
        $domain      = new McpInstancesDomainService($em, $docker);
        $accountInfo = new AccountCoreInfoDto('acc-id');

        $em->method('getRepository')->willReturn($repo);
        $inst = new McpInstance(
            'acc-id',
            InstanceType::PLAYWRIGHT_V1,
            1280,
            720,
            24,
            'v',
            'b'
        );
        $repo->method('findBy')->with(['accountCoreId' => 'acc-id'])->willReturn([$inst]);

        $infos = $domain->getMcpInstanceInfosForAccount($accountInfo);

        $this->assertCount(1, $infos);
        $this->assertSame($inst->getVncPassword(), $infos[0]->getVncPassword());
    }
}
