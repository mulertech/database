<?php

namespace MulerTech\Database\Migration\Entity;

use MulerTech\Database\Mapping\ColumnKey;
use MulerTech\Database\Mapping\ColumnType;
use MulerTech\Database\Mapping\MtColumn;
use MulerTech\Database\Mapping\MtEntity;

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
        unsigned: true,
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
    private ?string $executed_at = null;

    /**
     * @var int $execution_time Execution time in milliseconds
     */
    #[MtColumn(columnName: 'execution_time', columnType: ColumnType::INT, unsigned: true, isNullable: false, columnDefault: '0')]
    private int $execution_time = 0;

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
        return $this->version;
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
        return $this->executed_at;
    }

    /**
     * @param string $executed_at
     * @return $this
     */
    public function setExecutedAt(string $executed_at): self
    {
        $this->executed_at = $executed_at;
        return $this;
    }

    /**
     * @return int
     */
    public function getExecutionTime(): int
    {
        return $this->execution_time;
    }

    /**
     * @param int $execution_time
     * @return $this
     */
    public function setExecutionTime(int $execution_time): self
    {
        $this->execution_time = $execution_time;
        return $this;
    }
}
