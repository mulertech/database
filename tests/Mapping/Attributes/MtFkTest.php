<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping\Attributes;

use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Types\FkRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(MtFk::class)]
class MtFkTest extends TestCase
{
    public function testConstructorWithDefaultValues(): void
    {
        $fk = new MtFk();
        
        $this->assertNull($fk->constraintName);
        $this->assertNull($fk->column);
        $this->assertNull($fk->referencedTable);
        $this->assertNull($fk->referencedColumn);
        $this->assertNull($fk->deleteRule);
        $this->assertNull($fk->updateRule);
    }

    public function testConstructorWithAllParameters(): void
    {
        $fk = new MtFk(
            constraintName: 'fk_user_id',
            column: 'user_id',
            referencedTable: 'users',
            referencedColumn: 'id',
            deleteRule: FkRule::CASCADE,
            updateRule: FkRule::RESTRICT
        );
        
        $this->assertEquals('fk_user_id', $fk->constraintName);
        $this->assertEquals('user_id', $fk->column);
        $this->assertEquals('users', $fk->referencedTable);
        $this->assertEquals('id', $fk->referencedColumn);
        $this->assertEquals(FkRule::CASCADE, $fk->deleteRule);
        $this->assertEquals(FkRule::RESTRICT, $fk->updateRule);
    }

    public function testAttributeTargetsProperty(): void
    {
        $reflection = new ReflectionClass(MtFk::class);
        $attributes = $reflection->getAttributes();
        
        $this->assertCount(1, $attributes);
        $this->assertEquals(\Attribute::class, $attributes[0]->getName());
        
        $attributeInstance = $attributes[0]->newInstance();
        $this->assertEquals(\Attribute::TARGET_PROPERTY, $attributeInstance->flags);
    }

    public function testConstructorWithPartialParameters(): void
    {
        $fk = new MtFk(
            column: 'category_id',
            referencedTable: 'categories',
            referencedColumn: 'id'
        );
        
        $this->assertNull($fk->constraintName);
        $this->assertEquals('category_id', $fk->column);
        $this->assertEquals('categories', $fk->referencedTable);
        $this->assertEquals('id', $fk->referencedColumn);
        $this->assertNull($fk->deleteRule);
        $this->assertNull($fk->updateRule);
    }

    public function testForeignKeyWithCascadeDeleteRule(): void
    {
        $fk = new MtFk(
            constraintName: 'fk_order_user',
            column: 'user_id',
            referencedTable: 'users',
            referencedColumn: 'id',
            deleteRule: FkRule::CASCADE
        );
        
        $this->assertEquals('fk_order_user', $fk->constraintName);
        $this->assertEquals('user_id', $fk->column);
        $this->assertEquals('users', $fk->referencedTable);
        $this->assertEquals('id', $fk->referencedColumn);
        $this->assertEquals(FkRule::CASCADE, $fk->deleteRule);
        $this->assertNull($fk->updateRule);
    }

    public function testForeignKeyWithSetNullRule(): void
    {
        $fk = new MtFk(
            column: 'manager_id',
            referencedTable: 'employees',
            referencedColumn: 'id',
            deleteRule: FkRule::SET_NULL,
            updateRule: FkRule::SET_NULL
        );
        
        $this->assertEquals('manager_id', $fk->column);
        $this->assertEquals('employees', $fk->referencedTable);
        $this->assertEquals('id', $fk->referencedColumn);
        $this->assertEquals(FkRule::SET_NULL, $fk->deleteRule);
        $this->assertEquals(FkRule::SET_NULL, $fk->updateRule);
    }

    public function testForeignKeyWithRestrictRule(): void
    {
        $fk = new MtFk(
            constraintName: 'fk_product_category',
            column: 'category_id',
            referencedTable: 'categories',
            referencedColumn: 'id',
            deleteRule: FkRule::RESTRICT,
            updateRule: FkRule::RESTRICT
        );
        
        $this->assertEquals(FkRule::RESTRICT, $fk->deleteRule);
        $this->assertEquals(FkRule::RESTRICT, $fk->updateRule);
    }

    public function testForeignKeyWithNoActionRule(): void
    {
        $fk = new MtFk(
            column: 'parent_id',
            referencedTable: 'categories',
            referencedColumn: 'id',
            deleteRule: FkRule::NO_ACTION,
            updateRule: FkRule::NO_ACTION
        );
        
        $this->assertEquals(FkRule::NO_ACTION, $fk->deleteRule);
        $this->assertEquals(FkRule::NO_ACTION, $fk->updateRule);
    }

    public function testForeignKeyWithMixedRules(): void
    {
        $fk = new MtFk(
            constraintName: 'fk_mixed_rules',
            column: 'ref_id',
            referencedTable: 'reference_table',
            referencedColumn: 'id',
            deleteRule: FkRule::CASCADE,
            updateRule: FkRule::RESTRICT
        );
        
        $this->assertEquals(FkRule::CASCADE, $fk->deleteRule);
        $this->assertEquals(FkRule::RESTRICT, $fk->updateRule);
    }

    public function testForeignKeyWithoutConstraintName(): void
    {
        $fk = new MtFk(
            column: 'author_id',
            referencedTable: 'authors',
            referencedColumn: 'id'
        );
        
        $this->assertNull($fk->constraintName);
        $this->assertEquals('author_id', $fk->column);
        $this->assertEquals('authors', $fk->referencedTable);
        $this->assertEquals('id', $fk->referencedColumn);
    }
}