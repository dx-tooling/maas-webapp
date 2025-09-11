<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\McpInstancesManagement\Domain\Entity\McpInstance as DomainMcpInstance;
use App\McpInstancesManagement\Domain\Enum\InstanceType;
use ReflectionProperty;

final class DomainTestHelper
{
    public static function newDomainInstance(
        string       $accountCoreId,
        InstanceType $type,
        int          $screenWidth,
        int          $screenHeight,
        int          $colorDepth,
        string       $vncPassword,
        string       $mcpBearer
    ): DomainMcpInstance {
        return new DomainMcpInstance(
            $accountCoreId,
            $type,
            $screenWidth,
            $screenHeight,
            $colorDepth,
            $vncPassword,
            $mcpBearer
        );
    }

    public static function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}
