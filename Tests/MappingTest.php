<?php

namespace MulerTech\Database\Tests;

use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\Mapping\MtFk;
use MulerTech\Database\NonRelational\DocumentStore\FileContent\AttributeReader;
use MulerTech\Database\Tests\Files\Entity\Group;
use MulerTech\Database\Tests\Files\Entity\Groups;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\UserRepository;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class MappingTest extends TestCase
{

    /**
     * @return DbMapping
     */
    private function getDbMapping(): DbMapping
    {
        return new DbMapping(new AttributeReader(), __DIR__ . '/Files/Entity');
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testTableNameWithMtEntityMapping(): void
    {
        $this->assertEquals('group', $this->getDbMapping()->getTableName(Group::class));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testTableNameWithoutTableNameSet(): void
    {
        $this->assertEquals('groups', $this->getDbMapping()->getTableName(Groups::class));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testTableNameUpdate(): void
    {
        $this->assertEquals('users', $this->getDbMapping()->getTableName(User::class));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testTables(): void
    {
        $this->assertEquals(['users', 'groups', 'group'], $this->getDbMapping()->getTables());
    }

    /**
     * @return void
     */
    public function testGetEntities(): void
    {
        $this->assertEquals(
            [User::class, Groups::class, Group::class],
            $this->getDbMapping()->getEntities()
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testRepository(): void
    {
        $this->assertEquals(UserRepository::class, $this->getDbMapping()->getRepository(User::class));
    }

    /**
     * @throws ReflectionException
     */
    public function testAutoIncrement(): void
    {
        $this->assertEquals(100, $this->getDbMapping()->getAutoIncrement(User::class));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetColumns(): void
    {
        $this->assertEquals(['id', 'username', 'unit_id'], $this->getDbMapping()->getColumns(User::class));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetPropertiesColumns(): void
    {
        $propertiesColumns = $this->getDbMapping()->getPropertiesColumns(User::class);
        $this->assertEquals(['id' => 'id', 'username' => 'username', 'unit' => 'unit_id'], $propertiesColumns);
        //Find column name with property
        $this->assertEquals('unit_id', $propertiesColumns['unit']);
        //Find property with column name
        $this->assertEquals('unit', array_keys($propertiesColumns, 'unit_id')[0]);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetColumnNameOfUsername(): void
    {
        $this->assertEquals(
            'username',
            $this->getDbMapping()->getColumnName(User::class, 'username')
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetColumnNameOfUnit(): void
    {
        $this->assertEquals(
            'unit_id',
            $this->getDbMapping()->getColumnName(User::class, 'unit')
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetTypeOfId(): void
    {
        $this->assertEquals(
            'int unsigned',
            $this->getDbMapping()->getColumnType(User::class, 'id')
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testIsNullable(): void
    {
        $this->assertFalse($this->getDbMapping()->isNullable(User::class, 'id'));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetExtra(): void
    {
        $this->assertEquals(
            'auto_increment',
            $this->getDbMapping()->getExtra(User::class, 'id')
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetColumnKeyId(): void
    {
        $this->assertEquals('PRI', $this->getDbMapping()->getColumnKey(User::class, 'id'));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetColumnKeyUnit(): void
    {
        $this->assertEquals('MUL', $this->getDbMapping()->getColumnKey(User::class, 'unit'));
    }

    /**
     * @throws ReflectionException
     */
    public function testGetForeignKey(): void
    {
        $mtFk = new MtFk(
            referencedTable: 'units',
            referencedColumn: 'id',
            deleteRule: 'RESTRICT',
            updateRule: 'CASCADE'
        );

        $this->assertEquals(
            $mtFk,
            $this->getDbMapping()->getForeignKey(User::class, 'unit')
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetConstraintName(): void
    {
        $this->assertEquals(
            'fk_users_unit_id_units',
            $this->getDbMapping()->getConstraintName(User::class, 'unit')
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetReferencedTable(): void
    {
        $this->assertEquals(
            'units',
            $this->getDbMapping()->getReferencedTable(User::class, 'unit')
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetReferencedColumn(): void
    {
        $this->assertEquals(
            'id',
            $this->getDbMapping()->getReferencedColumn(User::class, 'unit')
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetDeleteRule(): void
    {
        $this->assertEquals(
            'RESTRICT',
            $this->getDbMapping()->getDeleteRule(User::class, 'unit')
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetUpdateRule(): void
    {
        $this->assertEquals(
            'CASCADE',
            $this->getDbMapping()->getUpdateRule(User::class, 'unit')
        );
    }


}