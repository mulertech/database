<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\ColumnMapping;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\Tests\Files\Mapping\EntityWithColumns;
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
        $columns = $this->columnMapping->getMtColumns(EntityWithColumns::class);
        
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
        $idType = $this->columnMapping->getColumnType(EntityWithColumns::class, 'id');
        $nameType = $this->columnMapping->getColumnType(EntityWithColumns::class, 'name');
        
        $this->assertEquals(ColumnType::INT, $idType);
        $this->assertEquals(ColumnType::VARCHAR, $nameType);
    }

    public function testGetColumnTypeReturnsNullForNonExistentProperty(): void
    {
        $type = $this->columnMapping->getColumnType(EntityWithColumns::class, 'nonExistent');
        
        $this->assertNull($type);
    }

    public function testGetColumnLengthReturnsCorrectLength(): void
    {
        $nameLength = $this->columnMapping->getColumnLength(EntityWithColumns::class, 'name');
        $emailLength = $this->columnMapping->getColumnLength(EntityWithColumns::class, 'email');
        
        $this->assertEquals(100, $nameLength);
        $this->assertEquals(255, $emailLength);
    }

    public function testGetColumnLengthReturnsNullForPropertyWithoutLength(): void
    {
        $idLength = $this->columnMapping->getColumnLength(EntityWithColumns::class, 'id');
        
        $this->assertNull($idLength);
    }

    public function testGetColumnTypeDefinitionReturnsCorrectSqlDefinition(): void
    {
        $idDefinition = $this->columnMapping->getColumnTypeDefinition(EntityWithColumns::class, 'id');
        $nameDefinition = $this->columnMapping->getColumnTypeDefinition(EntityWithColumns::class, 'name');
        $emailDefinition = $this->columnMapping->getColumnTypeDefinition(EntityWithColumns::class, 'email');
        
        $this->assertEquals('INT unsigned', $idDefinition);
        $this->assertEquals('VARCHAR(100)', $nameDefinition);
        $this->assertEquals('VARCHAR(255)', $emailDefinition);
    }

    public function testGetColumnTypeDefinitionReturnsNullForNonExistentProperty(): void
    {
        $definition = $this->columnMapping->getColumnTypeDefinition(EntityWithColumns::class, 'nonExistent');
        
        $this->assertNull($definition);
    }

    public function testIsNullableReturnsCorrectValue(): void
    {
        $idNullable = $this->columnMapping->isNullable(EntityWithColumns::class, 'id');
        $nameNullable = $this->columnMapping->isNullable(EntityWithColumns::class, 'name');
        $emailNullable = $this->columnMapping->isNullable(EntityWithColumns::class, 'email');
        
        $this->assertFalse($idNullable);
        $this->assertFalse($nameNullable);
        $this->assertTrue($emailNullable);
    }

    public function testGetExtraReturnsCorrectValue(): void
    {
        $idExtra = $this->columnMapping->getExtra(EntityWithColumns::class, 'id');
        $nameExtra = $this->columnMapping->getExtra(EntityWithColumns::class, 'name');
        
        $this->assertEquals('AUTO_INCREMENT', $idExtra);
        $this->assertNull($nameExtra);
    }

    public function testGetColumnDefaultReturnsCorrectValue(): void
    {
        $idDefault = $this->columnMapping->getColumnDefault(EntityWithColumns::class, 'id');
        $nameDefault = $this->columnMapping->getColumnDefault(EntityWithColumns::class, 'name');
        
        $this->assertNull($idDefault);
        $this->assertNull($nameDefault);
    }

    public function testGetColumnKeyReturnsCorrectValue(): void
    {
        $idKey = $this->columnMapping->getColumnKey(EntityWithColumns::class, 'id');
        $nameKey = $this->columnMapping->getColumnKey(EntityWithColumns::class, 'name');
        
        $this->assertEquals('PRI', $idKey);
        $this->assertNull($nameKey);
    }

    public function testIsUnsignedReturnsCorrectValue(): void
    {
        $idUnsigned = $this->columnMapping->isUnsigned(EntityWithColumns::class, 'id');
        $nameUnsigned = $this->columnMapping->isUnsigned(EntityWithColumns::class, 'name');
        
        $this->assertTrue($idUnsigned);
        $this->assertFalse($nameUnsigned);
    }

    public function testThrowsReflectionExceptionForInvalidClass(): void
    {
        $this->expectException(ReflectionException::class);
        $this->columnMapping->getMtColumns('NonExistentClass');
    }
}
