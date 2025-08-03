<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Core\Cache;

use MulerTech\Database\Core\Cache\MetadataRelationsHelper;
use MulerTech\Database\Mapping\DbMappingInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MetadataRelationsHelper::class)]
final class MetadataRelationsHelperTest extends TestCase
{
    private MetadataRelationsHelper $helper;
    private DbMappingInterface $dbMapping;

    protected function setUp(): void
    {
        $this->helper = new MetadataRelationsHelper();
        $this->dbMapping = $this->createMock(DbMappingInterface::class);
    }

    public function testAddRelationIfExistsWithValidData(): void
    {
        $relations = [];
        $relationData = ['targetEntity' => 'User', 'mappedBy' => 'id'];
        
        $this->helper->addRelationIfExists($relations, 'oneToMany', $relationData);
        
        $this->assertArrayHasKey('oneToMany', $relations);
        $this->assertEquals($relationData, $relations['oneToMany']);
    }

    public function testAddRelationIfExistsWithNullData(): void
    {
        $relations = ['existing' => 'data'];
        
        $this->helper->addRelationIfExists($relations, 'oneToMany', null);
        
        $this->assertArrayNotHasKey('oneToMany', $relations);
        $this->assertArrayHasKey('existing', $relations);
    }

    public function testAddRelationIfExistsWithEmptyArray(): void
    {
        $relations = ['existing' => 'data'];
        
        $this->helper->addRelationIfExists($relations, 'oneToMany', []);
        
        $this->assertArrayNotHasKey('oneToMany', $relations);
        $this->assertArrayHasKey('existing', $relations);
    }

    public function testAddRelationIfExistsOverwritesExisting(): void
    {
        $relations = ['oneToMany' => 'old_data'];
        $relationData = ['targetEntity' => 'User', 'mappedBy' => 'id'];
        
        $this->helper->addRelationIfExists($relations, 'oneToMany', $relationData);
        
        $this->assertEquals($relationData, $relations['oneToMany']);
    }

    public function testBuildRelationsDataWithAllRelationTypes(): void
    {
        $oneToOneData = ['targetEntity' => 'Profile', 'mappedBy' => 'user'];
        $oneToManyData = ['targetEntity' => 'Post', 'mappedBy' => 'author'];
        $manyToOneData = ['targetEntity' => 'Category', 'inversedBy' => 'posts'];
        $manyToManyData = ['targetEntity' => 'Tag', 'joinTable' => 'post_tags'];
        
        $this->dbMapping->method('getOneToOne')->willReturn($oneToOneData);
        $this->dbMapping->method('getOneToMany')->willReturn($oneToManyData);
        $this->dbMapping->method('getManyToOne')->willReturn($manyToOneData);
        $this->dbMapping->method('getManyToMany')->willReturn($manyToManyData);
        
        $result = $this->helper->buildRelationsData($this->dbMapping, 'TestEntity');
        
        $this->assertCount(4, $result);
        $this->assertEquals($oneToOneData, $result['oneToOne']);
        $this->assertEquals($oneToManyData, $result['oneToMany']);
        $this->assertEquals($manyToOneData, $result['manyToOne']);
        $this->assertEquals($manyToManyData, $result['manyToMany']);
    }

    public function testBuildRelationsDataWithSomeRelations(): void
    {
        $oneToManyData = ['targetEntity' => 'Post', 'mappedBy' => 'author'];
        $manyToOneData = ['targetEntity' => 'Category', 'inversedBy' => 'posts'];
        
        $this->dbMapping->method('getOneToOne')->willReturn([]);
        $this->dbMapping->method('getOneToMany')->willReturn($oneToManyData);
        $this->dbMapping->method('getManyToOne')->willReturn($manyToOneData);
        $this->dbMapping->method('getManyToMany')->willReturn([]);
        
        $result = $this->helper->buildRelationsData($this->dbMapping, 'TestEntity');
        
        $this->assertCount(2, $result);
        $this->assertArrayNotHasKey('oneToOne', $result);
        $this->assertEquals($oneToManyData, $result['oneToMany']);
        $this->assertEquals($manyToOneData, $result['manyToOne']);
        $this->assertArrayNotHasKey('manyToMany', $result);
    }

