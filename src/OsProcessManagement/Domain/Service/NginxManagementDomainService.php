<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Domain\Service;

use App\McpInstances\Facade\Dto\McpInstanceInfoDto;

readonly class NginxManagementDomainService
{
    /**
     * @param array<McpInstanceInfoDto> $instanceInfos
     */
    public static function generateNginxConfig(array $instanceInfos): string
    {
        $config = "# Port mappings\n";
        $config .= "map \$instance_id \$backend_port {\n";
        $config .= "    default \"\";\n";
        foreach ($instanceInfos as $instance) {
            $sanitizedId = str_replace('-', '', $instance->id);
            $config .= sprintf("    %s \"%d\";\n", $sanitizedId, $instance->mcpPort);
        }
        $config .= "}\n\n";

        // Generate token validation maps for each instance
        foreach ($instanceInfos as $instance) {
            $sanitizedId = str_replace('-', '', $instance->id);
            $config .= sprintf("# Token validation for instance %s\n", $instance->id);
            $config .= sprintf("map \$http_authorization \$is_valid_%s {\n", $sanitizedId);
            $config .= "    default \"0\";\n";
            $config .= sprintf("    \"Bearer %s\" \"1\";\n", $instance->password);
            $config .= "}\n\n";
        }

        // Generate basic auth validation maps for each instance
        foreach ($instanceInfos as $instance) {
            $sanitizedId = str_replace('-', '', $instance->id);
            $username = 'user' . $sanitizedId;
            $basicAuth = base64_encode($username . ':' . $instance->password);
            $config .= sprintf("# Basic Auth validation for instance %s\n", $instance->id);
            $config .= sprintf("map \$http_authorization \$is_valid_basic_%s {\n", $sanitizedId);
            $config .= "    default \"0\";\n";
            $config .= sprintf("    \"Basic %s\" \"1\";\n", $basicAuth);
            $config .= "}\n\n";
        }

        // Generate final validation map
        $config .= "# Final validation map\n";
        $config .= "map \$instance_id \$is_valid {\n";
        $config .= "    default \"0\";\n";
        foreach ($instanceInfos as $instance) {
            $sanitizedId = str_replace('-', '', $instance->id);
            $config .= sprintf("    %s \$is_valid_%s;\n", $sanitizedId, $sanitizedId);
        }
        $config .= "}\n\n";

        // Generate final basic auth validation map
        $config .= "# Final basic auth validation map\n";
        $config .= "map \$instance_id \$is_valid_basic {\n";
        $config .= "    default \"0\";\n";
        foreach ($instanceInfos as $instance) {
            $sanitizedId = str_replace('-', '', $instance->id);
            $config .= sprintf("    %s \$is_valid_basic_%s;\n", $sanitizedId, $sanitizedId);
        }
        $config .= "}\n";

        return $config;
    }

    /**
     * @param array<McpInstanceInfoDto> $mcpInstanceInfos
     */
    public function reconfigureAndRestartNginx(array $mcpInstanceInfos): void
    {
        $config = self::generateNginxConfig($mcpInstanceInfos);
        // write config file
        // restart nginx
    }
}
