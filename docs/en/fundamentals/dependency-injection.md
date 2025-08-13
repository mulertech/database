# Dependency Injection

Configuring and using MulerTech Database with dependency injection containers.

## Basic Setup

### Manual Configuration

```php
<?php

declare(strict_types=1);

use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Mapping\MetadataRegistry;

// Database configuration
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'my_app',
    'username' => 'user',
    'password' => 'password'
];

// Create instances
$pdm = new PhpDatabaseManager($config);
$metadataRegistry = new MetadataRegistry();
$entityManager = new EntityManager($pdm, $metadataRegistry);
```

### Service Container Integration

Most PHP frameworks provide dependency injection containers. Here's how to integrate with popular ones:

## PSR-11 Container

```php
use Psr\Container\ContainerInterface;
use MulerTech\Database\ORM\EntityManagerInterface;

class DatabaseServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Database Manager
        $container->set(PhpDatabaseManager::class, function() {
            return new PhpDatabaseManager($this->getDatabaseConfig());
        });

        // Metadata Registry
        $container->set(MetadataRegistry::class, function() {
            return new MetadataRegistry();
        });

        // Entity Manager
        $container->set(EntityManagerInterface::class, function($container) {
            return new EntityManager(
                $container->get(PhpDatabaseManager::class),
                $container->get(MetadataRegistry::class)
            );
        });
    }

    private function getDatabaseConfig(): array
    {
        return [
            'host' => $_ENV['DB_HOST'],
            'port' => (int) $_ENV['DB_PORT'],
            'database' => $_ENV['DB_DATABASE'],
            'username' => $_ENV['DB_USERNAME'],
            'password' => $_ENV['DB_PASSWORD']
        ];
    }
}
```

## Framework Integration

### Symfony Integration

```php
// config/services.yaml
services:
    MulerTech\Database\Database\Interface\PhpDatabaseManager:
        arguments:
            $config:
                host: '%env(DB_HOST)%'
                port: '%env(int:DB_PORT)%'
                database: '%env(DB_DATABASE)%'
                username: '%env(DB_USERNAME)%'
                password: '%env(DB_PASSWORD)%'

    MulerTech\Database\Mapping\MetadataRegistry: ~

    MulerTech\Database\ORM\EntityManagerInterface:
        class: MulerTech\Database\ORM\EntityManager
        arguments:
            - '@MulerTech\Database\Database\Interface\PhpDatabaseManager'
            - '@MulerTech\Database\Mapping\MetadataRegistry'

    # Auto-wire repositories
    App\Repository\:
        resource: '../src/Repository/*'
        arguments:
            - '@MulerTech\Database\ORM\EntityManagerInterface'
```

```php
// src/Controller/UserController.php
use MulerTech\Database\ORM\EntityManagerInterface;

class UserController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function create(Request $request): Response
    {
        $user = new User();
        $user->setName($request->get('name'));
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        return new JsonResponse(['id' => $user->getId()]);
    }
}
```

### Laravel Integration

```php
// app/Providers/DatabaseServiceProvider.php
use Illuminate\Support\ServiceProvider;
use MulerTech\Database\ORM\EntityManagerInterface;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PhpDatabaseManager::class, function() {
            return new PhpDatabaseManager([
                'host' => config('database.connections.mysql.host'),
                'port' => config('database.connections.mysql.port'),
                'database' => config('database.connections.mysql.database'),
                'username' => config('database.connections.mysql.username'),
                'password' => config('database.connections.mysql.password'),
            ]);
        });

        $this->app->singleton(MetadataRegistry::class);

        $this->app->singleton(EntityManagerInterface::class, function($app) {
            return new EntityManager(
                $app->make(PhpDatabaseManager::class),
                $app->make(MetadataRegistry::class)
            );
        });
    }
}
```

```php
// app/Http/Controllers/UserController.php
use MulerTech\Database\ORM\EntityManagerInterface;

class UserController extends Controller
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function store(Request $request)
    {
        $user = new User();
        $user->setName($request->input('name'));
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        return response()->json(['id' => $user->getId()]);
    }
}
```

## Repository Injection

### Interface-based Repositories

```php
interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;
    public function findActiveUsers(): array;
}

class UserRepository extends EntityRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findActiveUsers(): array
    {
        return $this->findBy(['active' => true]);
    }
}
```

### Container Configuration

```php
// Register repository
$container->set(UserRepositoryInterface::class, function($container) {
    $entityManager = $container->get(EntityManagerInterface::class);
    return $entityManager->getRepository(User::class);
});

// Or with custom repository class
$container->set(UserRepositoryInterface::class, function($container) {
    return new UserRepository(
        $container->get(EntityManagerInterface::class)
    );
});
```

