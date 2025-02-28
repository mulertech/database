<?php

namespace MulerTech\Database\Tests\Files\Entity;

use MulerTech\Database\Mapping\MtColumn;
use MulerTech\Database\Mapping\MtEntity;
use MulerTech\Database\Mapping\MtFk;
use MulerTech\Database\Tests\Files\ParentUserRepository;

/**
 * Class ParentUser
 * @package MulerTech\Database\Tests\Files
 * @author Sébastien Muler
 */
#[MtEntity(repository: ParentUserRepository::class, tableName: "parent_users_test", autoIncrement: 1)]
class ParentUser
{
    #[MtColumn(columnType: "int unsigned", isNullable: false, extra: "auto_increment", columnKey: MtColumn::PRIMARY_KEY)]
    private ?int $id = null;

    #[MtColumn(columnType: "varchar(255)", isNullable: false, columnDefault: "Jack")]
    private ?string $username = null;

    /**
     * @var int|null $unit
     */
    #[MtColumn(columnName: "unit_id", columnType: "int unsigned", isNullable: false, columnKey: MtColumn::MULTIPLE_KEY)]
    #[MtFk(referencedTable: Unit::class, referencedColumn: "id", deleteRule: MtFk::RESTRICT, updateRule: MtFk::CASCADE)]
    private ?int $unit = null;
}