<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder;

use MulerTech\Database\ORM\EmEngine;

/**
 * Class QueryBuilder
 *
 * Factory for creating query builders
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryBuilder
{
    /**
     * @var array<string, int>
     */
    private array $creationStats = [];

    /**
     * @param EmEngine|null $emEngine
     */
    public function __construct(
        private readonly ?EmEngine $emEngine = null,
    ) {
    }

    /**
     * @param string ...$columns
     * @return SelectBuilder
     */
    public function select(string ...$columns): SelectBuilder
    {
        $builder = new SelectBuilder($this->emEngine);

        if (!empty($columns)) {
            $builder->select(...$columns);
        }

        $this->incrementCreationStat('SELECT');
        return $builder;
    }

    /**
     * @param string $table
     * @return InsertBuilder
     */
    public function insert(string $table): InsertBuilder
    {
        $builder = new InsertBuilder($this->emEngine);
        $builder->into($table);

        $this->incrementCreationStat('INSERT');
        return $builder;
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return UpdateBuilder
     */
    public function update(string $table, ?string $alias = null): UpdateBuilder
    {
        $builder = new UpdateBuilder($this->emEngine);
        $builder->table($table, $alias);

        $this->incrementCreationStat('UPDATE');
        return $builder;
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return DeleteBuilder
     */
    public function delete(string $table, ?string $alias = null): DeleteBuilder
    {
        $builder = new DeleteBuilder($this->emEngine);
        $builder->from($table, $alias);

        $this->incrementCreationStat('DELETE');
        return $builder;
    }

    /**
     * @param string $sql
     * @return RawQueryBuilder
     */
    public function raw(string $sql): RawQueryBuilder
    {
        $builder = new RawQueryBuilder($this->emEngine, $sql);

        $this->incrementCreationStat('RAW');
        return $builder;
    }

    /**
     * @param string $type
     * @return void
     */
    private function incrementCreationStat(string $type): void
    {
        $this->creationStats[$type] = ($this->creationStats[$type] ?? 0) + 1;
    }
}
