<?php

namespace MulerTech\Database\Relational\Sql;

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Query\QueryFactory;
use MulerTech\Database\Query\SelectBuilder;
use MulerTech\Database\Query\InsertBuilder;
use MulerTech\Database\Query\UpdateBuilder;
use MulerTech\Database\Query\DeleteBuilder;
use MulerTech\Database\PhpInterface\Statement;
use PDO;
use RuntimeException;

/**
 * @package MulerTech\Database\Relational\Sql
 * @author Sébastien Muler
 */
class QueryBuilder
{
    /**
     * @var QueryFactory
     */
    private readonly QueryFactory $queryFactory;

    /**
     * @var SelectBuilder|InsertBuilder|UpdateBuilder|DeleteBuilder|null
     */
    private SelectBuilder|InsertBuilder|UpdateBuilder|DeleteBuilder|null $currentBuilder = null;

    /**
     * @var string|null
     */
    private ?string $currentType = null;

    /**
     * @param EmEngine|null $emEngine
     */
    public function __construct(?EmEngine $emEngine = null)
    {
        $this->queryFactory = new QueryFactory($emEngine);
    }

    /**
     * @param string ...$fields
     * @return self
     */
    public function select(string ...$fields): self
    {
        $this->currentType = 'SELECT';
        $this->currentBuilder = $this->queryFactory->select(...$fields);
        return $this;
    }

