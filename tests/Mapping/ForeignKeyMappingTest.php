<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping;

use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\EntityProcessor;
use MulerTech\Database\Mapping\ForeignKeyMapping;
use MulerTech\Database\Mapping\Types\FkRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use MulerTech\Database\Tests\Files\Mapping\EntityWithForeignKeys;
use MulerTech\Database\Tests\Files\Mapping\EntityWithCustomConstraint;
use MulerTech\Database\Tests\Files\Mapping\EntityWithNullReferencedTable;

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
        $foreignKeys = $this->foreignKeyMapping->getMtFk(EntityWithForeignKeys::class);
        
        $this->assertIsArray($foreignKeys);
        $this->assertArrayHasKey('userId', $foreignKeys);
        $this->assertArrayHasKey('categoryId', $foreignKeys);
        $this->assertInstanceOf(MtFk::class, $foreignKeys['userId']);
        $this->assertInstanceOf(MtFk::class, $foreignKeys['categoryId']);
    }

    public function testGetForeignKeyReturnsCorrectForeignKey(): void
    {
        $userIdFk = $this->foreignKeyMapping->getForeignKey(EntityWithForeignKeys::class, 'userId');
        $categoryIdFk = $this->foreignKeyMapping->getForeignKey(EntityWithForeignKeys::class, 'categoryId');
        
        $this->assertInstanceOf(MtFk::class, $userIdFk);
        $this->assertInstanceOf(MtFk::class, $categoryIdFk);
        
        $this->assertEquals('users', $userIdFk->referencedTable);
        $this->assertEquals('categories', $categoryIdFk->referencedTable);
    }

    public function testGetForeignKeyReturnsNullForNonExistentProperty(): void
    {
        $foreignKey = $this->foreignKeyMapping->getForeignKey(EntityWithForeignKeys::class, 'nonExistent');
        
        $this->assertNull($foreignKey);
    }

    public function testGetConstraintNameReturnsCorrectConstraintName(): void
    {
        $this->entityProcessor->expects($this->once())
            ->method('getEntities')
            ->willReturn([EntityWithForeignKeys::class]);
        
        $this->entityProcessor->expects($this->once())
            ->method('getColumnName')
            ->with(EntityWithForeignKeys::class, 'userId')
            ->willReturn('user_id');
        
        $this->entityProcessor->expects($this->once())
            ->method('getTableName')
            ->with(EntityWithForeignKeys::class)
            ->willReturn('posts');
        
        $constraintName = $this->foreignKeyMapping->getConstraintName(EntityWithForeignKeys::class, 'userId');
        
        $this->assertEquals('fk_posts_user_id_users', $constraintName);
    }

    public function testGetConstraintNameReturnsNullWhenForeignKeyNotFound(): void
    {
        $constraintName = $this->foreignKeyMapping->getConstraintName(EntityWithForeignKeys::class, 'nonExistent');
        
        $this->assertNull($constraintName);
    }

    public function testGetConstraintNameReturnsNullWhenDbMappingReturnsNull(): void
    {
        $this->entityProcessor->expects($this->once())
            ->method('getEntities')
            ->willReturn([EntityWithForeignKeys::class]);
        
        $this->entityProcessor->expects($this->once())
            ->method('getColumnName')
            ->with(EntityWithForeignKeys::class, 'userId')
            ->willReturn(null);
        
        $constraintName = $this->foreignKeyMapping->getConstraintName(EntityWithForeignKeys::class, 'userId');
        
        $this->assertNull($constraintName);
    }

    public function testGetReferencedTableReturnsCorrectTable(): void
    {
        $userTable = $this->foreignKeyMapping->getReferencedTable(EntityWithForeignKeys::class, 'userId');
        $categoryTable = $this->foreignKeyMapping->getReferencedTable(EntityWithForeignKeys::class, 'categoryId');
        
        $this->assertEquals('users', $userTable);
        $this->assertEquals('categories', $categoryTable);
    }

    public function testGetReferencedTableReturnsNullForNonExistentProperty(): void
    {
        $table = $this->foreignKeyMapping->getReferencedTable(EntityWithForeignKeys::class, 'nonExistent');
        
        $this->assertNull($table);
    }

    public function testGetReferencedColumnReturnsCorrectColumn(): void
    {
        $userColumn = $this->foreignKeyMapping->getReferencedColumn(EntityWithForeignKeys::class, 'userId');
        $categoryColumn = $this->foreignKeyMapping->getReferencedColumn(EntityWithForeignKeys::class, 'categoryId');
        
        $this->assertEquals('id', $userColumn);
        $this->assertEquals('id', $categoryColumn);
    }

    public function testGetReferencedColumnReturnsNullForNonExistentProperty(): void
    {
        $column = $this->foreignKeyMapping->getReferencedColumn(EntityWithForeignKeys::class, 'nonExistent');
        
        $this->assertNull($column);
    }

    public function testGetDeleteRuleReturnsCorrectRule(): void
    {
        $userDeleteRule = $this->foreignKeyMapping->getDeleteRule(EntityWithForeignKeys::class, 'userId');
        $categoryDeleteRule = $this->foreignKeyMapping->getDeleteRule(EntityWithForeignKeys::class, 'categoryId');
        
        $this->assertEquals(FkRule::CASCADE, $userDeleteRule);
        $this->assertEquals(FkRule::SET_NULL, $categoryDeleteRule);
    }

    public function testGetDeleteRuleReturnsNullForNonExistentProperty(): void
    {
        $deleteRule = $this->foreignKeyMapping->getDeleteRule(EntityWithForeignKeys::class, 'nonExistent');
        
        $this->assertNull($deleteRule);
    }

    public function testGetUpdateRuleReturnsCorrectRule(): void
    {
        $userUpdateRule = $this->foreignKeyMapping->getUpdateRule(EntityWithForeignKeys::class, 'userId');
        $categoryUpdateRule = $this->foreignKeyMapping->getUpdateRule(EntityWithForeignKeys::class, 'categoryId');
        
        $this->assertEquals(FkRule::RESTRICT, $userUpdateRule);
        $this->assertEquals(FkRule::CASCADE, $categoryUpdateRule);
    }

    public function testGetUpdateRuleReturnsNullForNonExistentProperty(): void
    {
        $updateRule = $this->foreignKeyMapping->getUpdateRule(EntityWithForeignKeys::class, 'nonExistent');
        
        $this->assertNull($updateRule);
    }

    public function testGetConstraintNameWithCustomConstraintName(): void
    {
        $constraintName = $this->foreignKeyMapping->getConstraintName(EntityWithCustomConstraint::class, 'authorId');
        
        // Should return null because the custom constraint name is set, but the method doesn't use it
        // The actual constraint name would come from the attribute itself
        $this->assertNull($constraintName);
    }

    public function testForeignKeyMappingWithNullReferencedTable(): void
    {
        // First, we need to mock the entityProcessor to have the entity loaded
        $this->entityProcessor->expects($this->once())
            ->method('getEntities')
            ->willReturn([EntityWithNullReferencedTable::class]);
        
        $constraintName = $this->foreignKeyMapping->getConstraintName(EntityWithNullReferencedTable::class, 'someId');
        $referencedTable = $this->foreignKeyMapping->getReferencedTable(EntityWithNullReferencedTable::class, 'someId');
        $referencedColumn = $this->foreignKeyMapping->getReferencedColumn(EntityWithNullReferencedTable::class, 'someId');
        $deleteRule = $this->foreignKeyMapping->getDeleteRule(EntityWithNullReferencedTable::class, 'someId');
        $updateRule = $this->foreignKeyMapping->getUpdateRule(EntityWithNullReferencedTable::class, 'someId');
        
        $this->assertNull($constraintName);
        $this->assertNull($referencedTable);
        $this->assertNull($referencedColumn);
        $this->assertNull($deleteRule);
        $this->assertNull($updateRule);
    }
}
