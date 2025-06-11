<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Domain\Service;

use App\OsProcessManagement\Domain\Dto\PlaywrightMcpProcessInfoDto;

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

    public function getPlaywrightMcpProcessInfo(
        int $port
    ): PlaywrightMcpProcessInfoDto {
        return new PlaywrightMcpProcessInfoDto();
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
                $result[] = new \App\OsProcessManagement\Domain\Dto\VirtualFramebufferProcessInfoDto(
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
                $result[] = new \App\OsProcessManagement\Domain\Dto\VncServerProcessInfoDto(
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
                $result[] = new \App\OsProcessManagement\Domain\Dto\VncWebsocketProcessInfoDto(
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
