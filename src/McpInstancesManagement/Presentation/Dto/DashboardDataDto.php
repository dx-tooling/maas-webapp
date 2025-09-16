<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Presentation\Dto;

use App\McpInstancesManagement\Facade\Dto\InstanceStatusDto;
use App\McpInstancesManagement\Facade\Dto\ProcessStatusDto;

final readonly class DashboardDataDto
{
    /**
     * @param array<int,array{value:string,display:string}> $availableTypes
     */
    public function __construct(
        public ?McpInstanceInfoDto $instance,
        public ?ProcessStatusDto   $processStatus,
        public ?InstanceStatusDto  $genericStatus,
        public array               $availableTypes,
    ) {
    }
}
