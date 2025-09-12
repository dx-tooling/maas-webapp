<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Facade\Dto;

use App\McpInstancesManagement\Facade\ContainerState;
use App\McpInstancesManagement\Facade\InstanceType;
use DateTimeImmutable;

final readonly class McpInstanceDto
{
    public function __construct(
        public string            $id,
        public DateTimeImmutable $createdAt,
        public string            $accountCoreId,
        public ?string           $instanceSlug,
        public ?string           $containerName,
        public ContainerState    $containerState,
        public InstanceType      $instanceType,
        public int               $screenWidth,
        public int               $screenHeight,
        public int               $colorDepth,
        public string            $vncPassword,
        public string            $mcpBearer,
        public ?string           $mcpSubdomain,
        public ?string           $vncSubdomain,
    ) {
    }
}
