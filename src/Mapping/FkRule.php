<?php

namespace MulerTech\Database\Mapping;

/**
 * Enum FkRule
 * @package MulerTech\Database\Mapping
 * @author Sébastien Muler
 */
enum FkRule: string
{
    case CASCADE = 'CASCADE';
    case SET_NULL = 'SET NULL';
    case NO_ACTION = 'NO ACTION';
    case RESTRICT = 'RESTRICT';
    case SET_DEFAULT = 'SET DEFAULT';
}