    /**
     * @param bool $distinct
     * @return self
     */
    public function distinct(bool $distinct = true): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->distinct($distinct);
        }
        return $this;
    }

    /**
     * @param string $table
     * @return self
     */
    public function insert(string $table): self
    {
        $this->currentType = 'INSERT';
        $this->currentBuilder = $this->queryFactory->insert($table);
        return $this;
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return self
     */
    public function update(string $table, ?string $alias = null): self
    {
        $this->currentType = 'UPDATE';
        $this->currentBuilder = $this->queryFactory->update($table, $alias);
        return $this;
    }

    /**
     * @param string $table
     * @return self
     */
    public function delete(string $table): self
    {
        $this->currentType = 'DELETE';
        $this->currentBuilder = $this->queryFactory->delete($table);
        return $this;
    }

    /**
     * @param string|QueryBuilder|null $table
     * @param string|null $alias
     * @return self
     */
    public function from(string|QueryBuilder|null $table = null, ?string $alias = null): self
    {
        if ($this->currentBuilder instanceof SelectBuilder && $table !== null) {
            if ($table instanceof QueryBuilder) {
                // Convert QueryBuilder to SelectBuilder if possible
                $selectBuilder = $table->getCurrentBuilder();
                if ($selectBuilder instanceof SelectBuilder) {
                    $this->currentBuilder->from($selectBuilder, $alias);
                } else {
                    throw new RuntimeException('Subquery must be a SELECT query');
                }
            } else {
                $this->currentBuilder->from($table, $alias);
            }
        }
        return $this;
    }

    /**
     * @param string $subQueryAlias
     * @return self
     */
    public function alias(string $subQueryAlias): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->alias($subQueryAlias);
        }
        return $this;
    }

    /**
     * @param string $to
     * @param string|null $on
     * @param string|null $alias
     * @return self
     */
    public function innerJoin(string $to, ?string $on = null, ?string $alias = null): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            // Parse table and condition from parameters for backward compatibility
            $this->currentBuilder->innerJoin($to, $on, $alias);
        }
        return $this;
    }

    /**
     * @param string $to
     * @param string|null $on
     * @param string|null $alias
     * @return self
     */
    public function leftJoin(string $to, ?string $on = null, ?string $alias = null): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->leftJoin($to, $on, $alias);
        }
        return $this;
    }

    /**
     * @param string $to
     * @param string|null $on
     * @param string|null $alias
     * @return self
     */
    public function rightJoin(string $to, ?string $on = null, ?string $alias = null): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->rightJoin($to, $on, $alias);
        }
        return $this;
    }

    /**
     * @param string $to
     * @param string|null $on
     * @param string|null $alias
     * @return self
     */
    public function fullJoin(string $to, ?string $on = null, ?string $alias = null): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->fullJoin($to, $on, $alias);
        }
        return $this;
    }

    /**
     * @param string $to
     * @param string|null $alias
     * @return self
     */
    public function naturalJoin(string $to, ?string $alias = null): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->naturalJoin($to, $alias);
        }
        return $this;
    }

    /**
     * @param string $to
     * @return self
     */
    public function crossJoin(string $to): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->crossJoin($to);
        }
        return $this;
    }

    /**
     * @param SqlOperations|string $condition
     * @return self
     */
    public function where(SqlOperations|string $condition): self
    {
        if ($this->currentBuilder instanceof SelectBuilder ||
            $this->currentBuilder instanceof UpdateBuilder ||
            $this->currentBuilder instanceof DeleteBuilder) {
            $this->currentBuilder->where($condition);
        }
        return $this;
    }

    /**
     * @param SqlOperations|string $condition
     * @return self
     */
    public function andWhere(SqlOperations|string $condition): self
    {
        if ($this->currentBuilder instanceof SelectBuilder ||
            $this->currentBuilder instanceof UpdateBuilder ||
            $this->currentBuilder instanceof DeleteBuilder) {
            $this->currentBuilder->andWhere($condition);
        }
        return $this;
    }

    /**
     * @param SqlOperations|string $condition
     * @return self
     */
    public function orWhere(SqlOperations|string $condition): self
    {
        if ($this->currentBuilder instanceof SelectBuilder ||
            $this->currentBuilder instanceof UpdateBuilder ||
            $this->currentBuilder instanceof DeleteBuilder) {
            $this->currentBuilder->orWhere($condition);
        }
        return $this;
    }

    /**
     * @param string $where
     * @return self
     */
    public function manualWhere(string $where): self
    {
        return $this->where($where);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @return self
     */
    public function set(string $column, mixed $value): self
    {
        if ($this->currentBuilder instanceof UpdateBuilder || $this->currentBuilder instanceof InsertBuilder) {
            $this->currentBuilder->set($column, $this->processValue($value));
        }

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param int|null $type
     * @return self
     */
    public function setValue(string $column, mixed $value, ?int $type = PDO::PARAM_STR): self
    {
        if ($this->currentBuilder instanceof UpdateBuilder || $this->currentBuilder instanceof InsertBuilder) {
            $this->currentBuilder->set($column, $this->processValue($value), $type);
        }

        return $this;
    }

    /**
     * @param array<int, array{0: string, 1: mixed}> $values
     * @return self
     */
    public function setValues(array $values): self
    {
        foreach ($values as $value) {
            $this->set($value[0], $value[1]);
        }
        return $this;
    }

    /**
     * @param string ...$fields
     * @return self
     */
    public function groupBy(string ...$fields): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->groupBy(...$fields);
        }
        return $this;
    }

    /**
     * @param bool $rollup
     * @return self
     */
    public function withRollup(bool $rollup = true): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->withRollup($rollup);
        }
        return $this;
    }

    /**
     * @param SqlOperations|string $having
     * @return self
     */
    public function having(SqlOperations|string $having): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->having($having);
        }
        return $this;
    }

    /**
     * @param SqlOperations|string $having
     * @return self
     */
    public function manualHaving(SqlOperations|string $having): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->having($having);
        }
        return $this;
    }

    /**
     * @param SqlOperations|string $having
     * @return self
     */
    public function andHaving(SqlOperations|string $having): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->andHaving($having);
        }
        return $this;
    }

    /**
     * @param SqlOperations|string $having
     * @return self
     */
    public function orHaving(SqlOperations|string $having): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->orHaving($having);
        }
        return $this;
    }

    /**
     * @param SqlOperations|string $having
     * @return self
     */
    public function andNotHaving(SqlOperations|string $having): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->andNotHaving($having);
        }
        return $this;
    }

    /**
     * @param SqlOperations|string $having
     * @return self
     */
    public function orNotHaving(SqlOperations|string $having): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->orNotHaving($having);
        }
        return $this;
    }

    /**
     * @param string $by
     * @param string $order
     * @return self
     */
    public function orderBy(string $by, string $order = 'ASC'): self
    {
        if ($this->currentBuilder instanceof SelectBuilder ||
            $this->currentBuilder instanceof UpdateBuilder ||
            $this->currentBuilder instanceof DeleteBuilder) {
            $this->currentBuilder->orderBy($by, strtolower($order) === 'asc' ? 'ASC' : 'DESC');
        }
        return $this;
    }

    /**
     * @param string $by
     * @param string $order
     * @return self
     */
    public function addOrderBy(string $by, string $order = 'ASC'): self
    {
        return $this->orderBy($by, strtolower($order) === 'asc' ? 'ASC' : 'DESC');
    }

    /**
     * @param int $limit
     * @return self
     */
    public function limit(int $limit = 0): self
    {
        if ($this->currentBuilder instanceof SelectBuilder ||
            $this->currentBuilder instanceof UpdateBuilder ||
            $this->currentBuilder instanceof DeleteBuilder) {
            $this->currentBuilder->limit($limit);
        }
        return $this;
    }

    /**
     * @param int|null $page
     * @param int $manuallyOffset
     * @return self
     */
    public function offset(?int $page = 1, int $manuallyOffset = 0): self
    {
        if ($this->currentBuilder instanceof SelectBuilder) {
            $this->currentBuilder->offset($page, $manuallyOffset);
        }

        return $this;
    }

    /**
     * @param QueryBuilder ...$queries
     * @return self
     */
    public function union(QueryBuilder ...$queries): self
    {
        if (empty($queries)) {
            throw new RuntimeException('Union requires at least one query');
        }

        $selectBuilders = [];
        foreach ($queries as $query) {
            $builder = $query->getCurrentBuilder();
            if (!$builder instanceof SelectBuilder) {
                throw new RuntimeException('Union queries must be SELECT queries');
            }
            $selectBuilders[] = $builder;
        }

        if (count($selectBuilders) === 1) {
            return $queries[0];
        }

        $firstBuilder = array_shift($selectBuilders);
        $firstBuilder->union(...$selectBuilders);

        return $queries[0];
    }

    /**
     * @param QueryBuilder ...$queries
     * @return string
     */
    public function unionAll(QueryBuilder ...$queries): string
    {
        if (empty($queries)) {
            throw new RuntimeException('Union All requires at least one query');
        }

        $selectBuilders = [];
        foreach ($queries as $query) {
            $builder = $query->getCurrentBuilder();
            if (!$builder instanceof SelectBuilder) {
                throw new RuntimeException('Union All queries must be SELECT queries');
            }
            $selectBuilders[] = $builder;
        }

        if (count($selectBuilders) === 1) {
            return $queries[0];
        }

        $firstBuilder = array_shift($selectBuilders);
        $firstBuilder->unionAll(...$selectBuilders);

        return $queries[0];
    }

    /**
     * @param mixed $value
     * @param int $type
     * @return string
     */
    public function addNamedParameter(mixed $value, int $type = PDO::PARAM_STR): string
    {
        if ($this->currentBuilder !== null) {
            return $this->currentBuilder->addNamedParameter($value, $type);
        }
        throw new RuntimeException('No active builder to add parameter to');
    }

    /**
     * @return array<string, array{0: mixed, 1: int}>
     */
    public function getNamedParameters(): array
    {
        if ($this->currentBuilder !== null) {
            return $this->currentBuilder->getNamedParameters();
        }
        return [];
    }

    /**
     * @return string
     */
    public function getQuery(): string
    {
        if ($this->currentBuilder === null) {
            throw new RuntimeException('No query built yet');
        }

        return $this->currentBuilder->toSql();
    }

    /**
     * @return Statement
     */
    public function getResult(): Statement
    {
        if ($this->currentBuilder === null) {
            throw new RuntimeException('No query built yet');
        }

        return $this->currentBuilder->getResult();
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        if ($this->currentBuilder === null) {
            throw new RuntimeException('No query built yet');
        }

        $this->currentBuilder->execute();
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        try {
            return $this->getQuery();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->currentType;
    }

    /**
     * @param string $name
     * @return array{name: string, alias: string|null}
     */
    public function extractAlias(string $name): array
    {
        $name = trim($name);

        if (str_contains($name, ' as ')) {
            $return = explode(' as ', $name);
            return ['name' => $return[0], 'alias' => $return[1]];
        }
        if (str_contains($name, ' AS ')) {
            $return = explode(' AS ', $name);
            return ['name' => $return[0], 'alias' => $return[1]];
        }
        if (str_contains($name, ' ')) {
            $return = explode(' ', $name);
            return ['name' => $return[0], 'alias' => $return[1]];
        }
        return ['name' => $name, 'alias' => null];
    }

    /**
     * @return QueryFactory
     */
    public function getQueryFactory(): QueryFactory
    {
        return $this->queryFactory;
    }

    /**
     * @return SelectBuilder|InsertBuilder|UpdateBuilder|DeleteBuilder|null
     */
    public function getCurrentBuilder(): SelectBuilder|InsertBuilder|UpdateBuilder|DeleteBuilder|null
    {
        return $this->currentBuilder;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return $this->queryFactory->getAllStats();
    }

    /**
     * @param string $queryType
     * @return void
     */
    public function invalidateCache(string $queryType = ''): void
    {
        $this->queryFactory->invalidateCache($queryType);
    }

    /**
     * @return array{sql: string, parameters: array<string|int, mixed>}
     */
    public function compileWithParameters(): array
    {
        if ($this->currentBuilder === null) {
            throw new RuntimeException('No query built yet');
        }

        $sql = $this->currentBuilder->toSql();
        $namedParams = $this->currentBuilder->getNamedParameters();

        // Convert named parameters to simple array for PDO binding
        $parameters = [];
        foreach ($namedParams as $key => $valueAndType) {
            $parameters[$key] = $valueAndType[0]; // Extract only the value
        }

        return [
            'sql' => $sql,
            'parameters' => $parameters
        ];
    }

    /**
     * Process value for SQL injection protection
     *
     * @param mixed $value
     * @return mixed
     */
    private function processValue(mixed $value): mixed
    {
        if ($value instanceof Raw) {
            return $value->getValue();
        }

        return $value; // Will be handled by individual builders
    }

    /**
     * @param SqlOperations|string $condition
     * @return self
     */
    public function andNotWhere(SqlOperations|string $condition): self
    {
        if ($this->currentBuilder instanceof SelectBuilder ||
            $this->currentBuilder instanceof UpdateBuilder ||
            $this->currentBuilder instanceof DeleteBuilder) {
            $this->currentBuilder->andNotWhere($condition);
        }
        return $this;
    }

    /**
     * @param SqlOperations|string $condition
     * @return self
     */
    public function orNotWhere(SqlOperations|string $condition): self
    {
        if ($this->currentBuilder instanceof SelectBuilder ||
            $this->currentBuilder instanceof UpdateBuilder ||
            $this->currentBuilder instanceof DeleteBuilder) {
            $this->currentBuilder->orNotWhere($condition);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getSubQuery(): string
    {
        if ($this->currentBuilder === null) {
            throw new RuntimeException('No query built yet');
        }

        return '(' . $this->currentBuilder->toSql() . ')';
    }
}
