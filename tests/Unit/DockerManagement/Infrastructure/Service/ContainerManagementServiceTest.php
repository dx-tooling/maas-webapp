<?php

declare(strict_types=1);

namespace App\Tests\Unit\DockerManagement\Infrastructure\Service;

use App\DockerManagement\Infrastructure\Service\ContainerManagementService;
use App\McpInstances\Domain\Entity\McpInstance;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
}
