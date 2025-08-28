<?php

declare(strict_types=1);

namespace App\McpInstances\Presentation\Components;

use App\McpInstances\Domain\Service\McpInstancesDomainService;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(
    name    : 'mcp_instances|presentation|health_overview',
    template: '@mcp_instances.presentation/health_overview.component.html.twig'
)]
final class HealthOverviewComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: false)]
    public string $instanceId;

    public function __construct(private readonly McpInstancesDomainService $domainService)
    {
    }

    /**
     * @return array{
     *   instanceId: string,
     *   processes: array{
     *     xvfb: array<string, mixed>|null,
     *     mcp: array<string, mixed>|null,
     *     vnc: array<string, mixed>|null,
     *     websocket: array<string, mixed>|null
     *   },
     *   allRunning: bool,
     *   containerStatus: array{
     *     containerName: string,
     *     state: string,
     *     healthy: bool,
     *     mcpUp: bool,
     *     noVncUp: bool,
     *     mcpEndpoint: string|null,
     *     vncEndpoint: string|null
     *   }
     * }
     */
    public function getStatus(): array
    {
        return $this->domainService->getProcessStatusForInstance($this->instanceId);
    }
}
