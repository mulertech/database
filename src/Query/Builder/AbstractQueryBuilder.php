<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder;

use MulerTech\Database\Core\Parameters\QueryParameterBag;
use MulerTech\Database\Core\Traits\ParameterHandlerTrait;
use MulerTech\Database\Core\Traits\SqlFormatterTrait;
use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Database\Interface\Statement;
use PDO;
use RuntimeException;
use stdClass;

/**
 * Class AbstractQueryBuilder
 *
 * Base class for all query builders with enhanced functionality
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
abstract class AbstractQueryBuilder
{
    use ParameterHandlerTrait;
    use SqlFormatterTrait;

    /**
     * @var QueryParameterBag
     */
    protected QueryParameterBag $parameterBag;

    /**
     * @var bool
     */
    protected bool $isDirty = true;

    /**
     * @var string|null
     */
    protected ?string $cachedSql = null;

    /**
     * @param EmEngine|null $emEngine
     */
    public function __construct(protected ?EmEngine $emEngine = null)
    {
        $this->parameterBag = new QueryParameterBag();
    }

    /**
     * @return string
     */
    abstract public function getQueryType(): string;

    /**
     * @return string
     */
    abstract protected function buildSql(): string;

    /**
     * @return string
     */
    public function toSql(): string
    {
        // Build SQL
        $sql = $this->buildSql();

        $this->isDirty = false;

        return $sql;
    }

    /**
     * @return Statement
     */
    public function getResult(): Statement
    {
        if ($this->emEngine === null) {
            throw new RuntimeException('EmEngine is not set. Cannot prepare statement.');
        }

        $sql = $this->toSql();
        $stmt = $this->emEngine->getEntityManager()->getPdm()->prepare($sql);

        $this->bindAllParameters($stmt);

        return $stmt;
    }

    /**
     * @return int
     */
    public function execute(): int
    {
        $stmt = $this->getResult();
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * @param string $fetchClass
     * @return array<object>
     */
    public function fetchAll(string $fetchClass = stdClass::class): array
    {
        $stmt = $this->getResult();
        $stmt->execute();

        if ($fetchClass === stdClass::class) {
            $result = $stmt->fetchAll(PDO::FETCH_OBJ);
            return array_filter($result, 'is_object');
        }

        $result = $stmt->fetchAll(PDO::FETCH_CLASS, $fetchClass);
        return array_filter($result, 'is_object');
    }

    /**
     * @param string $fetchClass
     * @return object|null
     */
    public function fetchOne(string $fetchClass = stdClass::class): ?object
    {
        $stmt = $this->getResult();
        $stmt->execute();

        if ($fetchClass === stdClass::class) {
            $result = $stmt->fetch(PDO::FETCH_OBJ);
            return ($result !== false && is_object($result)) ? $result : null;
        }

        $stmt->setFetchMode(PDO::FETCH_CLASS, $fetchClass);
        $result = $stmt->fetch();
        return ($result !== false && is_object($result)) ? $result : null;
    }

    /**
     * @return mixed
     */
    public function fetchScalar(): mixed
    {
        $stmt = $this->getResult();
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    /**
     * @param Statement $stmt
     * @return void
     */
    protected function bindAllParameters(Statement $stmt): void
    {
        // Bind from trait
        $this->bindParameters($stmt);

        // Bind from parameter bag
        $this->parameterBag->bind($stmt);
    }

    /**
     * @return QueryParameterBag
     */
    public function getParameterBag(): QueryParameterBag
    {
        return $this->parameterBag;
    }

    /**
     * @return self
     */
    public function clone(): self
    {
        $clone = clone $this;
        $clone->parameterBag = clone $this->parameterBag;
        $clone->resetParameters();
        $clone->isDirty = true;
        $clone->cachedSql = null;
        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDebugInfo(): array
    {
        return [
            'sql' => $this->toSql(),
            'parameters' => $this->parameterBag->toArray(),
            'type' => $this->getQueryType(),
            'cached' => !$this->isDirty,
        ];
    }

    /**
     * Common method for handling parameter binding across all builders
     * @param mixed $value
     * @param int|null $type
     * @return string Parameter placeholder
     */
    protected function bindParameter(mixed $value, ?int $type = PDO::PARAM_STR): string
    {
        if ($value instanceof Raw) {
            return $value->getValue();
        }
        return $this->parameterBag->add($value, $type);
    }

    /**
     * Common method for validating table names
     * @param string $table
     * @return void
     * @throws RuntimeException
     */
    protected function validateTableName(string $table): void
    {
        if (empty($table)) {
            throw new RuntimeException('Table name cannot be empty');
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new RuntimeException('Invalid table name format');
        }
    }

    /**
     * Common method for validating column names
     * @param string $column
     * @return void
     * @throws RuntimeException
     */
    protected function validateColumnName(string $column): void
    {
        if (empty($column)) {
            throw new RuntimeException('Column name cannot be empty');
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new RuntimeException('Invalid column name format');
        }
    }

    /**
     * Common method for building SET clauses
     * @param array<string, mixed> $data
     * @return string
     */
    protected function buildSetClause(array $data): string
    {
        $setParts = [];
        foreach ($data as $column => $value) {
            $this->validateColumnName($column);
            $placeholder = $this->bindParameter($value);
            $setParts[] = "`$column` = $placeholder";
        }
        return implode(', ', $setParts);
    }
}
