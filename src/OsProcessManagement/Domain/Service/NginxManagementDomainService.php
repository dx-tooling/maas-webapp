<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Domain\Service;

use App\McpInstances\Facade\Dto\McpInstanceInfoDto;
use App\McpInstances\Facade\McpInstancesFacadeInterface;


readonly class NginxManagementDomainService
{
    public function __construct(
        private McpInstancesFacadeInterface $mcpInstancesFacade,
    ) {

    }

    /**
     * @param McpInstanceInfoDto[] $instanceInfos
     */
    public static function generateNginxConfig(array $instanceInfos): string
    {
        $config = "# Port mappings\n";
        $config .= "map \$instance_id \$backend_port {\n";
        $config .= "    default \"\";\n";
        foreach ($instanceInfos as $instance) {
            $config .= sprintf("    %s \"%d\";\n", $instance->id, $instance->mcpPort);
        }
        $config .= "}\n\n";

        // Generate token validation maps for each instance
        foreach ($instanceInfos as $instance) {
            $config .= sprintf("# Token validation for instance %s\n", $instance->id);
            $config .= sprintf("map \$http_authorization \$is_valid_%s {\n", $instance->id);
            $config .= "    default \"0\";\n";
            $config .= sprintf("    \"Bearer %s\" \"1\";\n", $instance->password);
            $config .= "}\n\n";
        }

        // Generate final validation map
        $config .= "# Final validation map\n";
        $config .= "map \$instance_id \$is_valid {\n";
        $config .= "    default \"0\";\n";
        foreach ($instanceInfos as $instance) {
            $config .= sprintf("    %s \$is_valid_%s;\n", $instance->id, $instance->id);
        }
        $config .= "}\n";

        return $config;
    }

    public function reconfigureAndRestartNginx(): void
    {
        $config = self::generateNginxConfig(
            $this->mcpInstancesFacade->getMcpInstanceInfos(),
        );
        // write config file
        // restart nginx
    }
}
