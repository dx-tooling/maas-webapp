<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Presentation\Dto;

use DateTimeImmutable;

final readonly class McpInstanceInfoDto
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
        public string            $containerState,
        public string            $instanceType,
        public string            $instanceTypeDisplayName,
        public int               $screenWidth,
        public int               $screenHeight,
        public int               $colorDepth,
        public string            $vncPassword,
        public string            $mcpBearer,
        public ?string           $mcpSubdomain,
        public ?string           $vncSubdomain,
        /** @var list<string> */
        public array             $vncExternalPaths = [],
        /** @var list<string> */
        public array             $mcpExternalPaths = [],
        public array             $userEnvironmentVariables = [],
    ) {
    }
}
