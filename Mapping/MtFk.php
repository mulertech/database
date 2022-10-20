<?php

namespace mtphp\Database\Mapping;

/**
 * Class MtFk
 * @package mtphp\Database\Mapping
 * @author Sébastien Muler
 * @Annotation
 */
class MtFk
{

    /**
     * @var string $constraintName
     */
    public $constraintName;
    /**
     * @var string $referencedTable
     */
    public $referencedTable;
    /**
     * @var string $referencedColumn
     */
    public $referencedColumn;
    /**
     * @var string $deleteRule
     */
    public $deleteRule;
    /**
     * @var string $updateRule
     */
    public $updateRule;

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
     * @param mixed $constraintName
     */
    public function setConstraintName($constraintName): void
    {
        $this->constraintName = $constraintName;
    }

    /**
     * @return string|null
     */
    public function getReferencedTable(): ?string
    {
        return $this->referencedTable;
    }

    /**
     * @param mixed $referencedTable
     */
    public function setReferencedTable($referencedTable): void
    {
        $this->referencedTable = $referencedTable;
    }

    /**
     * @return string|null
     */
    public function getReferencedColumn(): ?string
    {
        return $this->referencedColumn;
    }

    /**
     * @param mixed $referencedColumn
     */
    public function setReferencedColumn($referencedColumn): void
    {
        $this->referencedColumn = $referencedColumn;
    }

    /**
     * @return string|null
     */
    public function getDeleteRule(): ?string
    {
        return $this->deleteRule;
    }

    /**
     * @param mixed $deleteRule
     */
    public function setDeleteRule($deleteRule): void
    {
        $this->deleteRule = $deleteRule;
    }

    /**
     * @return string|null
     */
    public function getUpdateRule(): ?string
    {
        return $this->updateRule;
    }

    /**
     * @param mixed $updateRule
     */
    public function setUpdateRule($updateRule): void
    {
        $this->updateRule = $updateRule;
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