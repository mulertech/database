<?php

namespace MulerTech\Database\Relational\Sql;

use RuntimeException;

/**
 * Class SqlQuery is a SQL query.
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SqlQuery
{
    public const string INSERT = 'INSERT';
    public const string INTO = 'INTO';
    public const string VALUES = 'VALUES';
    public const string SELECT = 'SELECT';
    public const string AS = 'AS';
    public const string UPDATE = 'UPDATE';
    public const string SET = 'SET';
    public const string DELETE = 'DELETE';
    public const string DISTINCT = 'DISTINCT';
    public const string FROM = 'FROM';
    public const string INNER_JOIN = 'INNER JOIN';
    public const string CROSS_JOIN = 'CROSS JOIN';
    public const string LEFT_JOIN = 'LEFT JOIN';
    public const string RIGHT_JOIN = 'RIGHT JOIN';
    public const string FULL_JOIN = 'FULL JOIN';
    public const string NATURAL_JOIN = 'NATURAL JOIN';
    public const string UNION_JOIN = 'UNION JOIN';
    public const string WHERE = 'WHERE';
    public const string GROUP_BY = 'GROUP BY';
    public const string WITH_ROLLUP = 'WITH ROLLUP';
    public const string HAVING = 'HAVING';
    public const string ORDER_BY = 'ORDER BY';
    public const string LIMIT = 'LIMIT';
    public const string OFFSET = 'OFFSET';
    public const string UNION = 'UNION';
    public const string UNION_ALL = 'UNION ALL';

    /**
     * @var QueryBuilder $queryBuilder
     */
    private QueryBuilder $queryBuilder;
    /**
     * The joined tables like :
     * [0 => ['t1', 't2'], 1 => ['table3', 'table4']]
     * The default value is the alias of table, if not given it's the table.
     * @var array<int, array<string>> $joinedTables
     */
    private array $joinedTables;

    /**
     * SqlQuery constructor.
     * @param QueryBuilder $queryBuilder
     */
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * @param string $string can be a simple string or two string with one contain point,
     * example : 'table.column alias'
     * @return string Example : '`table`.`column` `alias`'
     */
    public static function escape(string $string): string
    {
        $string = trim($string);
        //No escape for subquery
        if ($string === '*' || str_contains($string, '(')) {
            return $string;
        }
        if (str_contains($string, ' = ')) {
            $parts = explode(' = ', $string);
            return self::escape($parts[0]) . '=' . self::escape($parts[1]);
        }
        if (str_contains($string, '=')) {
            $parts = explode('=', $string);
            return self::escape($parts[0]) . '=' . self::escape($parts[1]);
        }
        if (str_contains($string, ' as ')) {
            $parts = explode(' as ', $string);
            return self::escape($parts[0]) . ' ' . self::escape($parts[1]);
        }
        if (str_contains($string, ' AS ')) {
            $parts = explode(' AS ', $string);
            return self::escape($parts[0]) . ' ' . self::escape($parts[1]);
        }
        if (str_contains($string, ' ')) {
            $parts = explode(' ', $string);
            return self::escape($parts[0]) . ' ' . self::escape($parts[1]);
        }
        if (str_contains($string, '.')) {
            $parts = explode('.', $string);
            return self::escape($parts[0]) . '.' . self::escape($parts[1]);
        }
        return "`" . str_replace("`", "``", $string) . "`";
    }

    /**
     * @return string
     */
    public function generateQuery(): string
    {
        $type = $this->queryBuilder->getType();
        switch ($type) {
            case self::SELECT:
                return $this->generateSelect();
            case self::INSERT:
                return $this->generateInsert();
            case self::UPDATE:
                return $this->generateUpdate();
            case self::DELETE:
                return $this->generateDelete();
        }

        throw new RuntimeException(
            sprintf(
                'Class SqlQuery, function generateFrom. The type (%s) of the query (for the "%s" table) into the QueryBuilder was not define or incorrect.',
                ($type) ?? 'not define',
                $this->queryBuilder->getFrom()[0]['name']
            )
        );
    }

    /**
     * @return string
     */
    private function generateSelect(): string
    {
        $query = self::SELECT . ' ';
        if ($this->queryBuilder->isDistinct()) {
            $query .= self::DISTINCT . ' ';
        }
        $query .= implode(', ', $this->queryBuilder->getSelect());
        $query .= $this->generateFrom();
        $query .= $this->generateJoin(
            $this->queryBuilder->getFrom()[0]['alias'] ?? $this->queryBuilder->getFrom()[0]['name']
        );
        $query .= $this->generateWhere();
        $query .= $this->generateGroupBy();
        $query .= $this->generateHaving();
        $query .= $this->generateOrderBy();
        $query .= $this->generateLimitOffset();
        if (!empty($alias = $this->queryBuilder->getAlias())) {
            return '(' . $query . ') ' . self::AS . ' ' . $alias;
        }
        return $query;
    }

    /**
     * @return string
     */
    private function generateInsert(): string
    {
        $query = self::INSERT;
        $query .= $this->generateFrom();
        $query .= $this->generateInsertValues();
        return $query;
    }

    /**
     * @return string
     */
    private function generateUpdate(): string
    {
        $query = self::UPDATE;
        $query .= $this->generateFrom();
        $query .= $this->generateUpdateValues();
        $query .= $this->generateWhere();
        return $query;
    }

    /**
     * @return string
     */
    private function generateDelete(): string
    {
        $query = self::DELETE;
        $query .= $this->generateFrom();
        $query .= $this->generateWhere();
        return $query;
    }

    /**
     * @return string
     */
    private function generateFrom(): string
    {
        $type = $this->queryBuilder->getType();
        if (empty($from = $this->queryBuilder->getFrom())) {
            throw new RuntimeException(
                sprintf(
                    'Class SqlQuery, function generateFrom. The from variable was not found, for the "%s" request.',
                    ($type) ?? 'not define'
                )
            );
        }

        $tableUse = ' ';

        if ($type === self::SELECT) {
            return $tableUse . self::FROM . ' ' . $this->generateSelectFroms($from);
        }

        if ($type === self::INSERT || $type === self::DELETE) {
            $tableUse .= ($type === self::INSERT) ? self::INTO . ' ' : self::FROM . ' ';
        }

        $tableUse .= self::escape($from[0]['name']);

        return $tableUse . ((!empty($from[0]['alias'])) ? ' ' . self::escape($from[0]['alias']) : '');
    }

    /**
     * @param array<int, array<string, string>> $from
     * @return string
     */
    private function generateSelectFroms(array $from): string
    {
        $fromList = [];

        foreach ($from as $item) {
            $fromList[] = $this->generateSelectFrom($item);
        }

        return implode(', ', $fromList);
    }

    /**
     * @param array<string, string> $from
     * @return string
     */
    private function generateSelectFrom(array $from): string
    {
        $fromQuery = $from['name'] instanceof QueryBuilder ? $from['name']->getSubQuery() : self::escape($from['name']);

        if (is_string($from['alias'])) {
            $fromQuery .= ' ' . self::escape($from['alias']);
        }

        return $fromQuery;
    }

    /**
     * @param string|null $table
     * @param int $level
     * @return string
     */
    private function generateJoin(?string $table = null, int $level = 0): string
    {
        $query = '';
        if ($level === 0) {
            $this->joinedTables[0][] = $table;
        }

        if (isset($this->joinedTables[$level])) {
            $levelTables = $this->joinedTables[$level];
            if (!empty($this->queryBuilder->getJoin())) {
                foreach ($this->queryBuilder->getJoin() as $key => $value) {
                    if (in_array($key, $levelTables, true)) {
                        foreach ($value as $join) {
                            $query .= ' ' . $join['type'] . ' ' . self::escape(
                                    $join['to']
                                ) . (($join['on']) ? ' ON ' . self::escape($join['on']) : '');
                            $aliasOrName = $this->queryBuilder->extractAlias($join['to']);
                            $this->joinedTables[$level + 1][] = $aliasOrName['alias'] ?? $aliasOrName['name'];
                        }
                        unset($this->queryBuilder->getJoin()[$key]);
                    }
                }
                $query .= $this->generateJoin(null, ++$level);
            }
        }
        return $query;
    }

    /**
     * @return string
     */
    private function generateWhere(): string
    {
        return (!empty($this->queryBuilder->getWhere())) ? ' ' . self::WHERE . $this->queryBuilder->getWhere() : '';
    }

    /**
     * @return string
     */
    private function generateGroupBy(): string
    {
        $groupBy = '';
        if (!empty($this->queryBuilder->getGroupBy())) {
            $groupBy = ' ' . self::GROUP_BY . ' ' . implode(', ', $this->queryBuilder->getGroupBy());
            if ($this->queryBuilder->getWithRollup()) {
                $groupBy .= ' ' . self::WITH_ROLLUP;
            }
        }
        return $groupBy;
    }

    /**
     * @return string
     */
    private function generateHaving(): string
    {
        return (!empty($this->queryBuilder->getHaving())) ? ' ' . self::HAVING . $this->queryBuilder->getHaving() : '';
    }

    /**
     * @return string
     */
    private function generateOrderBy(): string
    {
        return (!empty($this->queryBuilder->getOrderBy())) ? ' ' . self::ORDER_BY . ' ' . implode(
                ', ',
                $this->queryBuilder->getOrderBy()
            ) : '';
    }

    /**
     * @return string
     */
    private function generateLimitOffset(): string
    {
        $limit = '';
        if ($this->queryBuilder->getLimit() !== 0) {
            $limit .= ' ' . self::LIMIT . ' ' . $this->queryBuilder->getLimit();
        }
        if ($this->queryBuilder->getOffset() !== 0) {
            $limit .= ' ' . self::OFFSET . ' ' . $this->queryBuilder->getOffset();
        }
        return $limit;
    }

    /**
     * @return string
     */
    private function generateInsertValues(): string
    {
        if (empty($values = $this->queryBuilder->getValues())) {
            throw new RuntimeException(
                sprintf(
                    'Class SqlQuery, function generateValues. The values of the insert for the table "%s" was not set.',
                    $this->queryBuilder->getFrom()[0]['name']
                )
            );
        }
        if (!empty($this->queryBuilder->getDynamicParameters()) && !empty($this->queryBuilder->getNamedParameters())) {
            throw new RuntimeException(
                'Class SqlQuery, function generateValues. Parameters are dynamically and named defined, there must be one or the other.'
            );
        }
        $query = ' (`' . implode('`, `', array_keys($values)) . '`) ' . self::VALUES . ' (';
        if (array_values($values)[0][0] === '?' && !empty($this->queryBuilder->getDynamicParameters())) {
            $dynamicalValues = array_fill(0, count($values), '?');
            $query .= implode(', ', $dynamicalValues) . ')';
            return $query;
        }
        //make named parameters and set values
        $names = array_keys($this->queryBuilder->getNamedParameters());
        $query .= implode(', ', array_slice($names, 0, count($values))) . ')';
        return $query;
    }

    /**
     * @return string
     */
    private function generateUpdateValues(): string
    {
        if (empty($values = $this->queryBuilder->getValues())) {
            throw new RuntimeException(
                sprintf(
                    'Class SqlQuery, function generateValues. The values of the update for the table "%s" was not set.',
                    $this->queryBuilder->getFrom()[0]['name']
                )
            );
        }

        if (!empty($this->queryBuilder->getDynamicParameters())) {
            if (!empty($this->queryBuilder->getNamedParameters())) {
                throw new RuntimeException(
                    'Class SqlQuery, function generateValues. Parameters are dynamically and named defined, there must be one or the other.'
                );
            }

            return ' SET ' . implode(', ', array_map(fn($value) => self::escape($value) . ' = ?', array_keys($values)));
        }

        return ' SET ' . implode(', ', array_map(
                fn ($key, $value) => self::escape($key) . ' = ' . $value[0],
                array_keys($values),
                array_values($values)
            ));
    }
}