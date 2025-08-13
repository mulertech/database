# Exceptions et Gestion d'Erreurs - API Reference

Cette section documente toutes les exceptions spécifiques à MulerTech Database ORM et les stratégies de gestion d'erreurs.

## 📋 Table des matières

- [Hiérarchie des exceptions](#hiérarchie-des-exceptions)
- [Exceptions de base](#exceptions-de-base)
- [Exceptions de connexion](#exceptions-de-connexion)
- [Exceptions de mapping](#exceptions-de-mapping)
- [Exceptions de requête](#exceptions-de-requête)
- [Exceptions de transaction](#exceptions-de-transaction)
- [Exceptions de cache](#exceptions-de-cache)
- [Gestion des erreurs](#gestion-des-erreurs)

## Hiérarchie des exceptions

```
\Exception
├── MulerTechDatabaseException (base)
├── ConnectionException
├── QueryException
├── MappingException
├── TransactionException
├── CacheException
├── ValidationException
├── EntityNotFoundException
├── DuplicateEntityException
└── ConfigurationException
```

## Exceptions de base

### MulerTechDatabaseException

Exception de base pour tous les erreurs de MulerTech Database.

```php
namespace MulerTech\Database\Exception;

/**
 * Exception de base pour MulerTech Database
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MulerTechDatabaseException extends \Exception
{
    /**
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Obtient des informations contextuelles sur l'erreur
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [];
    }
}
```

### RuntimeException

Exception pour les erreurs d'exécution.

```php
namespace MulerTech\Database\Exception;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class RuntimeException extends MulerTechDatabaseException
{
    public function __construct(
        string $message = 'Runtime error occurred',
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
```

### InvalidArgumentException

Exception pour les arguments invalides.

```php
namespace MulerTech\Database\Exception;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class InvalidArgumentException extends MulerTechDatabaseException
{
    private mixed $invalidValue;

    /**
     * @param string $message
     * @param mixed $invalidValue
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'Invalid argument provided',
        mixed $invalidValue = null,
        int $code = 400,
        ?\Throwable $previous = null
    ) {
        $this->invalidValue = $invalidValue;
        parent::__construct($message, $code, $previous);
    }

    public function getInvalidValue(): mixed
    {
        return $this->invalidValue;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [
            'invalid_value' => $this->invalidValue,
            'value_type' => gettype($this->invalidValue)
        ];
    }
}
```

## Exceptions de connexion

### ConnectionException

Exception pour les erreurs de connexion à la base de données.

```php
namespace MulerTech\Database\Exception;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ConnectionException extends MulerTechDatabaseException
{
    private array $connectionConfig;

    /**
     * @param string $message
     * @param array<string, mixed> $connectionConfig
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'Database connection failed',
        array $connectionConfig = [],
        int $code = 503,
        ?\Throwable $previous = null
    ) {
        $this->connectionConfig = $this->sanitizeConfig($connectionConfig);
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConnectionConfig(): array
    {
        return $this->connectionConfig;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [
            'connection_config' => $this->connectionConfig
        ];
    }

    /**
     * Supprime les informations sensibles de la configuration
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function sanitizeConfig(array $config): array
    {
        $sanitized = $config;
        
        // Masquer les informations sensibles
        if (isset($sanitized['password'])) {
            $sanitized['password'] = '***';
        }
        
        if (isset($sanitized['username'])) {
            $sanitized['username'] = substr($sanitized['username'], 0, 3) . '***';
        }
        
        return $sanitized;
    }
}
```

### ConnectionTimeoutException

Exception pour les timeouts de connexion.

```php
namespace MulerTech\Database\Exception;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ConnectionTimeoutException extends ConnectionException
{
    private float $timeout;

    /**
     * @param float $timeout
     * @param string $message
     * @param array<string, mixed> $connectionConfig
     * @param \Throwable|null $previous
     */
    public function __construct(
        float $timeout,
        string $message = 'Connection timeout exceeded',
        array $connectionConfig = [],
        ?\Throwable $previous = null
    ) {
        $this->timeout = $timeout;
        parent::__construct($message, $connectionConfig, 504, $previous);
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return array_merge(parent::getContext(), [
            'timeout' => $this->timeout
        ]);
    }
}
```

## Exceptions de mapping

### MappingException

Exception pour les erreurs de mapping d'entités.

```php
namespace MulerTech\Database\Exception;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MappingException extends MulerTechDatabaseException
{
    private string $entityClass;

    /**
     * @param string $entityClass
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $entityClass,
        string $message = 'Entity mapping error',
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        $this->entityClass = $entityClass;
        parent::__construct($message, $code, $previous);
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [
            'entity_class' => $this->entityClass
        ];
    }
}
```

### InvalidMappingException

Exception pour les configurations de mapping invalides.

```php
namespace MulerTech\Database\Exception;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class InvalidMappingException extends MappingException
{
    private string $field;
    private mixed $invalidValue;

    /**
     * @param string $entityClass
     * @param string $field
     * @param mixed $invalidValue
     * @param string $message
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $entityClass,
        string $field,
        mixed $invalidValue = null,
        string $message = 'Invalid mapping configuration',
        ?\Throwable $previous = null
    ) {
        $this->field = $field;
        $this->invalidValue = $invalidValue;
        
        $fullMessage = sprintf(
            '%s for field "%s" in entity "%s"',
            $message,
            $field,
            $entityClass
        );
        
        parent::__construct($entityClass, $fullMessage, 400, $previous);
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getInvalidValue(): mixed
    {
        return $this->invalidValue;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return array_merge(parent::getContext(), [
            'field' => $this->field,
            'invalid_value' => $this->invalidValue
        ]);
    }
}
```

## Exceptions de requête

### QueryException

Exception pour les erreurs de requête SQL.

```php
namespace MulerTech\Database\Exception;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryException extends MulerTechDatabaseException
{
    private string $sql;
    private array $parameters;

    /**
     * @param string $sql
     * @param array<mixed> $parameters
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $sql,
        array $parameters = [],
        string $message = 'Query execution failed',
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        $this->sql = $sql;
        $this->parameters = $parameters;
        parent::__construct($message, $code, $previous);
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [
            'sql' => $this->sql,
            'parameters' => $this->parameters
        ];
    }
}
```

### SqlSyntaxException

Exception pour les erreurs de syntaxe SQL.

```php
namespace MulerTech\Database\Exception;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SqlSyntaxException extends QueryException
{
    private ?int $errorPosition;

    /**
     * @param string $sql
     * @param int|null $errorPosition
     * @param array<mixed> $parameters
     * @param string $message
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $sql,
        ?int $errorPosition = null,
        array $parameters = [],
        string $message = 'SQL syntax error',
        ?\Throwable $previous = null
    ) {
        $this->errorPosition = $errorPosition;
        parent::__construct($sql, $parameters, $message, 400, $previous);
    }

    public function getErrorPosition(): ?int
    {
        return $this->errorPosition;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return array_merge(parent::getContext(), [
            'error_position' => $this->errorPosition
        ]);
    }
}
```

## Exceptions de transaction

### TransactionException

Exception pour les erreurs de transaction.

```php
namespace MulerTech\Database\Exception;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class TransactionException extends MulerTechDatabaseException
{
    private string $operation;

    /**
     * @param string $operation
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $operation,
        string $message = 'Transaction error',
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        $this->operation = $operation;
        parent::__construct($message, $code, $previous);
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [
            'operation' => $this->operation
        ];
    }
}
```

### DeadlockException

Exception pour les deadlocks de transaction.

```php
namespace MulerTech\Database\Exception;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class DeadlockException extends TransactionException
{
    private array $involvedTables;

    /**
     * @param array<string> $involvedTables
     * @param string $message
     * @param \Throwable|null $previous
     */
    public function __construct(
        array $involvedTables = [],
        string $message = 'Transaction deadlock detected',
        ?\Throwable $previous = null
    ) {
        $this->involvedTables = $involvedTables;
        parent::__construct('deadlock', $message, 409, $previous);
    }

    /**
     * @return array<string>
     */
    public function getInvolvedTables(): array
    {
        return $this->involvedTables;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return array_merge(parent::getContext(), [
            'involved_tables' => $this->involvedTables
        ]);
    }
}
```

## Exceptions de cache

### CacheException

Exception pour les erreurs de cache.

```php
namespace MulerTech\Database\Exception;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class CacheException extends MulerTechDatabaseException
{
    private string $cacheKey;
    private string $operation;

    /**
     * @param string $cacheKey
     * @param string $operation
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $cacheKey,
        string $operation,
        string $message = 'Cache operation failed',
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        $this->cacheKey = $cacheKey;
        $this->operation = $operation;
        parent::__construct($message, $code, $previous);
    }

    public function getCacheKey(): string
    {
        return $this->cacheKey;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [
            'cache_key' => $this->cacheKey,
            'operation' => $this->operation
        ];
    }
}
```

## Exceptions d'entité

### EntityNotFoundException

Exception quand une entité n'est pas trouvée.

```php
namespace MulerTech\Database\Exception;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EntityNotFoundException extends MulerTechDatabaseException
{
    private string $entityClass;
    private mixed $identifier;

    /**
     * @param string $entityClass
     * @param mixed $identifier
     * @param string $message
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $entityClass,
        mixed $identifier,
        string $message = 'Entity not found',
        ?\Throwable $previous = null
    ) {
        $this->entityClass = $entityClass;
        $this->identifier = $identifier;
        
        $fullMessage = sprintf(
            '%s: %s with identifier "%s"',
            $message,
            $entityClass,
            (string) $identifier
        );
        
        parent::__construct($fullMessage, 404, $previous);
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getIdentifier(): mixed
    {
        return $this->identifier;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [
            'entity_class' => $this->entityClass,
            'identifier' => $this->identifier
        ];
    }
}
```

### DuplicateEntityException

Exception pour les entités dupliquées.

```php
namespace MulerTech\Database\Exception;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class DuplicateEntityException extends MulerTechDatabaseException
{
    private string $entityClass;
    private array $conflictingFields;

    /**
     * @param string $entityClass
     * @param array<string, mixed> $conflictingFields
     * @param string $message
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $entityClass,
        array $conflictingFields = [],
        string $message = 'Duplicate entity detected',
        ?\Throwable $previous = null
    ) {
        $this->entityClass = $entityClass;
        $this->conflictingFields = $conflictingFields;
        
        $fullMessage = sprintf(
            '%s: %s with conflicting fields: %s',
            $message,
            $entityClass,
            implode(', ', array_keys($conflictingFields))
        );
        
        parent::__construct($fullMessage, 409, $previous);
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConflictingFields(): array
    {
        return $this->conflictingFields;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [
            'entity_class' => $this->entityClass,
            'conflicting_fields' => $this->conflictingFields
        ];
    }
}
```

## Gestion des erreurs

### ErrorHandler

Gestionnaire d'erreurs centralisé pour MulerTech Database.

```php
namespace MulerTech\Database\Exception;

use Psr\Log\LoggerInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ErrorHandler
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Gère une exception de MulerTech Database
     *
     * @param MulerTechDatabaseException $exception
     * @param bool $rethrow
     * @throws MulerTechDatabaseException
     */
    public function handleException(MulerTechDatabaseException $exception, bool $rethrow = true): void
    {
        // Log de l'exception
        $this->logException($exception);
        
        // Actions spécifiques selon le type d'exception
        $this->handleSpecificException($exception);
        
        if ($rethrow) {
            throw $exception;
        }
    }

    /**
     * Log une exception avec son contexte
     */
    private function logException(MulerTechDatabaseException $exception): void
    {
        if (!$this->logger) {
            return;
        }

        $level = $this->getLogLevel($exception);
        $context = [
            'exception_class' => get_class($exception),
            'exception_code' => $exception->getCode(),
            'context' => $exception->getContext(),
            'trace' => $exception->getTraceAsString()
        ];

        $this->logger->log($level, $exception->getMessage(), $context);
    }

    /**
     * Détermine le niveau de log selon le type d'exception
     */
    private function getLogLevel(MulerTechDatabaseException $exception): string
    {
        return match (true) {
            $exception instanceof ConnectionException => 'critical',
            $exception instanceof QueryException => 'error',
            $exception instanceof EntityNotFoundException => 'warning',
            $exception instanceof InvalidArgumentException => 'notice',
            default => 'error'
        };
    }

    /**
     * Actions spécifiques selon le type d'exception
     */
    private function handleSpecificException(MulerTechDatabaseException $exception): void
    {
        match (true) {
            $exception instanceof DeadlockException => $this->handleDeadlock($exception),
            $exception instanceof ConnectionTimeoutException => $this->handleConnectionTimeout($exception),
            default => null
        };
    }

    private function handleDeadlock(DeadlockException $exception): void
    {
        // Logique spécifique aux deadlocks
        // Par exemple : retry automatique, notification d'équipe, etc.
    }

    private function handleConnectionTimeout(ConnectionTimeoutException $exception): void
    {
        // Logique spécifique aux timeouts
        // Par exemple : tentative de reconnexion, fallback sur cache, etc.
    }
}
```

### Exemples d'usage

```php
use MulerTech\Database\Exception\ErrorHandler;
use MulerTech\Database\Exception\EntityNotFoundException;
use MulerTech\Database\Exception\QueryException;

// Gestionnaire d'erreurs avec logger
$errorHandler = new ErrorHandler($logger);

try {
    $user = $em->find(User::class, 999);
    if (!$user) {
        throw new EntityNotFoundException(User::class, 999);
    }
} catch (EntityNotFoundException $e) {
    $errorHandler->handleException($e, false); // Log sans re-throw
    // Gérer l'absence d'entité (ex: afficher erreur 404)
}

try {
    $em->createQueryBuilder()
       ->select('invalid_column')
       ->from('non_existent_table')
       ->getQuery()
       ->execute();
} catch (QueryException $e) {
    $errorHandler->handleException($e); // Log et re-throw
}

// Gestion globale d'exceptions
set_exception_handler(function (\Throwable $exception) use ($errorHandler) {
    if ($exception instanceof MulerTechDatabaseException) {
        $errorHandler->handleException($exception, false);
    }
    
    // Gérer les autres types d'exceptions...
});
```

---

Cette documentation des exceptions fournit un aperçu complet de la gestion d'erreurs dans MulerTech Database ORM, permettant un debugging efficace et une gestion robuste des erreurs en production.
