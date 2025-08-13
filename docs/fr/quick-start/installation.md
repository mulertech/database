# Installation et Configuration

🌍 **Languages:** [🇫🇷 Français](installation.md) | [🇬🇧 English](../../en/quick-start/installation.md)

---


## 📋 Table des Matières

- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration de Base](#configuration-de-base)
- [Configuration Avancée](#configuration-avancée)
- [Vérification de l'Installation](#vérification-de-linstallation)
- [Résolution de Problèmes](#résolution-de-problèmes)

---

## Prérequis

### 🔧 Environnement Technique

- **PHP 8.4+** avec les extensions suivantes :
  - `pdo` (requis)
  - `pdo_mysql` pour MySQL/MariaDB
  - `pdo_pgsql` pour PostgreSQL
  - `pdo_sqlite` pour SQLite
  - `iconv` (requis)
  - `zlib` (requis)

- **Composer** 2.0+
- **Base de données** :
  - MySQL 5.7+ / MariaDB 10.3+
  - PostgreSQL 12+
  - SQLite 3.25+

### ✅ Vérification des Prérequis

```bash
# Vérifier la version PHP
php --version

# Vérifier les extensions PDO
php -m | grep pdo

# Vérifier Composer
composer --version
```

---

## Installation

### 🚀 Installation via Composer

#### Méthode 1 : Commande directe
```bash
composer require mulertech/database "^1.0"
```

#### Méthode 2 : Ajouter au composer.json
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

### 🔄 Mise à jour
```bash
# Mise à jour du package seul
composer update mulertech/database

# Mise à jour complète
composer update
```

---

## Configuration de Base

### 📊 Configuration Minimale

```php
<?php
require_once 'vendor/autoload.php';

use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Mapping\MetadataRegistry;

// Configuration de la base de données
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'my_application',
    'username' => 'db_user',
    'password' => 'db_password',
    'charset' => 'utf8mb4',
    'driver' => 'mysql' // mysql, pgsql, sqlite
];

try {
    // Initialisation des composants
    $pdm = new PhpDatabaseManager($config);
    $metadataRegistry = new MetadataRegistry();
    $entityManager = new EntityManager($pdm, $metadataRegistry);
    
    echo "✅ Connexion réussie !\\n";
    
} catch (Exception $e) {
    echo "❌ Erreur de connexion : " . $e->getMessage() . "\\n";
}
```

### 🗄️ Configuration par Type de Base

#### MySQL/MariaDB
```php
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'my_app',
    'username' => 'user',
    'password' => 'password',
    'charset' => 'utf8mb4',
    'driver' => 'mysql',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]
];
```

#### PostgreSQL
```php
$config = [
    'host' => 'localhost',
    'port' => 5432,
    'database' => 'my_app',
    'username' => 'user',
    'password' => 'password',
    'driver' => 'pgsql',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
];
```

#### SQLite
```php
$config = [
    'database' => '/path/to/database.sqlite',
    'driver' => 'sqlite',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
];
```

---

## Configuration Avancée

### 🌍 Variables d'Environnement

Créez un fichier `.env` :
```env
# Base de données
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=my_application
DB_USERNAME=db_user
DB_PASSWORD=db_password
DB_CHARSET=utf8mb4

# Environnement
APP_ENV=development
APP_DEBUG=true

# Cache
CACHE_DRIVER=memory
CACHE_TTL=3600
```

Configuration PHP avec variables d'environnement :
```php
<?php
// Charger les variables d'environnement (utiliser vlucas/phpdotenv si besoin)
$config = [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => (int)($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_DATABASE'] ?? '',
    'username' => $_ENV['DB_USERNAME'] ?? '',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    'driver' => $_ENV['DB_DRIVER'] ?? 'mysql'
];
```

### 🔧 Configuration avec Events Manager

```php
<?php
use MulerTech\EventManager\EventManager;
use MulerTech\Database\Event\PrePersistEvent;

// Setup avec gestionnaire d'événements
$eventManager = new EventManager();
$entityManager = new EntityManager($pdm, $metadataRegistry, $eventManager);

// Ajouter des listeners
$eventManager->addListener(PrePersistEvent::class, function(PrePersistEvent $event) {
    $entity = $event->getEntity();
    
    // Logique avant sauvegarde
    if (method_exists($entity, 'setCreatedAt')) {
        $entity->setCreatedAt(new DateTime());
    }
});
```

### 🗂️ Configuration Multi-Environnements

```php
<?php
class DatabaseConfig
{
    public static function get(string $environment = 'development'): array
    {
        $configs = [
            'development' => [
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'myapp_dev',
                'username' => 'dev_user',
                'password' => 'dev_pass',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            ],
            
            'testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            ],
            
            'production' => [
                'host' => $_ENV['DB_HOST'],
                'port' => (int)$_ENV['DB_PORT'],
                'database' => $_ENV['DB_DATABASE'],
                'username' => $_ENV['DB_USERNAME'],
                'password' => $_ENV['DB_PASSWORD'],
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_PERSISTENT => true
                ]
            ]
        ];
        
        return $configs[$environment] ?? $configs['development'];
    }
}

// Usage
$config = DatabaseConfig::get($_ENV['APP_ENV'] ?? 'development');
$entityManager = new EntityManager(new PhpDatabaseManager($config), new MetadataRegistry());
```

---

## Vérification de l'Installation

### 🧪 Test de Connexion Simple

Créez un fichier `test-connection.php` :

```php
<?php
require_once 'vendor/autoload.php';

use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Mapping\MetadataRegistry;

// Configuration (adaptez selon votre environnement)
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'test',
    'username' => 'root',
    'password' => '',
    'driver' => 'mysql'
];

try {
    echo "🔄 Test de connexion...\\n";
    
    $pdm = new PhpDatabaseManager($config);
    $metadataRegistry = new MetadataRegistry();
    $entityManager = new EntityManager($pdm, $metadataRegistry);
    
    // Test de requête simple
    $result = $pdm->executeQuery("SELECT 1 as test");
    
    if ($result && $result[0]['test'] === 1) {
        echo "✅ Connexion réussie !\\n";
        echo "📊 Driver: " . $config['driver'] . "\\n";
        echo "🗄️ Base: " . $config['database'] . "\\n";
    } else {
        echo "❌ Test de requête échoué\\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\\n";
    echo "💡 Vérifiez votre configuration de base de données\\n";
}
```

```bash
php test-connection.php
```

### 🔍 Test avec Entity Simple

```php
<?php
use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey};

#[MtEntity(tableName: 'test_users')]
class TestUser
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $name;

    // Getters/Setters
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
}

try {
    // Test de l'ORM
    $user = new TestUser();
    $user->setName('Test User');
    
    $entityManager->persist($user);
    $entityManager->flush();
    
    echo "✅ Entity créée avec ID: " . $user->getId() . "\\n";
    
} catch (Exception $e) {
    echo "❌ Erreur ORM: " . $e->getMessage() . "\\n";
}
```

---

## Résolution de Problèmes

### 🐛 Erreurs Courantes

#### Extension PDO manquante
```
Error: PDO extension not found
```
**Solution :**
```bash
# Ubuntu/Debian
sudo apt-get install php8.4-pdo php8.4-mysql

# CentOS/RHEL
sudo yum install php-pdo php-mysql

# macOS (Homebrew)
brew install php@8.4
```

#### Erreur de connexion MySQL
```
SQLSTATE[HY000] [1045] Access denied for user
```
**Solutions :**
1. Vérifiez username/password
2. Vérifiez les permissions utilisateur :
```sql
GRANT ALL PRIVILEGES ON database_name.* TO 'username'@'localhost';
FLUSH PRIVILEGES;
```

#### Erreur de charset
```
SQLSTATE[HY000] [2019] Can't initialize character set
```
**Solution :**
```php
$config['charset'] = 'utf8mb4';
$config['options'][PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
```

### 🔧 Debug et Logs

#### Activer le mode debug
```php
$config['options'][PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;

// Log des requêtes (développement uniquement)
$pdm->enableQueryLogging();
```

#### Vérifier les logs
```php
// Après exécution de requêtes
$logs = $pdm->getQueryLogs();
foreach ($logs as $log) {
    echo "Query: " . $log['query'] . "\\n";
    echo "Time: " . $log['time'] . "ms\\n";
}
```

### 📊 Vérification de Performance

```php
<?php
// Test de performance basique
$start = microtime(true);

for ($i = 0; $i < 100; $i++) {
    $user = new TestUser();
    $user->setName("User $i");
    $entityManager->persist($user);
}

$entityManager->flush();

$time = (microtime(true) - $start) * 1000;
echo "💾 100 entités créées en " . round($time, 2) . "ms\\n";
```

---

## ➡️ Étapes Suivantes

Une fois l'installation terminée :

1. 📖 Consultez [Premiers Pas](first-steps.md) pour créer votre première entité
2. 🎯 Voir [Exemples de Base](basic-examples.md) pour des cas d'usage concrets
3. 🏗️ Explorez [Architecture](../core-concepts/architecture.md) pour comprendre les concepts

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../README.md)
- 📖 [Documentation Complète](../README.md)
- 🐛 [Signaler un Problème](https://github.com/mulertech/database/issues)
- 💬 [Support](mailto:sebastien.muler@mulertech.net)