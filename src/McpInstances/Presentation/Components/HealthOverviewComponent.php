<?php

declare(strict_types=1);

namespace App\McpInstances\Presentation\Components;

use App\McpInstances\Facade\McpInstancesFacadeInterface;
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

    public function __construct(private readonly McpInstancesFacadeInterface $facade)
    {
    }

    /**
     * @return array{
     *   processes: array{
     *     xvfb: array<string,mixed>|null,
     *     mcp: array<string,mixed>|null,
     *     vnc: array<string,mixed>|null,
     *     websocket: array<string,mixed>|null
     *   },
     *   allRunning: bool,
     *   containerStatus: array<string,mixed>
     * }
     */
    public function getStatus(): array
    {
        return $this->facade->getProcessStatusForInstance($this->instanceId);
    }
}
