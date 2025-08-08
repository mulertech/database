<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\EntityProcessor;
use MulerTech\Database\Mapping\ForeignKeyMapping;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\Mapping\Types\FkRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use MulerTech\Database\Tests\Files\Mapping\TestEntityWithForeignKeys;
use MulerTech\Database\Tests\Files\Mapping\TestEntityWithCustomConstraint;
use MulerTech\Database\Tests\Files\Mapping\TestEntityWithNullReferencedTable;

#[CoversClass(ForeignKeyMapping::class)]
class ForeignKeyMappingTest extends TestCase
{
    private ForeignKeyMapping $foreignKeyMapping;
    private EntityProcessor&MockObject $entityProcessor;

    protected function setUp(): void
    {
        $this->entityProcessor = $this->createMock(EntityProcessor::class);
        $this->foreignKeyMapping = new ForeignKeyMapping($this->entityProcessor);
    }

    public function testGetMtFkReturnsArrayOfMtFkAttributes(): void
    {
        $foreignKeys = $this->foreignKeyMapping->getMtFk(TestEntityWithForeignKeys::class);
        
        $this->assertIsArray($foreignKeys);
        $this->assertArrayHasKey('userId', $foreignKeys);
        $this->assertArrayHasKey('categoryId', $foreignKeys);
        $this->assertInstanceOf(MtFk::class, $foreignKeys['userId']);
        $this->assertInstanceOf(MtFk::class, $foreignKeys['categoryId']);
    }

    public function testGetForeignKeyReturnsCorrectForeignKey(): void
    {
        $userIdFk = $this->foreignKeyMapping->getForeignKey(TestEntityWithForeignKeys::class, 'userId');
        $categoryIdFk = $this->foreignKeyMapping->getForeignKey(TestEntityWithForeignKeys::class, 'categoryId');
        
        $this->assertInstanceOf(MtFk::class, $userIdFk);
        $this->assertInstanceOf(MtFk::class, $categoryIdFk);
        
        $this->assertEquals('users', $userIdFk->referencedTable);
        $this->assertEquals('categories', $categoryIdFk->referencedTable);
    }

    public function testGetForeignKeyReturnsNullForNonExistentProperty(): void
    {
        $foreignKey = $this->foreignKeyMapping->getForeignKey(TestEntityWithForeignKeys::class, 'nonExistent');
        
        $this->assertNull($foreignKey);
    }

    public function testGetConstraintNameReturnsCorrectConstraintName(): void
    {
        $this->entityProcessor->expects($this->once())
            ->method('getEntities')
            ->willReturn([TestEntityWithForeignKeys::class]);
        
        $this->entityProcessor->expects($this->once())
            ->method('getColumnName')
            ->with(TestEntityWithForeignKeys::class, 'userId')
            ->willReturn('user_id');
        
        $this->entityProcessor->expects($this->once())
            ->method('getTableName')
            ->with(TestEntityWithForeignKeys::class)
            ->willReturn('posts');
        
        $constraintName = $this->foreignKeyMapping->getConstraintName(TestEntityWithForeignKeys::class, 'userId');
        
        $this->assertEquals('fk_posts_user_id_users', $constraintName);
    }

    public function testGetConstraintNameReturnsNullWhenForeignKeyNotFound(): void
    {
        $constraintName = $this->foreignKeyMapping->getConstraintName(TestEntityWithForeignKeys::class, 'nonExistent');
        
        $this->assertNull($constraintName);
    }

    public function testGetConstraintNameReturnsNullWhenDbMappingReturnsNull(): void
    {
        $this->entityProcessor->expects($this->once())
            ->method('getEntities')
            ->willReturn([TestEntityWithForeignKeys::class]);
        
        $this->entityProcessor->expects($this->once())
            ->method('getColumnName')
            ->with(TestEntityWithForeignKeys::class, 'userId')
            ->willReturn(null);
        
        $constraintName = $this->foreignKeyMapping->getConstraintName(TestEntityWithForeignKeys::class, 'userId');
        
        $this->assertNull($constraintName);
    }

    public function testGetReferencedTableReturnsCorrectTable(): void
    {
        $userTable = $this->foreignKeyMapping->getReferencedTable(TestEntityWithForeignKeys::class, 'userId');
        $categoryTable = $this->foreignKeyMapping->getReferencedTable(TestEntityWithForeignKeys::class, 'categoryId');
        
        $this->assertEquals('users', $userTable);
        $this->assertEquals('categories', $categoryTable);
    }

