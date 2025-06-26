<?php

declare(strict_types=1);

namespace MulerTech\Database\Query;

use MulerTech\Database\Core\Cache\QueryStructureCache;
use MulerTech\Database\Core\Parameters\QueryParameterBag;
use MulerTech\Database\Core\Traits\ParameterHandlerTrait;
use MulerTech\Database\Core\Traits\SqlFormatterTrait;
use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\PhpInterface\Statement;
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
     * @var QueryStructureCache|null
     */
    protected static ?QueryStructureCache $structureCache = null;

    /**
     * @var QueryCompiler|null
     */
    protected ?QueryCompiler $compiler = null;

    /**
     * @var array<string, mixed>
     */
    protected array $queryParts = [];

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

        if (self::$structureCache === null) {
            self::$structureCache = new QueryStructureCache();
        }
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
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        }

        return $stmt->fetchAll(PDO::FETCH_CLASS, $fetchClass);
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
        } else {
            $stmt->setFetchMode(PDO::FETCH_CLASS, $fetchClass);
            $result = $stmt->fetch();
        }

        return $result !== false ? $result : null;
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
     * @param string $key
     * @param mixed $value
     * @return self
     */
    protected function setQueryPart(string $key, mixed $value): self
    {
        $this->queryParts[$key] = $value;
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $key
     * @return mixed
     */
    protected function getQueryPart(string $key): mixed
    {
        return $this->queryParts[$key] ?? null;
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function hasQueryPart(string $key): bool
    {
        return isset($this->queryParts[$key]);
    }

    /**
     * @return QueryParameterBag
     */
    public function getParameterBag(): QueryParameterBag
    {
        return $this->parameterBag;
    }

    /**
     * @param QueryCompiler $compiler
     * @return self
     */
    public function setCompiler(QueryCompiler $compiler): self
    {
        $this->compiler = $compiler;
        return $this;
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
}
