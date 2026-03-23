<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder;

use MulerTech\Database\ORM\EmEngine;

/**
 * Class QueryBuilder.
 *
 * Factory for creating query builders
 *
 * @author Sébastien Muler
 */
class QueryBuilder
{
    /**
     * @var array<string, int>
     */
    private array $creationStats = [];

    public function __construct(
        private readonly ?EmEngine $emEngine = null,
    ) {
    }

    public function select(string ...$columns): SelectBuilder
    {
        $builder = new SelectBuilder($this->emEngine);

        if (!empty($columns)) {
            $builder->select(...$columns);
        }

        $this->incrementCreationStat('SELECT');

        return $builder;
    }

    public function insert(string $table): InsertBuilder
    {
        $builder = new InsertBuilder($this->emEngine);
        $builder->into($table);

        $this->incrementCreationStat('INSERT');

        return $builder;
    }

    public function update(string $table, ?string $alias = null): UpdateBuilder
    {
        $builder = new UpdateBuilder($this->emEngine);
        $builder->table($table, $alias);

        $this->incrementCreationStat('UPDATE');

        return $builder;
    }

    public function delete(string $table, ?string $alias = null): DeleteBuilder
    {
        $builder = new DeleteBuilder($this->emEngine);
        $builder->from($table, $alias);

        $this->incrementCreationStat('DELETE');

        return $builder;
    }

    public function raw(string $sql): RawQueryBuilder
    {
        $builder = new RawQueryBuilder($this->emEngine, $sql);

        $this->incrementCreationStat('RAW');

        return $builder;
    }

    private function incrementCreationStat(string $type): void
    {
        $this->creationStats[$type] = ($this->creationStats[$type] ?? 0) + 1;
    }
}
