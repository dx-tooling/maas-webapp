<?php

declare(strict_types=1);

namespace App\Tests\Unit\DockerManagement\Infrastructure\Service;

use App\DockerManagement\Infrastructure\Service\ContainerManagementService;
use App\McpInstances\Domain\Entity\McpInstance;
use App\McpInstances\Domain\Enum\ContainerState;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class ContainerManagementServiceTest extends TestCase
{
    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var ParameterBagInterface&MockObject */
    private ParameterBagInterface $params;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->params = $this->createMock(ParameterBagInterface::class);
        $this->params->method('get')->with('kernel.project_dir')->willReturn(__DIR__ . '/../../../../..');
    }

    public function testCreateContainerFailsWhenNamesMissing(): void
    {
        $service  = new ContainerManagementService($this->logger, $this->params);
        $instance = new McpInstance('acc', 1280, 720, 24, 'vnc', 'bearer');

        // Intentionally do not set derived fields; createContainer should log and return false
        $this->assertFalse($service->createContainer($instance));
    }

    public function testDockerRunInvocationUsesWrapperInValidateOnlyMode(): void
    {
        $service  = new ContainerManagementService($this->logger, $this->params);
        $instance = new McpInstance('acc', 1280, 720, 24, 'vncpass', 'bearer');

        // Prepare derived fields so that createContainer proceeds
        // We simulate what generateDerivedFields() does
        $r      = new ReflectionClass($instance);
        $idProp = $r->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($instance, '00000000-0000-0000-0000-000000000abc');
        $instance->generateDerivedFields();

        // Logger may be called multiple times; no strict parameter expectations here
        $this->logger->expects($this->any())->method('info');

        // Force wrapper path usage without sudo and enable validation-only mode so process exits quickly with 0
        putenv('MAAS_WRAPPER_NO_SUDO=1');
        putenv('MAAS_WRAPPER_VALIDATE_ONLY=1');
        putenv('DOCKER_BIN=/bin/docker');

        // Execute; since wrapper runs in validation mode, createContainer should return true (docker run exits 0)
        $this->assertTrue($service->createContainer($instance));

        // Cleanup env changes for isolation
        putenv('MAAS_WRAPPER_NO_SUDO');
        putenv('MAAS_WRAPPER_VALIDATE_ONLY');
        putenv('DOCKER_BIN');
    }

    public function testStartStopRestartRemoveInvokeWrapper(): void
    {
        // Use a fresh logger with expectations on messages
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->any())->method('info');

        $service  = new ContainerManagementService($logger, $this->params);
        $instance = new McpInstance('acc', 1280, 720, 24, 'vncpass', 'bearer');

        $r      = new ReflectionClass($instance);
        $idProp = $r->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($instance, '00000000-0000-0000-0000-000000000def');
        $instance->generateDerivedFields();

        // Env: use wrapper directly and validate-only
        putenv('MAAS_WRAPPER_NO_SUDO=1');
        putenv('MAAS_WRAPPER_VALIDATE_ONLY=1');
        putenv('DOCKER_BIN=/bin/docker');

        $this->assertTrue($service->startContainer($instance));
        $this->assertTrue($service->stopContainer($instance));
        $this->assertTrue($service->restartContainer($instance));
        $this->assertTrue($service->removeContainer($instance));

        // Cleanup env
        putenv('MAAS_WRAPPER_NO_SUDO');
        putenv('MAAS_WRAPPER_VALIDATE_ONLY');
        putenv('DOCKER_BIN');
    }

    public function testGetContainerStateInvokesInspectViaWrapper(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())
            ->method('info')
            ->with($this->stringContains('inspect'));

        $service  = new ContainerManagementService($logger, $this->params);
        $instance = new McpInstance('acc', 1280, 720, 24, 'vncpass', 'bearer');
        $r        = new ReflectionClass($instance);
        $idProp   = $r->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($instance, '00000000-0000-0000-0000-000000000abc');
        $instance->generateDerivedFields();

        putenv('MAAS_WRAPPER_NO_SUDO=1');
        putenv('MAAS_WRAPPER_VALIDATE_ONLY=1');
        putenv('DOCKER_BIN=/bin/docker');

        // In validate-only mode, stdout is not the status, so service returns ERROR
        $this->assertSame(ContainerState::ERROR, $service->getContainerState($instance));

        putenv('MAAS_WRAPPER_NO_SUDO');
        putenv('MAAS_WRAPPER_VALIDATE_ONLY');
        putenv('DOCKER_BIN');
    }

    // Exec path is covered by shell wrapper tests; PHP test focuses on run/start/stop/restart/rm/inspect
}
