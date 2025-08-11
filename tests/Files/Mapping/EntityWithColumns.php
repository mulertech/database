<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Mapping;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\Mapping\Types\ColumnKey;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EntityWithColumns
{
    #[MtColumn(
        columnType: ColumnType::INT,
        isUnsigned: true,
        isNullable: false,
        extra: 'AUTO_INCREMENT',
        columnKey: ColumnKey::PRIMARY_KEY
    )]
    public int $id;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 100,
        isNullable: false
    )]
    public string $name;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 255,
        isNullable: true
    )]
    public ?string $email;

    public string $noColumnAttribute;
}

