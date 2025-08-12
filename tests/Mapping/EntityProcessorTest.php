<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping;

use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\EntityProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use MulerTech\Database\Tests\Files\Mapping\EntityForProcessor;
use MulerTech\Database\Tests\Files\Mapping\EntityWithoutMtEntity;
use MulerTech\Database\Tests\Files\Mapping\AnotherEntity;
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
        $reflection = new ReflectionClass(EntityForProcessor::class);
        $this->processor->processEntityClass($reflection);
        
        $tables = $this->processor->getTables();
        $columns = $this->processor->getColumnsMapping();
        
        $this->assertArrayHasKey(EntityForProcessor::class, $tables);
        $this->assertEquals('test_entities', $tables[EntityForProcessor::class]);
        
        $this->assertArrayHasKey(EntityForProcessor::class, $columns);
        $this->assertEquals([
            'id' => 'id',
            'name' => 'entity_name',
            'email' => 'email'
        ], $columns[EntityForProcessor::class]);
    }

    public function testProcessEntityClassWithoutMtEntityAttribute(): void
    {
        $reflection = new ReflectionClass(EntityWithoutMtEntity::class);
        $this->processor->processEntityClass($reflection);
        
        $tables = $this->processor->getTables();
        $columns = $this->processor->getColumnsMapping();
        
        $this->assertArrayNotHasKey(EntityWithoutMtEntity::class, $tables);
        $this->assertArrayNotHasKey(EntityWithoutMtEntity::class, $columns);
    }

    public function testGetMtEntityReturnsCorrectInstance(): void
    {
        $mtEntity = $this->processor->getMtEntity(EntityForProcessor::class);
        
        $this->assertInstanceOf(MtEntity::class, $mtEntity);
        $this->assertEquals('test_entities', $mtEntity->tableName);
        $this->assertEquals('TestRepository', $mtEntity->repository);
    }

    public function testGetMtEntityReturnsNullForEntityWithoutAttribute(): void
    {
        $mtEntity = $this->processor->getMtEntity(EntityWithoutMtEntity::class);
        
        $this->assertNull($mtEntity);
    }

    public function testGetRepositoryReturnsCorrectRepository(): void
    {
        $repository = $this->processor->getRepository(EntityForProcessor::class);
        
        $this->assertEquals('TestRepository', $repository);
    }

    public function testGetRepositoryThrowsExceptionForEntityWithoutMtEntity(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The MtEntity mapping is not implemented into the ' . EntityWithoutMtEntity::class . ' class.');
        
        $this->processor->getRepository(EntityWithoutMtEntity::class);
    }

    public function testGetAutoIncrementReturnsCorrectValue(): void
    {
        $autoIncrement = $this->processor->getAutoIncrement(EntityForProcessor::class);
        
        $this->assertEquals(1000, $autoIncrement);
    }

    public function testGetAutoIncrementReturnsNullForEntityWithoutMtEntity(): void
    {
        $autoIncrement = $this->processor->getAutoIncrement(EntityWithoutMtEntity::class);
        
        $this->assertNull($autoIncrement);
    }

    public function testGetTableNameReturnsCorrectTableName(): void
    {
        $reflection = new ReflectionClass(EntityForProcessor::class);
        $this->processor->processEntityClass($reflection);
        
        $tableName = $this->processor->getTableName(EntityForProcessor::class);
        
        $this->assertEquals('test_entities', $tableName);
    }

    public function testGetTableNameReturnsNullForUnprocessedEntity(): void
    {
        $tableName = $this->processor->getTableName('UnprocessedEntity');
        
        $this->assertNull($tableName);
    }

    public function testGetTablesReturnsAllProcessedTables(): void
    {
        $reflection1 = new ReflectionClass(EntityForProcessor::class);
        $reflection2 = new ReflectionClass(AnotherEntity::class);
        
        $this->processor->processEntityClass($reflection1);
        $this->processor->processEntityClass($reflection2);
        
        $tables = $this->processor->getTables();
        
        $this->assertCount(2, $tables);
        $this->assertArrayHasKey(EntityForProcessor::class, $tables);
        $this->assertArrayHasKey(AnotherEntity::class, $tables);
        $this->assertEquals('test_entities', $tables[EntityForProcessor::class]);
        $this->assertEquals('others', $tables[AnotherEntity::class]);
    }

    public function testGetColumnsMappingReturnsAllProcessedColumns(): void
    {
        $reflection1 = new ReflectionClass(EntityForProcessor::class);
        $reflection2 = new ReflectionClass(AnotherEntity::class);
        
        $this->processor->processEntityClass($reflection1);
        $this->processor->processEntityClass($reflection2);
        
        $columns = $this->processor->getColumnsMapping();
        
        $this->assertCount(2, $columns);
        $this->assertArrayHasKey(EntityForProcessor::class, $columns);
        $this->assertArrayHasKey(AnotherEntity::class, $columns);
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
        $reflection = new ReflectionClass(EntityForProcessor::class);
        $this->processor->processEntityClass($reflection);
        
        $columnName = $this->processor->getColumnName(EntityForProcessor::class, 'name');
        
        $this->assertEquals('entity_name', $columnName);
    }

    public function testGetColumnNameReturnsNullForUnknownProperty(): void
    {
        $reflection = new ReflectionClass(EntityForProcessor::class);
        $this->processor->processEntityClass($reflection);
        
        $columnName = $this->processor->getColumnName(EntityForProcessor::class, 'unknownProperty');
        
        $this->assertNull($columnName);
    }

    public function testGetEntitiesReturnsListOfProcessedEntities(): void
    {
        $reflection1 = new ReflectionClass(EntityForProcessor::class);
        $reflection2 = new ReflectionClass(AnotherEntity::class);
        
        $this->processor->processEntityClass($reflection1);
        $this->processor->processEntityClass($reflection2);
        
        $entities = $this->processor->getEntities();
        
        $this->assertCount(2, $entities);
        $this->assertContains(EntityForProcessor::class, $entities);
        $this->assertContains(AnotherEntity::class, $entities);
    }

    public function testBuildEntityMetadataForClassThrowsExceptionForEntityWithoutMtEntity(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Entity ' . EntityWithoutMtEntity::class . ' does not have MtEntity attribute');
        
        $this->processor->buildEntityMetadataForClass(EntityWithoutMtEntity::class);
    }

    public function testProcessEntityClassReturnsFalseForEntityWithoutMtEntity(): void
    {
        $reflection = new ReflectionClass(EntityWithoutMtEntity::class);
        $result = $this->processor->processEntityClass($reflection);
        
        $this->assertFalse($result);
        
        // Verify that no metadata was stored
        $tables = $this->processor->getTables();
        $columns = $this->processor->getColumnsMapping();
        
        $this->assertArrayNotHasKey(EntityWithoutMtEntity::class, $tables);
        $this->assertArrayNotHasKey(EntityWithoutMtEntity::class, $columns);
    }
}
