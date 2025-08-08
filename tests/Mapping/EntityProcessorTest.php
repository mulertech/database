<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\EntityProcessor;
use MulerTech\Database\Mapping\Types\ColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use MulerTech\Database\Tests\Files\Mapping\TestEntityForProcessor;
use MulerTech\Database\Tests\Files\Mapping\TestEntityWithoutMtEntity;
use MulerTech\Database\Tests\Files\Mapping\AnotherTestEntity;
use MulerTech\Database\Tests\Files\Mapping\EntityWithDefaultTableName;

#[CoversClass(EntityProcessor::class)]
class EntityProcessorTest extends TestCase
{
    private EntityProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new EntityProcessor();
    }

    public function testProcessEntityClassWithMtEntityAttribute(): void
    {
        $reflection = new ReflectionClass(TestEntityForProcessor::class);
        $this->processor->processEntityClass($reflection);
        
        $tables = $this->processor->getTables();
        $columns = $this->processor->getColumnsMapping();
        
        $this->assertArrayHasKey(TestEntityForProcessor::class, $tables);
        $this->assertEquals('test_entities', $tables[TestEntityForProcessor::class]);
        
        $this->assertArrayHasKey(TestEntityForProcessor::class, $columns);
        $this->assertEquals([
            'id' => 'id',
            'name' => 'entity_name',
            'email' => 'email'
        ], $columns[TestEntityForProcessor::class]);
    }

    public function testProcessEntityClassWithoutMtEntityAttribute(): void
    {
        $reflection = new ReflectionClass(TestEntityWithoutMtEntity::class);
        $this->processor->processEntityClass($reflection);
        
        $tables = $this->processor->getTables();
        $columns = $this->processor->getColumnsMapping();
        
        $this->assertArrayNotHasKey(TestEntityWithoutMtEntity::class, $tables);
        $this->assertArrayNotHasKey(TestEntityWithoutMtEntity::class, $columns);
    }

    public function testClassNameToTableNameConvertsCorrectly(): void
    {
        $this->assertEquals('test_entity_for_processor', $this->processor->classNameToTableName(TestEntityForProcessor::class));
        $this->assertEquals('another_test_entity', $this->processor->classNameToTableName(AnotherTestEntity::class));
        $this->assertEquals('entity_with_default_table_name', $this->processor->classNameToTableName(EntityWithDefaultTableName::class));
    }

    public function testClassNameToTableNameWithTestClasses(): void
    {
        $tableName = $this->processor->classNameToTableName(TestEntityWithoutMtEntity::class);
        
        $this->assertEquals('test_entity_without_mt_entity', $tableName);
    }

    public function testGetMtEntityReturnsCorrectInstance(): void
    {
        $mtEntity = $this->processor->getMtEntity(TestEntityForProcessor::class);
        
        $this->assertInstanceOf(MtEntity::class, $mtEntity);
        $this->assertEquals('test_entities', $mtEntity->tableName);
        $this->assertEquals('TestRepository', $mtEntity->repository);
    }

    public function testGetMtEntityReturnsNullForEntityWithoutAttribute(): void
    {
        $mtEntity = $this->processor->getMtEntity(TestEntityWithoutMtEntity::class);
        
        $this->assertNull($mtEntity);
    }

    public function testGetRepositoryReturnsCorrectRepository(): void
    {
        $repository = $this->processor->getRepository(TestEntityForProcessor::class);
        
        $this->assertEquals('TestRepository', $repository);
    }

    public function testGetRepositoryThrowsExceptionForEntityWithoutMtEntity(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The MtEntity mapping is not implemented into the ' . TestEntityWithoutMtEntity::class . ' class.');
        
        $this->processor->getRepository(TestEntityWithoutMtEntity::class);
    }

    public function testGetAutoIncrementReturnsCorrectValue(): void
    {
        $autoIncrement = $this->processor->getAutoIncrement(TestEntityForProcessor::class);
        
        $this->assertEquals(1000, $autoIncrement);
    }

    public function testGetAutoIncrementReturnsNullForEntityWithoutMtEntity(): void
    {
        $autoIncrement = $this->processor->getAutoIncrement(TestEntityWithoutMtEntity::class);
        
        $this->assertNull($autoIncrement);
    }

    public function testGetTableNameReturnsCorrectTableName(): void
    {
        $reflection = new ReflectionClass(TestEntityForProcessor::class);
        $this->processor->processEntityClass($reflection);
        
        $tableName = $this->processor->getTableName(TestEntityForProcessor::class);
        
        $this->assertEquals('test_entities', $tableName);
    }

    public function testGetTableNameReturnsNullForUnprocessedEntity(): void
    {
        $tableName = $this->processor->getTableName('UnprocessedEntity');
        
        $this->assertNull($tableName);
    }

    public function testGetTablesReturnsAllProcessedTables(): void
    {
        $reflection1 = new ReflectionClass(TestEntityForProcessor::class);
        $reflection2 = new ReflectionClass(AnotherTestEntity::class);
        
        $this->processor->processEntityClass($reflection1);
        $this->processor->processEntityClass($reflection2);
        
        $tables = $this->processor->getTables();
        
        $this->assertCount(2, $tables);
        $this->assertArrayHasKey(TestEntityForProcessor::class, $tables);
        $this->assertArrayHasKey(AnotherTestEntity::class, $tables);
        $this->assertEquals('test_entities', $tables[TestEntityForProcessor::class]);
        $this->assertEquals('others', $tables[AnotherTestEntity::class]);
    }

    public function testGetColumnsMappingReturnsAllProcessedColumns(): void
    {
        $reflection1 = new ReflectionClass(TestEntityForProcessor::class);
        $reflection2 = new ReflectionClass(AnotherTestEntity::class);
        
        $this->processor->processEntityClass($reflection1);
        $this->processor->processEntityClass($reflection2);
        
        $columns = $this->processor->getColumnsMapping();
        
        $this->assertCount(2, $columns);
        $this->assertArrayHasKey(TestEntityForProcessor::class, $columns);
        $this->assertArrayHasKey(AnotherTestEntity::class, $columns);
    }

    public function testProcessEntityWithDefaultTableName(): void
    {
        $reflection = new ReflectionClass(EntityWithDefaultTableName::class);
        $this->processor->processEntityClass($reflection);
        
        $tables = $this->processor->getTables();
        
        $this->assertEquals('entity_with_default_table_name', $tables[EntityWithDefaultTableName::class]);
    }

    public function testGetColumnNameReturnsCorrectColumnName(): void
    {
        $reflection = new ReflectionClass(TestEntityForProcessor::class);
        $this->processor->processEntityClass($reflection);
        
        $columnName = $this->processor->getColumnName(TestEntityForProcessor::class, 'name');
        
        $this->assertEquals('entity_name', $columnName);
    }

    public function testGetColumnNameReturnsNullForUnknownProperty(): void
    {
        $reflection = new ReflectionClass(TestEntityForProcessor::class);
        $this->processor->processEntityClass($reflection);
        
        $columnName = $this->processor->getColumnName(TestEntityForProcessor::class, 'unknownProperty');
        
        $this->assertNull($columnName);
    }

    public function testGetEntitiesReturnsListOfProcessedEntities(): void
    {
        $reflection1 = new ReflectionClass(TestEntityForProcessor::class);
        $reflection2 = new ReflectionClass(AnotherTestEntity::class);
        
        $this->processor->processEntityClass($reflection1);
        $this->processor->processEntityClass($reflection2);
        
        $entities = $this->processor->getEntities();
        
        $this->assertCount(2, $entities);
        $this->assertContains(TestEntityForProcessor::class, $entities);
        $this->assertContains(AnotherTestEntity::class, $entities);
    }

    public function testBuildEntityMetadataForClassThrowsExceptionForEntityWithoutMtEntity(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Entity ' . TestEntityWithoutMtEntity::class . ' does not have MtEntity attribute');
        
        $this->processor->buildEntityMetadataForClass(TestEntityWithoutMtEntity::class);
    }

    public function testProcessEntityClassReturnsFalseForEntityWithoutMtEntity(): void
    {
        $reflection = new ReflectionClass(TestEntityWithoutMtEntity::class);
        $result = $this->processor->processEntityClass($reflection);
        
        $this->assertFalse($result);
        
        // Verify that no metadata was stored
        $tables = $this->processor->getTables();
        $columns = $this->processor->getColumnsMapping();
        
        $this->assertArrayNotHasKey(TestEntityWithoutMtEntity::class, $tables);
        $this->assertArrayNotHasKey(TestEntityWithoutMtEntity::class, $columns);
    }
}
