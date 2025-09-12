<?php

declare(strict_types=1);

namespace App\Tests\Unit\McpInstances\Infrastructure;

use App\McpInstancesConfiguration\Facade\Exception\InvalidInstanceTypesConfigException;
use App\McpInstancesConfiguration\Infrastructure\YamlInstanceTypesConfigProvider;
use PHPUnit\Framework\TestCase;

final class YamlInstanceTypesConfigProviderTest extends TestCase
{
    public function testParsesValidConfig(): void
    {
        $file     = __DIR__ . '/../../../Resources/mcp_instance_types_valid.yaml';
        $provider = new YamlInstanceTypesConfigProvider($file);

        $cfg = $provider->getConfig();

        self::assertArrayHasKey('playwright-v1', $cfg->types);
        self::assertArrayHasKey('_legacy', $cfg->types);

        $pv1 = $cfg->types['playwright-v1'];
        self::assertSame('Playwright MCP v1', $pv1->displayName);
        self::assertSame('Modern Playwright MCP instance with enhanced automation features and improved performance', $pv1->description);
        self::assertSame('maas-mcp-instance-playwright-v1', $pv1->docker->image);
        self::assertArrayHasKey('mcp', $pv1->endpoints);
        self::assertArrayHasKey('vnc', $pv1->endpoints);
        self::assertSame(8080, $pv1->endpoints['mcp']->port);
        self::assertSame('bearer', $pv1->endpoints['mcp']->auth);
        self::assertSame(['/mcp', '/sse'], $pv1->endpoints['mcp']->externalPaths);
        self::assertNotNull($pv1->endpoints['mcp']->health);
        self::assertNotNull($pv1->endpoints['mcp']->health->http);
        self::assertSame('/mcp', $pv1->endpoints['mcp']->health->http->path);
        self::assertSame(500, $pv1->endpoints['mcp']->health->http->acceptStatusLt);
    }

    public function testRejectsMissingMcpEndpoint(): void
    {
        $file     = __DIR__ . '/../../../Resources/mcp_instance_types_invalid_missing_mcp.yaml';
        $provider = new YamlInstanceTypesConfigProvider($file);

        $this->expectException(InvalidInstanceTypesConfigException::class);
        $provider->getConfig();
    }
}
