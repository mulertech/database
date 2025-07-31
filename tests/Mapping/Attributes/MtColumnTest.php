<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping\Attributes;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Types\ColumnKey;
use MulerTech\Database\Mapping\Types\ColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute;
use ReflectionClass;

#[CoversClass(MtColumn::class)]
class MtColumnTest extends TestCase
{
    public function testConstructorWithDefaultValues(): void
    {
        $column = new MtColumn();
        
        $this->assertNull($column->columnName);
        $this->assertNull($column->columnType);
        $this->assertNull($column->length);
        $this->assertNull($column->scale);
        $this->assertFalse($column->isUnsigned);
        $this->assertTrue($column->isNullable);
        $this->assertNull($column->extra);
        $this->assertNull($column->columnDefault);
        $this->assertNull($column->columnKey);
        $this->assertEquals([], $column->choices);
    }

    public function testConstructorWithAllParameters(): void
    {
        $column = new MtColumn(
            columnName: 'test_column',
            columnType: ColumnType::VARCHAR,
            length: 255,
            scale: 2,
            isUnsigned: true,
            isNullable: false,
            extra: 'AUTO_INCREMENT',
            columnDefault: 'default_value',
            columnKey: ColumnKey::PRIMARY_KEY,
            choices: ['option1', 'option2']
        );
        
        $this->assertEquals('test_column', $column->columnName);
        $this->assertEquals(ColumnType::VARCHAR, $column->columnType);
        $this->assertEquals(255, $column->length);
        $this->assertEquals(2, $column->scale);
        $this->assertTrue($column->isUnsigned);
        $this->assertFalse($column->isNullable);
        $this->assertEquals('AUTO_INCREMENT', $column->extra);
        $this->assertEquals('default_value', $column->columnDefault);
        $this->assertEquals(ColumnKey::PRIMARY_KEY, $column->columnKey);
        $this->assertEquals(['option1', 'option2'], $column->choices);
    }

    public function testAttributeTargetsProperty(): void
    {
        $reflection = new ReflectionClass(MtColumn::class);
        $attributes = $reflection->getAttributes();
        
        $this->assertCount(1, $attributes);
        $this->assertEquals(\Attribute::class, $attributes[0]->getName());
        
        $attributeInstance = $attributes[0]->newInstance();
        $this->assertEquals(\Attribute::TARGET_PROPERTY, $attributeInstance->flags);
    }

    public function testConstructorWithPartialParameters(): void
    {
        $column = new MtColumn(
            columnName: 'partial_column',
            columnType: ColumnType::INT,
            isNullable: false
        );
        
        $this->assertEquals('partial_column', $column->columnName);
        $this->assertEquals(ColumnType::INT, $column->columnType);
        $this->assertNull($column->length);
        $this->assertNull($column->scale);
        $this->assertFalse($column->isUnsigned);
        $this->assertFalse($column->isNullable);
        $this->assertNull($column->extra);
        $this->assertNull($column->columnDefault);
        $this->assertNull($column->columnKey);
        $this->assertEquals([], $column->choices);
    }

    public function testDecimalColumnWithPrecisionAndScale(): void
    {
        $column = new MtColumn(
            columnName: 'price',
            columnType: ColumnType::DECIMAL,
            length: 10,
            scale: 2
        );
        
        $this->assertEquals('price', $column->columnName);
        $this->assertEquals(ColumnType::DECIMAL, $column->columnType);
        $this->assertEquals(10, $column->length);
        $this->assertEquals(2, $column->scale);
    }

    public function testUnsignedColumn(): void
    {
        $column = new MtColumn(
            columnName: 'id',
            columnType: ColumnType::INT,
            isUnsigned: true,
            columnKey: ColumnKey::PRIMARY_KEY
        );
        
        $this->assertEquals('id', $column->columnName);
        $this->assertEquals(ColumnType::INT, $column->columnType);
        $this->assertTrue($column->isUnsigned);
        $this->assertEquals(ColumnKey::PRIMARY_KEY, $column->columnKey);
    }

    public function testEnumColumnWithChoices(): void
    {
        $choices = ['active', 'inactive', 'pending'];
        $column = new MtColumn(
            columnName: 'status',
            columnType: ColumnType::ENUM,
            choices: $choices
        );
        
        $this->assertEquals('status', $column->columnName);
        $this->assertEquals(ColumnType::ENUM, $column->columnType);
        $this->assertEquals($choices, $column->choices);
    }

    public function testColumnWithDefaultValue(): void
    {
        $column = new MtColumn(
            columnName: 'created_at',
            columnType: ColumnType::TIMESTAMP,
            columnDefault: 'CURRENT_TIMESTAMP'
        );
        
        $this->assertEquals('created_at', $column->columnName);
        $this->assertEquals(ColumnType::TIMESTAMP, $column->columnType);
        $this->assertEquals('CURRENT_TIMESTAMP', $column->columnDefault);
    }
}