    public function testBuildRelationsDataWithNoRelations(): void
    {
        $this->dbMapping->method('getOneToOne')->willReturn([]);
        $this->dbMapping->method('getOneToMany')->willReturn([]);
        $this->dbMapping->method('getManyToOne')->willReturn([]);
        $this->dbMapping->method('getManyToMany')->willReturn([]);
        
        $result = $this->helper->buildRelationsData($this->dbMapping, 'TestEntity');
        
        $this->assertEmpty($result);
    }

    public function testBuildRelationsDataCallsAllMethods(): void
    {
        $entityClass = 'TestEntity';
        
        $this->dbMapping->expects($this->once())->method('getOneToOne')->with($entityClass);
        $this->dbMapping->expects($this->once())->method('getOneToMany')->with($entityClass);
        $this->dbMapping->expects($this->once())->method('getManyToOne')->with($entityClass);
        $this->dbMapping->expects($this->once())->method('getManyToMany')->with($entityClass);
        
        $this->helper->buildRelationsData($this->dbMapping, $entityClass);
    }

    public function testAddRelationIfExistsWithVariousDataTypes(): void
    {
        $relations = [];
        
        // Test with array containing nested data
        $complexRelationData = [
            'targetEntity' => 'User',
            'cascade' => ['persist', 'remove'],
            'fetch' => 'LAZY',
            'optional' => false
        ];
        
        $this->helper->addRelationIfExists($relations, 'manyToOne', $complexRelationData);
        
        $this->assertEquals($complexRelationData, $relations['manyToOne']);
    }

    public function testAddRelationIfExistsWithFalsyButValidData(): void
    {
        $relations = [];
        
        // Test with array containing falsy but valid values
        $relationData = [
            'optional' => false,
            'orphanRemoval' => false,
            'count' => 0
        ];
        
        $this->helper->addRelationIfExists($relations, 'oneToMany', $relationData);
        
        $this->assertArrayHasKey('oneToMany', $relations);
        $this->assertEquals($relationData, $relations['oneToMany']);
    }

    public function testBuildRelationsDataPreservesOrder(): void
    {
        $this->dbMapping->method('getOneToOne')->willReturn(['oneToOne' => 'data']);
        $this->dbMapping->method('getOneToMany')->willReturn(['oneToMany' => 'data']);
        $this->dbMapping->method('getManyToOne')->willReturn(['manyToOne' => 'data']);
        $this->dbMapping->method('getManyToMany')->willReturn(['manyToMany' => 'data']);
        
        $result = $this->helper->buildRelationsData($this->dbMapping, 'TestEntity');
        
        $expectedOrder = ['oneToOne', 'oneToMany', 'manyToOne', 'manyToMany'];
        $actualOrder = array_keys($result);
        
        $this->assertEquals($expectedOrder, $actualOrder);
    }

    public function testHelperIsStateless(): void
    {
        // First call
        $this->dbMapping->method('getOneToOne')->willReturn(['first' => 'call']);
        $this->dbMapping->method('getOneToMany')->willReturn([]);
        $this->dbMapping->method('getManyToOne')->willReturn([]);
        $this->dbMapping->method('getManyToMany')->willReturn([]);
        
        $result1 = $this->helper->buildRelationsData($this->dbMapping, 'Entity1');
        
        // Second call with different data
        $dbMapping2 = $this->createMock(DbMappingInterface::class);
        $dbMapping2->method('getOneToOne')->willReturn([]);
        $dbMapping2->method('getOneToMany')->willReturn(['second' => 'call']);
        $dbMapping2->method('getManyToOne')->willReturn([]);
        $dbMapping2->method('getManyToMany')->willReturn([]);
        
        $result2 = $this->helper->buildRelationsData($dbMapping2, 'Entity2');
        
        // Results should be independent
        $this->assertEquals(['oneToOne' => ['first' => 'call']], $result1);
        $this->assertEquals(['oneToMany' => ['second' => 'call']], $result2);
    }

    public function testAddRelationModifiesArrayByReference(): void
    {
        $relations = ['existing' => 'data'];
        $originalRelations = $relations;
        
        $this->helper->addRelationIfExists($relations, 'newRelation', ['new' => 'data']);
        
        // Original array should be modified
        $this->assertNotEquals($originalRelations, $relations);
        $this->assertArrayHasKey('newRelation', $relations);
        $this->assertEquals('data', $relations['existing']); // Existing data preserved
    }
}