<?php

namespace MulerTech\Database\Query;

use MulerTech\Database\Relational\Sql\Raw;
use PDO;
use RuntimeException;

/**
 * INSERT query builder with batch operation support
 *
 * @package MulerTech\Database\Query
 * @author Sébastien Muler
 */
class InsertBuilder extends AbstractQueryBuilder
{
    /**
     * @var string
     */
    private string $table = '';

    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $batchValues = [];

    /**
     * @var bool
     */
    private bool $ignore = false;

    /**
     * @var bool
     */
    private bool $replace = false;

    /**
     * @var array<string, mixed>
     */
    private array $onDuplicateUpdate = [];

    /**
     * @var SelectBuilder|null
     */
    private ?SelectBuilder $selectQuery = null;

    /**
     * @param string $table
     * @return self
     */
    public function into(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param int $type
     * @return self
     */
    public function set(string $column, mixed $value, ?int $type = PDO::PARAM_STR): self
    {
        if ($value instanceof Raw) {
            $this->values[$column] = $value->getValue();
        } else {
            $this->values[$column] = $type !== null
                ? $this->addNamedParameter($value, $type) : $this->addNamedParameter($value);
        }
        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $batchData
     * @return self
     */
    public function batchValues(array $batchData): self
    {
        if (empty($batchData)) {
            throw new RuntimeException('Batch data cannot be empty');
        }

        // Déterminer la liste complète des colonnes
        $allColumns = [];
        foreach ($batchData as $row) {
            foreach (array_keys($row) as $col) {
                if (!in_array($col, $allColumns, true)) {
                    $allColumns[] = $col;
                }
            }
        }

        // Normaliser chaque ligne pour qu'elle ait toutes les colonnes (avec null si absent)
        $normalizedBatch = [];
        foreach ($batchData as $row) {
            $normalizedRow = [];
            foreach ($allColumns as $col) {
                $value = array_key_exists($col, $row) ? $row[$col] : null;
                // Process each value for parameterization
                if ($value instanceof Raw) {
                    $normalizedRow[$col] = $value->getValue();
                } else {
                    $normalizedRow[$col] = $value; // Will be parameterized in buildBatchInsert
                }
            }
            $normalizedBatch[] = $normalizedRow;
        }

        $this->batchValues = $normalizedBatch;
        return $this;
    }

    /**
     * @param bool $ignore
     * @return self
     */
    public function ignore(bool $ignore = true): self
    {
        $this->ignore = $ignore;
        $this->replace = false; // Cannot use both IGNORE and REPLACE
        return $this;
    }

    /**
     * @param bool $replace
     * @return self
     */
    public function replace(bool $replace = true): self
    {
        $this->replace = $replace;
        $this->ignore = false; // Cannot use both IGNORE and REPLACE
        return $this;
    }

    /**
     * @param array<string, mixed> $updates
     * @return self
     */
    public function onDuplicateKeyUpdate(array $updates): self
    {
        $processedUpdates = [];
        foreach ($updates as $column => $value) {
            if ($value instanceof Raw) {
                $processedUpdates[$column] = $value->getValue();
            } else {
                $processedUpdates[$column] = $value;
            }
        }
        $this->onDuplicateUpdate = $processedUpdates;
        return $this;
    }

    /**
     * @param SelectBuilder $selectQuery
     * @param array<string> $columns
     * @return self
     */
    public function fromSelect(SelectBuilder $selectQuery, array $columns = []): self
    {
        $this->selectQuery = $selectQuery;
        if (!empty($columns)) {
            $this->values = array_fill_keys($columns, null);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->table)) {
            throw new RuntimeException('Table name must be specified');
        }

        $sql = $this->getInsertKeyword() . ' INTO ' . self::escapeIdentifier($this->table);

        if ($this->selectQuery !== null) {
            return $this->buildInsertFromSelect($sql);
        }

        if (!empty($this->batchValues)) {
            return $this->buildBatchInsert($sql);
        }

        if (!empty($this->values)) {
            return $this->buildSingleInsert($sql);
        }

        throw new RuntimeException('No values specified for INSERT');
    }

    /**
     * @return string
     */
    public function getQueryType(): string
    {
        return $this->replace ? 'REPLACE' : 'INSERT';
    }

    /**
     * @return string
     */
    private function getInsertKeyword(): string
    {
        if ($this->replace) {
            return 'REPLACE';
        }

        $keyword = 'INSERT';
        if ($this->ignore) {
            $keyword .= ' IGNORE';
        }

        return $keyword;
    }

    /**
     * @param string $sql
     * @return string
     */
    private function buildSingleInsert(string $sql): string
    {
        $columns = array_keys($this->values);
        $sql .= ' (' . implode(', ', array_map([self::class, 'escapeIdentifier'], $columns)) . ')';
        $sql .= ' VALUES (';

        $sql .= implode(', ', array_values($this->values)) . ')';

        return $this->addOnDuplicateKeyUpdate($sql);
    }

    /**
     * @param string $sql
     * @return string
     */
    private function buildBatchInsert(string $sql): string
    {
        if (empty($this->batchValues)) {
            throw new RuntimeException('No batch values provided');
        }

        $columns = array_keys($this->batchValues[0]);
        $sql .= ' (' . implode(', ', array_map([self::class, 'escapeIdentifier'], $columns)) . ')';
        $sql .= ' VALUES ';

        $valueGroups = [];
        $paramCounter = 1; // compteur global pour garantir l'unicité
        foreach ($this->batchValues as $row) {
            $valueParts = [];
            foreach ($columns as $column) {
                $paramName = ':batchParam' . $paramCounter++;
                $this->namedParameters[$paramName] = [$row[$column], \PDO::PARAM_STR];
                $valueParts[] = $paramName;
            }
            $valueGroups[] = '(' . implode(', ', $valueParts) . ')';
        }

        $sql .= implode(', ', $valueGroups);

        return $this->addOnDuplicateKeyUpdate($sql);
    }

    /**
     * @param string $sql
     * @return string
     */
    private function buildInsertFromSelect(string $sql): string
    {
        if (!empty($this->values)) {
            $columns = array_keys($this->values);
            $sql .= ' (' . implode(', ', array_map([self::class, 'escapeIdentifier'], $columns)) . ')';
        }

        // Vérification de nullité avant d'appeler les méthodes
        if ($this->selectQuery !== null) {
            $sql .= ' ' . $this->selectQuery->toSql();

            // Merge named parameters from SELECT query
            $selectNamedParams = $this->selectQuery->getNamedParameters();
            foreach ($selectNamedParams as $key => $value) {
                $this->namedParameters[$key] = $value;
            }
        }

        return $this->addOnDuplicateKeyUpdate($sql);
    }

    /**
     * @param string $sql
     * @return string
     */
    private function addOnDuplicateKeyUpdate(string $sql): string
    {
        if (empty($this->onDuplicateUpdate) || $this->replace) {
            return $sql;
        }

        $sql .= ' ON DUPLICATE KEY UPDATE ';

        $updateParts = [];
        foreach ($this->onDuplicateUpdate as $column => $value) {
            $escapedColumn = self::escapeIdentifier($column);

            if ($value === 'VALUES') {
                $updateParts[] = "$escapedColumn = VALUES($escapedColumn)";
            } elseif ($value instanceof Raw) {
                // If the value is a Raw expression, we use it directly
                $value = $value->getValue();
                $updateParts[] = "$escapedColumn = $value";
            } else {
                $updateParts[] = "$escapedColumn = " . $this->addNamedParameter($value);
            }
        }

        $sql .= implode(', ', $updateParts);

        return $sql;
    }

    /**
     * @return array<string>
     */
    public function getBatchColumns(): array
    {
        if (!empty($this->batchValues)) {
            return array_keys($this->batchValues[0]);
        }

        return array_keys($this->values);
    }

    /**
     * @return int
     */
    public function getBatchSize(): int
    {
        return count($this->batchValues);
    }

    /**
     * @return bool
     */
    public function isBatchInsert(): bool
    {
        return !empty($this->batchValues);
    }
}
