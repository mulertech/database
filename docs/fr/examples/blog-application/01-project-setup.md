# Configuration du Projet Blog

Guide de configuration complète pour l'application blog utilisant MulerTech Database ORM.

## Table des Matières
- [Structure du projet](#structure-du-projet)
- [Installation des dépendances](#installation-des-dépendances)
- [Configuration de l'environnement](#configuration-de-lenvironnement)
- [Configuration de la base de données](#configuration-de-la-base-de-données)
- [Configuration de l'ORM](#configuration-de-lorm)
- [Autoloading et DI](#autoloading-et-di)

## Structure du projet

```
blog-application/
├── bin/
│   └── console                     # CLI pour commandes
├── config/
│   ├── database.php               # Configuration BDD
│   ├── cache.php                  # Configuration cache
│   └── services.php               # Conteneur DI
├── public/
│   ├── index.php                  # Point d'entrée web
│   ├── api.php                    # Point d'entrée API
│   └── assets/                    # CSS, JS, images
├── src/
│   ├── Entity/                    # Entités du domaine
│   ├── Repository/                # Repositories personnalisés
│   ├── Service/                   # Services métier
│   ├── Controller/                # Contrôleurs web
│   ├── API/                       # Contrôleurs API
│   ├── ValueObject/               # Objets valeur
│   ├── Exception/                 # Exceptions métier
│   └── Enum/                      # Énumérations
├── migrations/                    # Migrations BDD
├── fixtures/                      # Données de test
├── tests/                         # Tests unitaires/intégration
├── var/
│   ├── cache/                     # Cache applicatif
│   └── logs/                      # Logs
├── composer.json                  # Dépendances
└── .env                          # Variables d'environnement
```

## Installation des dépendances

### composer.json

```json
{
    "name": "mulertech/blog-example",
    "description": "Blog application example using MulerTech Database ORM",
    "type": "project",
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "mulertech/database": "^1.0",
        "symfony/console": "^7.0",
        "symfony/dependency-injection": "^7.0",
        "symfony/config": "^7.0",
        "symfony/yaml": "^7.0",
        "psr/log": "^3.0",
        "monolog/monolog": "^3.0",
        "ramsey/uuid": "^4.7",
        "vlucas/phpdotenv": "^5.5"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "phpstan/phpstan": "^1.10",
        "friendsofphp/php-cs-fixer": "^3.0",
        "fakerphp/faker": "^1.23"
    },
    "autoload": {
        "psr-4": {
            "Blog\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Blog\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "phpunit",
        "test-coverage": "phpunit --coverage-html coverage",
        "stan": "phpstan analyse src --level 8",
        "cs-fix": "php-cs-fixer fix src",
        "post-install-cmd": [
            "@php bin/console cache:clear",
            "@php bin/console mt:migration:status"
        ]
    },
    "config": {
        "sort-packages": true,
        "optimize-autoloader": true,
        "preferred-install": "dist"
    }
}
```

### Installation

```bash
# Installation du projet
composer install

# Installation des assets (optionnel)
npm install

# Permissions (Unix/Linux)
chmod +x bin/console
chmod -R 755 var/
```

## Configuration de l'environnement

### .env

```env
# Environnement
APP_ENV=development
APP_DEBUG=true
APP_SECRET=your-secret-key-here

# Base de données
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=blog_example
DB_USER=root
DB_PASSWORD=
DB_CHARSET=utf8mb4

# Cache
CACHE_DRIVER=file
CACHE_TTL=3600

# Logs
LOG_LEVEL=debug
LOG_PATH=var/logs/app.log

# Sécurité
BCRYPT_COST=12
JWT_SECRET=your-jwt-secret

# Features
ENABLE_DEBUG_TOOLBAR=true
ENABLE_PROFILER=true
```

### Chargement de l'environnement

```php
<?php
// config/environment.php

declare(strict_types=1);

use Dotenv\Dotenv;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class Environment
{
    private static bool $loaded = false;

    public static function load(string $path = __DIR__ . '/..'): void
    {
        if (self::$loaded) {
            return;
        }

        if (file_exists($path . '/.env')) {
            $dotenv = Dotenv::createImmutable($path);
            $dotenv->load();
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public static function isProduction(): bool
    {
        return self::get('APP_ENV') === 'production';
    }

    public static function isDevelopment(): bool
    {
        return self::get('APP_ENV') === 'development';
    }

    public static function isDebug(): bool
    {
        return (bool) self::get('APP_DEBUG', false);
    }
}
```

## Configuration de la base de données

### config/database.php

```php
<?php

declare(strict_types=1);

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
return [
    'default' => 'mysql',
    
    'connections' => [
        'mysql' => [
            'driver' => Environment::get('DB_DRIVER', 'mysql'),
            'host' => Environment::get('DB_HOST', 'localhost'),
            'port' => (int) Environment::get('DB_PORT', 3306),
            'database' => Environment::get('DB_NAME', 'blog_example'),
            'username' => Environment::get('DB_USER', 'root'),
            'password' => Environment::get('DB_PASSWORD', ''),
            'charset' => Environment::get('DB_CHARSET', 'utf8mb4'),
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => true,
            'engine' => 'InnoDB',
            
            // Pool de connexions
            'pool' => [
                'min_connections' => 2,
                'max_connections' => 10,
                'idle_timeout' => 300,
            ],
            
            // Options PDO
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ],
        ],
        
        'test' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'blog_test',
            'username' => 'test',
            'password' => 'test',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
    
    'migrations' => [
        'directory' => __DIR__ . '/../migrations',
        'namespace' => 'Blog\\Migration',
        'table' => 'migrations',
    ],
];
```

## Configuration de l'ORM

### config/orm.php

```php
<?php

declare(strict_types=1);

use MulerTech\Database\Configuration\Configuration;
use MulerTech\Database\EntityManager;
use MulerTech\Database\Cache\FileCache;
use MulerTech\Database\Event\EventDispatcher;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ORMConfiguration
{
    public static function create(): EntityManager
    {
        $dbConfig = require __DIR__ . '/database.php';
        $connectionConfig = $dbConfig['connections'][$dbConfig['default']];
        
        $config = new Configuration($connectionConfig);
        
        // Configuration du cache
        if (Environment::get('CACHE_DRIVER') === 'file') {
            $cache = new FileCache(
                __DIR__ . '/../var/cache/orm',
                (int) Environment::get('CACHE_TTL', 3600)
            );
            $config->setMetadataCache($cache);
            $config->setQueryCache($cache);
        }
        
        // Configuration des entités
        $config->addEntityNamespace('Blog\\Entity', __DIR__ . '/../src/Entity');
        
        // Configuration de l'événementiel
        $eventDispatcher = new EventDispatcher();
        $config->setEventDispatcher($eventDispatcher);
        
        // Configuration du développement
        if (Environment::isDevelopment()) {
            $config->enableQueryLogging();
            $config->setAutoGenerateProxies(true);
        } else {
            $config->setAutoGenerateProxies(false);
        }
        
        return new EntityManager($config);
    }
}
```

### Enregistrement des types personnalisés

```php
<?php

declare(strict_types=1);

namespace Blog\Bootstrap;

use MulerTech\Database\Types\Type;
use Blog\ValueObject\Types\EmailType;
use Blog\ValueObject\Types\SlugType;
use Blog\ValueObject\Types\PostStatusType;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class TypeRegistration
{
    public static function registerTypes(): void
    {
        Type::addType('email', EmailType::class);
        Type::addType('slug', SlugType::class);
        Type::addType('post_status', PostStatusType::class);
    }
}
```

## Autoloading et DI

### Bootstrap principal

```php
<?php
// public/index.php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Blog\Application;

// Chargement de l'environnement
Environment::load(__DIR__ . '/..');

// Enregistrement des types personnalisés
Blog\Bootstrap\TypeRegistration::registerTypes();

// Démarrage de l'application
$app = new Application();
$app->run();
```

### Conteneur de services

```php
<?php
// config/services.php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use MulerTech\Database\EntityManager;
use Blog\Service\UserService;
use Blog\Service\PostService;
use Blog\Service\CommentService;
use Blog\Repository\UserRepository;
use Blog\Repository\PostRepository;
use Blog\Repository\CommentRepository;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
return function (ContainerBuilder $container): void {
    // EntityManager
    $container->register('entity_manager', EntityManager::class)
        ->setFactory([ORMConfiguration::class, 'create']);

    // Repositories
    $container->register('user_repository', UserRepository::class)
        ->setArguments([new Reference('entity_manager')]);

    $container->register('post_repository', PostRepository::class)
        ->setArguments([new Reference('entity_manager')]);

    $container->register('comment_repository', CommentRepository::class)
        ->setArguments([new Reference('entity_manager')]);

    // Services
    $container->register('user_service', UserService::class)
        ->setArguments([
            new Reference('user_repository'),
            new Reference('entity_manager')
        ]);

    $container->register('post_service', PostService::class)
        ->setArguments([
            new Reference('post_repository'),
            new Reference('entity_manager')
        ]);

    $container->register('comment_service', CommentService::class)
        ->setArguments([
            new Reference('comment_repository'),
            new Reference('entity_manager')
        ]);
};
```

### Application principale

```php
<?php

declare(strict_types=1);

namespace Blog;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Config\FileLocator;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class Application
{
    private ContainerBuilder $container;

    public function __construct()
    {
        $this->container = new ContainerBuilder();
        $this->loadServices();
    }

    public function run(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $router = new Router($this->container);
        $response = $router->handle($method, $uri);
        
        $response->send();
    }

    private function loadServices(): void
    {
        $loader = new PhpFileLoader(
            $this->container,
            new FileLocator(__DIR__ . '/../config')
        );
        
        $loader->load('services.php');
        $this->container->compile();
    }

    public function getContainer(): ContainerBuilder
    {
        return $this->container;
    }
}
```

## Console CLI

### bin/console

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use Blog\Command\MigrationCommand;
use Blog\Command\FixturesCommand;
use Blog\Command\CacheCommand;

Environment::load(__DIR__ . '/..');
Blog\Bootstrap\TypeRegistration::registerTypes();

$app = new Application('Blog Console', '1.0.0');

// Commandes personnalisées
$app->add(new MigrationCommand());
$app->add(new FixturesCommand());
$app->add(new CacheCommand());

$app->run();
```

## Configuration de cache

### config/cache.php

```php
<?php

declare(strict_types=1);

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
return [
    'default' => Environment::get('CACHE_DRIVER', 'file'),
    
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../var/cache',
            'ttl' => (int) Environment::get('CACHE_TTL', 3600),
        ],
        
        'redis' => [
            'driver' => 'redis',
            'host' => Environment::get('REDIS_HOST', '127.0.0.1'),
            'port' => (int) Environment::get('REDIS_PORT', 6379),
            'database' => (int) Environment::get('REDIS_DB', 0),
        ],
        
        'memory' => [
            'driver' => 'array',
        ],
    ],
];
```

---

**Prochaine étape :** [Définition des entités](02-entities.md) - Création des modèles de données du blog.
