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
    public const string CASCADE = 'CASCADE';
    public const string SET_NULL = 'SET NULL';
    public const string NO_ACTION = 'NO ACTION';
    public const string RESTRICT = 'RESTRICT';
    public const string SET_DEFAULT = 'SET DEFAULT';

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