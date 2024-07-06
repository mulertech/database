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
    public function __construct(
        public string|null $constraintName = null,
        public string|null $referencedTable = null,
        public string|null $referencedColumn = null,
        public string|null $deleteRule = null,
        public string|null $updateRule = null
    )
    {}
    /**
     * @param string|null $table
     * @param string|null $column
     * @return string|null
     */
    public function getConstraintName(string $table = null, string $column = null): ?string
    {
        return $this->constraintName ?? $this->generateConstraintName($table, $column);
    }

    /**
     * @param string $table
     * @param string $column
     * @return string
     */
    private function generateConstraintName(string $table, string $column): string
    {
        return 'fk_' . $table . '_' . $column . '_' . $this->referencedTable;
    }

}