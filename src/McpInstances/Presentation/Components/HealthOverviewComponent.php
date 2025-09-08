<?php

declare(strict_types=1);

namespace App\McpInstances\Presentation\Components;

use App\McpInstances\Domain\Dto\ProcessStatusDto;
use App\McpInstances\Presentation\McpInstancesPresentationService;
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

    public function __construct(private readonly McpInstancesPresentationService $presentationService)
    {
    }

    public function getStatus(): ProcessStatusDto
    {
        return $this->presentationService->getProcessStatusForInstance($this->instanceId);
    }
}
