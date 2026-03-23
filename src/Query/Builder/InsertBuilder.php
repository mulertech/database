<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder;

use MulerTech\Database\Query\Builder\Traits\QueryOptionsTrait;
use MulerTech\Database\Query\Builder\Traits\ValidationTrait;

/**
 * Class InsertBuilder.
 *
 * INSERT query builder with batch operation support
 *
 * @author Sébastien Muler
 */
class InsertBuilder extends AbstractQueryBuilder
{
    use QueryOptionsTrait;
    use ValidationTrait;

    private string $table = '';

    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $batchValues = [];

    private bool $replace = false;

    /**
     * @var array<string, mixed>
     */
    private array $onDuplicateUpdate = [];

    private ?SelectBuilder $selectQuery = null;

    public function into(string $table): self
    {
        $this->validateTableName($table);
        $this->table = $table;
        $this->isDirty = true;

        return $this;
    }

    public function set(string $column, mixed $value, ?int $type = \PDO::PARAM_STR): self
    {
        $this->validateColumnName($column);

        if ($value instanceof Raw) {
            $this->values[$column] = $value->getValue();

            return $this;
        }

        $this->values[$column] = $this->parameterBag->add($value, $type);
        $this->isDirty = true;

        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $batchData
     */
    public function batchValues(array $batchData): self
    {
        $this->validateNotEmpty($batchData, 'Batch data');

        $this->batchValues = $this->normalizeBatchData($batchData);
        $this->isDirty = true;

        return $this;
    }

    /**
     * Enable REPLACE option.
     */
    public function replace(): self
    {
        $this->replace = true;
        $this->ignore = false; // Cannot use both IGNORE and REPLACE
        $this->isDirty = true;

        return $this;
    }

    /**
     * Disable REPLACE option.
     */
    public function withoutReplace(): self
    {
        $this->replace = false;
        $this->isDirty = true;

        return $this;
    }

    /**
     * @param array<string, mixed> $updates
     */
    public function onDuplicateKeyUpdate(array $updates): self
    {
        $this->onDuplicateUpdate = $updates;
        $this->isDirty = true;

        return $this;
    }

    /**
     * @param array<string> $columns
     */
    public function fromSelect(SelectBuilder $selectQuery, array $columns = []): self
    {
        $this->selectQuery = $selectQuery;
        if (!empty($columns)) {
            $this->validateColumnNames($columns);
            $this->values = array_fill_keys($columns, null);
        }
        $this->isDirty = true;

        return $this;
    }

    protected function buildSql(): string
    {
        $this->validateNotEmpty($this->table, 'Table name');

        $sql = $this->getInsertKeyword().' INTO '.$this->formatIdentifier($this->table);

        if (null !== $this->selectQuery) {
            return $this->buildInsertFromSelect($sql);
        }

        if (!empty($this->batchValues)) {
            return $this->buildBatchInsert($sql);
        }

        if (!empty($this->values)) {
            return $this->buildSingleInsert($sql);
        }

        throw new \RuntimeException('No values specified for INSERT');
    }

    public function getQueryType(): string
    {
        return $this->replace ? 'REPLACE' : 'INSERT';
    }

    /**
     * Normalize batch data to ensure all rows have the same columns.
     *
     * @param array<int, array<string, mixed>> $batchData
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBatchData(array $batchData): array
    {
        $allColumns = $this->extractAllColumns($batchData);

        return array_map(static function (array $row) use ($allColumns) {
            $normalizedRow = [];
            foreach ($allColumns as $col) {
                $value = $row[$col] ?? null;
                $normalizedRow[$col] = $value instanceof Raw ? $value->getValue() : $value;
            }

            return $normalizedRow;
        }, $batchData);
    }

    /**
     * Extract all unique columns from batch data.
     *
     * @param array<int, array<string, mixed>> $batchData
     *
     * @return array<string>
     */
    private function extractAllColumns(array $batchData): array
    {
        return array_unique(array_merge(...array_map('array_keys', $batchData)));
    }

    private function getInsertKeyword(): string
    {
        if ($this->replace) {
            return 'REPLACE';
        }

        $keyword = 'INSERT';
        $modifiers = $this->buildQueryModifiers();

        if (!empty($modifiers)) {
            $keyword .= ' '.implode(' ', $modifiers);
        }

        return $keyword;
    }

    private function buildSingleInsert(string $sql): string
    {
        $columns = array_keys($this->values);
        $sql .= ' ('.implode(', ', array_map([$this, 'formatIdentifier'], $columns)).')';
        $sql .= ' VALUES ('.implode(', ', array_values($this->values)).')';

        return $this->addOnDuplicateKeyUpdate($sql);
    }

    private function buildBatchInsert(string $sql): string
    {
        $columns = array_keys($this->batchValues[0]);
        $sql .= ' ('.implode(', ', array_map([$this, 'formatIdentifier'], $columns)).')';
        $sql .= ' VALUES '.$this->buildBatchValues($columns);

        return $this->addOnDuplicateKeyUpdate($sql);
    }

    /**
     * Build batch values string.
     *
     * @param array<string> $columns
     */
    private function buildBatchValues(array $columns): string
    {
        $valueGroups = [];
        foreach ($this->batchValues as $row) {
            $valueParts = [];
            foreach ($columns as $column) {
                $valueParts[] = $this->parameterBag->add($row[$column]);
            }
            $valueGroups[] = '('.implode(', ', $valueParts).')';
        }

        return implode(', ', $valueGroups);
    }

    private function buildInsertFromSelect(string $sql): string
    {
        if (!empty($this->values)) {
            $columns = array_keys($this->values);
            $sql .= ' ('.implode(', ', array_map([$this, 'formatIdentifier'], $columns)).')';
        }

        if ($this->selectQuery instanceof SelectBuilder) {
            $this->selectQuery->setParameterBag($this->parameterBag);
            $sql .= ' '.$this->selectQuery->toSql();
        }

        return $this->addOnDuplicateKeyUpdate($sql);
    }

    private function addOnDuplicateKeyUpdate(string $sql): string
    {
        if (empty($this->onDuplicateUpdate) || $this->replace) {
            return $sql;
        }

        $sql .= ' ON DUPLICATE KEY UPDATE ';
        $updateParts = [];

        foreach ($this->onDuplicateUpdate as $column => $value) {
            $updateParts[] = $this->buildUpdatePart($column, $value);
        }

        return $sql.implode(', ', $updateParts);
    }

    /**
     * Build individual update part for ON DUPLICATE KEY UPDATE.
     */
    private function buildUpdatePart(string $column, mixed $value): string
    {
        $escapedColumn = $this->formatIdentifier($column);

        if ('VALUES' === $value) {
            return "$escapedColumn = VALUES($escapedColumn)";
        }

        if ($value instanceof Raw) {
            return "$escapedColumn = ".$value->getValue();
        }

        return "$escapedColumn = ".$this->parameterBag->add($value);
    }
}
