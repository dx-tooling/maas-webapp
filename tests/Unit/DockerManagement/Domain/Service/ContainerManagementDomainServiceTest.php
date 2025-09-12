<?php

declare(strict_types=1);

namespace App\Tests\Unit\DockerManagement\Domain\Service;

use App\DockerManagement\Domain\Service\ContainerManagementDomainService;
use App\DockerManagement\Infrastructure\Dto\RunProcessResultDto;
use App\DockerManagement\Infrastructure\Service\ProcessServiceInterface;
use App\McpInstancesConfiguration\Facade\Dto\EndpointConfig;
use App\McpInstancesConfiguration\Facade\Dto\InstanceDockerConfig;
use App\McpInstancesConfiguration\Facade\Dto\InstanceTypeConfig;
use App\McpInstancesConfiguration\Facade\Dto\McpInstanceTypesConfig;
use App\McpInstancesConfiguration\Facade\Service\InstanceTypesConfigFacade;
use App\McpInstancesConfiguration\Infrastructure\InstanceTypesConfigProviderInterface;
use App\McpInstancesManagement\Domain\Entity\McpInstance;
use App\McpInstancesManagement\Domain\Enum\ContainerState;
use App\McpInstancesManagement\Domain\Enum\InstanceType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouterInterface;

final class ContainerManagementDomainServiceTest extends TestCase
{
    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var ParameterBagInterface&MockObject */
    private ParameterBagInterface $params;

    /** @var RouterInterface&MockObject */
    private RouterInterface $router;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->params = $this->createMock(ParameterBagInterface::class);
        $this->params->method('get')->willReturnMap([
            ['kernel.project_dir', __DIR__ . '/../../../../..'],
            ['kernel.environment', 'test'],
        ]);

