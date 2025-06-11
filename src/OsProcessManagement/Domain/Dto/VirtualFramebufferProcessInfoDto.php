<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Domain\Dto;

readonly class VirtualFramebufferProcessInfoDto
{
    public function __construct(
        public int    $pid,
        public float  $percentCpuUsage,
        public float  $percentMemoryUsage,
        public int    $displayNumber,
        public string $commandLine
    ) {
    }
}