    public function testGetReferencedTableReturnsNullForNonExistentProperty(): void
    {
        $table = $this->foreignKeyMapping->getReferencedTable(TestEntityWithForeignKeys::class, 'nonExistent');
        
        $this->assertNull($table);
    }

    public function testGetReferencedColumnReturnsCorrectColumn(): void
    {
        $userColumn = $this->foreignKeyMapping->getReferencedColumn(TestEntityWithForeignKeys::class, 'userId');
        $categoryColumn = $this->foreignKeyMapping->getReferencedColumn(TestEntityWithForeignKeys::class, 'categoryId');
        
        $this->assertEquals('id', $userColumn);
        $this->assertEquals('id', $categoryColumn);
    }

    public function testGetReferencedColumnReturnsNullForNonExistentProperty(): void
    {
        $column = $this->foreignKeyMapping->getReferencedColumn(TestEntityWithForeignKeys::class, 'nonExistent');
        
        $this->assertNull($column);
    }

    public function testGetDeleteRuleReturnsCorrectRule(): void
    {
        $userDeleteRule = $this->foreignKeyMapping->getDeleteRule(TestEntityWithForeignKeys::class, 'userId');
        $categoryDeleteRule = $this->foreignKeyMapping->getDeleteRule(TestEntityWithForeignKeys::class, 'categoryId');
        
        $this->assertEquals(FkRule::CASCADE, $userDeleteRule);
        $this->assertEquals(FkRule::SET_NULL, $categoryDeleteRule);
    }

    public function testGetDeleteRuleReturnsNullForNonExistentProperty(): void
    {
        $deleteRule = $this->foreignKeyMapping->getDeleteRule(TestEntityWithForeignKeys::class, 'nonExistent');
        
        $this->assertNull($deleteRule);
    }

    public function testGetUpdateRuleReturnsCorrectRule(): void
    {
        $userUpdateRule = $this->foreignKeyMapping->getUpdateRule(TestEntityWithForeignKeys::class, 'userId');
        $categoryUpdateRule = $this->foreignKeyMapping->getUpdateRule(TestEntityWithForeignKeys::class, 'categoryId');
        
        $this->assertEquals(FkRule::RESTRICT, $userUpdateRule);
        $this->assertEquals(FkRule::CASCADE, $categoryUpdateRule);
    }

    public function testGetUpdateRuleReturnsNullForNonExistentProperty(): void
    {
        $updateRule = $this->foreignKeyMapping->getUpdateRule(TestEntityWithForeignKeys::class, 'nonExistent');
        
        $this->assertNull($updateRule);
    }

    public function testGetConstraintNameWithCustomConstraintName(): void
    {
        $constraintName = $this->foreignKeyMapping->getConstraintName(TestEntityWithCustomConstraint::class, 'authorId');
        
        // Should return null because the custom constraint name is set, but the method doesn't use it
        // The actual constraint name would come from the attribute itself
        $this->assertNull($constraintName);
    }

    public function testForeignKeyMappingWithNullReferencedTable(): void
    {
        // First, we need to mock the entityProcessor to have the entity loaded
        $this->entityProcessor->expects($this->once())
            ->method('getEntities')
            ->willReturn([TestEntityWithNullReferencedTable::class]);
        
        $constraintName = $this->foreignKeyMapping->getConstraintName(TestEntityWithNullReferencedTable::class, 'someId');
        $referencedTable = $this->foreignKeyMapping->getReferencedTable(TestEntityWithNullReferencedTable::class, 'someId');
        $referencedColumn = $this->foreignKeyMapping->getReferencedColumn(TestEntityWithNullReferencedTable::class, 'someId');
        $deleteRule = $this->foreignKeyMapping->getDeleteRule(TestEntityWithNullReferencedTable::class, 'someId');
        $updateRule = $this->foreignKeyMapping->getUpdateRule(TestEntityWithNullReferencedTable::class, 'someId');
        
        $this->assertNull($constraintName);
        $this->assertNull($referencedTable);
        $this->assertNull($referencedColumn);
        $this->assertNull($deleteRule);
        $this->assertNull($updateRule);
    }
}
