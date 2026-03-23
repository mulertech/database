<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Builder;

use MulerTech\Database\Core\Traits\SqlFormatterTrait;
use MulerTech\Database\Schema\Types\IndexType;

/**
 * Class IndexDefinition.
 *
 * @author Sébastien Muler
 */
class IndexDefinition
{
    use SqlFormatterTrait;

    /**
     * @var array<int, string>
     */
    private array $columns = [];

    private IndexType $type = IndexType::INDEX;

    private ?string $algorithm = null;

    private ?int $keyBlockSize = null;

    private ?string $comment = null;

    private bool $visible = true;

    public function __construct(private readonly string $name, private readonly string $table)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

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

    public function getType(): IndexType
    {
        return $this->type;
    }

    public function getAlgorithm(): ?string
    {
        return $this->algorithm;
    }

    public function getKeyBlockSize(): ?int
    {
        return $this->keyBlockSize;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * @param string|array<int, string> $columns
     */
    public function columns(string|array $columns): self
    {
        $this->columns = is_array($columns) ? $columns : [$columns];

        return $this;
    }

    public function unique(): self
    {
        $this->type = IndexType::UNIQUE;

        return $this;
    }

    public function fullText(): self
    {
        $this->type = IndexType::FULLTEXT;

        return $this;
    }

    public function algorithm(string $algorithm): self
    {
        $this->algorithm = strtoupper($algorithm);

        return $this;
    }

    public function keyBlockSize(int $size): self
    {
        $this->keyBlockSize = $size;

        return $this;
    }

    public function comment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Make the index visible.
     */
    public function visible(): self
    {
        $this->visible = true;

        return $this;
    }

    /**
     * Make the index invisible.
     */
    public function invisible(): self
    {
        $this->visible = false;

        return $this;
    }

    /**
     * Generate the SQL statement for creating the index.
     */
    public function toSql(): string
    {
        if (empty($this->columns)) {
            throw new \InvalidArgumentException('The index must have at least one column.');
        }

        $columnList = implode(', ', array_map(function ($col) {
            return $this->escapeIdentifier($col);
        }, $this->columns));

        // Fix: avoid repeating INDEX keyword for standard indexes
        $name = $this->escapeIdentifier($this->name);
        $table = $this->escapeIdentifier($this->table);

        $sql = (IndexType::INDEX === $this->type)
            ? "CREATE INDEX $name ON $table ($columnList)"
            : "CREATE {$this->type->value} INDEX $name ON $table ($columnList)";

        $options = [];

        if (null !== $this->algorithm) {
            $options[] = "ALGORITHM = $this->algorithm";
        }

        if (null !== $this->keyBlockSize) {
            $options[] = "KEY_BLOCK_SIZE = $this->keyBlockSize";
        }

        if (null !== $this->comment) {
            $options[] = "COMMENT '".str_replace("'", "''", $this->comment)."'";
        }

        if (!$this->visible) {
            $options[] = 'INVISIBLE';
        }

        if (!empty($options)) {
            $sql .= ' '.implode(' ', $options);
        }

        return $sql;
    }
}
