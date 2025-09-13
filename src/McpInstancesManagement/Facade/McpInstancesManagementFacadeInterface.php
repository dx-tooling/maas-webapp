<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Facade;

use App\McpInstancesManagement\Facade\Dto\McpInstanceDto;

interface McpInstancesManagementFacadeInterface
{
    public function getMcpInstanceById(string $id): ?McpInstanceDto;

    public function getMcpInstanceBySlug(string $slug): ?McpInstanceDto;
}
