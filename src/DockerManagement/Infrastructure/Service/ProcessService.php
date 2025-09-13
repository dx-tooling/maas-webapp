<?php

declare(strict_types=1);

namespace App\DockerManagement\Infrastructure\Service;

use App\DockerManagement\Infrastructure\Dto\RunProcessResultDto;
use Symfony\Component\Process\Process;

final readonly class ProcessService implements ProcessServiceInterface
{
    public function runProcess(
        array   $command,
        ?string $cwd = null,
        ?array  $env = null,
        mixed   $input = null,
        ?float  $timeout = 60
    ): RunProcessResultDto {
        $process = new Process($command, null, $env, null, 60);
        $process->run();

        return new RunProcessResultDto(
            $process->getExitCode() ?? 1,
            $process->getOutput(),
            $process->getErrorOutput(),
        );
    }
}
