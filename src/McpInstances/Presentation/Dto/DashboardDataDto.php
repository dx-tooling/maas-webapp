<?php

declare(strict_types=1);

namespace App\McpInstances\Presentation\Dto;

use App\McpInstances\Domain\Dto\InstanceStatusDto;
use App\McpInstances\Domain\Dto\ProcessStatusDto;

readonly class DashboardDataDto
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
