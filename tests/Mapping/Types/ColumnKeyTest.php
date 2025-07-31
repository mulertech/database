<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping\Types;

use MulerTech\Database\Mapping\Types\ColumnKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnKey::class)]
class ColumnKeyTest extends TestCase
{
    public function testAllColumnKeysHaveExpectedValues(): void
    {
        $expectedValues = [
            'PRI' => ColumnKey::PRIMARY_KEY,
            'UNI' => ColumnKey::UNIQUE_KEY,
            'MUL' => ColumnKey::MULTIPLE_KEY,
        ];

        foreach ($expectedValues as $expectedValue => $columnKey) {
            $this->assertEquals($expectedValue, $columnKey->value);
        }
    }

    public function testColumnKeyIsStringBackedEnum(): void
    {
        $this->assertInstanceOf(\BackedEnum::class, ColumnKey::PRIMARY_KEY);
        $this->assertIsString(ColumnKey::PRIMARY_KEY->value);
    }

    public function testPrimaryKeyValue(): void
    {
        $this->assertEquals('PRI', ColumnKey::PRIMARY_KEY->value);
    }

    public function testUniqueKeyValue(): void
    {
        $this->assertEquals('UNI', ColumnKey::UNIQUE_KEY->value);
    }

    public function testMultipleKeyValue(): void
    {
        $this->assertEquals('MUL', ColumnKey::MULTIPLE_KEY->value);
    }

    public function testFromStringValues(): void
    {
        $this->assertEquals(ColumnKey::PRIMARY_KEY, ColumnKey::from('PRI'));
        $this->assertEquals(ColumnKey::UNIQUE_KEY, ColumnKey::from('UNI'));
        $this->assertEquals(ColumnKey::MULTIPLE_KEY, ColumnKey::from('MUL'));
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(ColumnKey::tryFrom('INVALID'));
        $this->assertNull(ColumnKey::tryFrom('PRIMARY'));
        $this->assertNull(ColumnKey::tryFrom('UNIQUE'));
    }

    public function testGetAllCases(): void
    {
        $cases = ColumnKey::cases();
        $this->assertCount(3, $cases);
        $this->assertContains(ColumnKey::PRIMARY_KEY, $cases);
        $this->assertContains(ColumnKey::UNIQUE_KEY, $cases);
        $this->assertContains(ColumnKey::MULTIPLE_KEY, $cases);
    }

    public function testColumnKeyComparison(): void
    {
        $primaryKey1 = ColumnKey::PRIMARY_KEY;
        $primaryKey2 = ColumnKey::from('PRI');
        $uniqueKey = ColumnKey::UNIQUE_KEY;

        $this->assertSame($primaryKey1, $primaryKey2);
        $this->assertNotSame($primaryKey1, $uniqueKey);
    }

    public function testColumnKeyInArrays(): void
    {
        $keys = [
            ColumnKey::PRIMARY_KEY,
            ColumnKey::UNIQUE_KEY,
            ColumnKey::MULTIPLE_KEY,
        ];

        $this->assertContains(ColumnKey::PRIMARY_KEY, $keys);
        $this->assertContains(ColumnKey::UNIQUE_KEY, $keys);
        $this->assertContains(ColumnKey::MULTIPLE_KEY, $keys);
    }

    public function testColumnKeySwitch(): void
    {
        $getDescription = function(ColumnKey $key): string {
            return match($key) {
                ColumnKey::PRIMARY_KEY => 'Primary key constraint',
                ColumnKey::UNIQUE_KEY => 'Unique key constraint',
                ColumnKey::MULTIPLE_KEY => 'Multiple/Index key constraint',
            };
        };

        $this->assertEquals('Primary key constraint', $getDescription(ColumnKey::PRIMARY_KEY));
        $this->assertEquals('Unique key constraint', $getDescription(ColumnKey::UNIQUE_KEY));
        $this->assertEquals('Multiple/Index key constraint', $getDescription(ColumnKey::MULTIPLE_KEY));
    }
}