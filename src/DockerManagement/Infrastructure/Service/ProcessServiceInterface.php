<?php

declare(strict_types=1);

namespace App\DockerManagement\Infrastructure\Service;

use App\DockerManagement\Infrastructure\Dto\RunProcessResultDto;

interface ProcessServiceInterface
{
    /**
     * @param list<string>               $command
     * @param array<string, string>|null $env
     */
    public function runProcess(
        array   $command,
        ?string $cwd = null,
        ?array  $env = null,
        mixed   $input = null,
        ?float  $timeout = 60
    ): RunProcessResultDto;
}