### Usage in Services

```php
class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function createUser(string $name, string $email): User
    {
        // Check if user exists
        $existingUser = $this->userRepository->findByEmail($email);
        if ($existingUser) {
            throw new UserAlreadyExistsException();
        }

        // Create new user
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function getActiveUsers(): array
    {
        return $this->userRepository->findActiveUsers();
    }
}
```

## Configuration Management

### Environment-based Configuration

```php
class DatabaseConfig
{
    public static function fromEnvironment(): array
    {
        return [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'database' => $_ENV['DB_DATABASE'] ?? throw new InvalidArgumentException('DB_DATABASE required'),
            'username' => $_ENV['DB_USERNAME'] ?? throw new InvalidArgumentException('DB_USERNAME required'),
            'password' => $_ENV['DB_PASSWORD'] ?? throw new InvalidArgumentException('DB_PASSWORD required'),
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        ];
    }
}
```

### Multi-Environment Setup

```php
class DatabaseServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Production database
        $container->set('database.production', function() {
            return new PhpDatabaseManager(DatabaseConfig::fromEnvironment());
        });

        // Test database (for testing)
        $container->set('database.test', function() {
            return new PhpDatabaseManager([
                'host' => 'localhost',
                'database' => 'test_db',
                'username' => 'test',
                'password' => 'test'
            ]);
        });

        // Use appropriate database based on environment
        $container->set(PhpDatabaseManager::class, function($container) {
            $env = $_ENV['APP_ENV'] ?? 'production';
            return $container->get("database.{$env}");
        });
    }
}
```

## Factory Pattern

### Entity Manager Factory

```php
class EntityManagerFactory
{
    public function create(array $config): EntityManagerInterface
    {
        $pdm = new PhpDatabaseManager($config);
        $registry = new MetadataRegistry();
        
        // Configure metadata cache
        if ($config['cache_metadata'] ?? false) {
            $registry->enableCache();
        }
        
        return new EntityManager($pdm, $registry);
    }

    public function createForTesting(): EntityManagerInterface
    {
        return $this->create([
            'host' => 'localhost',
            'database' => ':memory:',
            'driver' => 'sqlite'
        ]);
    }
}
```

## Testing Configuration

### Test Container Setup

```php
class TestDatabaseServiceProvider extends DatabaseServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Override with in-memory SQLite for tests
        $container->set(PhpDatabaseManager::class, function() {
            return new PhpDatabaseManager([
                'driver' => 'sqlite',
                'database' => ':memory:'
            ]);
        });

        parent::register($container);
    }
}
```

### Test Case Base Class

```php
abstract class DatabaseTestCase extends PHPUnit\Framework\TestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $container = $this->createTestContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        
        // Create schema for tests
        $this->createTestSchema();
    }

    private function createTestContainer(): ContainerInterface
    {
        $container = new Container();
        (new TestDatabaseServiceProvider())->register($container);
        return $container;
    }
}
```

## Best Practices

### 1. Use Interfaces

Always depend on interfaces rather than concrete classes:

```php
// Good
public function __construct(EntityManagerInterface $entityManager) {}

// Avoid
public function __construct(EntityManager $entityManager) {}
```

### 2. Single Responsibility

Keep service classes focused on single responsibilities:

```php
// Good - focused on user operations
class UserService
{
    public function createUser(string $name, string $email): User {}
    public function updateUser(User $user, array $data): void {}
    public function deleteUser(User $user): void {}
}

// Avoid - mixed responsibilities
class UserAndPostService
{
    public function createUser(string $name): User {}
    public function createPost(string $title): Post {}
}
```

### 3. Configuration Validation

Validate configuration early:

```php
class DatabaseConfig
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password
    ) {
        if (empty($this->host)) {
            throw new InvalidArgumentException('Database host cannot be empty');
        }
        
        if ($this->port <= 0 || $this->port > 65535) {
            throw new InvalidArgumentException('Invalid database port');
        }
    }
}
```

### 4. Lazy Loading

Use lazy loading for expensive dependencies:

```php
$container->set(EntityManagerInterface::class, function($container) {
    return fn() => new EntityManager(
        $container->get(PhpDatabaseManager::class),
        $container->get(MetadataRegistry::class)
    );
});
```

## Next Steps

- [Core Classes](core-classes.md) - Learn about the main classes
- [Interfaces](interfaces.md) - Understand available contracts
- [Entity Manager](../data-access/entity-manager.md) - Deep dive into entity management
