# Injection de Dépendances

## Table des Matières
- [Vue d'ensemble](#vue-densemble)
- [Configuration du Container](#configuration-du-container)
- [Injection dans les Services](#injection-dans-les-services)
- [Services Disponibles](#services-disponibles)
- [Création de Services Personnalisés](#création-de-services-personnalisés)
- [Bonnes Pratiques](#bonnes-pratiques)

## Vue d'ensemble

MulerTech Database utilise un système d'injection de dépendances pour gérer les services et leurs dépendances. Ce système permet une meilleure testabilité et une architecture plus modulaire.

### Principe de Base

```php
<?php
use MulerTech\Database\Core\Container\Container;
use MulerTech\Database\ORM\EmEngine;

// Le container gère automatiquement les dépendances
$container = new Container();
$emEngine = $container->get(EmEngine::class);
```

## Configuration du Container

### Configuration Automatique

Le container se configure automatiquement avec les services de base :

```php
<?php
use MulerTech\Database\Core\Container\Container;
use MulerTech\Database\Database\MySQLDriver;

$container = new Container([
    'database' => [
        'driver' => MySQLDriver::class,
        'host' => 'localhost',
        'database' => 'myapp',
        'username' => 'user',
        'password' => 'password'
    ]
]);
```

### Configuration Manuelle

Vous pouvez également enregistrer des services manuellement :

```php
<?php
use MulerTech\Database\Core\Container\Container;

$container = new Container();

// Service simple
$container->bind('config', ['debug' => true]);

// Service avec factory
$container->bind(Logger::class, function($container) {
    return new Logger($container->get('config')['debug']);
});

// Singleton
$container->singleton(CacheManager::class, function($container) {
    return new CacheManager($container->get('config'));
});
```

## Injection dans les Services

### Injection par Constructeur

Le container résout automatiquement les dépendances par constructeur :

```php
<?php
use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Database\Interface\DatabaseDriverInterface;

class UserService
{
    public function __construct(
        private EmEngine $emEngine,
        private DatabaseDriverInterface $driver,
        private LoggerInterface $logger
    ) {}

    public function createUser(array $userData): User
    {
        $this->logger->info('Creating user', $userData);
        
        $user = new User();
        $user->setName($userData['name']);
        $user->setEmail($userData['email']);
        
        $this->emEngine->persist($user);
        $this->emEngine->flush();
        
        return $user;
    }
}

// Résolution automatique
$userService = $container->get(UserService::class);
```

### Injection par Interface

Le container peut résoudre les interfaces vers leurs implémentations :

```php
<?php
use MulerTech\Database\Core\Container\Container;
use MulerTech\Database\Database\Interface\DatabaseDriverInterface;
use MulerTech\Database\Database\MySQLDriver;

$container = new Container();

// Liaison interface -> implémentation
$container->bind(DatabaseDriverInterface::class, MySQLDriver::class);

// Le service recevra automatiquement MySQLDriver
class RepositoryService
{
    public function __construct(
        private DatabaseDriverInterface $driver
    ) {}
}
```

## Services Disponibles

### Services Core

| Service | Interface | Description |
|---------|-----------|-------------|
| `EmEngine` | - | Moteur ORM principal |
| `DatabaseDriverInterface` | `MySQLDriver` | Driver de base de données |
| `MetadataRegistry` | - | Registre des métadonnées d'entités |
| `ChangeSetManager` | - | Gestionnaire des changements |
| `EventDispatcher` | - | Gestionnaire d'événements |

### Services de Cache

```php
<?php
use MulerTech\Database\Core\Cache\CacheInterface;
use MulerTech\Database\Core\Cache\ArrayCache;

// Cache en mémoire (par défaut)
$container->bind(CacheInterface::class, ArrayCache::class);

// Cache Redis personnalisé
$container->bind(CacheInterface::class, function($container) {
    return new RedisCache($container->get('redis.connection'));
});
```

### Services de Logging

```php
<?php
use Psr\Log\LoggerInterface;
use Monolog\Logger;

$container->bind(LoggerInterface::class, function($container) {
    $logger = new Logger('mulertech-db');
    $logger->pushHandler(new StreamHandler('logs/database.log'));
    return $logger;
});
```

## Création de Services Personnalisés

### Service Simple

```php
<?php
class EmailService
{
    public function __construct(
        private array $config,
        private LoggerInterface $logger
    ) {}

    public function sendWelcomeEmail(User $user): void
    {
        $this->logger->info('Sending welcome email', ['user_id' => $user->getId()]);
        // Logique d'envoi d'email
    }
}

// Enregistrement
$container->bind(EmailService::class);
$container->bind('email.config', [
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => 587
]);
```

### Service avec Configuration

```php
<?php
class NotificationService
{
    public function __construct(
        private EmEngine $emEngine,
        private EmailService $emailService,
        private array $config
    ) {}

    public function notifyUserRegistration(User $user): void
    {
        // Sauvegarder la notification
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType('registration');
        
        $this->emEngine->persist($notification);
        $this->emEngine->flush();

        // Envoyer l'email
        if ($this->config['send_emails']) {
            $this->emailService->sendWelcomeEmail($user);
        }
    }
}

// Configuration
$container->bind('notification.config', [
    'send_emails' => true,
    'queue_notifications' => false
]);

$container->bind(NotificationService::class, function($container) {
    return new NotificationService(
        $container->get(EmEngine::class),
        $container->get(EmailService::class),
        $container->get('notification.config')
    );
});
```

### Service Provider Pattern

```php
<?php
use MulerTech\Database\Core\Container\ServiceProviderInterface;

class AppServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        // Services core de l'application
        $container->bind(UserService::class);
        $container->bind(ProductService::class);
        $container->bind(OrderService::class);

        // Configuration partagée
        $container->bind('app.config', [
            'timezone' => 'Europe/Paris',
            'locale' => 'fr_FR'
        ]);
    }

    public function boot(Container $container): void
    {
        // Initialisation après enregistrement
        $userService = $container->get(UserService::class);
        $userService->initialize();
    }
}

// Enregistrement du provider
$container->addProvider(new AppServiceProvider());
```

## Bonnes Pratiques

### 1. Utilisez les Interfaces

```php
<?php
// ✅ Bon - Dépend de l'interface
class UserController
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}
}

// ❌ Éviter - Dépend de l'implémentation
class UserController
{
    public function __construct(
        private UserRepository $userRepository
    ) {}
}
```

### 2. Évitez les Dépendances Circulaires

```php
<?php
// ❌ Éviter - Dépendance circulaire
class ServiceA
{
    public function __construct(private ServiceB $serviceB) {}
}

class ServiceB
{
    public function __construct(private ServiceA $serviceA) {}
}

// ✅ Bon - Utiliser un service commun ou des événements
class ServiceA
{
    public function __construct(private EventDispatcher $eventDispatcher) {}
    
    public function doSomething(): void
    {
        $this->eventDispatcher->dispatch(new SomethingHappened());
    }
}

class ServiceB
{
    public function __construct(private EventDispatcher $eventDispatcher) {}
    
    public function __invoke(): void
    {
        $this->eventDispatcher->addListener(SomethingHappened::class, [$this, 'handle']);
    }
}
```

### 3. Configurez les Services par Environnement

```php
<?php
// config/services_dev.php
return [
    LoggerInterface::class => function($container) {
        $logger = new Logger('dev');
        $logger->pushHandler(new StreamHandler('php://stdout'));
        return $logger;
    },
    
    CacheInterface::class => ArrayCache::class, // Cache en mémoire pour les tests
];

// config/services_prod.php
return [
    LoggerInterface::class => function($container) {
        $logger = new Logger('prod');
        $logger->pushHandler(new RotatingFileHandler('logs/app.log'));
        return $logger;
    },
    
    CacheInterface::class => function($container) {
        return new RedisCache($container->get('redis'));
    },
];
```

### 4. Utilisez des Factory pour la Logique Complexe

```php
<?php
class DatabaseConnectionFactory
{
    public function create(array $config): DatabaseDriverInterface
    {
        return match($config['driver']) {
            'mysql' => new MySQLDriver($config),
            'postgresql' => new PostgreSQLDriver($config),
            'sqlite' => new SQLiteDriver($config),
            default => throw new InvalidArgumentException("Unsupported driver: {$config['driver']}")
        };
    }
}

$container->bind(DatabaseDriverInterface::class, function($container) {
    $factory = new DatabaseConnectionFactory();
    return $factory->create($container->get('database.config'));
});
```

### 5. Testez avec des Mocks

```php
<?php
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    public function testCreateUser(): void
    {
        $container = new Container();
        
        // Mock des dépendances
        $mockEmEngine = $this->createMock(EmEngine::class);
        $mockLogger = $this->createMock(LoggerInterface::class);
        
        $container->bind(EmEngine::class, $mockEmEngine);
        $container->bind(LoggerInterface::class, $mockLogger);
        
        $userService = $container->get(UserService::class);
        
        // Test du service
        $user = $userService->createUser(['name' => 'John', 'email' => 'john@example.com']);
        
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John', $user->getName());
    }
}
```

---

**Navigation :**
- [← Architecture](architecture.md)
- [→ Configuration](configuration.md)
- [↑ Concepts Fondamentaux](../README.md)
