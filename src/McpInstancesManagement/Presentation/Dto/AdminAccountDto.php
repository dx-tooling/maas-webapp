<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Presentation\Dto;

use DateTimeImmutable;

readonly class AdminAccountDto
{
    public function __construct(
        public string            $id,
        public string            $email,
        /** @var array<string> */
        public array             $roles,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
