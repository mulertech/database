<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Builder;

use InvalidArgumentException;
use MulerTech\Database\Core\Traits\SqlFormatterTrait;
use MulerTech\Database\Schema\Types\IndexType;

/**
 * Class IndexDefinition
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class IndexDefinition
{
    use SqlFormatterTrait;

    /**
     * @var array<int, string>
     */
    private array $columns = [];

    /**
     * @var IndexType
     */
    private IndexType $type = IndexType::INDEX;

    /**
     * @var string|null
     */
    private ?string $algorithm = null;

    /**
     * @var int|null
     */
    private ?int $keyBlockSize = null;

    /**
     * @var string|null
     */
    private ?string $comment = null;

    /**
     * @var bool
     */
    private bool $visible = true;

    /**
     * @param string $name
     * @param string $table
     */
    public function __construct(private readonly string $name, private readonly string $table)
    {
    }

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
     * @return array<int, string>
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
     * @param string|array<int, string> $columns
     * @return self
     */
    public function columns(string|array $columns): self
    {
        $this->columns = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    /**
     * @return self
     */
    public function unique(): self
    {
        $this->type = IndexType::UNIQUE;
        return $this;
    }

    /**
     * @return self
     */
    public function fullText(): self
    {
        $this->type = IndexType::FULLTEXT;
        return $this;
    }

    /**
     * @param string $algorithm
     * @return self
     */
    public function algorithm(string $algorithm): self
    {
        $this->algorithm = strtoupper($algorithm);
        return $this;
    }

    /**
     * @param int $size
     * @return self
     */
    public function keyBlockSize(int $size): self
    {
        $this->keyBlockSize = $size;
        return $this;
    }

    /**
     * @param string $comment
     * @return self
     */
    public function comment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Make the index visible
     * @return self
     */
    public function visible(): self
    {
        $this->visible = true;
        return $this;
    }

    /**
     * Make the index invisible
     * @return self
     */
    public function invisible(): self
    {
        $this->visible = false;
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

        $columnList = implode(', ', array_map(function ($col) {
            return $this->escapeIdentifier($col);
        }, $this->columns));

        // Fix: avoid repeating INDEX keyword for standard indexes
        $name = $this->escapeIdentifier($this->name);
        $table = $this->escapeIdentifier($this->table);
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
