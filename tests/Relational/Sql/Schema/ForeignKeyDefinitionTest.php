<?php

namespace MulerTech\Database\Tests\Relational\Sql\Schema;

use MulerTech\Database\Schema\ForeignKeyDefinition;
use MulerTech\Database\Schema\ReferentialAction;
use PHPUnit\Framework\TestCase;

class ForeignKeyDefinitionTest extends TestCase
{
    public function testConstructor(): void
    {
        $foreignKey = new ForeignKeyDefinition('test_fk');
        $this->assertEquals('test_fk', $foreignKey->getName());
    }

    public function testColumnsWithString(): void
    {
        $foreignKey = new ForeignKeyDefinition('test_fk');
        $result = $foreignKey->columns('user_id');
        
        $this->assertSame($foreignKey, $result, 'Method should return $this for chaining');
        $this->assertEquals(['user_id'], $foreignKey->getColumns());
    }

    public function testColumnsWithArray(): void
    {
        $foreignKey = new ForeignKeyDefinition('test_fk');
        $result = $foreignKey->columns(['user_id', 'role_id']);
        
        $this->assertSame($foreignKey, $result, 'Method should return $this for chaining');
        $this->assertEquals(['user_id', 'role_id'], $foreignKey->getColumns());
    }

    public function testReferencesWithStringColumn(): void
    {
        $foreignKey = new ForeignKeyDefinition('test_fk');
        $result = $foreignKey->references('users', 'id');
        
        $this->assertSame($foreignKey, $result, 'Method should return $this for chaining');
        $this->assertEquals('users', $foreignKey->getReferencedTable());
        $this->assertEquals(['id'], $foreignKey->getReferencedColumns());
    }

    public function testReferencesWithArrayColumns(): void
    {
        $foreignKey = new ForeignKeyDefinition('test_fk');
        $result = $foreignKey->references('users_roles', ['user_id', 'role_id']);
        
        $this->assertSame($foreignKey, $result, 'Method should return $this for chaining');
        $this->assertEquals('users_roles', $foreignKey->getReferencedTable());
        $this->assertEquals(['user_id', 'role_id'], $foreignKey->getReferencedColumns());
    }

    public function testOnDelete(): void
    {
        $foreignKey = new ForeignKeyDefinition('test_fk');
        $result = $foreignKey->onDelete(ReferentialAction::CASCADE);
        
        $this->assertSame($foreignKey, $result, 'Method should return $this for chaining');
        $this->assertEquals(ReferentialAction::CASCADE, $foreignKey->getOnDelete());
    }

    public function testOnUpdate(): void
    {
        $foreignKey = new ForeignKeyDefinition('test_fk');
        $result = $foreignKey->onUpdate(ReferentialAction::CASCADE);
        
        $this->assertSame($foreignKey, $result, 'Method should return $this for chaining');
        $this->assertEquals(ReferentialAction::CASCADE, $foreignKey->getOnUpdate());
    }
    
    public function testDefaultActionValues(): void
    {
        $foreignKey = new ForeignKeyDefinition('test_fk');
        
        $this->assertNull($foreignKey->getOnUpdate());
        $this->assertNull($foreignKey->getOnDelete());
    }

    public function testForeignKeyChaining(): void
    {
        $foreignKey = new ForeignKeyDefinition('test_fk');
        $foreignKey
            ->columns(['article_id', 'user_id'])
            ->references('articles_users', ['article_id', 'user_id'])
            ->onDelete(ReferentialAction::CASCADE)
            ->onUpdate(ReferentialAction::NO_ACTION);
        
        $this->assertEquals('test_fk', $foreignKey->getName());
        $this->assertEquals(['article_id', 'user_id'], $foreignKey->getColumns());
        $this->assertEquals('articles_users', $foreignKey->getReferencedTable());
        $this->assertEquals(['article_id', 'user_id'], $foreignKey->getReferencedColumns());
        $this->assertEquals(ReferentialAction::NO_ACTION, $foreignKey->getOnUpdate());
        $this->assertEquals(ReferentialAction::CASCADE, $foreignKey->getOnDelete());
    }

    public function testDropForeignKey(): void
    {
        $foreignKey = new ForeignKeyDefinition('test_fk');
        $result = $foreignKey->setDrop();

        $this->assertSame($foreignKey, $result, 'Method should return $this for chaining');
        $this->assertTrue($foreignKey->isDrop());
    }

    public function testDropForeignKeyWithParameter(): void
    {
        $foreignKey = new ForeignKeyDefinition('test_fk');
        $result = $foreignKey->setDrop(true);

        $this->assertSame($foreignKey, $result, 'Method should return $this for chaining');
        $this->assertTrue($foreignKey->isDrop());
    }
}
