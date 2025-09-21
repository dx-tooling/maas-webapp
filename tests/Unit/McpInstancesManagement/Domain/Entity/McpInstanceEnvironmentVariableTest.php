<?php

declare(strict_types=1);

namespace App\Tests\Unit\McpInstancesManagement\Domain\Entity;

use App\McpInstancesManagement\Domain\Entity\McpInstance;
use App\McpInstancesManagement\Domain\Entity\McpInstanceEnvironmentVariable;
use App\McpInstancesManagement\Facade\Enum\InstanceType;
use PHPUnit\Framework\TestCase;

final class McpInstanceEnvironmentVariableTest extends TestCase
{
    public function testConstructorSetsPropertiesCorrectly(): void
    {
        $mcpInstance = new McpInstance(
            'account-id',
            InstanceType::METABASE_V1,
            1280,
            720,
            24,
            'vncpass',
            'bearer'
        );

        $envVar = new McpInstanceEnvironmentVariable(
            'METABASE_URL',
            'https://demo.metabase.com',
            $mcpInstance
        );

        $this->assertSame('METABASE_URL', $envVar->getKey());
        $this->assertSame('https://demo.metabase.com', $envVar->getValue());
        $this->assertSame($mcpInstance, $envVar->getMcpInstance());
        $this->assertNull($envVar->getId());
    }

    public function testSettersUpdatePropertiesCorrectly(): void
    {
        $mcpInstance = new McpInstance(
            'account-id',
            InstanceType::METABASE_V1,
            1280,
            720,
            24,
            'vncpass',
            'bearer'
        );

        $envVar = new McpInstanceEnvironmentVariable(
            'OLD_KEY',
            'old_value',
            $mcpInstance
        );

        $envVar->setKey('NEW_KEY');
        $envVar->setValue('new_value');

        $this->assertSame('NEW_KEY', $envVar->getKey());
        $this->assertSame('new_value', $envVar->getValue());
    }

    public function testSetMcpInstanceUpdatesRelationship(): void
    {
        $originalInstance = new McpInstance(
            'account-1',
            InstanceType::METABASE_V1,
            1280,
            720,
            24,
            'vncpass1',
            'bearer1'
        );

        $newInstance = new McpInstance(
            'account-2',
            InstanceType::PLAYWRIGHT_V1,
            1920,
            1080,
            32,
            'vncpass2',
            'bearer2'
        );

        $envVar = new McpInstanceEnvironmentVariable(
            'TEST_KEY',
            'test_value',
            $originalInstance
        );

        $envVar->setMcpInstance($newInstance);

        $this->assertSame($newInstance, $envVar->getMcpInstance());
    }
}
