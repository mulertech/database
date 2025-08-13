# Installation

Guide d'installation de MulerTech Database.

## Prérequis

### Environnement Technique

- **PHP 8.4+** avec les extensions :
  - `pdo` (requis)
  - `pdo_mysql` pour MySQL/MariaDB
  - `pdo_pgsql` pour PostgreSQL
  - `pdo_sqlite` pour SQLite

- **Composer** 2.0+
- **Base de données** :
  - MySQL 5.7+ / MariaDB 10.3+
  - PostgreSQL 12+
  - SQLite 3.25+

### Vérification des Prérequis

```bash
# Vérifier la version PHP
php --version

# Vérifier les extensions PDO
php -m | grep pdo

# Vérifier Composer
composer --version
```

## Installation

### Installation via Composer

```bash
composer require mulertech/database "^1.0"
```

Ou ajouter au `composer.json` :

```json
{
    "require": {
        "mulertech/database": "^1.0"
    }
}
```

```bash
composer install
```

## Configuration par Type de Base

### MySQL/MariaDB

```php
<?php
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'my_app',
    'username' => 'user',
    'password' => 'password',
    'charset' => 'utf8mb4',
    'driver' => 'mysql'
];
```

### PostgreSQL

```php
<?php
$config = [
    'host' => 'localhost',
    'port' => 5432,
    'database' => 'my_app',
    'username' => 'user',
    'password' => 'password',
    'driver' => 'pgsql'
];
```

### SQLite

```php
<?php
$config = [
    'database' => '/path/to/database.sqlite',
    'driver' => 'sqlite'
];
```

## Test d'Installation

Créez `test-connection.php` :

```php
<?php
require_once 'vendor/autoload.php';

use MulerTech\Database\Database\Interface\PhpDatabaseManager;

$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'test',
    'username' => 'root',
    'password' => '',
    'driver' => 'mysql'
];

try {
    $pdm = new PhpDatabaseManager($config);
    $result = $pdm->executeQuery("SELECT 1 as test");
    
    if ($result && $result[0]['test'] === 1) {
        echo "Connexion réussie !\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
```

```bash
php test-connection.php
```

## Résolution de Problèmes

### Extension PDO manquante
```bash
# Ubuntu/Debian
sudo apt-get install php8.4-pdo php8.4-mysql

# macOS (Homebrew)
brew install php@8.4
```

### Erreur de connexion MySQL
```
SQLSTATE[HY000] [1045] Access denied for user
```

Vérifiez les permissions utilisateur :
```sql
GRANT ALL PRIVILEGES ON database_name.* TO 'username'@'localhost';
FLUSH PRIVILEGES;
```

## Étapes Suivantes

1. [Premiers Pas](first-steps.md) - Configuration complète et première entité
2. [Exemples de Base](basic-examples.md) - Cas d'usage pratiques
