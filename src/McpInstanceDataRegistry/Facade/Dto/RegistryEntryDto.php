<?php

declare(strict_types=1);

namespace App\McpInstanceDataRegistry\Facade\Dto;

use DateTimeImmutable;

final readonly class RegistryEntryDto
{
    public function __construct(
        public string            $id,
        public string            $instanceId,
        public string            $key,
        public string            $value,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt
    ) {
    }
}
