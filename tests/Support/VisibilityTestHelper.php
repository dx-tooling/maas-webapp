<?php

declare(strict_types=1);

namespace App\Tests\Support;

use ReflectionProperty;

final class VisibilityTestHelper
{
    public static function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}
