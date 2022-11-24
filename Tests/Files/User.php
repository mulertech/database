<?php

namespace mtphp\Database\Tests\Files;

use mtphp\Database\Mapping\MtEntity;
use mtphp\Database\Mapping\MtColumn;
use mtphp\Database\Mapping\MtFk;

/**
 * Class User
 * @package mtphp\Database\Tests\Files
 * @author Sébastien Muler
 * @MtEntity (tableName="users", repository=UserRepository::class)
 */
class User
{

    /**
     * @MtColumn(columnType="int unsigned", isNullable=false, extra="auto_increment", columnKey="PRI")
     */
    private $id;
    /**
     * @MtColumn(columnType="varchar(255)", isNullable=false)
     */
    private $username;
    /**
     * @var int $unit
     * @MtColumn(columnName="unit_id", columnType="int unsigned", isNullable=false, columnKey="MUL")
     * @MtFk(referencedTable="units", referencedColumn="id", deleteRule="RESTRICT", updateRule="CASCADE")
     */
    private $unit;
    /**
     * Test of a variable which is not a column in the database
     * @var int $group
     */
    private $group;


}