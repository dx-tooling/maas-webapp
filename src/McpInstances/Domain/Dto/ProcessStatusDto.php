<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Dto;

readonly class ProcessStatusDto
{
    public function __construct(
        public string                    $instanceId,
        public ServiceStatusDto          $processes,
        public bool                      $allRunning,
        public ProcessStatusContainerDto $containerStatus,
    ) {
    }
}
