<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Facade;

use App\McpInstancesManagement\Domain\Entity\McpInstance as McpInstanceEntity;
use App\McpInstancesManagement\Facade\Dto\McpInstanceDto;

interface McpInstancesManagementFacadeInterface
{
    public function toDto(McpInstanceEntity $entity): McpInstanceDto;

    public function getById(string $id): ?McpInstanceDto;
}
