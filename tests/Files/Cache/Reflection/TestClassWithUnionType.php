<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Cache\Reflection;

/**
 * Test class with union type property
 */
class TestClassWithUnionType
{
    public string|int $unionProperty;
}