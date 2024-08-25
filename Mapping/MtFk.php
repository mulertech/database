<?php

namespace MulerTech\Database\Mapping;

use Attribute;

/**
 * Class MtFk
 * @package MulerTech\Database\Mapping
 * @author Sébastien Muler
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MtFk
{
    public const CASCADE = 'CASCADE';
    public const SET_NULL = 'SET NULL';
    public const NO_ACTION = 'NO ACTION';
    public const RESTRICT = 'RESTRICT';
    public const SET_DEFAULT = 'SET DEFAULT';

    /**
     * MtFk constructor.
     * @param string|null $constraintName
     * @param string|null $referencedTable
     * @param string|null $referencedColumn
     * @param string|null $deleteRule
     * @param string|null $updateRule
     */
    public function __construct(
        public string|null $constraintName = null,
        public string|null $referencedTable = null,
        public string|null $referencedColumn = null,
        public string|null $deleteRule = null,
        public string|null $updateRule = null
    )
    {}
}