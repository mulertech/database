<?php

namespace MulerTech\Database\Tests\Relational\Sql\Schema;

use MulerTech\Database\Mapping\Types\FkRule;
use MulerTech\Database\Schema\Builder\ForeignKeyDefinition;
use PHPUnit\Framework\TestCase;

class ForeignKeyDefinitionTest extends TestCase
{
    /**
     * Test the constructor (no parameters in current implementation)
     */
    public function testConstructor(): void
    {
        $foreignKey = new ForeignKeyDefinition();
        $this->assertInstanceOf(ForeignKeyDefinition::class, $foreignKey);
    }

    /**
     * Test setting a column
     */
    public function testColumn(): void
    {
        $foreignKey = new ForeignKeyDefinition();
        $result = $foreignKey->column('user_id');

        $this->assertSame($foreignKey, $result, 'Method should return $this for chaining');
    }

    /**
     * Test setting referenced table and column
     */
    public function testReferences(): void
    {
        $foreignKey = new ForeignKeyDefinition();
        $result = $foreignKey->references('users', 'id');
        
        $this->assertSame($foreignKey, $result, 'Method should return $this for chaining');
    }

    /**
     * Test setting onDelete rule
     */
    public function testOnDelete(): void
    {
        $foreignKey = new ForeignKeyDefinition();
        $result = $foreignKey->onDelete(FkRule::CASCADE);
        
        $this->assertSame($foreignKey, $result, 'Method should return $this for chaining');
    }

    /**
     * Test setting onUpdate rule
     */
    public function testOnUpdate(): void
    {
        $foreignKey = new ForeignKeyDefinition();
        $result = $foreignKey->onUpdate(FkRule::CASCADE);
        
        $this->assertSame($foreignKey, $result, 'Method should return $this for chaining');
    }

    /**
     * Test method chaining
     */
    public function testForeignKeyChaining(): void
    {
        $foreignKey = new ForeignKeyDefinition();
        $result = $foreignKey
            ->column('user_id')
            ->references('users', 'id')
            ->onDelete(FkRule::CASCADE)
            ->onUpdate(FkRule::NO_ACTION);

        $this->assertSame($foreignKey, $result, 'All methods should return $this for chaining');
    }

    /**
     * Test toSql method with complete definition
     */
    public function testToSqlWithCompleteDefinition(): void
    {
        $foreignKey = new ForeignKeyDefinition();
        $foreignKey
            ->column('user_id')
            ->references('users', 'id')
            ->onDelete(FkRule::CASCADE)
            ->onUpdate(FkRule::RESTRICT);

        $sql = $foreignKey->toSql();

        $this->assertStringContainsString('FOREIGN KEY (`user_id`)', $sql);
        $this->assertStringContainsString('REFERENCES `users`(`id`)', $sql);
        $this->assertStringContainsString('ON UPDATE RESTRICT', $sql);
        $this->assertStringContainsString('ON DELETE CASCADE', $sql);
    }

    /**
     * Test toSql method with SET NULL rule
     */
    public function testToSqlWithSetNull(): void
    {
        $foreignKey = new ForeignKeyDefinition();
        $foreignKey
            ->column('category_id')
            ->references('categories', 'id')
            ->onDelete(FkRule::SET_NULL)
            ->onUpdate(FkRule::CASCADE);

        $sql = $foreignKey->toSql();

        $this->assertStringContainsString('FOREIGN KEY (`category_id`)', $sql);
        $this->assertStringContainsString('REFERENCES `categories`(`id`)', $sql);
        $this->assertStringContainsString('ON UPDATE CASCADE', $sql);
        $this->assertStringContainsString('ON DELETE SET NULL', $sql);
    }

