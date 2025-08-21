<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Presentation\Controller;

use App\Authentication\Presentation\Controller\ForwardAuthController;
use App\McpInstances\Domain\Entity\McpInstance;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class ForwardAuthControllerTest extends TestCase
{
    public function testAllowsWhenTokenMatches(): void
    {
        $em     = $this->createMock(EntityManagerInterface::class);
        $repo   = $this->createMock(EntityRepository::class);
        $cache  = $this->createMock(CacheItemPoolInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $em->method('getRepository')->willReturn($repo);
        $instance = $this->createConfiguredMock(McpInstance::class, ['getMcpBearer' => 'expected-token']);
        $repo->method('findOneBy')->with(['instanceSlug' => 'abcd'])->willReturn($instance);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cache->method('getItem')->willReturn($cacheItem);

        $controller = new ForwardAuthController($em, $cache, $logger);
        $server     = [
            'HTTP_HOST'          => 'mcp-abcd.mcp-as-a-service.com',
            'HTTP_AUTHORIZATION' => 'Bearer expected-token',
        ];
        $request = Request::create('/', 'GET', [], [], [], $server);

        $response = $controller->mcpBearerCheckAction($request);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testDeniesWhenTokenMissing(): void
    {
        $em     = $this->createMock(EntityManagerInterface::class);
        $cache  = $this->createMock(CacheItemPoolInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $controller = new ForwardAuthController($em, $cache, $logger);
        $server     = [
            'HTTP_HOST' => 'mcp-abcd.mcp-as-a-service.com',
        ];
        $request = Request::create('/', 'GET', [], [], [], $server);

        $response = $controller->mcpBearerCheckAction($request);
        $this->assertSame(401, $response->getStatusCode());
    }
}
