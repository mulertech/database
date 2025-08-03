<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Cache\Reflection;

/**
 * Test class for reflection testing
 */
class TestReflectionClass
{
    public string $publicProperty;
    protected int $protectedProperty;
    private bool $privateProperty;
    public static string $staticProperty;
    public $noTypeProperty;
    
    public function __construct(
        string $param1,
        int $param2 = 10,
        ?bool $param3 = null
    ) {
    }
}