<?php

namespace MulerTech\Database\Tests\Mapping;

use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\Mapping\Attributes\MtManyToOne;
use MulerTech\Database\Mapping\Attributes\MtOneToMany;
use MulerTech\Database\Mapping\Attributes\MtOneToOne;
use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\Mapping\Types\FkRule;
use MulerTech\Database\Tests\Files\Entity\Group;
use MulerTech\Database\Tests\Files\Entity\GroupUser;
use MulerTech\Database\Tests\Files\Entity\SameTableName;
use MulerTech\Database\Tests\Files\Entity\SubDirectory\GroupSub;
use MulerTech\Database\Tests\Files\Entity\Unit;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\WithoutMapping;
use MulerTech\Database\Tests\Files\EntityNotMapped\User as UserWithoutMapping;
use MulerTech\Database\Tests\Files\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class DbMappingTest extends TestCase
{
    /**
     * @var DbMapping|null
     */
    private ?DbMapping $dbMapping = null;

    /**
     * @return DbMapping
     */
    private function getDbMapping(): DbMapping
    {
        if ($this->dbMapping === null) {
            $metadataCache = new MetadataCache();
            // Load entities from test directory
            $metadataCache->loadEntitiesFromPath(
                dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
            );
            $this->dbMapping = new DbMapping($metadataCache);
        }
        return $this->dbMapping;
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
        $this->assertEquals('same_table_name', $this->getDbMapping()->getTableName(SameTableName::class));
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
            ['group_sub', 'groups_test', 'link_user_group_test', 'same_table_name', 'units_test', 'users_test'],
            $dbMapping->getTables()
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testTablesWithEmptyDirectory(): void
    {
        $metadataCache = new MetadataCache();
        $dbMapping = new DbMapping($metadataCache);
        $this->assertEquals([], $dbMapping->getTables());
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetEntities(): void
    {
        $this->assertEquals(
            [Group::class, GroupUser::class, SameTableName::class, GroupSub::class, Unit::class, User::class],
            $this->getDbMapping()->getEntities()
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetEntitiesWithEmptyDirectory(): void
    {
        $metadataCache = new MetadataCache();
        $dbMapping = new DbMapping($metadataCache);
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
        $this->assertEquals(
            [
                'id',
                'username',
                'size',
                'account_balance',
                'unit_id',
                'age',
                'score',
                'views',
                'big_number',
                'decimal_value',
                'double_value',
                'char_code',
                'description',
                'tiny_text',
                'medium_text',
                'long_text',
                'binary_data',
                'varbinary_data',
                'blob_data',
                'tiny_blob',
                'medium_blob',
                'long_blob',
                'birth_date',
                'created_at',
                'updated_at',
                'work_time',
                'birth_year',
                'is_active',
                'is_verified',
                'status',
                'permissions',
                'metadata',
                'location',
                'coordinates',
                'path',
                'area',
                'manager'
            ],
            $this->getDbMapping()->getColumns(User::class)
        );
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
        $this->assertEquals([
            'id' => 'id',
            'username' => 'username',
            'size' => 'size',
            'accountBalance' => 'account_balance',
            'unit' => 'unit_id',
            'age' => 'age',
            'score' => 'score',
            'views' => 'views',
            'bigNumber' => 'big_number',
            'decimalValue' => 'decimal_value',
            'doubleValue' => 'double_value',
            'charCode' => 'char_code',
            'description' => 'description',
            'tinyText' => 'tiny_text',
            'mediumText' => 'medium_text',
            'longText' => 'long_text',
            'binaryData' => 'binary_data',
            'varbinaryData' => 'varbinary_data',
            'blobData' => 'blob_data',
            'tinyBlob' => 'tiny_blob',
            'mediumBlob' => 'medium_blob',
            'longBlob' => 'long_blob',
            'birthDate' => 'birth_date',
            'createdAt' => 'created_at',
            'updatedAt' => 'updated_at',
            'workTime' => 'work_time',
            'birthYear' => 'birth_year',
            'isActive' => 'is_active',
            'isVerified' => 'is_verified',
            'status' => 'status',
            'permissions' => 'permissions',
            'metadata' => 'metadata',
            'location' => 'location',
            'coordinates' => 'coordinates',
            'path' => 'path',
            'area' => 'area',
            'manager' => 'manager'
        ], $propertiesColumns);
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
            ColumnType::INT,
            $this->getDbMapping()->getColumnType(User::class, 'id')
        );
    }

    public function testGetColumnLengthOfUsername(): void
    {
        $this->assertEquals(
            255,
            $this->getDbMapping()->getColumnLength(User::class, 'username')
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
     * @return void
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfFloat(): void
    {
        $this->assertEquals(
            ColumnType::FLOAT,
            $this->getDbMapping()->getColumnType(User::class, 'accountBalance')
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
            referencedTable: 'units_test',
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
        $this->assertNull($this->getDbMapping()->getConstraintName(User::class, 'id'));
        $this->assertNull(
            $this->getDbMapping()->getConstraintName(UserWithoutMapping::class, 'accountBalance')
        );
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
            FkRule::RESTRICT,
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
            FkRule::CASCADE,
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
            [
                'unit' => new MtOneToOne(targetEntity: Unit::class),
                'manager' => new MtOneToOne(targetEntity: User::class),
            ],
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
            ['children' => new MtOneToMany(targetEntity: Group::class, inverseJoinProperty: 'parent')],
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
            targetEntity: Group::class,
            mappedBy: GroupUser::class,
            joinProperty: 'user',
            inverseJoinProperty: 'group'
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

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testIsUnsigned(): void
    {
        $this->assertTrue($this->getDbMapping()->isUnsigned(User::class, 'id'));
        $this->assertFalse($this->getDbMapping()->isUnsigned(User::class, 'username'));
    }

    /**
     * Test des nouveaux types de colonnes ajoutés
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfTinyInt(): void
    {
        $this->assertEquals(
            ColumnType::TINYINT,
            $this->getDbMapping()->getColumnType(User::class, 'age')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfSmallInt(): void
    {
        $this->assertEquals(
            ColumnType::SMALLINT,
            $this->getDbMapping()->getColumnType(User::class, 'score')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfMediumInt(): void
    {
        $this->assertEquals(
            ColumnType::MEDIUMINT,
            $this->getDbMapping()->getColumnType(User::class, 'views')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfBigInt(): void
    {
        $this->assertEquals(
            ColumnType::BIGINT,
            $this->getDbMapping()->getColumnType(User::class, 'bigNumber')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfDecimal(): void
    {
        $this->assertEquals(
            ColumnType::DECIMAL,
            $this->getDbMapping()->getColumnType(User::class, 'decimalValue')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfDouble(): void
    {
        $this->assertEquals(
            ColumnType::DOUBLE,
            $this->getDbMapping()->getColumnType(User::class, 'doubleValue')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfChar(): void
    {
        $this->assertEquals(
            ColumnType::CHAR,
            $this->getDbMapping()->getColumnType(User::class, 'charCode')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfText(): void
    {
        $this->assertEquals(
            ColumnType::TEXT,
            $this->getDbMapping()->getColumnType(User::class, 'description')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfTinyText(): void
    {
        $this->assertEquals(
            ColumnType::TINYTEXT,
            $this->getDbMapping()->getColumnType(User::class, 'tinyText')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfMediumText(): void
    {
        $this->assertEquals(
            ColumnType::MEDIUMTEXT,
            $this->getDbMapping()->getColumnType(User::class, 'mediumText')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfLongText(): void
    {
        $this->assertEquals(
            ColumnType::LONGTEXT,
            $this->getDbMapping()->getColumnType(User::class, 'longText')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfBinary(): void
    {
        $this->assertEquals(
            ColumnType::BINARY,
            $this->getDbMapping()->getColumnType(User::class, 'binaryData')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfVarBinary(): void
    {
        $this->assertEquals(
            ColumnType::VARBINARY,
            $this->getDbMapping()->getColumnType(User::class, 'varbinaryData')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfBlob(): void
    {
        $this->assertEquals(
            ColumnType::BLOB,
            $this->getDbMapping()->getColumnType(User::class, 'blobData')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfTinyBlob(): void
    {
        $this->assertEquals(
            ColumnType::TINYBLOB,
            $this->getDbMapping()->getColumnType(User::class, 'tinyBlob')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfMediumBlob(): void
    {
        $this->assertEquals(
            ColumnType::MEDIUMBLOB,
            $this->getDbMapping()->getColumnType(User::class, 'mediumBlob')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfLongBlob(): void
    {
        $this->assertEquals(
            ColumnType::LONGBLOB,
            $this->getDbMapping()->getColumnType(User::class, 'longBlob')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfDate(): void
    {
        $this->assertEquals(
            ColumnType::DATE,
            $this->getDbMapping()->getColumnType(User::class, 'birthDate')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfDateTime(): void
    {
        $this->assertEquals(
            ColumnType::DATETIME,
            $this->getDbMapping()->getColumnType(User::class, 'createdAt')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfTimestamp(): void
    {
        $this->assertEquals(
            ColumnType::TIMESTAMP,
            $this->getDbMapping()->getColumnType(User::class, 'updatedAt')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfTime(): void
    {
        $this->assertEquals(
            ColumnType::TIME,
            $this->getDbMapping()->getColumnType(User::class, 'workTime')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfYear(): void
    {
        $this->assertEquals(
            ColumnType::YEAR,
            $this->getDbMapping()->getColumnType(User::class, 'birthYear')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfEnum(): void
    {
        $this->assertEquals(
            ColumnType::ENUM,
            $this->getDbMapping()->getColumnType(User::class, 'status')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfSet(): void
    {
        $this->assertEquals(
            ColumnType::SET,
            $this->getDbMapping()->getColumnType(User::class, 'permissions')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfJson(): void
    {
        $this->assertEquals(
            ColumnType::JSON,
            $this->getDbMapping()->getColumnType(User::class, 'metadata')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfGeometry(): void
    {
        $this->assertEquals(
            ColumnType::GEOMETRY,
            $this->getDbMapping()->getColumnType(User::class, 'location')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfPoint(): void
    {
        $this->assertEquals(
            ColumnType::POINT,
            $this->getDbMapping()->getColumnType(User::class, 'coordinates')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfLineString(): void
    {
        $this->assertEquals(
            ColumnType::LINESTRING,
            $this->getDbMapping()->getColumnType(User::class, 'path')
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetColumnTypeOfPolygon(): void
    {
        $this->assertEquals(
            ColumnType::POLYGON,
            $this->getDbMapping()->getColumnType(User::class, 'area')
        );
    }
}