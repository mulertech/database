<?php

namespace MulerTech\Database\Relational\Sql\Schema;

/**
 * Enum for foreign key referential actions
 */
enum ReferentialAction: string
{
    case CASCADE = 'CASCADE';
    case SET_NULL = 'SET NULL';
    case RESTRICT = 'RESTRICT';
    case NO_ACTION = 'NO ACTION';
    case SET_DEFAULT = 'SET DEFAULT';

    /**
     * Convert to enum call string for code generation
     * @return string
     */
    public function toEnumCallString(): string
    {
        return sprintf('%s::%s', self::class, $this->name);
    }
}
