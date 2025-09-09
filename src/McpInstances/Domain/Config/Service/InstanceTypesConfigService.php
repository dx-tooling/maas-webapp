<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Config\Service;

use App\McpInstances\Domain\Config\Dto\EndpointConfig;
use App\McpInstances\Domain\Config\Dto\InstanceTypeConfig;
use App\McpInstances\Domain\Enum\InstanceType;
use App\McpInstances\Infrastructure\Config\InstanceTypesConfigProviderInterface;
use RuntimeException;

final readonly class InstanceTypesConfigService implements InstanceTypesConfigServiceInterface
{
    public function __construct(
        private InstanceTypesConfigProviderInterface $provider
    ) {
    }

    public function getTypeConfig(InstanceType $type): ?InstanceTypeConfig
    {
        $config = $this->provider->getConfig();

        return $config->types[$type->value] ?? null;
    }

    /**
     * @return list<string>
     */
    public function buildTraefikLabels(
        InstanceType $type,
        string       $instanceSlug,
        string       $rootDomain,
        string       $forwardAuthUrl
    ): array {
        $typeCfg = $this->getTypeConfig($type);
        if ($typeCfg === null) {
            return [];
        }

        $labels = ['traefik.enable=true'];

        foreach ($typeCfg->endpoints as $endpointId => $ep) {
            $labels = array_merge(
                $labels,
                $this->labelsForEndpoint(
                    $endpointId,
                    $ep,
                    $instanceSlug,
                    $rootDomain,
                    $forwardAuthUrl
                )
            );
        }

        if (!array_is_list($labels)) {
            throw new RuntimeException('Labels must be a list');
        }

        return $labels;
    }

    /**
     * @return string[]
     */
    private function labelsForEndpoint(
        string         $endpointId,
        EndpointConfig $ep,
        string         $slug,
        string         $rootDomain,
        string         $forwardAuthUrl
    ): array {
        $host    = $endpointId . '-' . $slug . '.' . $rootDomain;
        $router  = $endpointId . '-' . $slug;
        $service = $endpointId . '-' . $slug;

        $labels   = [];
        $labels[] = 'traefik.http.routers.' . $router . '.rule=Host(`' . $host . '`)';
        $labels[] = 'traefik.http.routers.' . $router . '.entrypoints=websecure';
        $labels[] = 'traefik.http.routers.' . $router . '.tls=true';
        $labels[] = 'traefik.http.routers.' . $router . '.service=' . $service;
        $labels[] = 'traefik.http.services.' . $service . '.loadbalancer.server.port=' . $ep->port;

        // Context header middleware
        $labels[] = 'traefik.http.middlewares.ctx-' . $slug . '.headers.customrequestheaders.X-MCP-Instance=' . $slug;

        $middlewares = ['ctx-' . $slug];
        if ($ep->auth === 'bearer' || $endpointId === 'mcp') {
            // ForwardAuth middleware
            $labels[]      = 'traefik.http.middlewares.mcp-' . $slug . '-auth.forwardauth.address=' . $forwardAuthUrl;
            $labels[]      = 'traefik.http.middlewares.mcp-' . $slug . '-auth.forwardauth.authRequestHeaders=Authorization,X-MCP-Instance';
            $middlewares[] = 'mcp-' . $slug . '-auth';
        }

        $labels[] = 'traefik.http.routers.' . $router . '.middlewares=' . implode(',', $middlewares);

        return $labels;
    }
}