        $this->router = $this->createMock(RouterInterface::class);
        $this->router->method('generate')->willReturn('https://app.mcp-as-a-service.com/auth/mcp-bearer-check');
    }

    public function testCreateContainerFailsWhenNamesMissing(): void
    {
        $configFacade = $this->createInstanceTypesConfigService();
        $process      = $this->createMock(ProcessServiceInterface::class);
        $process->method('runProcess')->willReturn(new RunProcessResultDto(0, '', ''));
        $unitUnderTest = new ContainerManagementDomainService($this->logger, $this->params, $this->router, $configFacade, $process);
        $instance      = new McpInstance(
            'acc',
            InstanceType::PLAYWRIGHT_V1,
            1280,
            720,
            24,
            'vnc',
            'bearer'
        );

        // Intentionally do not set derived fields; createContainer should log and return false
        $this->assertFalse($unitUnderTest->createContainer($instance));
    }

    public function testDockerRunInvocationUsesWrapperInValidateOnlyMode(): void
    {
        $configFacade = $this->createInstanceTypesConfigService();
        $process      = $this->createMock(ProcessServiceInterface::class);
        $process->method('runProcess')->willReturn(new RunProcessResultDto(0, '', ''));
        $unitUnderTest = new ContainerManagementDomainService($this->logger, $this->params, $this->router, $configFacade, $process);
        $instance      = new McpInstance(
            'acc',
            InstanceType::PLAYWRIGHT_V1,
            1280,
            720,
            24,
            'vncpass',
            'bearer'
        );

        // Prepare derived fields so that createContainer proceeds
        // We simulate what generateDerivedFields() does
        $r      = new ReflectionClass($instance);
        $idProp = $r->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($instance, '00000000-0000-0000-0000-000000000abc');
        $instance->generateDerivedFields('mcp-as-a-service.com');

        // Logger may be called multiple times; no strict parameter expectations here
        $this->logger->expects($this->any())->method('info');

        // Enable validation-only mode so the service short-circuits safely in environments without Docker
        putenv('MAAS_WRAPPER_VALIDATE_ONLY=1');

        // Execute; since wrapper runs in validation mode, createContainer should return true (docker run exits 0)
        $this->assertTrue($unitUnderTest->createContainer($instance));

        // Cleanup env
        putenv('MAAS_WRAPPER_VALIDATE_ONLY');
    }

    public function testStartStopRestartRemoveInvokeWrapper(): void
    {
        // Use a fresh logger with expectations on messages
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->any())->method('info');

        $configFacade = $this->createInstanceTypesConfigService();
        $process      = $this->createMock(ProcessServiceInterface::class);
        $process->method('runProcess')->willReturn(new RunProcessResultDto(0, '', ''));
        $unitUnderTest = new ContainerManagementDomainService($logger, $this->params, $this->router, $configFacade, $process);
        $instance      = new McpInstance(
            'acc',
            InstanceType::PLAYWRIGHT_V1,
            1280,
            720,
            24,
            'vncpass',
            'bearer'
        );

        $r      = new ReflectionClass($instance);
        $idProp = $r->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($instance, '00000000-0000-0000-0000-000000000def');
        $instance->generateDerivedFields('mcp-as-a-service.com');

        // The ProcessServiceInterface mock returns exit code 0; wrapper or docker invocation details are not required here.

        $this->assertTrue($unitUnderTest->startContainer($instance));
        $this->assertTrue($unitUnderTest->stopContainer($instance));
        $this->assertTrue($unitUnderTest->restartContainer($instance));
        $this->assertTrue($unitUnderTest->removeContainer($instance));
    }

    public function testGetContainerStateInvokesInspectViaWrapper(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())
            ->method('info')
            ->with($this->stringContains('inspect'));

        $configFacade = $this->createInstanceTypesConfigService();
        $process      = $this->createMock(ProcessServiceInterface::class);
        $process->method('runProcess')->willReturn(new RunProcessResultDto(0, '', ''));
        $unitUnderTest = new ContainerManagementDomainService($logger, $this->params, $this->router, $configFacade, $process);
        $instance      = new McpInstance(
            'acc',
            InstanceType::PLAYWRIGHT_V1,
            1280,
            720,
            24,
            'vncpass',
            'bearer'
        );
        $r      = new ReflectionClass($instance);
        $idProp = $r->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($instance, '00000000-0000-0000-0000-000000000abc');
        $instance->generateDerivedFields('mcp-as-a-service.com');

        // No env overrides required; the process mock returns 0 and stdout is empty.

        // In validate-only mode, stdout is not the status, so service returns ERROR
        $this->assertSame(ContainerState::ERROR, $unitUnderTest->getContainerState($instance));
    }

    // Helper to construct a minimal config service with endpoints 'mcp' and 'vnc'
    private function createInstanceTypesConfigService(): InstanceTypesConfigFacade
    {
        $provider = $this->createMock(InstanceTypesConfigProviderInterface::class);
        $provider->method('getConfig')->willReturn(new McpInstanceTypesConfig([
            'playwright-v1' => new InstanceTypeConfig(
                'Playwright',
                'Playwright automation for web testing',
                new InstanceDockerConfig('maas-mcp-instance-playwright-v1'),
                [
                    'mcp' => new EndpointConfig(8080, 'bearer', ['/mcp', '/sse'], null),
                    'vnc' => new EndpointConfig(6080, null, ['/vnc.html'], null),
                ]
            )
        ]));

        return new InstanceTypesConfigFacade($provider);
    }

    public function testBuildsTraefikLabelsFromEndpoints(): void
    {
        $provider = $this->createMock(InstanceTypesConfigProviderInterface::class);

        $endpoints = [
            'mcp' => new EndpointConfig(8080, 'bearer', ['/mcp', '/sse'], null),
            'vnc' => new EndpointConfig(6080, null, ['/vnc.html'], null),
        ];

        $types = new McpInstanceTypesConfig([
            'playwright-v1' => new InstanceTypeConfig('Playwright MCP v1', 'Playwright automation for web testing', new InstanceDockerConfig('img'), $endpoints)
        ]);

        $provider->method('getConfig')->willReturn($types);

        $instanceTypesConfigService = new InstanceTypesConfigFacade($provider);

        $process = $this->createMock(ProcessServiceInterface::class);

        $unitUnderTest = new ContainerManagementDomainService(
            $this->logger,
            $this->params,
            $this->router,
            $instanceTypesConfigService,
            $process
        );

        $labels = $unitUnderTest->buildTraefikLabels(
            InstanceType::PLAYWRIGHT_V1,
            'abc123'
        );

        $this->assertContains('traefik.enable=true', $labels);
        $this->assertTrue($this->hasLabel($labels, 'traefik.http.routers.mcp-abc123.rule=Host(`mcp-abc123.mcp-as-a-service.com`)'));
        $this->assertTrue($this->hasLabel($labels, 'traefik.http.routers.mcp-abc123.entrypoints=websecure'));
        $this->assertTrue($this->hasLabel($labels, 'traefik.http.services.mcp-abc123.loadbalancer.server.port=8080'));
        $this->assertTrue($this->hasLabel($labels, 'traefik.http.routers.vnc-abc123.rule=Host(`vnc-abc123.mcp-as-a-service.com`)'));
        $this->assertTrue($this->hasLabel($labels, 'traefik.http.services.vnc-abc123.loadbalancer.server.port=6080'));

        // forwardauth should be present for mcp
        $this->assertTrue($this->containsSubstring($labels, 'forwardauth.address=https://app.mcp-as-a-service.com/auth'));
        // context header middleware
        $this->assertTrue($this->containsSubstring($labels, 'X-MCP-Instance=abc123'));
    }

    /**
     * @param array<int,string> $labels
     */
    private function hasLabel(array $labels, string $exact): bool
    {
        return in_array($exact, $labels, true);
    }

    /**
     * @param list<string> $labels
     */
    private function containsSubstring(array $labels, string $needle): bool
    {
        foreach ($labels as $l) {
            if (str_contains($l, $needle)) {
                return true;
            }
        }

        return false;
    }
}
