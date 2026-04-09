<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Mapping;

/**
 * Test entity whose constructor throws an Exception (for testing catch branches).
 */
class EntityWithThrowingConstructor
{
    public function __construct()
    {
        throw new \RuntimeException('Constructor failure');
    }
}
