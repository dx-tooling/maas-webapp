<?php

declare(strict_types=1);

namespace App\McpInstances\Presentation\Dto;

use App\McpInstances\Domain\Dto\ProcessStatusDto;
use App\McpInstances\Domain\Enum\InstanceType;

readonly class DashboardDataDto
{
    public function __construct(
        public ?McpInstanceInfoDto $instance,
        public ?ProcessStatusDto   $processStatus,
        /** @var array<InstanceType> */
        public array               $availableTypes,
    ) {
    }
}
