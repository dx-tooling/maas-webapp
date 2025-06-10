<?php

declare(strict_types=1);

namespace App\Common\Facade\Value;

use ValueError;

abstract readonly class AbstractId
{
    public function __construct(
        public string $value
    ) {
        if (empty($value)) {
            throw new ValueError('ID value cannot be empty');
        }
    }

    final public function __toString(): string
    {
        return $this->value;
    }
}
