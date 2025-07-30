<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Migration\Entity;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Types\ColumnKey;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * MigrationHistory entity for tracking executed migrations
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[MtEntity(tableName: 'migration_history')]
class MigrationHistory
{
    /**
     * @var int|null $id
     */
    #[MtColumn(
        columnName: 'id',
        columnType: ColumnType::INT,
        isUnsigned: true,
        isNullable: false,
        extra: 'auto_increment',
        columnKey: ColumnKey::PRIMARY_KEY
    )]
    private ?int $id = null;

    /**
     * @var string|null $version Migration version (YYYYMMDD-HHMM)
     */
    #[MtColumn(columnName: 'version', columnType: ColumnType::VARCHAR, length: 13, isNullable: false, columnKey: ColumnKey::UNIQUE_KEY)]
    private ?string $version = null;

    /**
     * @var string|null $executed_at When the migration was executed
     */
    #[MtColumn(columnName: 'executed_at', columnType: ColumnType::DATETIME, isNullable: false, columnDefault: 'CURRENT_TIMESTAMP')]
    private ?string $executedAt = null;

    /**
     * @var int $execution_time Execution time in milliseconds
     */
    #[MtColumn(columnName: 'execution_time', columnType: ColumnType::INT, isUnsigned: true, isNullable: false, columnDefault: '0')]
    private int $executionTime = 0;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     * @return $this
     */
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version ?? '';
    }

    /**
     * @param string $version
     * @return $this
     */
    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @return string
     */
    public function getExecutedAt(): string
    {
        return $this->executedAt ?? '';
    }

    /**
     * @param string $executedAt
     * @return $this
     */
    public function setExecutedAt(string $executedAt): self
    {
        $this->executedAt = $executedAt;
        return $this;
    }

    /**
     * @return int
     */
    public function getExecutionTime(): int
    {
        return $this->executionTime;
    }

    /**
     * @param int $executionTime
     * @return $this
     */
    public function setExecutionTime(int $executionTime): self
    {
        $this->executionTime = $executionTime;
        return $this;
    }
}
