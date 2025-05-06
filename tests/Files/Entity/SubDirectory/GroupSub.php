<?php

namespace MulerTech\Database\Tests\Files\Entity\SubDirectory;

use MulerTech\Database\Mapping\ColumnKey;
use MulerTech\Database\Mapping\ColumnType;
use MulerTech\Database\Mapping\MtColumn;
use MulerTech\Database\Mapping\MtEntity;

/**
 * Class Groups
 * @package MulerTech\Database\Tests\Files
 * @author Sébastien Muler
 */
#[MtEntity]
class GroupSub
{
    #[MtColumn(columnType: ColumnType::INT, unsigned: true, isNullable: false, extra: "auto_increment", columnKey: ColumnKey::PRIMARY_KEY)]
    private ?int $id = null;
}