<?php

declare(strict_types=1);

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
    /**
     * MtFk constructor.
     * @param string|null $constraintName
     * @param class-string|null $referencedTable
     * @param string|null $referencedColumn
     * @param FkRule|null $deleteRule
     * @param FkRule|null $updateRule
     */
    public function __construct(
        public string|null $constraintName = null,
        public string|null $referencedTable = null,
        public string|null $referencedColumn = null,
        public FkRule|null $deleteRule = null,
        public FkRule|null $updateRule = null
    ) {
    }
}
