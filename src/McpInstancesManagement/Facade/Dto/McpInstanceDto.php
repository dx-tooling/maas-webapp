<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Facade\Dto;

use App\McpInstancesManagement\Facade\Enum\ContainerState;
use App\McpInstancesManagement\Facade\Enum\InstanceType;
use DateTimeImmutable;

final readonly class McpInstanceDto
{
    /**
     * @param array<string,string> $userEnvironmentVariables
     */
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
        public array             $userEnvironmentVariables,
    ) {
    }
}
