<?php

declare(strict_types=1);

namespace App\DockerManagement\Infrastructure\Dto;

final readonly class RunProcessResultDto
{
    public function __construct(
        public int    $exitCode,
        public string $stdout,
        public string $stderr,
    ) {
    }
}
