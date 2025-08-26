<?php

declare(strict_types=1);

namespace App\McpInstances\Facade\Dto;

use DateTimeImmutable;

readonly class McpInstanceAdminOverviewDto
{
    public function __construct(
        public string            $instanceId,
        public string            $instanceSlug,
        public string            $containerName,
        public string            $containerState,
        public DateTimeImmutable $instanceCreatedAt,
        public string            $accountId,
        public string            $accountEmail,
        public DateTimeImmutable $accountCreatedAt,
        /** @var string[] $accountRoles */
        public array             $accountRoles,
        public bool              $isHealthy,
        public ?string           $mcpEndpoint,
        public ?string           $vncEndpoint,
        public int               $screenWidth,
        public int               $screenHeight,
        public int               $colorDepth,
    ) {
    }
}
