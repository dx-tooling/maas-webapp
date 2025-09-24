<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Presentation\Controller;

use App\Authentication\Presentation\Controller\ForwardAuthController;
use App\McpInstancesManagement\Facade\Dto\McpInstanceDto;
use App\McpInstancesManagement\Facade\Enum\ContainerState;
use App\McpInstancesManagement\Facade\Enum\InstanceType;
use App\McpInstancesManagement\Facade\McpInstancesManagementFacadeInterface;
use EnterpriseToolingForSymfony\SharedBundle\DateAndTime\Service\DateAndTimeService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class ForwardAuthControllerTest extends TestCase
{
    public function testAllowsWhenTokenMatches(): void
    {
        $cache                     = $this->createMock(CacheItemPoolInterface::class);
        $logger                    = $this->createMock(LoggerInterface::class);
        $instancesManagementFacade = $this->createMock(McpInstancesManagementFacadeInterface::class);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')
                  ->willReturn(false);
        $cache->method('getItem')
              ->willReturn($cacheItem);

        $instancesManagementFacade
            ->method('getMcpInstanceBySlug')
            ->willReturn(
                new McpInstanceDto(
                    'mcp-abcd-id',
                    DateAndTimeService::getDateTimeImmutable(),
                    '',
                    'mcp-abcd',
                    '',
                    ContainerState::RUNNING,
                    InstanceType::PLAYWRIGHT_V1,
                    1280,
                    720,
                    24,
                    '',
                    'expected-token',
                    '',
                    '',
                    []
                )
            );

        $unitUnderTest = new ForwardAuthController(
            $cache,
            $logger,
            $instancesManagementFacade
        );
        $server = [
            'HTTP_HOST'          => 'mcp-abcd.mcp-as-a-service.com',
            'HTTP_AUTHORIZATION' => 'Bearer expected-token',
        ];
        $request = Request::create('/', 'GET', [], [], [], $server);

        $response = $unitUnderTest->mcpBearerCheckAction($request);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testDeniesWhenTokenMissing(): void
    {
        $cache                     = $this->createMock(CacheItemPoolInterface::class);
        $logger                    = $this->createMock(LoggerInterface::class);
        $instancesManagementFacade = $this->createMock(McpInstancesManagementFacadeInterface::class);

        $unitUnderTest = new ForwardAuthController(
            $cache,
            $logger,
            $instancesManagementFacade
        );
        $server = [
            'HTTP_HOST' => 'mcp-abcd.mcp-as-a-service.com',
        ];
        $request = Request::create('/', 'GET', [], [], [], $server);

        $response = $unitUnderTest->mcpBearerCheckAction($request);
        $this->assertSame(401, $response->getStatusCode());
    }
}
