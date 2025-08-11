<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Mapping;

use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EntityWithCustomConstraint
{
    #[MtFk(
        constraintName: 'fk_custom_author',
        column: 'author_id',
        referencedTable: 'authors',
        referencedColumn: 'id'
    )]
    #[MtColumn(columnType: ColumnType::INT)]
    public int $authorId;
}

