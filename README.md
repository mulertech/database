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
Un package PHP moderne pour la **gestion de base de données** combinant une **couche d'abstraction (DBAL)** et un **mapping objet-relationnel (ORM)** performant. Conçu pour PHP 8.4+ avec un focus sur la simplicité, la performance et la maintenabilité.

---

## 📋 Table des Matières

- [✨ Fonctionnalités](#-fonctionnalités)
- [🚀 Installation Rapide](#-installation-rapide)
- [🎯 Premier Exemple](#-premier-exemple)
- [📚 Documentation](#-documentation)
- [🧪 Tests et Qualité](#-tests-et-qualité)
- [🤝 Contribution](#-contribution)
- [📄 Licence](#-licence)

---

## ✨ Fonctionnalités

### 🗄️ **ORM Moderne**
- **Mapping par attributs PHP 8** (`#[MtEntity]`, `#[MtColumn]`, etc.)
- **Relations complètes** (OneToOne, OneToMany, ManyToMany)
- **Entity Manager** avec suivi automatique des modifications
- **Repositories personnalisés** avec requêtes typées
- **Système d'événements** (PrePersist, PostUpdate, etc.)
- **Cache intelligent** pour les métadonnées et requêtes

### 🔧 **Query Builder Expressif**
- **API fluide** pour construire des requêtes complexes
- **Requêtes typées** avec autocomplétion IDE
- **Jointures avancées** et sous-requêtes
- **Support SQL brut** quand nécessaire
- **Optimisation automatique** des requêtes

### 🛠️ **Gestion de Schéma**
- **Migrations automatiques** avec détection des changements
- **Commandes CLI** intégrées (`migration:run`, `migration:rollback`)
- **Comparaison de schémas** et génération de diff
- **Support multi-environnements**

### 🎯 **Performance et Fiabilité**
- **Lazy loading** des relations
- **Connection pooling** et gestion des transactions
- **Cache multi-niveaux** (métadonnées, requêtes, résultats)
- **Tests complets** (100% de couverture)
- **Analyse statique** PHPStan niveau 9

---

## 🚀 Installation Rapide

### Prérequis
- **PHP 8.4+**
- **PDO** avec driver MySQL/PostgreSQL/SQLite
- **Composer**

### Installation
```bash
composer require mulertech/database "^1.0"
```

### Configuration Minimale
```php
<?php
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Mapping\MetadataRegistry;

// Configuration de la base de données
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'my_app',
    'username' => 'user',
    'password' => 'password'
];

// Initialisation
$pdm = new PhpDatabaseManager($config);
$metadataRegistry = new MetadataRegistry();
$entityManager = new EntityManager($pdm, $metadataRegistry);
```

---

## 🎯 Premier Exemple

### 1. Définir une Entité
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

    // Getters et Setters...
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    // ...
}
```

### 2. Opérations CRUD
```php
// Créer un utilisateur
$user = new User();
$user->setName('John Doe');
$user->setEmail('john@example.com');
$user->setCreatedAt(new DateTime());

$entityManager->persist($user);
$entityManager->flush(); // L'ID est automatiquement assigné

// Rechercher des utilisateurs
$users = $entityManager->getRepository(User::class)->findAll();
$user = $entityManager->getRepository(User::class)->find(1);
$users = $entityManager->getRepository(User::class)->findBy(['name' => 'John']);

// Modifier un utilisateur
$user->setEmail('john.doe@example.com');
$entityManager->flush(); // Modification automatiquement détectée

// Supprimer un utilisateur
$entityManager->remove($user);
$entityManager->flush();
```

### 3. Query Builder
```php
$queryBuilder = new QueryBuilder($entityManager->getEmEngine());

// Requête simple
$users = $queryBuilder
    ->select('u.name', 'u.email')
    ->from('users', 'u')
    ->where('u.name', 'LIKE', '%John%')
    ->orderBy('u.createdAt', 'DESC')
    ->limit(10)
    ->getResult();

// Requête avec jointure
$results = $queryBuilder
    ->select('u.name', 'p.title')
    ->from('users', 'u')
    ->join('posts', 'p', 'u.id = p.user_id')
    ->where('p.published', '=', true)
    ->getResult();
```

### 4. Migrations
```bash
# Générer une migration
./vendor/bin/console migration:generate

# Exécuter les migrations
./vendor/bin/console migration:run

# Rollback
./vendor/bin/console migration:rollback
```

---

## 📚 Documentation

### 🚀 **Démarrage Rapide**
- [Installation et Configuration](docs/fr/quick-start/installation.md)
- [Premiers Pas](docs/fr/quick-start/first-steps.md)
- [Exemples de Base](docs/fr/quick-start/basic-examples.md)

### 🏗️ **Concepts Fondamentaux**
- [Architecture Générale](docs/fr/fundamentals/architecture.md)
- [Classes Principales](docs/fr/fundamentals/core-classes.md)
- [Injection de Dépendances](docs/fr/fundamentals/dependency-injection.md)
- [Interfaces](docs/fr/fundamentals/interfaces.md)

### 🎯 **Mapping d'Entités**
- [Attributs de Mapping](docs/fr/entity-mapping/attributes.md)
- [Relations entre Entités](docs/fr/entity-mapping/relationships.md)
- [Types et Colonnes](docs/fr/entity-mapping/types-and-columns.md)

### 🗄️ **Accès aux Données**
- [Entity Manager](docs/fr/data-access/entity-manager.md)
- [Repositories](docs/fr/data-access/repositories.md)
- [Suivi des Modifications](docs/fr/data-access/change-tracking.md)
- [Système d'Événements](docs/fr/data-access/events.md)
- [Query Builder](docs/fr/data-access/query-builder.md)
- [Requêtes SQL Brutes](docs/fr/data-access/raw-queries.md)

### 🛠️ **Schéma et Migrations**
- [Migrations](docs/fr/schema-migrations/migrations.md)
- [Outils de Migration](docs/fr/schema-migrations/migration-tools.md)

---

## 🧪 Tests et Qualité

### Exécuter les Tests
```bash
# Tests unitaires
./vendor/bin/phpunit

# Tests avec couverture
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html coverage

# Analyse statique
./vendor/bin/phpstan analyze

# Style de code
./vendor/bin/php-cs-fixer fix
```

### Métriques de Qualité
- ✅ **100% de couverture de tests**
- ✅ **PHPStan niveau 9** (analyse statique maximale)
- ✅ **PHP CS Fixer** (style de code cohérent)
- ✅ **Zéro vulnérabilité** (audit sécurité automatique)

### Environnement Docker
```bash
# Démarrer l'environnement de test
./vendor/bin/mtdocker up

# Exécuter les tests dans Docker
./vendor/bin/mtdocker test

# Tests avec couverture
./vendor/bin/mtdocker test-coverage
```

---

## 🤝 Contribution

### Guide de Contribution
1. **Fork** le repository
2. **Créer** une branche feature (`git checkout -b feature/ma-fonctionnalite`)
3. **Commit** les modifications (`git commit -am 'Ajouter ma fonctionnalité'`)
4. **Push** vers la branche (`git push origin feature/ma-fonctionnalite`)
5. **Créer** une Pull Request

### Standards de Développement
- **PSR-12** pour le style de code
- **PHPStan niveau 9** obligatoire
- **Tests unitaires** pour toute nouvelle fonctionnalité
- **Documentation** mise à jour

### Environnement de Développement
```bash
# Cloner le repository
git clone https://github.com/mulertech/database.git
cd database

# Installer les dépendances
composer install

# Configurer l'environnement
cp .env.example .env

# Démarrer Docker (optionnel)
./vendor/bin/mtdocker up
```

---

## 📄 Licence

Ce projet est sous licence **MIT**. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

---

## 🔗 Liens Utiles

- **📦 Packagist** : [mulertech/database](https://packagist.org/packages/mulertech/database)
- **🐛 Issues** : [GitHub Issues](https://github.com/mulertech/database/issues)
- **📧 Support** : sebastien.muler@mulertech.net
- **🌐 Site Web** : [mulertech.net](https://mulertech.net)

---

## 🏷️ Versions

- **v1.0.x** : Version stable actuelle
- Voir [CHANGELOG.md](CHANGELOG.md) pour l'historique complet

---

<div align="center">
  <strong>Développé avec ❤️ par <a href="https://github.com/mulertech">MulerTech</a></strong>
</div>
