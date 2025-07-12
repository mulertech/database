<?php

namespace MulerTech\Database\Tests\Files\EntityNotMapped;

use MulerTech\Database\Mapping\ColumnKey;
use MulerTech\Database\Mapping\ColumnType;
use MulerTech\Database\Mapping\MtColumn;
use MulerTech\Database\Mapping\MtEntity;
use MulerTech\Database\Mapping\MtFk;
use MulerTech\Database\Tests\Files\Repository\UserRepository;

/**
 * Class User
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[MtEntity(repository: UserRepository::class, tableName: "users_test", autoIncrement: 100)]
class User
{
    #[MtColumn(columnType: ColumnType::INT, unsigned: true, isNullable: false, extra: "auto_increment", columnKey: ColumnKey::PRIMARY_KEY)]
    private ?int $id = null;

    #[MtColumn(columnName: "account_balance", columnType: ColumnType::FLOAT, length: 10, scale: "2", isNullable: true)]
    #[MtFk(referencedTable: NotTable::class)]
    private ?float $accountBalance = null;
}