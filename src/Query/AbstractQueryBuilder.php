<?php

namespace MulerTech\Database\Query;

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\PhpInterface\Statement;
use MulerTech\Database\Relational\Sql\Raw;
use PDO;
use RuntimeException;

/**
 * Abstract base class for all query builders providing common functionality
 *
 * @package MulerTech\Database\Query
 * @author Sébastien Muler
 */
abstract class AbstractQueryBuilder
{
    /**
     * @var array<string, array{0: mixed, 1: int}>
     */
    protected array $namedParameters = [];

    /**
     * @var string
     */
    protected const NAMED_PARAMETERS_PREFIX = 'namedParam';

    /**
     * @param EmEngine|null $emEngine
     */
    public function __construct(protected readonly ?EmEngine $emEngine = null)
    {
    }

    /**
     * @return string
     */
    abstract public function toSql(): string;

    /**
     * @return string
     */
    abstract public function getQueryType(): string;

    /**
     * @param mixed $value
     * @param int $type
     * @return string
     */
    public function addNamedParameter(mixed $value, int $type = PDO::PARAM_STR): string
    {
        $number = count($this->namedParameters) + 1;
        $paramName = ':' . self::NAMED_PARAMETERS_PREFIX . $number;
        $this->namedParameters[$paramName] = [$value, $type];

        return $paramName;
    }

    /**
     * @return array<string, array{0: mixed, 1: int}>
     */
    public function getNamedParameters(): array
    {
        return $this->namedParameters;
    }

    /**
     * @return array<int, array{0: int|string, 1: mixed, 2: int}>|null
     */
    public function getBindParameters(): ?array
    {
        $parameters = $this->namedParameters;

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
     * @return Statement
     */
    public function getResult(): Statement
    {
        if ($this->emEngine === null) {
            throw new RuntimeException('EmEngine is not defined.');
        }

        $pdo = $this->emEngine->getEntityManager()->getPdm();
        $statement = $pdo->prepare($this->toSql());

        if (!empty($this->namedParameters)) {
            foreach ($this->namedParameters as $key => $value) {
                $statement->bindParam($key, $value[0], $value[1]);
            }
        }

        return $statement;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $statement = $this->getResult();
        $statement->execute();
        $statement->closeCursor();
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->toSql();
    }

    /**
     * @param string $identifier
     * @return string
     */
    public static function escapeIdentifier(string $identifier): string
    {
        if ($identifier === '*' || str_contains($identifier, '(')) {
            return $identifier;
        }

        if (str_contains($identifier, '.')) {
            if (str_contains($identifier, ' ')) {
                $spaceParts = explode(' ', $identifier, 2);
                return self::escapeIdentifier($spaceParts[0]) . ' ' . $spaceParts[1];
            }

            $parts = explode('.', $identifier, 2);

            return self::escapeIdentifier($parts[0]) . '.' . self::escapeIdentifier($parts[1]);
        }

        if (str_contains($identifier, ' ')) {
            $as = str_contains(strtolower($identifier), ' as ') ? ' ' : ' AS ';
            $parts = explode(' ', $identifier, 2);
            return self::escapeIdentifier($parts[0]) . $as . $parts[1];
        }

        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * @param string $tableWithAlias
     * @return array{table: string, alias: string|null}
     */
    protected function parseTableAlias(string $tableWithAlias): array
    {
        $tableWithAlias = trim($tableWithAlias);

        if (str_contains($tableWithAlias, ' as ')) {
            [$table, $alias] = explode(' as ', $tableWithAlias, 2);
            return ['table' => trim($table), 'alias' => trim($alias)];
        }

        if (str_contains($tableWithAlias, ' AS ')) {
            [$table, $alias] = explode(' AS ', $tableWithAlias, 2);
            return ['table' => trim($table), 'alias' => trim($alias)];
        }

        if (str_contains($tableWithAlias, ' ')) {
            [$table, $alias] = explode(' ', $tableWithAlias, 2);
            return ['table' => trim($table), 'alias' => trim($alias)];
        }

        return ['table' => $tableWithAlias, 'alias' => null];
    }

    /**
     * @return void
     */
    protected function resetParameters(): void
    {
        $this->namedParameters = [];
    }
}
