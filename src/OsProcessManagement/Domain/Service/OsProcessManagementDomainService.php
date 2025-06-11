<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Domain\Service;

class OsProcessManagementDomainService
{
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

    public function launchPlaywrightMcp(
        int $port,
        int $displayNumber
    ): bool {
        shell_exec(
            "cd /mcp ; DISPLAY=:$displayNumber nohup npx @playwright/mcp@latest --port $port --no-sandbox --isolated --browser chromium --host 0.0.0.0 >/var/tmp/launchPlaywrightMcp.$port.log 2>&1 &"
        );

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
}
