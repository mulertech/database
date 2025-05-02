<?php

namespace MulerTech\Database\Relational\Sql;

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\PhpInterface\Statement;
use PDO;
use RuntimeException;

/**
 * Class QueryBuilder
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryBuilder
{
    /**
     * @var string $type It can be SELECT, INSERT, UPDATE or DELETE.
     */
    private string $type;

    /**
     * Alias of this query, for example $alias = 'adult_number' :
     * ...(SELECT COUNT(*) FROM users WHERE age > 18) as adult_number
     * @var string $alias
     */
    private string $alias = '';

    /**
     * @var array $select
     */
    private array $select = [];

    /**
     * @var bool $distinct
     */
    private bool $distinct = false;

    /**
     * @var array $from is used into SELECT, INSERT, UPDATE and DELETE.
     * Example : ['name' => 'table1', 'alias' => 'alias1']
     */
    private array $from = [];

    /**
     * @var array $join Stored by from table.
     */
    private array $join = [];

    /**
     * @var array $tablesJoined List the tables joined by table name (and alias is defined) like :
     * ['table1' => 'alias1', 'table2' => null,..]; (null for alias if not define).
     * It can be used for check if the alias is use just one time.
     */
    private array $tablesJoined = [];

    /**
     * @var SqlOperations $where
     */
    private SqlOperations $where;

    /**
     * @var array $groupBy
     */
    private array $groupBy = [];

    /**
     * @var bool $withRollup
     */
    private bool $withRollup = false;

    /**
     * @var SqlOperations $having
     */
    private SqlOperations $having;

    /**
     * @var array $orderBy like : ['column1 ASC', 'column2 DESC']
     */
    private array $orderBy = [];

    /**
     * @var int $limit
     */
    private int $limit = 0;

    /**
     * @var int $offset
     */
    private int $offset = 0;

    /**
     * @var QueryBuilder[] $union
     */
    private array $union = [];

    /**
     * @var QueryBuilder[] $unionAll
     */
    private array $unionAll = [];

    /**
     * @var array $values like : [0 => ['column' => 'value'], 1 => [...]]
     */
    private array $values = [];

    /**
     * @var array $updates
     */
    private array $updates = [];

    /**
     * Store the named parameters and values, example :
     * ['namedParam1' => 'value', 'namedParam2' => 'other value']
     * @var array $namedParameters
     */
    private array $namedParameters = [];

    /**
     * Store the dynamic parameters values, example :
     * ['value 1', 'value2',..]
     * @var array $dynamicParameters
     */
    private array $dynamicParameters = [];

    /**
     * This is the named parameters prefix, for example : namedParam1, namedParam2...
     */
    private const string NAMED_PARAMETERS_PREFIX = 'namedParam';

    /**
     * @param EmEngine|null $emEngine
     */
    public function __construct(private ?EmEngine $emEngine = null)
    {}

    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type ?? null;
    }

    /**
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @return array
     */
    public function getSelect(): array
    {
        return $this->select;
    }

    /**
     * @return bool
     */
    public function isDistinct(): bool
    {
        return $this->distinct;
    }

    /**
     * @param bool $distinct
     * @return $this
     */
    public function distinct(bool $distinct = true): QueryBuilder
    {
        $this->distinct = $distinct;
        return $this;
    }

    /**
     * @return array
     */
    public function getFrom(): array
    {
        return $this->from;
    }

    /**
     * @return array
     */
    public function getJoin(): array
    {
        return $this->join;
    }

    /**
     * @return SqlOperations|null
     */
    public function getWhere(): ?SqlOperations
    {
        return $this->where ?? null;
    }

    /**
     * @return array
     */
    public function getGroupBy(): array
    {
        return $this->groupBy;
    }

    /**
     * @return bool
     */
    public function getWithRollup(): bool
    {
        return $this->withRollup;
    }

    /**
     * @return SqlOperations|null
     */
    public function getHaving(): ?SqlOperations
    {
        return $this->having ?? null;
    }

    /**
     * @return array
     */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @return int
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Get the insert values.
     * @return array
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @return array
     */
    public function getDynamicParameters(): array
    {
        return $this->dynamicParameters;
    }

    /**
     * @return array
     */
    public function getNamedParameters(): array
    {
        return $this->namedParameters;
    }

    /**
     * @return array
     */
    public function getUpdates(): array
    {
        return $this->updates;
    }

    /**
     * @return string
     */
    public function getQuery(): string
    {
        if (!empty($union = $this->union)) {
            return implode(' ' . SqlQuery::UNION . ' ', $union);
        }
        if (!empty($unionAll = $this->unionAll)) {
            return implode(' ' . SqlQuery::UNION_ALL . ' ', $unionAll);
        }
        return new SqlQuery($this)->generateQuery();
    }

    /**
     * @return string
     */
    public function getSubQuery(): string
    {
        if (!empty($this->alias)) {
            return new SqlQuery($this)->generateQuery();
        }
        return '(' . $this->getQuery() . ')';
    }

    /**
     * @return array
     */
    public function getExecuteParameters(): array
    {
        $parameters = (!empty($namedParam = $this->getNamedParameters())) ? $namedParam : $this->getDynamicParameters();
        if (empty($parameters)) {
            throw new RuntimeException(
                'Class QueryBuilder, function getExecuteParameters. The named or dynamic parameters are not set.'
            );
        }
        $executeParameters = [];
        foreach ($parameters as $key => $value) {
            $executeParameters[$key] = $value[0];
        }
        return $executeParameters;
    }

    /**
     * @return array|null
     */
    public function getBindParameters(): ?array
    {
        $parameters = (!empty($namedParam = $this->getNamedParameters())) ? $namedParam : $this->getDynamicParameters();

        if (empty($parameters)) {
            return null;
        }

        $bindParams = [];
        foreach ($parameters as $key => $value) {
            $bindParams[] = [$key, $value[0], $value[1]];
        }
        return $bindParams;
    }

    /**
     * @param string $table
     * @return $this
     */
    public function insert(string $table): QueryBuilder
    {
        $this->type = SqlQuery::INSERT;
        $this->from[] = $this->extractAlias($table);
        return $this;
    }

    /**
     * Set value for the column, it's used for INSERT.
     * One of this three methods must be used for all the values :
     * First method :
     * '?' And the parameter must be added with the addDynamicParameter function directly here :
     *  setValue('column1', $qb->addDynamicParameter($value));
     *  or after set value :
     *  setValue('column1', '?')->addDynamicParameter($value);
     * ***
     * Second method :
     * ':column1' And the parameter must be added with the addNamedParameter function directly here :
     * setValue('column1', $qb->addNamedParameter($value));
     * or after set value :
     * setValue('column1', ':column1value')->setParameter(':column1value', $value);
     * ***
     * Third method :
     * 'a value directly here' (this value will be added to the named parameter automatically) :
     * setValue('column1', $value);
     *
     * @param string $column
     * @param mixed $value
     * @param string|null $type
     * @return $this
     */
    public function setValue(string $column, mixed $value, ?string $type = null): QueryBuilder
    {
        $this->values[$column] = [$value, $type];
        return $this;
    }

    /**
     * Set all the values like this :
     * [['column1' => 'value column 1']['column2' => 'value column 2']]
     * @param array $values
     * @return $this
     */
    public function setValues(array $values): QueryBuilder
    {
        foreach ($values as $value) {
            $this->setValue($value[0], $value[1]);
        }
        return $this;
    }

    /**
     * The select for human use, for example :
     * Just the name of columns :
     * select('name', 'address', 'age');
     * ***
     * The name of the columns with an alias for each :
     * select('name as username', 'address as user_address', 'age as user_age');
     * ***
     * A name of column with a subquery and this alias :
     * $subQuery = new QueryBuilder();
     * $subQuery->select('COUNT(*)')->from('products', 'pr')->where(SqlOperations::equal('pr.numprod', 'p.numprod'));
     * select('product_name', $subQuery->getSubQuery() . ' as nb_supplier');
     *
     * @param string ...$fields
     * @return $this
     */
    public function select(string ...$fields): QueryBuilder
    {
        $this->type = SqlQuery::SELECT;

        if ($fields === []) {
            $this->select = ['*'];
            return $this;
        }

        if (str_starts_with($fields[0], SqlQuery::DISTINCT)) {
            $this->distinct = true;
            $fields[0] = substr($fields[0], iconv_strlen(SqlQuery::DISTINCT) + 1);
        }
        $this->select = array_map([SqlQuery::class, 'escape'], $fields);
        return $this;
    }

    /**
     * @param string $alias
     * @return QueryBuilder
     */
    public function selectAlias(string $alias): QueryBuilder
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * From is used for SELECT, examples :
     * from('users as user') or like :
     * from('users', 'user') or like :
     * from('users')
     * Do not escape the column and alias, this is automatic.
     * @param string|self $from
     * @param string|null $alias
     * @return $this
     */
    public function from(string|QueryBuilder $from, ?string $alias = null): QueryBuilder
    {
        return $this->addFrom($from, $alias);
    }

    /**
     * See the function from above this.
     * @param string|self $from
     * @param string|null $alias If the alias is given this alias must be used into the join to find it.
     * @return $this
     */
    public function addFrom(string|QueryBuilder $from, ?string $alias = null): QueryBuilder
    {
        if (is_string($from) && is_null($alias)) {
            $this->from[] = $this->extractAlias($from);
        } else {
            $this->from[] = ['name' => $from, 'alias' => $alias];
        }
        return $this;
    }

    /**
     * @param string $from This must be the table name or the table name with alias, not only alias...
     * @param string $to Table (and alias), Alias needed for self join (join for the same table,
     * the tables are the same name but must have a unique alias) OR when the from table have an alias to find it.
     * Example :
     * table as table_alias... :
     * join('users as user', 'users as manager', 'LEFT JOIN', 'user.manager_id = manager.id')
     * ***
     * table table_alias... :
     * join('users user', 'users as manager', 'LEFT JOIN', 'user.manager_id = manager.id')
     * ***
     * just table... :
     * join('users', 'users as manager', 'LEFT JOIN', 'user.manager_id = manager.id')
     * ***
     * just alias... (if the alias is given into another addJoin) :
     * example first join : join('employee as emp', 'users as user', 'LEFT JOIN', 'employee.users_id = users.id')
     * join('user', 'users as manager', 'LEFT JOIN', 'user.manager_id = manager.id')
     *
     * @param string $type
     * @param string|null $on If it uses alias the aliases must be given in the from and to parameters.
     */
    private function addJoin(string $from, string $to, string $type, ?string $on = null): void
    {
        ['name' => $fromName, 'alias' => $fromAlias] = $this->extractAlias($from);
        ['name' => $toName, 'alias' => $toAlias] = $this->extractAlias($to);

        if ($fromAlias === null && $on !== null && !str_contains($on, $fromName)) {
            throw new RuntimeException(
                sprintf(
                    'Class : QueryBuilder, Function : addJoin. The from table "%s" is not an alias and the on condition "%s" is not a part of the from table.',
                    $from,
                    $on
                )
            );
        }

        if (!$this->tableKnown($fromName, $fromAlias)) {
            throw new RuntimeException(
                sprintf(
                    'Class : QueryBuilder, Function : addJoin. Unable to find the from table "%s" given for add join of type : %s',
                    $from,
                    $type
                )
            );
        }

        if ($toAlias === null && $on !== null && !str_contains($on, $toName)) {
            throw new RuntimeException(
                sprintf(
                    'Class : QueryBuilder, Function : addJoin. The to table "%s" is not an alias and the on condition "%s" is not a part of the to table.',
                    $to,
                    $on
                )
            );
        }

        //Check if the alias is used
        if ($toAlias !== null && in_array($toAlias, array_merge(...array_values($this->tablesJoined)))) {
            throw new RuntimeException(
                sprintf(
                    'Class : QueryBuilder, Function : addJoin. The alias "%s" for join of type "%s" is used.',
                    $toAlias,
                    $type
                )
            );
        }

        $this->join[$fromAlias ?? $fromName][] = [
            'type' => $type,
            'to' => $toName . (($toAlias) ? ' ' . $toAlias : ''),
            'on' => $on
        ];

        $this->tablesJoined[$toName][] = $toAlias;
    }

    /**
     * Check if the table is known and update the alias into from and nameAlias if null.
     * @param string $name
     * @param string|null $alias
     * @return bool
     */
    private function tableKnown(string $name, ?string $alias): bool
    {
        // alias priority
        if (!is_null($alias)) {
            //Priority 1 : vérification par alias
            if (isset($this->from[0]['alias']) && ($alias === $this->from[0]['alias'])) {
                return true;
            }
            if (isset($this->tablesJoined[$name]) && in_array($alias, $this->tablesJoined[$name], true)) {
                return true;
            }

            return false;
        }

        //Priority 2 : table name
        if (isset($this->from[0]['name']) && $name === $this->from[0]['name']) {
            return true;
        }

        if (isset($this->tablesJoined[$name])) {
            return true;
        }

        return false;
    }

    /**
     * @param string $from
     * @param string $to Table (and alias)
     * @param string|null $on The equal sign must be surrounded by spaces for the automatic escape to work.
     * @return $this
     */
    public function innerJoin(string $from, string $to, ?string $on = null): QueryBuilder
    {
        $this->addJoin($from, $to, SqlQuery::INNER_JOIN, $on);
        return $this;
    }

    /**
     * @param string $from
     * @param string $to Table (and alias)
     * @return $this
     */
    public function crossJoin(string $from, string $to): QueryBuilder
    {
        $this->addJoin($from, $to, SqlQuery::CROSS_JOIN);
        return $this;
    }

    /**
     * @param string $from
     * @param string $to Table (and alias)
     * @param string|null $on The equal sign must be surrounded by spaces for the automatic escape to work.
     * @return $this
     */
    public function leftJoin(string $from, string $to, ?string $on = null): QueryBuilder
    {
        $this->addJoin($from, $to, SqlQuery::LEFT_JOIN, $on);
        return $this;
    }

    /**
     * @param string $from
     * @param string $to Table (and alias)
     * @param string|null $on The equal sign must be surrounded by spaces for the automatic escape to work.
     * @return $this
     */
    public function rightJoin(string $from, string $to, ?string $on = null): QueryBuilder
    {
        $this->addJoin($from, $to, SqlQuery::RIGHT_JOIN, $on);
        return $this;
    }

    /**
     * @param string $from
     * @param string $to Table (and alias)
     * @param string|null $on The equal sign must be surrounded by spaces for the automatic escape to work.
     * @return $this
     */
    public function fullJoin(string $from, string $to, ?string $on = null): QueryBuilder
    {
        $this->addJoin($from, $to, SqlQuery::FULL_JOIN, $on);
        return $this;
    }

    /**
     * @param string $from
     * @param string $to Table (and alias)
     * @param string|null $on The equal sign must be surrounded by spaces for the automatic escape to work.
     * @return $this
     */
    public function naturalJoin(string $from, string $to, ?string $on = null): QueryBuilder
    {
        $this->addJoin($from, $to, SqlQuery::NATURAL_JOIN, $on);
        return $this;
    }

    /**
     * @param string $from
     * @param string $to Table (and alias)
     * @param string|null $on The equal sign must be surrounded by spaces for the automatic escape to work.
     * @return $this
     */
    public function unionJoin(string $from, string $to, ?string $on = null): QueryBuilder
    {
        $this->addJoin($from, $to, SqlQuery::UNION_JOIN, $on);
        return $this;
    }

    /**
     * @param string|SqlOperations $where
     * @return $this
     */
    public function where(SqlOperations|string $where): QueryBuilder
    {
        $this->where = is_string($where) ? new SqlOperations($where) : $where;

        return $this;
    }

    /**
     * @param string|SqlOperations $where
     * @return $this
     */
    public function andWhere(SqlOperations|string $where): QueryBuilder
    {
        $this->where->addOperation($where);
        return $this;
    }

    /**
     * @param string|SqlOperations $where
     * @return $this
     */
    public function andNotWhere(SqlOperations|string $where): QueryBuilder
    {
        $this->where->andNot($where);
        return $this;
    }

    /**
     * @param string|SqlOperations $where
     * @return $this
     */
    public function orWhere(SqlOperations|string $where): QueryBuilder
    {
        $this->where->addOperation($where, LinkOperator::OR);
        return $this;
    }

    /**
     * @param string|SqlOperations $where
     * @return $this
     */
    public function orNotWhere(SqlOperations|string $where): QueryBuilder
    {
        $this->where->orNot($where);
        return $this;
    }

    /**
     * Define the where part of this request manually.
     * @param string $where
     * @return $this
     */
    public function manualWhere(string $where): QueryBuilder
    {
        $this->where = new SqlOperations();
        $this->where->manualOperation($where);
        return $this;
    }

    /**
     * @param string ...$fields
     * @return $this
     */
    public function groupBy(string ...$fields): QueryBuilder
    {
        $this->groupBy = array_map([SqlQuery::class, 'escape'], $fields);
        return $this;
    }

    /**
     * @param bool $rollup
     * @return QueryBuilder
     */
    public function withRollup(bool $rollup = true): QueryBuilder
    {
        $this->withRollup = $rollup;
        return $this;
    }

    /**
     * @param string|SqlOperations $having
     * @return $this
     */
    public function having(SqlOperations|string $having): QueryBuilder
    {
        $sql = new SqlOperations();
        $sql->addOperation($having);
        $this->having = $sql;
        return $this;
    }

    /**
     * @param string|SqlOperations $having
     * @return $this
     */
    public function andHaving(SqlOperations|string $having): QueryBuilder
    {
        $this->having->addOperation($having);
        return $this;
    }

    /**
     * @param string|SqlOperations $having
     * @return $this
     */
    public function andNotHaving(SqlOperations|string $having): QueryBuilder
    {
        $this->having->andNot($having);
        return $this;
    }

    /**
     * @param string|SqlOperations $having
     * @return $this
     */
    public function orHaving(SqlOperations|string $having): QueryBuilder
    {
        $this->having->addOperation($having, LinkOperator::OR);
        return $this;
    }

    /**
     * @param string|SqlOperations $having
     * @return $this
     */
    public function orNotHaving(SqlOperations|string $having): QueryBuilder
    {
        $this->having->orNot($having);
        return $this;
    }

    /**
     * Define the having part of this request manually.
     * @param string $having
     * @return $this
     */
    public function manualHaving(string $having): QueryBuilder
    {
        $this->having = new SqlOperations();
        $this->having->manualOperation($having);
        return $this;
    }

    /**
     * @param string $by
     * @param string $order ASC (default) or DESC
     * @return $this
     */
    public function orderBy(string $by, string $order = 'ASC'): QueryBuilder
    {
        $this->orderBy[] = SqlQuery::escape($by) . ((strtoupper($order) === 'DESC') ? ' DESC' : ' ASC');
        return $this;
    }

    /**
     * @param string $by
     * @param string $order ASC (default) or DESC
     * @return $this
     */
    public function addOrderBy(string $by, string $order = 'ASC'): QueryBuilder
    {
        $this->orderBy[] = SqlQuery::escape($by) . ((strtoupper($order) === 'DESC') ? ' DESC' : ' ASC');
        return $this;
    }

    /**
     * Define the number of item per page.
     * @param int $limit Zero = no limit.
     * @return $this
     */
    public function limit(int $limit = 0): QueryBuilder
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Define the start page of this request with the limit number of item per page define,
     * or the start item number (offset).
     * @param int|null $page
     * @param int $offset To define offset the $page variable must be null.
     * @return $this
     */
    public function offset(?int $page = 1, int $offset = 0): QueryBuilder
    {
        if (!is_null($page)) {
            if ($this->limit === 0) {
                throw new RuntimeException(
                    'Class QueryBuilder, function : offset. The limit must be define before the offset.'
                );
            }
            $this->offset = $this->limit * ($page - 1);
        } else {
            $this->offset = $offset;
        }
        return $this;
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return $this
     */
    public function update(string $table, ?string $alias = null): QueryBuilder
    {
        $this->type = SqlQuery::UPDATE;
        if (is_null($alias)) {
            $this->from[0] = $this->extractAlias($table);
        } else {
            $this->from[0] = ['name' => $table, 'alias' => $alias];
        }
        return $this;
    }

    /**
     * Set the update value for the column.
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function set(string $column, mixed $value): QueryBuilder
    {
        $this->setValue($column, $this->addDynamicParameter($value));
        return $this;
    }

    /**
     * @param string $table
     * @return $this
     */
    public function delete(string $table): QueryBuilder
    {
        $this->type = SqlQuery::DELETE;
        $this->from[0] = ['name' => $table];
        return $this;
    }

    /**
     * @param mixed $value
     * @param int $type
     * @return string
     */
    public function addNamedParameter(mixed $value, int $type = PDO::PARAM_STR): string
    {
        if (!empty($this->dynamicParameters)) {
            throw new RuntimeException(
                'Class QueryBuilder, function addNamedParameter. A named parameter can\'t be define because one or more dynamic parameter is already defined.'
            );
        }

        $number = (empty($this->namedParameters)) ? 1 : count($this->namedParameters) + 1;
        $this->namedParameters[':' . self::NAMED_PARAMETERS_PREFIX . $number] = [$value, $type];
        return ':' . self::NAMED_PARAMETERS_PREFIX . $number;
    }

    /**
     * @param mixed $value
     * @param int $type
     * @return string
     */
    public function addDynamicParameter(mixed $value, int $type = PDO::PARAM_STR): string
    {
        if (!empty($this->namedParameters)) {
            throw new RuntimeException(
                'Class QueryBuilder, function addDynamicParameter. A dynamic parameter can\'t be define because one or more named parameter is already defined.'
            );
        }
        $key = (!empty($dynamicParams = $this->getDynamicParameters())) ? array_key_last($dynamicParams) + 1 : 1;
        $this->dynamicParameters[$key] = [$value, $type];
        return '?';
    }

    /**
     * @param int|string $key
     * @param mixed $value
     * @param int $type
     * @return $this
     */
    public function setParameter(int|string $key, mixed $value, int $type = PDO::PARAM_STR): QueryBuilder
    {
        if (is_numeric($key)) {
            $this->dynamicParameters[$key] = [$value, $type];
        } else {
            $this->addNamedParameter($value, $type);
        }
        return $this;
    }

    /**
     * @param QueryBuilder ...$queries
     * @return $this
     */
    public function union(QueryBuilder ...$queries): QueryBuilder
    {
        $this->union = $queries;
        return $this;
    }

    /**
     * @param QueryBuilder ...$queries
     * @return $this
     */
    public function unionAll(QueryBuilder ...$queries): QueryBuilder
    {
        $this->unionAll = $queries;
        return $this;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->getQuery();
    }

    /**
     * @param string $name
     * @return array
     */
    public function extractAlias(string $name): array
    {
        $name = trim($name);

        if (strpos($name, ' as ') !== false) {
            $return = explode(' as ', $name);
            return ['name' => $return[0], 'alias' => $return[1]];
        }
        if (strpos($name, ' AS ') !== false) {
            $return = explode(' AS ', $name);
            return ['name' => $return[0], 'alias' => $return[1]];
        }
        if (strpos($name, ' ') !== false) {
            $return = explode(' ', $name);
            return ['name' => $return[0], 'alias' => $return[1]];
        }
        return ['name' => $name, 'alias' => null];
    }

    /** EmEngine functions */

    /**
     * Get the result of this request with the EmEngine.
     * @param string $type
     * @return Statement
     * @todo link this function with the EmEngine, return format........
     */
    public function getResult(): Statement
    {
        if ($this->emEngine === null) {
            throw new RuntimeException(
                'Class QueryBuilder, function getResult. The EmEngine is not define.'
            );
        }

        $pdo = $this->emEngine->getEntityManager()->getPdm();
        $pdoStatement = $pdo->prepare($this->getQuery());

        if (!empty($bindParams = $this->getBindParameters())) {
            foreach ($bindParams as $params) {
                $pdoStatement->bindParam($params[0], $params[1], $params[2]);
            }
        }

        return $pdoStatement;
    }

    /**
     * Execute the SQL request into the database with the EmEngine.
     * @return void
     */
    public function execute(): void
    {
        if ($this->emEngine === null) {
            throw new RuntimeException(
                'Class QueryBuilder, function getResult. The EmEngine is not define.'
            );
        }

        $pdo = $this->emEngine->getEntityManager()->getPdm();
        $pdo->prepare($this->getQuery());
    }


}

