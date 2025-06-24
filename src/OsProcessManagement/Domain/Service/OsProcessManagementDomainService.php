<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Domain\Service;

use App\McpInstances\Domain\Entity\McpInstance;
use App\OsProcessManagement\Domain\Dto\PlaywrightMcpProcessInfoDto;
use App\OsProcessManagement\Domain\Dto\VirtualFramebufferProcessInfoDto;
use App\OsProcessManagement\Domain\Dto\VncServerProcessInfoDto;
use App\OsProcessManagement\Domain\Dto\VncWebsocketProcessInfoDto;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

readonly class OsProcessManagementDomainService
{
    public function __construct(
        private LoggerInterface        $logger,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function launchVirtualFramebuffer(
        int $displayNumber,
        int $screenWidth,
        int $screenHeight,
        int $colorDepth
    ): bool {
        $cmd = sprintf(
            'Xvfb :%d -screen 0 %dx%dx%d > /var/tmp/xvfb.%d.log 2>&1 &',
            $displayNumber,
            $screenWidth,
            $screenHeight,
            $colorDepth,
            $displayNumber
        );
        shell_exec($cmd);

        return true;
    }

    public function stopVirtualFramebuffer(
        int $displayNumber
    ): bool {
        // Find and kill the Xvfb process
        $cmd = "ps aux | grep 'Xvfb :$displayNumber' | grep -v grep | awk '{print $2}'";
        $pid = (int) shell_exec($cmd);

        if ($pid > 0) {
            shell_exec("kill -9 $pid");
        }

        return true;
    }

    public function restartVirtualFramebuffer(
        int $displayNumber
    ): bool {
        // Extract screen parameters from the running process
        $cmd    = "ps aux | grep 'Xvfb :$displayNumber' | grep -v grep";
        $output = shell_exec($cmd);

        if (!$output) {
            $this->logger->warning("[restartVirtualFramebuffer] No running Xvfb process found for display :$displayNumber");

            return false;
        }

        // Parse the command line to extract screen parameters
        if (preg_match('/Xvfb :(\d+) -screen 0 (\d+)x(\d+)x(\d+)/', $output, $matches)) {
            $displayNumber = (int) $matches[1];
            $screenWidth   = (int) $matches[2];
            $screenHeight  = (int) $matches[3];
            $colorDepth    = (int) $matches[4];

            // Stop the process
            $this->stopVirtualFramebuffer($displayNumber);

            // Wait a moment for the process to fully stop
            sleep(1);

            // Start the process again with the same parameters
            return $this->launchVirtualFramebuffer($displayNumber, $screenWidth, $screenHeight, $colorDepth);
        }

        $this->logger->error('[restartVirtualFramebuffer] Could not parse Xvfb command line parameters');

        return false;
    }

    public function launchPlaywrightMcp(
        int $port,
        int $displayNumber
    ): bool {
        $cmdLine = "/usr/bin/env bash /var/www/prod/maas-webapp/bin/launch-playwright-mcp.sh $displayNumber $port";
        $this->logger->info("[launchPlaywrightMcp] Running command line: '$cmdLine'.");
        shell_exec($cmdLine);

        return true;
    }

    public function stopPlaywrightMcp(
        int $port
    ): bool {
        // Find the main npm process that started the MCP server
        $cmd = "ps aux | grep 'npm exec @playwright/mcp@latest --port $port' | grep -v grep | awk '{print $2}'";
        $pid = (int) shell_exec($cmd);

        if ($pid > 0) {
            // Kill the entire process tree
            shell_exec("pkill -P $pid");
            shell_exec("kill -9 $pid");
        }

        // Also try to find and kill any remaining node processes for this port
        $cmd = "ps aux | grep 'mcp-server-playwright --port $port' | grep -v grep | awk '{print $2}'";
        $pid = (int) shell_exec($cmd);

        if ($pid > 0) {
            shell_exec("pkill -P $pid");
            shell_exec("kill -9 $pid");
        }

        return true;
    }

    public function restartPlaywrightMcp(
        int $port
    ): bool {
        // Extract display number from the running process
        $cmd    = "ps aux | grep 'playwright/mcp@.*--port $port' | grep -v grep";
        $output = shell_exec($cmd);

        if (!$output) {
            $this->logger->warning("[restartPlaywrightMcp] No running Playwright MCP process found for port $port");

            return false;
        }

        // Parse the command line to extract display number
        if (preg_match('/launch-playwright-mcp\.sh (\d+) (\d+)/', $output, $matches)) {
            $displayNumber = (int) $matches[1];
            $port          = (int) $matches[2];

            // Stop the process
            $this->stopPlaywrightMcp($port);

            // Wait a moment for the process to fully stop
            sleep(2);

            // Start the process again with the same parameters
            return $this->launchPlaywrightMcp($port, $displayNumber);
        }

        $this->logger->error('[restartPlaywrightMcp] Could not parse Playwright MCP command line parameters');

        return false;
    }

    public function launchVncServer(
        int    $port,
        int    $displayNumber,
        string $password
    ): bool {
        // Create VNC password file
        $passwordFile = "/var/tmp/vnc.$port.pwd";
        shell_exec("echo \"$password\" | vncpasswd -f > $passwordFile");

        // Launch VNC server
        $cmd = sprintf(
            'x11vnc -display :%d -forever -shared -rfbport %d -rfbauth %s > /var/tmp/vnc.%d.log 2>&1 &',
            $displayNumber,
            $port,
            $passwordFile,
            $port
        );
        shell_exec($cmd);

        return true;
    }

    public function stopVncServer(
        int $port,
        int $displayNumber
    ): bool {
        // Find and kill the VNC server process
        $cmd = "ps aux | grep 'x11vnc -display :$displayNumber -rfbport $port' | grep -v grep | awk '{print $2}'";
        $pid = (int) shell_exec($cmd);

        if ($pid > 0) {
            shell_exec("kill -9 $pid");
        }

        // Clean up password file
        $passwordFile = "/var/tmp/vnc.$port.pwd";
        if (file_exists($passwordFile)) {
            unlink($passwordFile);
        }

        return true;
    }

    public function restartVncServer(
        int $port
    ): bool {
        // Extract display number from the running process
        $cmd    = "ps aux | grep 'x11vnc.*-rfbport $port' | grep -v grep";
        $output = shell_exec($cmd);

        if (!$output) {
            $this->logger->warning("[restartVncServer] No running VNC server process found for port $port");

            return false;
        }

        // Parse the command line to extract display number
        if (preg_match('/x11vnc -display :(\d+).* -rfbport (\d+)/', $output, $matches)) {
            $displayNumber = (int) $matches[1];
            $port          = (int) $matches[2];

            // Get the VNC password from the MCP instance
            $mcpInstance = $this->findMcpInstanceByVncPort($port);
            if (!$mcpInstance) {
                $this->logger->error("[restartVncServer] Could not find MCP instance for VNC port $port");

                return false;
            }

            // Stop the process
            $this->stopVncServer($port, $displayNumber);

            // Wait a moment for the process to fully stop
            sleep(1);

            // Start the process again with the same parameters
            return $this->launchVncServer($port, $displayNumber, $mcpInstance->getVncPassword());
        }

        $this->logger->error('[restartVncServer] Could not parse VNC server command line parameters');

        return false;
    }

    public function launchVncWebsocket(
        int $httpPort,
        int $vncPort
    ): bool {
        $cmd = sprintf(
            'websockify --web=/usr/share/novnc/ %d localhost:%d > /var/tmp/websockify.%d.log 2>&1 &',
            $httpPort,
            $vncPort,
            $httpPort
        );
        shell_exec($cmd);

        return true;
    }

    public function stopVncWebsocket(
        int $httpPort
    ): bool {
        // Find and kill the websockify process
        $cmd = "ps aux | grep 'websockify.*$httpPort' | grep -v grep | awk '{print $2}'";
        $pid = (int) shell_exec($cmd);

        if ($pid > 0) {
            shell_exec("kill -9 $pid");
        }

        return true;
    }

    public function restartVncWebsocket(
        int $httpPort
    ): bool {
        // Extract VNC port from the running process
        $cmd    = "ps aux | grep 'websockify.*$httpPort' | grep -v grep";
        $output = shell_exec($cmd);

        if (!$output) {
            $this->logger->warning("[restartVncWebsocket] No running VNC websocket process found for HTTP port $httpPort");

            return false;
        }

        // Parse the command line to extract VNC port
        if (preg_match('/websockify [^ ]* (\d+) localhost:(\d+)/', $output, $matches)) {
            $httpPort = (int) $matches[1];
            $vncPort  = (int) $matches[2];

            // Stop the process
            $this->stopVncWebsocket($httpPort);

            // Wait a moment for the process to fully stop
            sleep(1);

            // Start the process again with the same parameters
            return $this->launchVncWebsocket($httpPort, $vncPort);
        }

        $this->logger->error('[restartVncWebsocket] Could not parse VNC websocket command line parameters');

        return false;
    }

    /**
     * Restart all processes for a specific MCP instance.
     */
    public function restartAllProcessesForInstance(McpInstance $mcpInstance): bool
    {
        $this->logger->info("[restartAllProcessesForInstance] Restarting all processes for instance {$mcpInstance->getId()}");

        try {
            // Stop all processes in reverse order
            $this->stopVncWebsocket($mcpInstance->getWebsocketPort());
            $this->stopVncServer($mcpInstance->getVncPort(), $mcpInstance->getDisplayNumber());
            $this->stopPlaywrightMcp($mcpInstance->getMcpPort());
            $this->stopVirtualFramebuffer($mcpInstance->getDisplayNumber());

            // Wait for processes to fully stop
            sleep(3);

            // Start all processes in correct order
            $virtualFramebufferSuccess = $this->launchVirtualFramebuffer(
                $mcpInstance->getDisplayNumber(),
                $mcpInstance->getScreenWidth(),
                $mcpInstance->getScreenHeight(),
                $mcpInstance->getColorDepth()
            );

            $playwrightMcpSuccess = $this->launchPlaywrightMcp(
                $mcpInstance->getMcpPort(),
                $mcpInstance->getDisplayNumber()
            );

            $vncServerSuccess = $this->launchVncServer(
                $mcpInstance->getVncPort(),
                $mcpInstance->getDisplayNumber(),
                $mcpInstance->getVncPassword()
            );

            $vncWebsocketSuccess = $this->launchVncWebsocket(
                $mcpInstance->getWebsocketPort(),
                $mcpInstance->getVncPort()
            );

            $success = $virtualFramebufferSuccess && $playwrightMcpSuccess && $vncServerSuccess && $vncWebsocketSuccess;

            if ($success) {
                $this->logger->info("[restartAllProcessesForInstance] Successfully restarted all processes for instance {$mcpInstance->getId()}");
            } else {
                $this->logger->error("[restartAllProcessesForInstance] Failed to restart some processes for instance {$mcpInstance->getId()}");
            }

            return $success;
        } catch (Exception $e) {
            $this->logger->error('[restartAllProcessesForInstance] Exception while restarting processes: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Find MCP instance by VNC port.
     */
    private function findMcpInstanceByVncPort(int $vncPort): ?McpInstance
    {
        $repo = $this->entityManager->getRepository(McpInstance::class);

        return $repo->findOneBy(['vncPort' => $vncPort]);
    }

    /**
     * @return VirtualFramebufferProcessInfoDto[]
     */
    public function getRunningVirtualFramebuffers(): array
    {
        $output = shell_exec("ps aux | grep '[X]vfb :' | grep -v grep");
        $lines  = $output ? explode("\n", trim($output)) : [];
        $result = [];
        foreach ($lines as $line) {
            // Match: ... Xvfb :<display> ...
            if (preg_match('/^(\S+)\s+(\d+)\s+([\d\.]+)\s+([\d\.]+)\s+\d+\s+\d+\s+\S+\s+\S+\s+\S+.*Xvfb :([0-9]+)/', $line, $m)) {
                $result[] = new VirtualFramebufferProcessInfoDto(
                    (int)$m[2], // pid
                    (float)$m[3], // cpu
                    (float)$m[4], // mem
                    (int)$m[5], // display number
                    $line // command
                );
            }
        }

        return $result;
    }

    /**
     * @return PlaywrightMcpProcessInfoDto[]
     */
    public function getRunningPlaywrightMcps(): array
    {
        $output = shell_exec("ps aux | grep 'playwright/mcp@' | grep -v grep");
        $lines  = $output ? explode("\n", trim($output)) : [];
        $result = [];
        foreach ($lines as $line) {
            // Match: ... --port <port> ...
            if (preg_match('/^(\S+)\s+(\d+)\s+([\d\.]+)\s+([\d\.]+)\s+\d+\s+\d+\s+\S+\s+\S+\s+\S+.*--port (\d+)/', $line, $m)) {
                $result[] = new PlaywrightMcpProcessInfoDto(
                    (int)$m[2], // pid
                    (float)$m[3], // cpu
                    (float)$m[4], // mem
                    (int)$m[5], // port
                    $line // command
                );
            }
        }

        return $result;
    }

    /**
     * @return VncServerProcessInfoDto[]
     */
    public function getRunningVncServers(): array
    {
        $output = shell_exec("ps aux | grep '[x]11vnc ' | grep -v grep");
        $lines  = $output ? explode("\n", trim($output)) : [];
        $result = [];
        foreach ($lines as $line) {
            // Match: ... x11vnc -display :<display> ... -rfbport <port>
            if (preg_match('/^(\S+)\s+(\d+)\s+([\d\.]+)\s+([\d\.]+)\s+\d+\s+\d+\s+\S+\s+\S+\s+\S+.*x11vnc -display :([0-9]+).* -rfbport (\d+)/', $line, $m)) {
                $result[] = new VncServerProcessInfoDto(
                    (int)$m[2], // pid
                    (float)$m[3], // cpu
                    (float)$m[4], // mem
                    (int)$m[5], // display
                    (int)$m[6], // port
                    $line // command
                );
            }
        }

        return $result;
    }

    /**
     * @return VncWebsocketProcessInfoDto[]
     */
    public function getRunningVncWebsockets(): array
    {
        $output = shell_exec("ps aux | grep '[w]ebsockify ' | grep -v grep");
        $lines  = $output ? explode("\n", trim($output)) : [];
        $result = [];
        foreach ($lines as $line) {
            // Match: ... websockify --web=... <httpPort> localhost:<vncPort>
            if (preg_match('/^(\S+)\s+(\d+)\s+([\d\.]+)\s+([\d\.]+)\s+\d+\s+\d+\s+\S+\s+\S+\s+\S+.*websockify [^ ]* (\d+) localhost:(\d+)/', $line, $m)) {
                $result[] = new VncWebsocketProcessInfoDto(
                    (int)$m[2], // pid
                    (float)$m[3], // cpu
                    (float)$m[4], // mem
                    (int)$m[5], // http port
                    (int)$m[6], // vnc port
                    $line // command
                );
            }
        }

        return $result;
    }
}