    /**
     * Test toSql method with NO ACTION rule
     */
    public function testToSqlWithNoAction(): void
    {
        $foreignKey = new ForeignKeyDefinition();
        $foreignKey
            ->column('parent_id')
            ->references('same_table', 'id')
            ->onDelete(FkRule::NO_ACTION)
            ->onUpdate(FkRule::NO_ACTION);
        
        $sql = $foreignKey->toSql();

        $this->assertStringContainsString('FOREIGN KEY (`parent_id`)', $sql);
        $this->assertStringContainsString('REFERENCES `same_table`(`id`)', $sql);
        $this->assertStringContainsString('ON UPDATE NO ACTION', $sql);
        $this->assertStringContainsString('ON DELETE NO ACTION', $sql);
    }

    /**
     * Test toSql throws exception when column is missing
     */
    public function testToSqlThrowsExceptionWhenColumnMissing(): void
    {
        $foreignKey = new ForeignKeyDefinition();
        $foreignKey
            ->references('users', 'id')
            ->onDelete(FkRule::CASCADE)
            ->onUpdate(FkRule::RESTRICT);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Foreign key definition is incomplete');

        $foreignKey->toSql();
    }

    /**
     * Test toSql throws exception when referenced table is missing
     */
    public function testToSqlThrowsExceptionWhenReferencedTableMissing(): void
    {
        $foreignKey = new ForeignKeyDefinition();
        $foreignKey
            ->column('user_id')
            ->onDelete(FkRule::CASCADE)
            ->onUpdate(FkRule::RESTRICT);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Foreign key definition is incomplete');

        $foreignKey->toSql();
    }

    /**
     * Test toSql throws exception when referenced column is missing
     */
    public function testToSqlThrowsExceptionWhenReferencedColumnMissing(): void
    {
        $foreignKey = new ForeignKeyDefinition();
        $foreignKey
            ->column('user_id')
            ->onDelete(FkRule::CASCADE)
            ->onUpdate(FkRule::RESTRICT);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Foreign key definition is incomplete');

        $foreignKey->toSql();
    }

    /**
     * Test toSql with RESTRICT rule
     */
    public function testToSqlWithRestrict(): void
    {
        $foreignKey = new ForeignKeyDefinition();
        $foreignKey
            ->column('role_id')
            ->references('roles', 'id')
            ->onDelete(FkRule::RESTRICT)
            ->onUpdate(FkRule::RESTRICT);

        $sql = $foreignKey->toSql();

        $this->assertStringContainsString('FOREIGN KEY (`role_id`)', $sql);
        $this->assertStringContainsString('REFERENCES `roles`(`id`)', $sql);
        $this->assertStringContainsString('ON UPDATE RESTRICT', $sql);
        $this->assertStringContainsString('ON DELETE RESTRICT', $sql);
    }

    /**
     * Test toSql with SET DEFAULT rule
     */
    public function testToSqlWithSetDefault(): void
    {
        $foreignKey = new ForeignKeyDefinition();
        $foreignKey
            ->column('status_id')
            ->references('statuses', 'id')
            ->onDelete(FkRule::SET_DEFAULT)
            ->onUpdate(FkRule::SET_DEFAULT);

        $sql = $foreignKey->toSql();

        $this->assertStringContainsString('FOREIGN KEY (`status_id`)', $sql);
        $this->assertStringContainsString('REFERENCES `statuses`(`id`)', $sql);
        $this->assertStringContainsString('ON UPDATE SET DEFAULT', $sql);
        $this->assertStringContainsString('ON DELETE SET DEFAULT', $sql);
    }

    /**
     * Test complex foreign key with mixed rules
     */
    public function testComplexForeignKeyDefinition(): void
    {
        $foreignKey = new ForeignKeyDefinition();
        $foreignKey
            ->column('organization_id')
            ->references('organizations', 'id')
            ->onDelete(FkRule::CASCADE)
            ->onUpdate(FkRule::SET_NULL);

        $sql = $foreignKey->toSql();

        $this->assertStringContainsString('FOREIGN KEY (`organization_id`)', $sql);
        $this->assertStringContainsString('REFERENCES `organizations`(`id`)', $sql);
        $this->assertStringContainsString('ON UPDATE SET NULL', $sql);
        $this->assertStringContainsString('ON DELETE CASCADE', $sql);
    }
}
