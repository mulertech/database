<?php

namespace MulerTech\Database\PhpInterface;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Class PhpDatabaseManager represent PDO connection.
 * @package MulerTech\Database\PhpInterface
 * @author Sébastien Muler
 */
class PhpDatabaseManager implements PhpDatabaseInterface
{

    public const string DATABASE_URL = 'DATABASE_URL';
    /**
     * @var ConnectorInterface $connector
     */
    private ConnectorInterface $connector;
    /**
     * @var array $parameters
     */
    private array $parameters;
    /**
     * @var PDO PDO connection.
     */
    private PDO $connection;
    /**
     * @var int $transactionLevel The transaction level is for prevent a beginTransaction when a transaction is up.
     */
    private int $transactionLevel = 0;

    /**
     * PhpDatabaseManager constructor.
     * @param ConnectorInterface $connector
     * @param $parameters
     */
    public function __construct(ConnectorInterface $connector, $parameters)
    {
        $this->connector = $connector;
        $this->parameters = $parameters;
    }

    /**
     * @return PDO Connection to database (PDO)
     * @throws RuntimeException
     */
    public function getConnection(): PDO
    {
        if (!isset($this->connection)) {
            $parameters = self::populateParameters($this->parameters);
            $this->connection = $this->connector->connect($parameters, $parameters['user'], $parameters['pass']);
        }

        return $this->connection;
    }

    /**
     * @param string $query
     * @param array $options
     * @return Statement
     */
    public function prepare(string $query, array $options = []): Statement
    {
        try {
            if (false === $statement = $this->getConnection()->prepare(...func_get_args())) {
                throw new RuntimeException($this->getConnection()->errorInfo()[2]);
            }

            return new Statement($statement);
        } catch (PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * @return bool
     */
    public function beginTransaction(): bool
    {
        if ($this->transactionLevel === 0) {
            try {
                $this->transactionLevel = 1;
                return $this->getConnection()->beginTransaction();
            } catch (PDOException $exception) {
                throw new PDOException($exception);
            }
        }
        //Transaction is up, increase the transaction level.
        ++$this->transactionLevel;
        return true;
    }

    /**
     * @return bool
     */
    public function commit(): bool
    {
        if ($this->transactionLevel === 1) {
            try {
                return $this->getConnection()->commit();
            } catch (PDOException $exception) {
                throw new PDOException($exception);
            }
        }
        //If the transaction level is greater than 1, there is a decrease but no commit yet.
        if ($this->transactionLevel !== 0) {
            --$this->transactionLevel;
        }
        return true;
    }

    /**
     * @return bool
     */
    public function rollBack(): bool
    {
        try {
            return $this->getConnection()->rollBack();
        } catch (PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->getConnection()->inTransaction();
    }

    /**
     * @param int $attribute
     * @param mixed $value
     * @return bool
     */
    public function setAttribute(int $attribute, $value): bool
    {
        return $this->getConnection()->setAttribute($attribute, $value);
    }

    /**
     * @param string $statement
     * @return int
     */
    public function exec(string $statement): int
    {
        return $this->getConnection()->exec($statement);
    }

    /**
     * @param string $statement
     * @param int $mode
     * @param null $arg3
     * @param array $ctorargs
     * @return Statement
     */
    public function query(string $statement, int $mode = PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, array $ctorargs = []): Statement
    {
        $pdo = $this->getConnection();
        $result = $pdo->query(...func_get_args());
        if (false === $result) {
            throw new RuntimeException(
                sprintf(
                    'Class : PhpDatabaseManager, function : query. The query was failed. Message : %s. Statement : %s.',
                    $this->getConnection()->errorInfo()[2],
                    $statement
                )
            );
        }
        return new Statement($result);
    }

    /**
     * @param string|null $name
     * @return string
     */
    public function lastInsertId(string $name = null): string
    {
        return $this->getConnection()->lastInsertId($name);
    }

    /**
     * @return mixed
     */
    public function errorCode(): mixed
    {
        return $this->getConnection()->errorCode();
    }

    /**
     * @return array
     */
    public function errorInfo(): array
    {
        return $this->getConnection()->errorInfo();
    }

    /**
     * @param int $attribute
     * @return mixed
     */
    public function getAttribute(int $attribute): mixed
    {
        return $this->getConnection()->getAttribute($attribute);
    }

    /**
     * @return array
     */
    public function getAvailableDrivers(): array
    {
        return $this->getConnection()::getAvailableDrivers();
    }

    /**
     * @param string $string
     * @param int $type
     * @return string
     */
    public function quote(string $string, int $type = PDO::PARAM_STR): string
    {
        if (false === $quoteString = $this->getConnection()->quote(...func_get_args())) {
            throw new RuntimeException('Class : PhpDatabaseManager, function : quote. The function quote is not supported by the database driver used.');
        }
        return $quoteString;
    }

    /**
     * Add the URL parameters parsed into the parameters variable.
     * @param array $parameters
     * @return array
     */
    public static function populateParameters(array $parameters = []): array
    {
        if (!empty($parameters[self::DATABASE_URL])) {
            $url = $parameters[self::DATABASE_URL];
            $parsedParams = self::decodeUrl(parse_url($url));
            if (isset($parsedParams['path'])) {
                $parsedParams['dbname'] = substr($parsedParams['path'], 1);
            }
            if (isset($parsedParams['query'])) {
                parse_str($parsedParams['query'], $parsedQuery);
                $parsedParams = array_merge($parsedParams, $parsedQuery);
            }
            return $parsedParams;
        }
        return self::populateEnvParameters($parameters);
    }

    /**
     * The database parameters can be defined individually in the env file with for each key name :
     * DATABASE_ with the name of the component (in uppercase)
     * @param array $parameters
     * @return array
     */
    private static function populateEnvParameters(array $parameters = []): array
    {
        if (false !== $scheme = getenv('DATABASE_SCHEME')) {
            $parameters['scheme'] = $scheme;
        }
        if (false !== $host = getenv('DATABASE_HOST')) {
            $parameters['host'] = $host;
        }
        if (false !== $port = getenv('DATABASE_PORT')) {
            $parameters['port'] = (int)$port;
        }
        if (false !== $user = getenv('DATABASE_USER')) {
            $parameters['user'] = $user;
        }
        if (false !== $pass = getenv('DATABASE_PASS')) {
            $parameters['pass'] = $pass;
        }
        if (false !== $path = getenv('DATABASE_PATH')) {
            $parameters['path'] = $path;
            $parameters['dbname'] = substr($parameters['path'], 1);
        }
        if (false !== $query = getenv('DATABASE_QUERY')) {
            $parameters['query'] = $query;
            parse_str($parameters['query'], $parsedQuery);
            $parameters = array_merge($parameters, $parsedQuery);
        }
        if (false !== $fragment = getenv('DATABASE_FRAGMENT')) {
            $parameters['fragment'] = $fragment;
        }
        return $parameters;
    }

    /**
     * @param array $url
     * @return array
     */
    private static function decodeUrl(array $url): array
    {
        array_walk_recursive($url, static function(&$urlPart) {
            $urlPart = urldecode($urlPart);
        });
        return $url;
    }

}