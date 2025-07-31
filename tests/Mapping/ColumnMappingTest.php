<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\ColumnMapping;
use MulerTech\Database\Mapping\Types\ColumnKey;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\Tests\Files\Mapping\TestEntityWithColumns;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionException;

#[CoversClass(ColumnMapping::class)]
class ColumnMappingTest extends TestCase
{
    private ColumnMapping $columnMapping;

    protected function setUp(): void
    {
        $this->columnMapping = new ColumnMapping();
    }

    public function testGetMtColumnsReturnsArrayOfMtColumnAttributes(): void
    {
        $columns = $this->columnMapping->getMtColumns(TestEntityWithColumns::class);
        
        $this->assertIsArray($columns);
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('name', $columns);
        $this->assertArrayHasKey('email', $columns);
        $this->assertInstanceOf(MtColumn::class, $columns['id']);
        $this->assertInstanceOf(MtColumn::class, $columns['name']);
        $this->assertInstanceOf(MtColumn::class, $columns['email']);
    }

    public function testGetColumnTypeReturnsCorrectType(): void
    {
        $idType = $this->columnMapping->getColumnType(TestEntityWithColumns::class, 'id');
        $nameType = $this->columnMapping->getColumnType(TestEntityWithColumns::class, 'name');
        
        $this->assertEquals(ColumnType::INT, $idType);
        $this->assertEquals(ColumnType::VARCHAR, $nameType);
    }

    public function testGetColumnTypeReturnsNullForNonExistentProperty(): void
    {
        $type = $this->columnMapping->getColumnType(TestEntityWithColumns::class, 'nonExistent');
        
        $this->assertNull($type);
    }

    public function testGetColumnLengthReturnsCorrectLength(): void
    {
        $nameLength = $this->columnMapping->getColumnLength(TestEntityWithColumns::class, 'name');
        $emailLength = $this->columnMapping->getColumnLength(TestEntityWithColumns::class, 'email');
        
        $this->assertEquals(100, $nameLength);
        $this->assertEquals(255, $emailLength);
    }

    public function testGetColumnLengthReturnsNullForPropertyWithoutLength(): void
    {
        $idLength = $this->columnMapping->getColumnLength(TestEntityWithColumns::class, 'id');
        
        $this->assertNull($idLength);
    }

    public function testGetColumnTypeDefinitionReturnsCorrectSqlDefinition(): void
    {
        $idDefinition = $this->columnMapping->getColumnTypeDefinition(TestEntityWithColumns::class, 'id');
        $nameDefinition = $this->columnMapping->getColumnTypeDefinition(TestEntityWithColumns::class, 'name');
        $emailDefinition = $this->columnMapping->getColumnTypeDefinition(TestEntityWithColumns::class, 'email');
        
        $this->assertEquals('INT unsigned', $idDefinition);
        $this->assertEquals('VARCHAR(100)', $nameDefinition);
        $this->assertEquals('VARCHAR(255)', $emailDefinition);
    }

    public function testGetColumnTypeDefinitionReturnsNullForNonExistentProperty(): void
    {
        $definition = $this->columnMapping->getColumnTypeDefinition(TestEntityWithColumns::class, 'nonExistent');
        
        $this->assertNull($definition);
    }

    public function testIsNullableReturnsCorrectValue(): void
    {
        $idNullable = $this->columnMapping->isNullable(TestEntityWithColumns::class, 'id');
        $nameNullable = $this->columnMapping->isNullable(TestEntityWithColumns::class, 'name');
        $emailNullable = $this->columnMapping->isNullable(TestEntityWithColumns::class, 'email');
        
        $this->assertFalse($idNullable);
        $this->assertFalse($nameNullable);
        $this->assertTrue($emailNullable);
    }

    public function testGetExtraReturnsCorrectValue(): void
    {
        $idExtra = $this->columnMapping->getExtra(TestEntityWithColumns::class, 'id');
        $nameExtra = $this->columnMapping->getExtra(TestEntityWithColumns::class, 'name');
        
        $this->assertEquals('AUTO_INCREMENT', $idExtra);
        $this->assertNull($nameExtra);
    }

    public function testGetColumnDefaultReturnsCorrectValue(): void
    {
        $idDefault = $this->columnMapping->getColumnDefault(TestEntityWithColumns::class, 'id');
        $nameDefault = $this->columnMapping->getColumnDefault(TestEntityWithColumns::class, 'name');
        
        $this->assertNull($idDefault);
        $this->assertNull($nameDefault);
    }

    public function testGetColumnKeyReturnsCorrectValue(): void
    {
        $idKey = $this->columnMapping->getColumnKey(TestEntityWithColumns::class, 'id');
        $nameKey = $this->columnMapping->getColumnKey(TestEntityWithColumns::class, 'name');
        
        $this->assertEquals('PRI', $idKey);
        $this->assertNull($nameKey);
    }

    public function testIsUnsignedReturnsCorrectValue(): void
    {
        $idUnsigned = $this->columnMapping->isUnsigned(TestEntityWithColumns::class, 'id');
        $nameUnsigned = $this->columnMapping->isUnsigned(TestEntityWithColumns::class, 'name');
        
        $this->assertTrue($idUnsigned);
        $this->assertFalse($nameUnsigned);
    }

    public function testThrowsReflectionExceptionForInvalidClass(): void
    {
        $this->expectException(ReflectionException::class);
        $this->columnMapping->getMtColumns('NonExistentClass');
    }
}
