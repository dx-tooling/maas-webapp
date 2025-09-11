<?php

declare(strict_types=1);

namespace App\Tests\Unit\McpInstances\Domain\Config\Service;

use App\McpInstancesConfiguration\Domain\Dto\EndpointConfig;
use App\McpInstancesConfiguration\Domain\Dto\InstanceDockerConfig;
use App\McpInstancesConfiguration\Domain\Dto\InstanceTypeConfig;
use App\McpInstancesConfiguration\Domain\Dto\McpInstanceTypesConfig;
use App\McpInstancesConfiguration\Domain\Service\InstanceTypesConfigService;
use App\McpInstancesConfiguration\Infrastructure\InstanceTypesConfigProviderInterface;
use App\McpInstancesManagement\Domain\Enum\InstanceType;
use PHPUnit\Framework\TestCase;

final class InstanceTypesConfigServiceTest extends TestCase
{
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

        $svc = new InstanceTypesConfigService($provider);

        $labels = $svc->buildTraefikLabels(InstanceType::PLAYWRIGHT_V1, 'abc123', 'example.com', 'https://app.example.com/auth');

        $this->assertContains('traefik.enable=true', $labels);
        $this->assertTrue($this->hasLabel($labels, 'traefik.http.routers.mcp-abc123.rule=Host(`mcp-abc123.example.com`)'));
        $this->assertTrue($this->hasLabel($labels, 'traefik.http.routers.mcp-abc123.entrypoints=websecure'));
        $this->assertTrue($this->hasLabel($labels, 'traefik.http.services.mcp-abc123.loadbalancer.server.port=8080'));
        $this->assertTrue($this->hasLabel($labels, 'traefik.http.routers.vnc-abc123.rule=Host(`vnc-abc123.example.com`)'));
        $this->assertTrue($this->hasLabel($labels, 'traefik.http.services.vnc-abc123.loadbalancer.server.port=6080'));

        // forwardauth should be present for mcp
        $this->assertTrue($this->containsSubstring($labels, 'forwardauth.address=https://app.example.com/auth'));
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
