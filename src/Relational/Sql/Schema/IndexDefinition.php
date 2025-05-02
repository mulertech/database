<?php

namespace MulerTech\Database\Relational\Sql\Schema;

use InvalidArgumentException;
use MulerTech\Database\Relational\Sql\SqlQuery;

/**
 * Class IndexDefinition
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class IndexDefinition
{
    private array $columns = [];
    private IndexType $type = IndexType::INDEX; // INDEX, UNIQUE, FULLTEXT
    private ?string $algorithm = null; // BTREE, HASH, etc.
    private ?int $keyBlockSize = null;
    private ?string $comment = null;
    private bool $visible = true;

    /**
     * @param string $name
     * @param string $table
     */
    public function __construct(private readonly string $name, private readonly string $table)
    {}

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return array
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return IndexType
     */
    public function getType(): IndexType
    {
        return $this->type;
    }

    /**
     * @return string|null
     */
    public function getAlgorithm(): ?string
    {
        return $this->algorithm;
    }

    /**
     * @return int|null
     */
    public function getKeyBlockSize(): ?int
    {
        return $this->keyBlockSize;
    }

    /**
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @return bool
     */
    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * @param string|array $columns
     * @return $this
     */
    public function columns(string|array $columns): self
    {
        $this->columns = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    /**
     * @return $this
     */
    public function unique(): self
    {
        $this->type = IndexType::UNIQUE;
        return $this;
    }

    /**
     * @return $this
     */
    public function fullText(): self
    {
        $this->type = IndexType::FULLTEXT;
        return $this;
    }

    /**
     * @param string $algorithm BTREE, HASH, etc.
     * @return $this
     */
    public function algorithm(string $algorithm): self
    {
        $this->algorithm = strtoupper($algorithm);
        return $this;
    }

    /**
     * @param int $size Size in bytes
     * @return $this
     */
    public function keyBlockSize(int $size): self
    {
        $this->keyBlockSize = $size;
        return $this;
    }

    /**
     * @param string $comment
     * @return $this
     */
    public function comment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @param bool $visible
     * @return $this
     */
    public function visible(bool $visible = true): self
    {
        $this->visible = $visible;
        return $this;
    }

    /**
     * Generate the SQL statement for creating the index
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->columns)) {
            throw new InvalidArgumentException("The index must have at least one column.");
        }

        $columnList = implode(', ', array_map(function($col) {
            return SqlQuery::escape($col);
        }, $this->columns));

        // Fix: avoid repeating INDEX keyword for standard indexes
        $name = SqlQuery::escape($this->name);
        $table = SqlQuery::escape($this->table);
        if ($this->type === IndexType::INDEX) {
            $sql = "CREATE INDEX $name ON $table ($columnList)";
        } else {
            $sql = "CREATE {$this->type->value} INDEX $name ON $table ($columnList)";
        }

        $options = [];

        if ($this->algorithm !== null) {
            $options[] = "ALGORITHM = $this->algorithm";
        }

        if ($this->keyBlockSize !== null) {
            $options[] = "KEY_BLOCK_SIZE = $this->keyBlockSize";
        }

        if ($this->comment !== null) {
            $options[] = "COMMENT '" . str_replace("'", "''", $this->comment) . "'";
        }

        if (!$this->visible) {
            $options[] = "INVISIBLE";
        }

        if (!empty($options)) {
            $sql .= " " . implode(" ", $options);
        }

        return $sql;
    }
}

