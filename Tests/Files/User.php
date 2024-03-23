<?php

namespace MulerTech\Database\Tests\Files;

use MulerTech\Database\Mapping\MtEntity;
use MulerTech\Database\Mapping\MtColumn;
use MulerTech\Database\Mapping\MtFk;

/**
 * Class User
 * @package MulerTech\Database\Tests\Files
 * @author Sébastien Muler
 * @MtEntity (tableName="users", repository=UserRepository::class, autoIncrement=100)
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