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
        $config = "";

        // Generate server blocks for each instance
        foreach ($instanceInfos as $instance) {
            $config .= sprintf("# Server block for instance %s\n", $instance->id);
            $config .= sprintf("server {\n");
            $config .= sprintf("    listen %d;\n", $instance->mcpProxyPort);
            $config .= "    server_name 127.0.0.1;\n\n";

            $config .= "    # Access and error logs\n";
            $config .= sprintf("    access_log /var/log/nginx/mcp-proxy-%s.access.log;\n", $instance->id);
            $config .= sprintf("    error_log /var/log/nginx/mcp-proxy-%s.error.log;\n\n", $instance->id);

            // Bearer token validation
            $config .= "    # Bearer token validation\n";
            $config .= "    if (\$http_authorization !~ \"^Bearer " . $instance->password . "$\") {\n";
            $config .= "        return 401 'Unauthorized';\n";
            $config .= "    }\n\n";

            // Proxy to the MCP server
            $config .= "    # Proxy to MCP server\n";
            $config .= sprintf("    set \$backend_port %d;\n", $instance->mcpPort);
            $config .= sprintf("    location / {\n");
            $config .= "        proxy_pass http://127.0.0.1:\$backend_port;\n";
            $config .= "    }\n";
            $config .= "}\n\n";
        }

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
