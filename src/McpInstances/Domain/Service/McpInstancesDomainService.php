<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Service;

use App\OsProcessManagement\Facade\OsProcessManagementFacadeInterface;


readonly class McpInstancesDomainService
{
    public function __construct(
        private OsProcessManagementFacadeInterface $osProcessMgmtFacade,
    ) {
    }

    public function createMcpInstance(
        string $accountCoreId,
    ): bool {
        // deny creation if this account code id already has one or more mcp instances

        // generate random display and port numbers and check existing data in mcp_instances to verify
        // that they are all not yet taken; repeat until a set of non-occupied numbers is found

        // then, create and persist the mcp instance entity, and then launch everything using $osProcessMgmtFacade
    }
}
