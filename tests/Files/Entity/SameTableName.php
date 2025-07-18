<?php

namespace MulerTech\Database\Tests\Files\Entity;

use MulerTech\Database\Mapping\ColumnKey;
use MulerTech\Database\Mapping\Metadata\ColumnType;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;

/**
 * Class Groups
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[MtEntity]
class SameTableName
{
    #[MtColumn(columnType: ColumnType::INT, unsigned: true, isNullable: false, extra: "auto_increment", columnKey: ColumnKey::PRIMARY_KEY)]
    private ?int $id = null;
}