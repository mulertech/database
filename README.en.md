# MulerTech Database

<!-- BADGES SECTION -->
[![Latest Version on Packagist](https://img.shields.io/packagist/v/mulertech/database.svg?style=flat-square)](https://packagist.org/packages/mulertech/database)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mulertech/database/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mulertech/database/actions/workflows/tests.yml)
[![GitHub PHPStan Action Status](https://img.shields.io/github/actions/workflow/status/mulertech/database/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/mulertech/database/actions/workflows/phpstan.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/mulertech/database.svg?style=flat-square)](https://packagist.org/packages/mulertech/database)
[![Test Coverage](https://raw.githubusercontent.com/mulertech/database/main/badge-coverage.svg)](https://packagist.org/packages/mulertech/database)

🌍 **Languages:** [🇫🇷 Français](README.md) | [🇬🇧 English](README.en.md)

---

<!-- DESCRIPTION SECTION -->
A modern PHP package for **database management** combining a performant **Database Abstraction Layer (DBAL)** and **Object-Relational Mapping (ORM)**. Designed for PHP 8.4+ with a focus on simplicity, performance and maintainability.

---

## 📋 Table of Contents

- [✨ Features](#-features)
- [🚀 Quick Installation](#-quick-installation)
- [🎯 First Example](#-first-example)
- [📚 Documentation](#-documentation)
- [🧪 Testing and Quality](#-testing-and-quality)
- [🤝 Contributing](#-contributing)
- [📄 License](#-license)

---

## ✨ Features

### 🗄️ **Modern ORM**
- **PHP 8 attribute mapping** (`#[MtEntity]`, `#[MtColumn]`, etc.)
- **Complete relations** (OneToOne, OneToMany, ManyToMany)
- **Entity Manager** with automatic change tracking
- **Custom repositories** with typed queries
- **Event system** (PrePersist, PostUpdate, etc.)
- **Smart caching** for metadata and queries

### 🔧 **Expressive Query Builder**
- **Fluent API** for building complex queries
- **Typed queries** with IDE autocompletion
- **Advanced joins** and subqueries
- **Raw SQL support** when needed
- **Automatic query optimization**

### 🛠️ **Schema Management**
- **Automatic migrations** with change detection
- **Built-in CLI commands** (`migration:run`, `migration:rollback`)
- **Schema comparison** and diff generation
- **Multi-environment support**

### 🎯 **Performance and Reliability**
- **Lazy loading** of relations
- **Connection pooling** and transaction management
- **Multi-level caching** (metadata, queries, results)
- **Comprehensive tests** (100% coverage)
- **Static analysis** PHPStan level 9

---

## 🚀 Quick Installation

### Requirements
- **PHP 8.4+**
- **PDO** with MySQL/PostgreSQL/SQLite driver
- **Composer**

### Installation
```bash
composer require mulertech/database "^1.0"
```

### Minimal Configuration
```php
<?php
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

// Initialization
$pdm = new PhpDatabaseManager($config);
$metadataRegistry = new MetadataRegistry();
$entityManager = new EntityManager($pdm, $metadataRegistry);
```

---

## 🎯 First Example

### 1. Define an Entity
```php
<?php
use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey};

#[MtEntity(tableName: 'users')]
class User
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $name;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255)]
    private string $email;

    #[MtColumn(columnType: ColumnType::DATETIME)]
    private DateTime $createdAt;

    // Getters and Setters...
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    // ...
}
```

### 2. CRUD Operations
```php
// Create a user
$user = new User();
$user->setName('John Doe');
$user->setEmail('john@example.com');
$user->setCreatedAt(new DateTime());

$entityManager->persist($user);
$entityManager->flush(); // ID is automatically assigned

// Find users
$users = $entityManager->getRepository(User::class)->findAll();
$user = $entityManager->getRepository(User::class)->find(1);
$users = $entityManager->getRepository(User::class)->findBy(['name' => 'John']);

// Update a user
$user->setEmail('john.doe@example.com');
$entityManager->flush(); // Change automatically detected

// Delete a user
$entityManager->remove($user);
$entityManager->flush();
```

### 3. Query Builder
```php
$queryBuilder = new QueryBuilder($entityManager->getEmEngine());

// Simple query
$users = $queryBuilder
    ->select('u.name', 'u.email')
    ->from('users', 'u')
    ->where('u.name', 'LIKE', '%John%')
    ->orderBy('u.createdAt', 'DESC')
    ->limit(10)
    ->getResult();

// Query with join
$results = $queryBuilder
    ->select('u.name', 'p.title')
    ->from('users', 'u')
    ->join('posts', 'p', 'u.id = p.user_id')
    ->where('p.published', '=', true)
    ->getResult();
```

### 4. Migrations
```bash
# Generate a migration
./vendor/bin/console migration:generate

# Run migrations
./vendor/bin/console migration:run

# Rollback
./vendor/bin/console migration:rollback
```

---

## 📚 Documentation

### 🚀 **Quick Start**
- [Installation and Configuration](docs/en/quick-start/installation.md)
- [First Steps](docs/en/quick-start/first-steps.md)
- [Basic Examples](docs/en/quick-start/basic-examples.md)

### 🏗️ **Fundamental Concepts**
- [General Architecture](docs/en/core-concepts/architecture.md)
- [Advanced Configuration](docs/en/core-concepts/configuration.md)
- [Dependency Injection](docs/en/core-concepts/dependency-injection.md)

### 🎯 **Entity Mapping**
- [Mapping Attributes](docs/en/entity-mapping/attributes.md)
- [Entity Relations](docs/en/entity-mapping/relationships.md)
- [Types and Columns](docs/en/entity-mapping/types-and-columns.md)
- [Custom Entities](docs/en/entity-mapping/custom-entities.md)

### 🗄️ **ORM and Entity Manager**
- [Entity Manager](docs/en/orm/entity-manager.md)
- [Repositories](docs/en/orm/repositories.md)
- [Change Tracking](docs/en/orm/change-tracking.md)
- [Event System](docs/en/orm/events.md)
- [Caching and Performance](docs/en/orm/caching.md)

### 🔧 **Query Builder**
- [Basic Queries](docs/en/query-builder/basic-queries.md)
- [Advanced Queries](docs/en/query-builder/advanced-queries.md)
- [Raw SQL Queries](docs/en/query-builder/raw-queries.md)
- [Optimization](docs/en/query-builder/query-optimization.md)

### 🛠️ **Schema and Migrations**
- [Creating Migrations](docs/en/schema-migrations/creating-migrations.md)
- [Running Migrations](docs/en/schema-migrations/running-migrations.md)
- [CLI Commands](docs/en/schema-migrations/migration-commands.md)
- [Schema Comparison](docs/en/schema-migrations/schema-diff.md)

### 🧪 **Testing and Development**
- [Test Configuration](docs/en/testing/unit-tests.md)
- [Integration Tests](docs/en/testing/integration-tests.md)
- [Docker Setup](docs/en/testing/docker-setup.md)

### 🔬 **Advanced Features**
- [Extending the ORM](docs/en/advanced/extending-orm.md)
- [Custom Types](docs/en/advanced/custom-types.md)
- [Internal Architecture](docs/en/advanced/internals.md)

### 📖 **Complete Examples**
- [Blog Application](docs/en/examples/blog-application/)
- [E-commerce](docs/en/examples/e-commerce/)
- [Code Samples](docs/en/examples/code-samples/)

### 📋 **API Reference**
- [Core Classes](docs/en/api-reference/core-classes.md)
- [Interfaces](docs/en/api-reference/interfaces.md)
- [Exceptions](docs/en/api-reference/exceptions.md)

---

## 🧪 Testing and Quality

### Running Tests
```bash
# Unit tests
./vendor/bin/phpunit

# Tests with coverage
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html coverage

# Static analysis
./vendor/bin/phpstan analyze

# Code style
./vendor/bin/php-cs-fixer fix
```

### Quality Metrics
- ✅ **100% test coverage**
- ✅ **PHPStan level 9** (maximum static analysis)
- ✅ **PHP CS Fixer** (consistent code style)
- ✅ **Zero vulnerabilities** (automatic security audit)

### Docker Environment
```bash
# Start test environment
./vendor/bin/mtdocker up

# Run tests in Docker
./vendor/bin/mtdocker test

# Tests with coverage
./vendor/bin/mtdocker test-coverage
```

---

## 🤝 Contributing

### Contribution Guide
1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/my-feature`)
3. **Commit** changes (`git commit -am 'Add my feature'`)
4. **Push** to the branch (`git push origin feature/my-feature`)
5. **Create** a Pull Request

### Development Standards
- **PSR-12** for code style
- **PHPStan level 9** mandatory
- **Unit tests** for any new feature
- **Documentation** updated

### Development Environment
```bash
# Clone the repository
git clone https://github.com/mulertech/database.git
cd database

# Install dependencies
composer install

# Configure environment
cp .env.example .env

# Start Docker (optional)
./vendor/bin/mtdocker up
```

---

## 📄 License

This project is licensed under the **MIT** license. See the [LICENSE](LICENSE) file for more details.

---

## 🔗 Useful Links

- **📦 Packagist** : [mulertech/database](https://packagist.org/packages/mulertech/database)
- **🐛 Issues** : [GitHub Issues](https://github.com/mulertech/database/issues)
- **📧 Support** : sebastien.muler@mulertech.net
- **🌐 Website** : [mulertech.net](https://mulertech.net)

---

## 🏷️ Versions

- **v1.0.x** : Current stable version
- See [CHANGELOG.md](CHANGELOG.md) for complete history

---

<div align="center">
  <strong>Developed with ❤️ by <a href="https://github.com/mulertech">MulerTech</a></strong>
</div>
