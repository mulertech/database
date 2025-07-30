<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping\Types;

/**
 * Enum FkRule
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
enum FkRule: string
{
    case CASCADE = 'CASCADE';
    case SET_NULL = 'SET NULL';
    case NO_ACTION = 'NO ACTION';
    case RESTRICT = 'RESTRICT';
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
