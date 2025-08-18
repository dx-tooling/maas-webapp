<?php

declare(strict_types=1);

namespace App\DockerManagement\Infrastructure\Service;

use App\McpInstances\Domain\Entity\McpInstance;
use App\McpInstances\Domain\Enum\ContainerState;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;

readonly class ContainerManagementService
{
    public function __construct(
        private LoggerInterface       $logger,
        private ParameterBagInterface $params
    ) {
    }

    private function getProjectDir(): string
    {
        $dir = $this->params->get('kernel.project_dir');
        if (!is_string($dir) || $dir === '') {
            throw new RuntimeException('Invalid kernel.project_dir parameter');
        }

        return $dir;
    }

    /**
     * Return the command prefix to invoke Docker.
     * If the wrapper exists in the project, prefer it via sudo -n; otherwise call docker directly.
     *
     * @return string[]
     */
    private function getDockerInvoker(): array
    {
        $wrapperPath = $this->getProjectDir() . '/bin/docker-cli-wrapper.sh';

        if (is_file($wrapperPath) && is_readable($wrapperPath)) {
            return ['sudo', '-n', $wrapperPath];
        }

        return ['/usr/bin/docker'];
    }

    /**
     * Run a docker command using either the wrapper or docker binary.
     *
     * @param string[] $args
     *
     * @return array{exitCode:int, stdout:string, stderr:string}
     */
    private function runDocker(array $args): array
    {
        $cmd = array_merge($this->getDockerInvoker(), $args);

        $process = new Process($cmd, null, null, null, 60);
        $process->run();

        return [
            'exitCode' => $process->getExitCode() ?? 1,
            'stdout'   => $process->getOutput(),
            'stderr'   => $process->getErrorOutput(),
        ];
    }

    public function createContainer(McpInstance $instance): bool
    {
        $containerName = $instance->getContainerName();
        $instanceSlug  = $instance->getInstanceSlug();

        if (!$containerName || !$instanceSlug) {
            $this->logger->error('[ContainerManagementService] Container name or instance slug not set');

            return false;
        }

        $this->logger->info("[ContainerManagementService] Creating container: {$containerName}");

        $result = $this->buildAndRunDockerRun($instance);

        if ($result['exitCode'] === 0) {
            $this->logger->info("[ContainerManagementService] Container {$containerName} created successfully");

            return true;
        } else {
            $this->logger->error("[ContainerManagementService] Failed to create container {$containerName}: " . ($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']));

            return false;
        }
    }

    public function startContainer(McpInstance $instance): bool
    {
        $containerName = $instance->getContainerName();
        if (!$containerName) {
            return false;
        }

        $this->logger->info("[ContainerManagementService] Starting container: {$containerName}");

        $result = $this->runDocker(['start', $containerName]);

        if ($result['exitCode'] === 0) {
            $this->logger->info("[ContainerManagementService] Container {$containerName} started successfully");

            return true;
        } else {
            $this->logger->error("[ContainerManagementService] Failed to start container {$containerName}: " . ($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']));

            return false;
        }
    }

    public function stopContainer(McpInstance $instance): bool
    {
        $containerName = $instance->getContainerName();
        if (!$containerName) {
            return false;
        }

        $this->logger->info("[ContainerManagementService] Stopping container: {$containerName}");

        $result = $this->runDocker(['stop', $containerName]);

        if ($result['exitCode'] === 0) {
            $this->logger->info("[ContainerManagementService] Container {$containerName} stopped successfully");

            return true;
        } else {
            $this->logger->error("[ContainerManagementService] Failed to stop container {$containerName}: " . ($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']));

            return false;
        }
    }

    public function removeContainer(McpInstance $instance): bool
    {
        $containerName = $instance->getContainerName();
        if (!$containerName) {
            return false;
        }

        $this->logger->info("[ContainerManagementService] Removing container: {$containerName}");

        $result = $this->runDocker(['rm', $containerName]);

        if ($result['exitCode'] === 0) {
            $this->logger->info("[ContainerManagementService] Container {$containerName} removed successfully");

            return true;
        } else {
            $this->logger->error("[ContainerManagementService] Failed to remove container {$containerName}: " . ($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']));

            return false;
        }
    }

    public function restartContainer(McpInstance $instance): bool
    {
        $containerName = $instance->getContainerName();
        if (!$containerName) {
            return false;
        }

        $this->logger->info("[ContainerManagementService] Restarting container: {$containerName}");

        $result = $this->runDocker(['restart', $containerName]);

        if ($result['exitCode'] === 0) {
            $this->logger->info("[ContainerManagementService] Container {$containerName} restarted successfully");

            return true;
        } else {
            $this->logger->error("[ContainerManagementService] Failed to restart container {$containerName}: " . ($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']));

            return false;
        }
    }

    public function getContainerState(McpInstance $instance): ContainerState
    {
        $containerName = $instance->getContainerName();
        if (!$containerName) {
            return ContainerState::ERROR;
        }

        $result = $this->runDocker(['inspect', '--format', '{{.State.Status}}', $containerName]);
        $status = trim($result['stdout']);

        if ($result['exitCode'] !== 0) {
            return ContainerState::ERROR;
        }

        return match ($status) {
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
        $mcpResult = $this->runDocker(['exec', $containerName, 'curl', '-f', 'http://localhost:8080/mcp']);

        // Check noVNC endpoint
        $vncResult = $this->runDocker(['exec', $containerName, 'curl', '-f', 'http://localhost:6080']);

        return $mcpResult['exitCode'] === 0 && $vncResult['exitCode'] === 0;
    }

    /**
     * Build and execute `docker run` for the instance.
     *
     * @return array{exitCode:int, stdout:string, stderr:string}
     */
    private function buildAndRunDockerRun(McpInstance $instance): array
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

        $args = ['run', '-d', '--name', (string) $containerName, '--memory=1g', '--restart=always', '--network=mcp_instances'];

        foreach ($envVars as $env) {
            $args[] = '-e';
            $args[] = $env;
        }

        foreach ($labels as $label) {
            $args[] = '--label';
            $args[] = $label;
        }

        // Wrapper allows only the image name without a tag
        $args[] = 'maas-mcp-instance';

        return $this->runDocker($args);
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
            // TLS handled by Traefik dynamic configuration (no certresolver on routers)
            "traefik.http.services.mcp-{$instanceSlug}.loadbalancer.server.port=8080",

            // MCP ForwardAuth middleware
            "traefik.http.middlewares.mcp-{$instanceSlug}-auth.forwardauth.address=https://app.mcp-as-a-service.com/auth/mcp-bearer-check",
            "traefik.http.routers.mcp-{$instanceSlug}.middlewares=mcp-{$instanceSlug}-auth",

            // VNC router and service
            "traefik.http.routers.vnc-{$instanceSlug}.rule=Host(`vnc-{$instanceSlug}.mcp-as-a-service.com`)",
            "traefik.http.routers.vnc-{$instanceSlug}.entrypoints=websecure",
            // TLS handled by Traefik dynamic configuration (no certresolver on routers)
            "traefik.http.services.vnc-{$instanceSlug}.loadbalancer.server.port=6080"
        ];
    }
}
