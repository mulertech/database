<?php

namespace MulerTech\Database\Tests;

use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\Mapping\FkRule;
use MulerTech\Database\Mapping\MtFk;
use MulerTech\Database\Mapping\MtManyToMany;
use MulerTech\Database\Mapping\MtManyToOne;
use MulerTech\Database\Mapping\MtOneToMany;
use MulerTech\Database\Mapping\MtOneToOne;
use MulerTech\Database\Tests\Files\Entity\Group;
use MulerTech\Database\Tests\Files\Entity\SameTableName;
use MulerTech\Database\Tests\Files\Entity\SubDirectory\GroupSub;
use MulerTech\Database\Tests\Files\Entity\Unit;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\WithoutMapping;
use MulerTech\Database\Tests\Files\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class MappingTest extends TestCase
{
    /**
     * @var DbMapping
     */
    private DbMapping $dbMapping;

    /**
     * @return DbMapping
     */
    private function getDbMapping(): DbMapping
    {
        return $this->dbMapping ?? ($this->dbMapping = new DbMapping(__DIR__ . '/Files/Entity'));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testTableNameWithMtEntityMapping(): void
    {
        $this->assertEquals('groups_test', $this->getDbMapping()->getTableName(Group::class));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testTableNameWithoutTableNameSet(): void
    {
        $this->assertEquals('sametablename', $this->getDbMapping()->getTableName(SameTableName::class));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testTableNameUpdated(): void
    {
        $this->assertEquals('users_test', $this->getDbMapping()->getTableName(User::class));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testTables(): void
    {
        $dbMapping = $this->getDbMapping();
        $this->assertEquals(
            ['groups_test', 'groupsub', 'sametablename', 'units_test', 'users_test'],
            $dbMapping->getTables()
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testTablesWithoutRecursiveDirectory(): void
    {
        $dbMapping = new DbMapping(__DIR__ . '/Files/Entity', false);
        $this->assertEquals(
            ['groups_test', 'sametablename', 'units_test', 'users_test'],
            $dbMapping->getTables()
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testTablesWithEmptyDirectory(): void
    {
        $dbMapping = new DbMapping(__DIR__ . '/Files/Entity/EmptyEntity');
        $this->assertEquals([], $dbMapping->getTables());
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetEntities(): void
    {
        $this->assertEquals(
            [Group::class, SameTableName::class, GroupSub::class, Unit::class, User::class],
            $this->getDbMapping()->getEntities()
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetEntitiesWithEmptyDirectory(): void
    {
        $dbMapping = new DbMapping(__DIR__ . '/Files/Entity/EmptyEntity');
        $this->assertEquals([], $dbMapping->getEntities());
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testRepository(): void
    {
        $this->assertEquals(
            UserRepository::class,
            $this->getDbMapping()->getRepository(User::class)
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testRepositoryWithoutRepositoryIntoMtEntityMapping(): void
    {
        $this->assertEquals(null, $this->getDbMapping()->getRepository(SameTableName::class));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testRepositoryWithoutMtEntityMapping(): void
    {
        $this->expectExceptionMessage(
            'The MtEntity mapping is not implemented into the MulerTech\Database\Tests\Files\Entity\WithoutMapping class.'
        );
        $this->getDbMapping()->getRepository(WithoutMapping::class);
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
    public function testNullAutoIncrement(): void
    {
        $this->assertEquals(null, $this->getDbMapping()->getAutoIncrement(SameTableName::class));
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
    public function testGetColumnsWithoutMtColumnMapping(): void
    {
        $this->assertEquals([], $this->getDbMapping()->getColumns(WithoutMapping::class));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetPropertiesColumns(): void
    {
        $propertiesColumns = $this->getDbMapping()->getPropertiesColumns(User::class);
        $this->assertEquals(['id' => 'id', 'username' => 'username', 'unit' => 'unit_id'], $propertiesColumns);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetPropertiesColumnsWithoutMtColumnMapping(): void
    {
        $this->assertEquals([], $this->getDbMapping()->getPropertiesColumns(WithoutMapping::class));
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
    public function testGetNullColumnName(): void
    {
        $this->assertEquals(
            null,
            $this->getDbMapping()->getColumnName(User::class, 'group')
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
    public function testGetColumnTypeOfNonColumn(): void
    {
        $this->assertEquals(
            null,
            $this->getDbMapping()->getColumnType(User::class, 'group')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfNonExistingColumn(): void
    {
        $this->assertEquals(
            null,
            $this->getDbMapping()->getColumnType(User::class, 'non_existing_column')
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
    public function testNullIsNullable(): void
    {
        $this->assertEquals(null, $this->getDbMapping()->isNullable(User::class, 'group'));
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
    public function testNullGetExtra(): void
    {
        $this->assertEquals(
            null,
            $this->getDbMapping()->getExtra(User::class, 'group')
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetColumnDefault(): void
    {
        $this->assertEquals(
            'John',
            $this->getDbMapping()->getColumnDefault(User::class, 'username')
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetNullColumnDefault(): void
    {
        $this->assertEquals(
            null,
            $this->getDbMapping()->getColumnDefault(User::class, 'id')
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
     * @return void
     * @throws ReflectionException
     */
    public function testGetNullColumnKey(): void
    {
        $this->assertEquals(null, $this->getDbMapping()->getColumnKey(User::class, 'group'));
    }

    /**
     * @throws ReflectionException
     */
    public function testGetForeignKey(): void
    {
        $mtFk = new MtFk(
            referencedTable: Unit::class,
            referencedColumn: 'id',
            deleteRule: FkRule::RESTRICT,
            updateRule: FkRule::CASCADE
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
    public function testGetNullForeignKey(): void
    {
        $this->assertEquals(null, $this->getDbMapping()->getForeignKey(User::class, 'id'));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetConstraintName(): void
    {
        $this->assertEquals(
            'fk_users_test_unit_id_units_test',
            $this->getDbMapping()->getConstraintName(User::class, 'unit')
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetNullConstraintName(): void
    {
        $this->assertEquals(null, $this->getDbMapping()->getConstraintName(User::class, 'id'));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetReferencedTable(): void
    {
        $this->assertEquals(
            'units_test',
            $this->getDbMapping()->getReferencedTable(User::class, 'unit')
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetNullReferencedTable(): void
    {
        $this->assertEquals(null, $this->getDbMapping()->getReferencedTable(User::class, 'id'));
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
    public function testGetNullReferencedColumn(): void
    {
        $this->assertEquals(null, $this->getDbMapping()->getReferencedColumn(User::class, 'id'));
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
    public function testGetNullDeleteRule(): void
    {
        $this->assertEquals(null, $this->getDbMapping()->getDeleteRule(User::class, 'id'));
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

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetNullUpdateRule(): void
    {
        $this->assertEquals(
            null,
            $this->getDbMapping()->getUpdateRule(User::class, 'id')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetOneToOne(): void
    {
        $this->assertEquals(
            ['unit' => new MtOneToOne(targetEntity: Unit::class)],
            $this->getDbMapping()->getOneToOne(User::class)
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetNoOneToOne(): void
    {
        $this->assertEquals([], $this->getDbMapping()->getOneToOne(Unit::class));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetOneToMany(): void
    {
        $this->assertEquals(
            ['children' => new MtOneToMany(targetEntity: Group::class, mappedBy: 'parent_id')],
            $this->getDbMapping()->getOneToMany(Group::class)
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetNoOneToMany(): void
    {
        $this->assertEquals([], $this->getDbMapping()->getOneToMany(Unit::class));
    }

    /**
     * @throws ReflectionException
     */
    public function testGetManyToOne(): void
    {
        $this->assertEquals(
            ['parent' => new MtManyToOne(targetEntity: Group::class)],
            $this->getDbMapping()->getManyToOne(Group::class)
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetNoManyToOne(): void
    {
        $this->assertEquals([], $this->getDbMapping()->getManyToOne(Unit::class));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetManyToMany(): void
    {
        $manyToMany = new MtManyToMany(
            targetEntity:      Group::class,
            joinEntity:        'link_user_group_test',
            joinColumn:        'user_id',
            inverseJoinColumn: 'group_id'
        );
        $this->assertEquals(
            ['groups' => $manyToMany],
            $this->getDbMapping()->getManyToMany(User::class)
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetNoManyToMany(): void
    {
        $this->assertEquals([], $this->getDbMapping()->getManyToMany(Unit::class));
    }
}