<?php

declare(strict_types=1);

namespace App\McpInstances\TestHarness\Service;

use App\McpInstances\Facade\Dto\McpInstanceInfoDto;

readonly class DemoDataService
{
    /** @return McpInstanceInfoDto[] */
    public function getFakeMcpInstanceInfoDtos(): array
    {
        $rootDomain = getenv('APP_ROOT_DOMAIN') ?: 'mcp-as-a-service.com';

        return [
            new McpInstanceInfoDto(
                'instance1',
                'inst-1-slug',
                'mcp-inst-1',
                'running',
                1280,
                720,
                24,
                'password_for_instance1',
                'bearer_token_instance1',
                'mcp-inst-1.' . $rootDomain,
                'vnc-inst-1.' . $rootDomain
            ),
            new McpInstanceInfoDto(
                'instance2',
                'inst-2-slug',
                'mcp-inst-2',
                'running',
                1920,
                1080,
                24,
                'foobar',
                'bearer_token_instance2',
                'mcp-inst-2.' . $rootDomain,
                'vnc-inst-2.' . $rootDomain
            ),
            new McpInstanceInfoDto(
                'instance3',
                'inst-3-slug',
                'mcp-inst-3',
                'stopped',
                1280,
                720,
                24,
                'password_for_instance3',
                'bearer_token_instance3',
                'mcp-inst-3.' . $rootDomain,
                'vnc-inst-3.' . $rootDomain
            ),
        ];
    }
}
