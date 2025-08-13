# Injection de Dépendances

🌍 **Languages:** [🇫🇷 Français](dependency-injection.md) | [🇬🇧 English](../../en/core-concepts/dependency-injection.md)

---

## Table des Matières
- [Vue d'ensemble](#vue-densemble)
- [Configuration avec un Container PSR-11](#configuration-avec-un-container-psr-11)
- [Services Principaux](#services-principaux)
- [Exemples d'Intégration](#exemples-dintégration)
- [Bonnes Pratiques](#bonnes-pratiques)

---

## Vue d'ensemble

MulerTech Database ne fournit pas son propre container d'injection de dépendances, mais s'intègre parfaitement avec tout container compatible PSR-11. Cette approche offre une flexibilité maximale pour l'intégration dans vos applications existantes.

### Principe de Base

```php
<?php
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\Mapping\MetadataRegistry;

// Configuration manuelle simple
$driver = new MySQLDriver([
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'user',
    'password' => 'password'
]);

$metadataRegistry = new MetadataRegistry('/path/to/entities');
$entityManager = new EntityManager($driver, $metadataRegistry);
```

---

## Configuration avec un Container PSR-11

### Exemple avec PHP-DI

```php
<?php
use DI\ContainerBuilder;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\Database\Interface\PhpDatabaseInterface;
use MulerTech\Database\Mapping\MetadataRegistry;

$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
    // Configuration de base
    'database.config' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'database' => $_ENV['DB_NAME'] ?? 'myapp',
        'username' => $_ENV['DB_USER'] ?? 'user',
        'password' => $_ENV['DB_PASS'] ?? 'password',
    ],
    
    'entities.path' => '/path/to/your/entities',
    
    // Driver de base de données
    PhpDatabaseInterface::class => function($container) {
        return new MySQLDriver($container->get('database.config'));
    },
    
    // Registre des métadonnées
    MetadataRegistry::class => function($container) {
        return new MetadataRegistry($container->get('entities.path'));
    },
    
    // Entity Manager
    EntityManagerInterface::class => function($container) {
        return new EntityManager(
            $container->get(PhpDatabaseInterface::class),
            $container->get(MetadataRegistry::class)
        );
    },
]);

$container = $containerBuilder->build();
```

### Exemple avec Symfony DI

```php
<?php
// config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # Configuration
    app.database.config:
        class: array
        arguments:
            - host: '%env(DB_HOST)%'
              database: '%env(DB_NAME)%'
              username: '%env(DB_USER)%'
              password: '%env(DB_PASS)%'

    # Driver
    MulerTech\Database\Database\Interface\PhpDatabaseInterface:
        class: MulerTech\Database\Database\MySQLDriver
        arguments:
            - '@app.database.config'

    # Métadonnées
    MulerTech\Database\Mapping\MetadataRegistry:
        arguments:
            - '%kernel.project_dir%/src/Entity'

    # Entity Manager
    MulerTech\Database\ORM\EntityManagerInterface:
        class: MulerTech\Database\ORM\EntityManager
        arguments:
            - '@MulerTech\Database\Database\Interface\PhpDatabaseInterface'
            - '@MulerTech\Database\Mapping\MetadataRegistry'
```

---

## Services Principaux

### Services Core Disponibles

| Service | Interface | Description |
|---------|-----------|-------------|
| `EntityManager` | `EntityManagerInterface` | Point d'entrée principal de l'ORM |
| `MySQLDriver` | `PhpDatabaseInterface` | Driver de base de données MySQL |
| `MetadataRegistry` | - | Registre des métadonnées d'entités |
| `EmEngine` | - | Moteur interne de l'ORM |

### Configuration des Services

```php
<?php
use MulerTech\Database\ORM\EntityRepository;

// Service personnalisé utilisant l'EntityManager
class UserService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function findUserByEmail(string $email): ?User
    {
        $repository = $this->entityManager->getRepository(User::class);
        return $repository->findOneBy(['email' => $email]);
    }

    public function createUser(string $name, string $email): User
    {
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        
        // Utilisation directe de l'EmEngine pour la persistance
        $this->entityManager->getEmEngine()->persist($user);
        $this->entityManager->getEmEngine()->flush();
        
        return $user;
    }
}
```

---

## Exemples d'Intégration

### Avec Laravel

```php
<?php
// app/Providers/DatabaseServiceProvider.php
use Illuminate\Support\ServiceProvider;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\Mapping\MetadataRegistry;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MySQLDriver::class, function ($app) {
            return new MySQLDriver([
                'host' => config('database.connections.mysql.host'),
                'database' => config('database.connections.mysql.database'),
                'username' => config('database.connections.mysql.username'),
                'password' => config('database.connections.mysql.password'),
            ]);
        });

        $this->app->singleton(MetadataRegistry::class, function ($app) {
            return new MetadataRegistry(app_path('Models'));
        });

        $this->app->singleton(EntityManager::class, function ($app) {
            return new EntityManager(
                $app->make(MySQLDriver::class),
                $app->make(MetadataRegistry::class)
            );
        });
    }
}
```

### Avec un Container Générique

```php
<?php
use Psr\Container\ContainerInterface;

class DatabaseContainerFactory
{
    public static function create(array $config): ContainerInterface
    {
        $container = new YourFavoriteContainer();
        
        $container->set('database.config', $config);
        
        $container->set(MySQLDriver::class, function($container) {
            return new MySQLDriver($container->get('database.config'));
        });
        
        $container->set(MetadataRegistry::class, function($container) {
            return new MetadataRegistry($container->get('entities.path'));
        });
        
        $container->set(EntityManager::class, function($container) {
            return new EntityManager(
                $container->get(MySQLDriver::class),
                $container->get(MetadataRegistry::class)
            );
        });
        
        return $container;
    }
}

// Utilisation
$container = DatabaseContainerFactory::create([
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'user',
    'password' => 'password'
]);

$entityManager = $container->get(EntityManager::class);
```

---

## Bonnes Pratiques

### 1. Utilisez les Interfaces

```php
<?php
// ✅ Bon - Dépend de l'interface
class UserController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
}

// ❌ Éviter - Dépend de l'implémentation concrète
class UserController
{
    public function __construct(
        private EntityManager $entityManager
    ) {}
}
```

### 2. Configuration par Environnement

```php
<?php
// Configuration de développement
$devConfig = [
    'host' => 'localhost',
    'database' => 'myapp_dev',
    'username' => 'dev_user',
    'password' => 'dev_password'
];

// Configuration de production
$prodConfig = [
    'host' => $_ENV['DB_HOST'],
    'database' => $_ENV['DB_NAME'],
    'username' => $_ENV['DB_USER'],
    'password' => $_ENV['DB_PASS']
];

$config = $_ENV['APP_ENV'] === 'production' ? $prodConfig : $devConfig;
```

### 3. Tests avec Injection

```php
<?php
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private UserService $userService;
    
    protected function setUp(): void
    {
        // Configuration de test avec SQLite en mémoire
        $driver = new MySQLDriver([
            'host' => 'localhost',
            'database' => ':memory:'
        ]);
        
        $metadataRegistry = new MetadataRegistry(__DIR__ . '/Fixtures/Entities');
        $this->entityManager = new EntityManager($driver, $metadataRegistry);
        $this->userService = new UserService($this->entityManager);
    }
    
    public function testCreateUser(): void
    {
        $user = $this->userService->createUser('John Doe', 'john@example.com');
        
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john@example.com', $user->getEmail());
    }
}
```

---

## ➡️ Étapes Suivantes

Pour approfondir votre compréhension :

1. 🏗️ [Architecture](architecture.md) - Architecture générale
2. 🗄️ [Entity Manager](../../fr/orm/entity-manager.md) - Utilisation détaillée
3. 🎯 [Exemples Pratiques](../../fr/quick-start/basic-examples.md) - Cas d'usage concrets

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../fr/../README.md)
- 📖 [Documentation Complète](../../fr/README.md)
- 🚀 [Démarrage Rapide](../../fr/quick-start/installation.md)
