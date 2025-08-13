# Configuration du Projet E-commerce

Cette section détaille l'installation et la configuration complète d'une application e-commerce utilisant MulerTech Database ORM, depuis l'initialisation du projet jusqu'à la configuration des services.

## Table des matières

- [Prérequis système](#prérequis-système)
- [Installation du projet](#installation-du-projet)
- [Configuration de l'environnement](#configuration-de-lenvironnement)
- [Structure des dossiers](#structure-des-dossiers)
- [Configuration de la base de données](#configuration-de-la-base-de-données)
- [Configuration des services](#configuration-des-services)
- [Variables d'environnement](#variables-denvironnement)
- [Initialisation des données](#initialisation-des-données)
- [Configuration Docker](#configuration-docker)
- [Scripts de développement](#scripts-de-développement)

## Prérequis système

### Logiciels requis

```bash
# Versions minimales requises
PHP >= 8.1
Composer >= 2.0
MySQL >= 8.0 ou PostgreSQL >= 13
Node.js >= 16 (pour les assets front-end)
Git
```

### Extensions PHP

```bash
# Extensions PHP obligatoires
ext-pdo
ext-pdo_mysql
ext-json
ext-mbstring
ext-openssl
ext-tokenizer
ext-xml
ext-ctype
ext-iconv
ext-fileinfo
ext-curl
ext-gd
ext-zip
```

## Installation du projet

### 1. Création du projet

```bash
# Création du répertoire projet
mkdir ecommerce-mulertech
cd ecommerce-mulertech

# Initialisation Composer
composer init

# Installation de MulerTech Database
composer require mulertech/database

# Installation des dépendances de développement
composer require --dev phpunit/phpunit
composer require --dev symfony/var-dumper
composer require --dev fakerphp/faker
```

### 2. Configuration Composer

```json
{
    "name": "company/ecommerce-mulertech",
    "description": "Application E-commerce avec MulerTech Database ORM",
    "type": "project",
    "require": {
        "php": "^8.1",
        "mulertech/database": "^1.0",
        "vlucas/phpdotenv": "^5.5",
        "monolog/monolog": "^3.0",
        "symfony/http-foundation": "^6.0",
        "symfony/routing": "^6.0",
        "twig/twig": "^3.0",
        "stripe/stripe-php": "^10.0",
        "paypal/rest-api-sdk-php": "^1.14"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "symfony/var-dumper": "^6.0",
        "fakerphp/faker": "^1.20",
        "phpstan/phpstan": "^1.10"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "App\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "phpunit",
        "stan": "phpstan analyse src",
        "migrate": "php bin/console mt:migration:migrate",
        "fixtures": "php bin/console fixtures:load"
    }
}
```

## Configuration de l'environnement

### Fichier .env

```bash
# Base de données
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=ecommerce_mulertech
DB_USER=root
DB_PASSWORD=secret
DB_CHARSET=utf8mb4

# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Europe/Paris

# Sécurité
APP_SECRET=your-secret-key-here
JWT_SECRET=your-jwt-secret-here

# Cache
CACHE_DRIVER=file
CACHE_PREFIX=ecommerce_

# Session
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Paiements
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_CLIENT_SECRET=your_paypal_client_secret
PAYPAL_MODE=sandbox

# Email
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null

# Stockage
STORAGE_DRIVER=local
STORAGE_ROOT=storage/app

# Logs
LOG_CHANNEL=single
LOG_LEVEL=debug
```

### Fichier .env.example

```bash
# Copie du fichier .env pour le versioning
cp .env .env.example
# Supprimer les valeurs sensibles du .env.example
```

## Structure des dossiers

```
ecommerce-mulertech/
├── bin/
│   └── console                    # CLI application
├── config/
│   ├── database.php              # Configuration DB
│   ├── services.php              # Configuration services
│   ├── routing.php               # Routes
│   └── cache.php                 # Configuration cache
├── public/
│   ├── index.php                 # Point d'entrée web
│   ├── assets/                   # Assets statiques
│   └── uploads/                  # Fichiers uploadés
├── src/
│   ├── Entity/                   # Entités ORM
│   ├── Repository/               # Repositories
│   ├── Service/                  # Services métier
│   ├── Controller/               # Contrôleurs web
│   ├── Command/                  # Commandes CLI
│   ├── Event/                    # Event listeners
│   ├── Enum/                     # Énumérations
│   └── Exception/                # Exceptions métier
├── storage/
│   ├── app/                      # Stockage application
│   ├── cache/                    # Cache
│   ├── logs/                     # Logs
│   └── migrations/               # Migrations DB
├── templates/                    # Templates Twig
├── tests/                        # Tests automatisés
├── vendor/                       # Dépendances
├── .env                          # Variables d'environnement
├── .env.example                  # Template env
├── .gitignore                    # Git ignore
├── composer.json                 # Configuration Composer
└── README.md                     # Documentation
```

## Configuration de la base de données

### config/database.php

```php
<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_NAME', 'ecommerce'),
            'username' => env('DB_USER', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'options' => [
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ],
        ],
        
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 5432),
            'database' => env('DB_NAME', 'ecommerce'),
            'username' => env('DB_USER', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'schema' => env('DB_SCHEMA', 'public'),
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ],
    ],
    
    'migrations' => [
        'table' => 'migrations',
        'path' => 'storage/migrations',
    ],
];
```

### Initialisation de la base de données

```php
<?php
// bin/console

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Database\MySQLDriver;

// Chargement des variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configuration de la base de données
$config = require __DIR__ . '/../config/database.php';
$dbConfig = $config['connections'][$config['default']];

// Initialisation du driver
$driver = new MySQLDriver(
    $dbConfig['host'],
    $dbConfig['database'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['port']
);

// Initialisation de l'EntityManager
$em = new EmEngine($driver);

// Commandes CLI disponibles
$commands = [
    'mt:migration:create' => 'Créer une nouvelle migration',
    'mt:migration:migrate' => 'Exécuter les migrations',
    'mt:migration:rollback' => 'Annuler la dernière migration',
    'fixtures:load' => 'Charger les données de test',
    'cache:clear' => 'Vider le cache',
    'schema:validate' => 'Valider le schéma de base de données',
];

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'mt:migration:migrate':
        echo "Exécution des migrations...\n";
        // Logique de migration
        break;
        
    case 'fixtures:load':
        echo "Chargement des fixtures...\n";
        require __DIR__ . '/../fixtures/load.php';
        break;
        
    case 'help':
    default:
        echo "Commandes disponibles:\n";
        foreach ($commands as $cmd => $desc) {
            echo "  {$cmd} - {$desc}\n";
        }
        break;
}
```

## Configuration des services

### config/services.php

```php
<?php

declare(strict_types=1);

return [
    'cache' => [
        'driver' => env('CACHE_DRIVER', 'file'),
        'prefix' => env('CACHE_PREFIX', 'ecommerce_'),
        'ttl' => env('CACHE_TTL', 3600),
        'path' => 'storage/cache',
    ],
    
    'session' => [
        'driver' => env('SESSION_DRIVER', 'file'),
        'lifetime' => env('SESSION_LIFETIME', 120),
        'path' => 'storage/sessions',
        'cookie' => [
            'name' => 'ecommerce_session',
            'secure' => env('APP_ENV') === 'production',
            'httponly' => true,
            'samesite' => 'lax',
        ],
    ],
    
    'payment' => [
        'gateways' => [
            'stripe' => [
                'enabled' => env('STRIPE_ENABLED', true),
                'public_key' => env('STRIPE_PUBLIC_KEY'),
                'secret_key' => env('STRIPE_SECRET_KEY'),
                'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            ],
            'paypal' => [
                'enabled' => env('PAYPAL_ENABLED', true),
                'client_id' => env('PAYPAL_CLIENT_ID'),
                'client_secret' => env('PAYPAL_CLIENT_SECRET'),
                'mode' => env('PAYPAL_MODE', 'sandbox'),
            ],
        ],
    ],
    
    'storage' => [
        'driver' => env('STORAGE_DRIVER', 'local'),
        'root' => env('STORAGE_ROOT', 'storage/app'),
        'url' => env('STORAGE_URL', '/storage'),
        'visibility' => 'public',
    ],
    
    'mail' => [
        'driver' => env('MAIL_MAILER', 'smtp'),
        'host' => env('MAIL_HOST'),
        'port' => env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'from' => [
            'address' => env('MAIL_FROM_ADDRESS', 'noreply@ecommerce.local'),
            'name' => env('MAIL_FROM_NAME', 'E-commerce MulerTech'),
        ],
    ],
    
    'inventory' => [
        'track_quantities' => true,
        'allow_backorders' => false,
        'low_stock_threshold' => 10,
        'out_of_stock_threshold' => 0,
    ],
    
    'shipping' => [
        'providers' => [
            'ups' => [
                'enabled' => false,
                'api_key' => env('UPS_API_KEY'),
            ],
            'fedex' => [
                'enabled' => false,
                'api_key' => env('FEDEX_API_KEY'),
            ],
            'dhl' => [
                'enabled' => false,
                'api_key' => env('DHL_API_KEY'),
            ],
        ],
        'default_weight_unit' => 'kg',
        'default_dimension_unit' => 'cm',
    ],
];
```

## Variables d'environnement

### Fonction helper

```php
<?php
// src/helpers.php

declare(strict_types=1);

if (!function_exists('env')) {
    /**
     * Récupère une variable d'environnement avec valeur par défaut
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Conversion des valeurs boolean-like
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!function_exists('config')) {
    /**
     * Récupère une valeur de configuration
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $config = [];
        
        if (empty($config)) {
            $config = [
                'database' => require __DIR__ . '/../config/database.php',
                'services' => require __DIR__ . '/../config/services.php',
            ];
        }
        
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $segment) {
            if (!isset($value[$segment])) {
                return $default;
            }
            $value = $value[$segment];
        }
        
        return $value;
    }
}
```

## Initialisation des données

### fixtures/load.php

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Faker\Factory;
use App\Entity\Category;
use App\Entity\Brand;
use App\Entity\Product;
use App\Entity\Customer;

$faker = Factory::create('fr_FR');

echo "Chargement des données de démonstration...\n";

// Création des catégories
$categories = [
    ['name' => 'Électronique', 'slug' => 'electronique'],
    ['name' => 'Vêtements', 'slug' => 'vetements'],
    ['name' => 'Maison & Jardin', 'slug' => 'maison-jardin'],
    ['name' => 'Sports & Loisirs', 'slug' => 'sports-loisirs'],
    ['name' => 'Livres', 'slug' => 'livres'],
];

foreach ($categories as $categoryData) {
    $category = new Category();
    $category->setName($categoryData['name']);
    $category->setSlug($categoryData['slug']);
    $category->setIsActive(true);
    $em->persist($category);
}

// Création des marques
$brands = [
    ['name' => 'Apple', 'slug' => 'apple'],
    ['name' => 'Samsung', 'slug' => 'samsung'],
    ['name' => 'Nike', 'slug' => 'nike'],
    ['name' => 'Adidas', 'slug' => 'adidas'],
    ['name' => 'IKEA', 'slug' => 'ikea'],
];

foreach ($brands as $brandData) {
    $brand = new Brand();
    $brand->setName($brandData['name']);
    $brand->setSlug($brandData['slug']);
    $brand->setIsActive(true);
    $em->persist($brand);
}

$em->flush();

echo "Données de démonstration chargées avec succès!\n";
```

## Configuration Docker

### docker-compose.yml

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "8000:8000"
    volumes:
      - .:/var/www/html
      - ./storage:/var/www/html/storage
    environment:
      - APP_ENV=development
      - DB_HOST=mysql
    depends_on:
      - mysql
      - redis
    networks:
      - ecommerce

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: ecommerce_mulertech
      MYSQL_USER: ecommerce
      MYSQL_PASSWORD: secret
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/init.sql:/docker-entrypoint-initdb.d/init.sql
    networks:
      - ecommerce

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    networks:
      - ecommerce

  mailhog:
    image: mailhog/mailhog
    ports:
      - "1025:1025"
      - "8025:8025"
    networks:
      - ecommerce

volumes:
  mysql_data:
  redis_data:

networks:
  ecommerce:
    driver: bridge
```

### Dockerfile

```dockerfile
FROM php:8.2-fpm

# Installation des extensions PHP
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configuration du répertoire de travail
WORKDIR /var/www/html

# Copie des fichiers de configuration
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Copie du code source
COPY . .

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
```

## Scripts de développement

### Makefile

```makefile
.PHONY: install start stop test migrate fixtures

install:
	composer install
	cp .env.example .env
	mkdir -p storage/{cache,logs,sessions,app}
	chmod -R 775 storage

start:
	docker-compose up -d
	@echo "Application démarrée sur http://localhost:8000"

stop:
	docker-compose down

test:
	./vendor/bin/phpunit

migrate:
	php bin/console mt:migration:migrate

fixtures:
	php bin/console fixtures:load

cache-clear:
	rm -rf storage/cache/*

logs:
	tail -f storage/logs/app.log

database-reset:
	docker-compose exec mysql mysql -u root -psecret -e "DROP DATABASE IF EXISTS ecommerce_mulertech; CREATE DATABASE ecommerce_mulertech;"
	make migrate
	make fixtures
```

## Vérification de l'installation

### Script de vérification

```php
<?php
// bin/verify-setup

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

echo "Vérification de la configuration...\n\n";

// Vérification PHP
echo "✓ Version PHP: " . PHP_VERSION . "\n";

// Vérification des extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ Extension {$ext}: installée\n";
    } else {
        echo "✗ Extension {$ext}: MANQUANTE\n";
    }
}

// Vérification des dossiers
$requiredDirs = ['storage', 'storage/cache', 'storage/logs'];
foreach ($requiredDirs as $dir) {
    if (is_dir($dir) && is_writable($dir)) {
        echo "✓ Dossier {$dir}: accessible en écriture\n";
    } else {
        echo "✗ Dossier {$dir}: PROBLÈME D'ACCÈS\n";
    }
}

// Vérification de la base de données
try {
    $config = require __DIR__ . '/../config/database.php';
    $dbConfig = $config['connections'][$config['default']];
    
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']}",
        $dbConfig['username'],
        $dbConfig['password']
    );
    
    echo "✓ Connexion base de données: OK\n";
} catch (Exception $e) {
    echo "✗ Connexion base de données: ÉCHEC - " . $e->getMessage() . "\n";
}

echo "\nConfiguration terminée!\n";
```

## Prochaines étapes

1. **[Entités](02-entities.md)** - Définition des modèles de données
2. **[Gestion du catalogue](03-catalog-management.md)** - Produits et catégories
3. **[Système de panier](04-cart-system.md)** - Panier d'achat
4. **[Traitement des commandes](05-order-processing.md)** - Workflow complet

---

La configuration du projet est maintenant complète. Vous disposez d'une base solide pour développer votre application e-commerce avec MulerTech Database ORM.
