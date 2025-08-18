<?php

declare(strict_types=1);

namespace App\DockerManagement\Infrastructure\Service;

use App\McpInstances\Domain\Entity\McpInstance;
use App\McpInstances\Domain\Enum\ContainerState;
use Psr\Log\LoggerInterface;

readonly class ContainerManagementService
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function createContainer(McpInstance $instance): bool
    {
        $containerName = $instance->getContainerName();
        $instanceSlug  = $instance->getInstanceSlug();

        if (!$containerName || !$instanceSlug) {
            $this->logger->error('[ContainerManagementService] Container name or instance slug not set');

            return false;
        }

        $cmd = $this->buildDockerRunCommand($instance);
        $this->logger->info("[ContainerManagementService] Creating container: {$containerName}");

        $result   = shell_exec($cmd . ' 2>&1');
        $exitCode = $this->getLastExitCode();

        if ($exitCode === 0) {
            $this->logger->info("[ContainerManagementService] Container {$containerName} created successfully");

            return true;
        } else {
            $this->logger->error("[ContainerManagementService] Failed to create container {$containerName}: {$result}");

            return false;
        }
    }

    public function startContainer(McpInstance $instance): bool
    {
        $containerName = $instance->getContainerName();
        if (!$containerName) {
            return false;
        }

        $cmd = "docker start {$containerName}";
        $this->logger->info("[ContainerManagementService] Starting container: {$containerName}");

        $result   = shell_exec($cmd . ' 2>&1');
        $exitCode = $this->getLastExitCode();

        if ($exitCode === 0) {
            $this->logger->info("[ContainerManagementService] Container {$containerName} started successfully");

            return true;
        } else {
            $this->logger->error("[ContainerManagementService] Failed to start container {$containerName}: {$result}");

            return false;
        }
    }

    public function stopContainer(McpInstance $instance): bool
    {
        $containerName = $instance->getContainerName();
        if (!$containerName) {
            return false;
        }

        $cmd = "docker stop {$containerName}";
        $this->logger->info("[ContainerManagementService] Stopping container: {$containerName}");

        $result   = shell_exec($cmd . ' 2>&1');
        $exitCode = $this->getLastExitCode();

        if ($exitCode === 0) {
            $this->logger->info("[ContainerManagementService] Container {$containerName} stopped successfully");

            return true;
        } else {
            $this->logger->error("[ContainerManagementService] Failed to stop container {$containerName}: {$result}");

            return false;
        }
    }

    public function removeContainer(McpInstance $instance): bool
    {
        $containerName = $instance->getContainerName();
        if (!$containerName) {
            return false;
        }

        $cmd = "docker rm {$containerName}";
        $this->logger->info("[ContainerManagementService] Removing container: {$containerName}");

        $result   = shell_exec($cmd . ' 2>&1');
        $exitCode = $this->getLastExitCode();

        if ($exitCode === 0) {
            $this->logger->info("[ContainerManagementService] Container {$containerName} removed successfully");

            return true;
        } else {
            $this->logger->error("[ContainerManagementService] Failed to remove container {$containerName}: {$result}");

            return false;
        }
    }

    public function restartContainer(McpInstance $instance): bool
    {
        $containerName = $instance->getContainerName();
        if (!$containerName) {
            return false;
        }

        $cmd = "docker restart {$containerName}";
        $this->logger->info("[ContainerManagementService] Restarting container: {$containerName}");

        $result   = shell_exec($cmd . ' 2>&1');
        $exitCode = $this->getLastExitCode();

        if ($exitCode === 0) {
            $this->logger->info("[ContainerManagementService] Container {$containerName} restarted successfully");

            return true;
        } else {
            $this->logger->error("[ContainerManagementService] Failed to restart container {$containerName}: {$result}");

            return false;
        }
    }

    public function getContainerState(McpInstance $instance): ContainerState
    {
        $containerName = $instance->getContainerName();
        if (!$containerName) {
            return ContainerState::ERROR;
        }

        $cmd      = "docker inspect --format='{{.State.Status}}' {$containerName}";
        $result   = trim((string) shell_exec($cmd . ' 2>/dev/null'));
        $exitCode = $this->getLastExitCode();

        if ($exitCode !== 0) {
            return ContainerState::ERROR;
        }

        return match ($result) {
            'running' => ContainerState::RUNNING,
            'exited', 'stopped' => ContainerState::STOPPED,
            'created' => ContainerState::CREATED,
            default   => ContainerState::ERROR
        };
    }

    public function isContainerHealthy(McpInstance $instance): bool
    {
        $containerName = $instance->getContainerName();
        if (!$containerName) {
            return false;
        }

        // Check if container is running
        if ($this->getContainerState($instance) !== ContainerState::RUNNING) {
            return false;
        }

        // Check MCP endpoint
        $mcpCmd      = "docker exec {$containerName} curl -f http://localhost:8080/mcp";
        $mcpResult   = shell_exec($mcpCmd . ' 2>/dev/null');
        $mcpExitCode = $this->getLastExitCode();

        // Check noVNC endpoint
        $vncCmd      = "docker exec {$containerName} curl -f http://localhost:6080";
        $vncResult   = shell_exec($vncCmd . ' 2>/dev/null');
        $vncExitCode = $this->getLastExitCode();

        return $mcpExitCode === 0 && $vncExitCode === 0;
    }

    private function buildDockerRunCommand(McpInstance $instance): string
    {
        $containerName = $instance->getContainerName();
        $instanceSlug  = $instance->getInstanceSlug();

        $envVars = [
            "INSTANCE_ID={$instanceSlug}",
            "SCREEN_WIDTH={$instance->getScreenWidth()}",
            "SCREEN_HEIGHT={$instance->getScreenHeight()}",
            "COLOR_DEPTH={$instance->getColorDepth()}",
            "VNC_PASSWORD={$instance->getVncPassword()}"
        ];

        $labels = $this->buildTraefikLabels($instance);

        $cmd = 'docker run -d';
        $cmd .= " --name {$containerName}";
        $cmd .= ' --memory=1g';
        $cmd .= ' --restart=always';
        $cmd .= ' --network=mcp_instances';

        foreach ($envVars as $env) {
            $cmd .= " -e \"{$env}\"";
        }

        foreach ($labels as $label) {
            $cmd .= " --label \"{$label}\"";
        }

        $cmd .= ' maas-mcp-instance:latest';

        return $cmd;
    }

    /**
     * @return array<string>
     */
    private function buildTraefikLabels(McpInstance $instance): array
    {
        $instanceSlug = $instance->getInstanceSlug();
        $mcpBearer    = $instance->getMcpBearer();

        return [
            'traefik.enable=true',

            // MCP router and service
            "traefik.http.routers.mcp-{$instanceSlug}.rule=Host(`mcp-{$instanceSlug}.mcp-as-a-service.com`)",
            "traefik.http.routers.mcp-{$instanceSlug}.entrypoints=websecure",
            "traefik.http.routers.mcp-{$instanceSlug}.tls.certresolver=letsencrypt",
            "traefik.http.services.mcp-{$instanceSlug}.loadbalancer.server.port=8080",

            // MCP ForwardAuth middleware
            "traefik.http.middlewares.mcp-{$instanceSlug}-auth.forwardauth.address=https://app.mcp-as-a-service.com/auth/mcp-bearer-check",
            "traefik.http.routers.mcp-{$instanceSlug}.middlewares=mcp-{$instanceSlug}-auth",

            // VNC router and service
            "traefik.http.routers.vnc-{$instanceSlug}.rule=Host(`vnc-{$instanceSlug}.mcp-as-a-service.com`)",
            "traefik.http.routers.vnc-{$instanceSlug}.entrypoints=websecure",
            "traefik.http.routers.vnc-{$instanceSlug}.tls.certresolver=letsencrypt",
            "traefik.http.services.vnc-{$instanceSlug}.loadbalancer.server.port=6080"
        ];
    }

    private function getLastExitCode(): int
    {
        return (int) shell_exec('echo $?');
    }
}
