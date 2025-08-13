# Attributs de Mapping

🌍 **Languages:** [🇫🇷 Français](attributes.md) | [🇬🇧 English](../../en/entity-mapping/attributes.md)

---

## 📋 Table des Matières

- [Vue d'Ensemble](#vue-densemble)
- [#[MtEntity] - Entité](#mtentity---entité)
- [#[MtColumn] - Colonne](#mtcolumn---colonne)
- [#[MtFk] - Clé Étrangère](#mtfk---clé-étrangère)
- [#[MtRelations] - Relations](#mtrelations---relations)
- [Types de Données](#types-de-données)
- [Exemples Avancés](#exemples-avancés)
- [Bonnes Pratiques](#bonnes-pratiques)

---

## Vue d'Ensemble

MulerTech Database utilise des **attributs PHP 8** pour définir le mapping entre vos classes et la base de données. Cette approche moderne remplace les annotations et fichiers de configuration traditionnels.

### 🎯 Avantages des Attributs

- **Type-safe** : Validation au niveau du langage
- **IDE-friendly** : Autocomplétion et vérification
- **Performance** : Parsing natif PHP
- **Maintenabilité** : Tout dans le code source

### 📦 Imports Nécessaires

```php
<?php
use MulerTech\Database\Mapping\Attributes\{
    MtEntity, MtColumn, MtFk, 
    MtOneToOne, MtOneToMany, MtManyToOne, MtManyToMany,
    MtIndex, MtUniqueConstraint
};
use MulerTech\Database\Mapping\Types\{
    ColumnType, ColumnKey, FkRule, IndexType
};
```

---

## #[MtEntity] - Entité

L'attribut `#[MtEntity]` marque une classe comme entité mappée à une table de base de données.

### 🏷️ Syntaxe

```php
#[MtEntity(
    tableName: string,              // Nom de la table (requis)
    repository?: string,            // Repository personnalisé
    schema?: string,                // Schéma de base de données
    readOnly?: bool,                // Entité en lecture seule
    cacheable?: bool,               // Mise en cache activée
    indexes?: array,                // Index de table
    uniqueConstraints?: array       // Contraintes d'unicité
)]
```

### 📝 Exemples

#### Entité Simple
```php
#[MtEntity(tableName: 'users')]
class User
{
    // Propriétés...
}
```

#### Entité avec Repository Personnalisé
```php
#[MtEntity(
    tableName: 'products', 
    repository: ProductRepository::class
)]
class Product
{
    // Propriétés...
}
```

---

## #[MtColumn] - Colonne

L'attribut `#[MtColumn]` définit le mapping d'une propriété vers une colonne de base de données.

### 🏷️ Syntaxe

```php
#[MtColumn(
    columnType: ColumnType,         // Type de colonne (requis)
    columnName?: string,            // Nom de colonne (défaut: nom propriété)
    length?: int,                   // Longueur (VARCHAR, etc.)
    precision?: int,                // Précision (DECIMAL)
    scale?: int,                    // Échelle (DECIMAL)
    isNullable?: bool,              // Null autorisé (défaut: false)
    isUnsigned?: bool,              // Non signé (nombres)
    columnKey?: ColumnKey,          // Type de clé
    columnDefault?: string,         // Valeur par défaut
    extra?: string,                 // Extra SQL (auto_increment, etc.)
    comment?: string                // Commentaire de colonne
)]
```

### 📊 Types de Colonnes

```php
enum ColumnType: string
{
    // Nombres entiers
    case TINYINT = 'TINYINT';
    case SMALLINT = 'SMALLINT';
    case MEDIUMINT = 'MEDIUMINT';
    case INT = 'INT';
    case BIGINT = 'BIGINT';
    
    // Nombres décimaux
    case DECIMAL = 'DECIMAL';
    case FLOAT = 'FLOAT';
    case DOUBLE = 'DOUBLE';
    
    // Chaînes de caractères
    case CHAR = 'CHAR';
    case VARCHAR = 'VARCHAR';
    case TEXT = 'TEXT';
    case LONGTEXT = 'LONGTEXT';
    
    // Date et temps
    case DATE = 'DATE';
    case TIME = 'TIME';
    case DATETIME = 'DATETIME';
    case TIMESTAMP = 'TIMESTAMP';
    
    // Autres
    case BOOLEAN = 'BOOLEAN';
    case JSON = 'JSON';
    case ENUM = 'ENUM';
}
```

### 📝 Exemples de Colonnes

#### Clé Primaire Auto-incrémentée
```php
#[MtColumn(
    columnType: ColumnType::INT,
    isUnsigned: true,
    isNullable: false,
    columnKey: ColumnKey::PRIMARY_KEY,
    extra: 'auto_increment'
)]
private ?int $id = null;
```

#### Email Unique
```php
#[MtColumn(
    columnType: ColumnType::VARCHAR,
    length: 320,
    isNullable: false,
    columnKey: ColumnKey::UNIQUE,
    comment: 'Adresse email unique'
)]
private string $email;
```

#### Prix avec Décimales
```php
#[MtColumn(
    columnType: ColumnType::DECIMAL,
    precision: 10,
    scale: 2,
    isNullable: false,
    isUnsigned: true,
    columnDefault: '0.00'
)]
private float $price;
```

---

## #[MtFk] - Clé Étrangère

L'attribut `#[MtFk]` définit une contrainte de clé étrangère.

### 🏷️ Syntaxe

```php
#[MtFk(
    referencedTable: string,        // Table référencée (requis)
    referencedColumn: string,       // Colonne référencée (requis)
    constraintName?: string,        // Nom de la contrainte
    onDelete?: FkRule,             // Action sur suppression
    onUpdate?: FkRule              // Action sur mise à jour
)]
```

### 🔄 Règles FK

```php
enum FkRule: string
{
    case RESTRICT = 'RESTRICT';     // Empêche la suppression/modification
    case CASCADE = 'CASCADE';       // Supprime/modifie en cascade
    case SET_NULL = 'SET NULL';     // Met à NULL
    case NO_ACTION = 'NO ACTION';   // Aucune action
}
```

### 📝 Exemples FK

```php
#[MtColumn(columnType: ColumnType::INT, isUnsigned: true)]
#[MtFk(
    referencedTable: 'users',
    referencedColumn: 'id',
    onDelete: FkRule::CASCADE
)]
private int $userId;
```

---

## #[MtRelations] - Relations

### 🔗 ManyToOne

```php
#[MtManyToOne(
    targetEntity: string,           // Entité cible (requis)
    joinColumn?: string,            // Colonne de jointure
    cascade?: array,                // Opérations en cascade
    fetch?: string                  // Type de chargement
)]
```

#### Exemple ManyToOne
```php
class Post
{
    #[MtColumn(columnType: ColumnType::INT, isUnsigned: true)]
    #[MtFk(referencedTable: 'users', referencedColumn: 'id')]
    private int $authorId;

    #[MtManyToOne(
        targetEntity: User::class,
        joinColumn: 'authorId'
    )]
    private ?User $author = null;
}
```

### 🔗 OneToMany

```php
#[MtOneToMany(
    targetEntity: string,           // Entité cible (requis)
    mappedBy: string,               // Propriété inverse (requis)
    cascade?: array,                // Opérations en cascade
    fetch?: string,                 // Type de chargement
    orphanRemoval?: bool            // Suppression des orphelins
)]
```

#### Exemple OneToMany
```php
class Category
{
    #[MtOneToMany(
        targetEntity: Product::class,
        mappedBy: 'categoryId',
        cascade: ['persist'],
        orphanRemoval: true
    )]
    private DatabaseCollection $products;

    public function __construct()
    {
        $this->products = new DatabaseCollection();
    }
}
```

### 🔗 ManyToMany

```php
#[MtManyToMany(
    targetEntity: string,           // Entité cible (requis)
    joinTable: string,              // Table de jointure (requis)
    joinColumn: string,             // Colonne de jointure (requis)
    inverseJoinColumn: string,      // Colonne inverse (requis)
    cascade?: array                 // Opérations en cascade
)]
```

#### Exemple ManyToMany
```php
class Post
{
    #[MtManyToMany(
        targetEntity: Tag::class,
        joinTable: 'post_tags',
        joinColumn: 'post_id',
        inverseJoinColumn: 'tag_id'
    )]
    private DatabaseCollection $tags;

    public function __construct()
    {
        $this->tags = new DatabaseCollection();
    }
}
```

---

## Types de Données

### 📅 Gestion des Dates

```php
class Event
{
    #[MtColumn(columnType: ColumnType::DATE)]
    private DateTime $eventDate;

    #[MtColumn(columnType: ColumnType::DATETIME)]
    private DateTime $createdAt;

    #[MtColumn(
        columnType: ColumnType::TIMESTAMP,
        columnDefault: 'CURRENT_TIMESTAMP',
        extra: 'ON UPDATE CURRENT_TIMESTAMP'
    )]
    private DateTime $updatedAt;
}
```

### 🔢 Gestion des Nombres

```php
class Product
{
    #[MtColumn(columnType: ColumnType::INT, isUnsigned: true)]
    private int $quantity;

    #[MtColumn(columnType: ColumnType::DECIMAL, precision: 10, scale: 2)]
    private float $price;

    #[MtColumn(columnType: ColumnType::FLOAT)]
    private float $discountPercentage;
}
```

### 📝 Gestion du Texte

```php
class Article
{
    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255)]
    private string $title;

    #[MtColumn(columnType: ColumnType::TEXT)]
    private string $content;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 255,
        columnKey: ColumnKey::UNIQUE
    )]
    private string $slug;
}
```

---

## Exemples Avancés

### 👤 Entité Utilisateur Complète

```php
<?php

namespace App\Entity;

use DateTime;
use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn, MtOneToMany};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey};
use MulerTech\Database\ORM\DatabaseCollection;

#[MtEntity(
    tableName: 'users',
    repository: UserRepository::class
)]
class User
{
    #[MtColumn(
        columnType: ColumnType::INT,
        isUnsigned: true,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 100,
        isNullable: false
    )]
    private string $fullName;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 320,
        columnKey: ColumnKey::UNIQUE
    )]
    private string $email;

    #[MtColumn(
        columnType: ColumnType::ENUM,
        options: ['active', 'inactive', 'suspended'],
        columnDefault: 'active'
    )]
    private string $status = 'active';

    #[MtColumn(
        columnType: ColumnType::JSON,
        isNullable: true
    )]
    private ?array $preferences = null;

    #[MtColumn(columnType: ColumnType::DATETIME)]
    private DateTime $createdAt;

    #[MtOneToMany(
        targetEntity: Post::class,
        mappedBy: 'authorId'
    )]
    private DatabaseCollection $posts;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->posts = new DatabaseCollection();
    }

    // Getters et Setters...
}
```

---

## Bonnes Pratiques

### ✅ Conventions de Nommage

```php
// ✅ BON - Noms explicites
#[MtEntity(tableName: 'user_profiles')]
class UserProfile
{
    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $firstName;
}

// ❌ ÉVITER - Noms ambigus
#[MtEntity(tableName: 'up')]
class UP
{
    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $fn;
}
```

### 🎯 Types Appropriés

```php
class Order
{
    // ID auto-incrémenté
    #[MtColumn(
        columnType: ColumnType::INT,
        isUnsigned: true,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    // Prix avec précision
    #[MtColumn(columnType: ColumnType::DECIMAL, precision: 10, scale: 2)]
    private float $totalAmount;

    // Statut avec enum
    #[MtColumn(
        columnType: ColumnType::ENUM,
        options: ['pending', 'paid', 'cancelled']
    )]
    private string $status;
}
```

---

## ➡️ Étapes Suivantes

Explorez les concepts suivants :

1. 🔗 [Relations entre Entités](relationships.md) - Associations complexes
2. 📊 [Types et Colonnes](types-and-columns.md) - Types de données avancés
3. 🗄️ [Entity Manager](../orm/entity-manager.md) - Gestion des entités
4. 🔧 [Query Builder](../query-builder/basic-queries.md) - Requêtes personnalisées

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../README.md)
- ⬅️ [Exemples de Base](../quick-start/basic-examples.md)
- ➡️ [Relations entre Entités](relationships.md)
- 📖 [Documentation Complète](../README.md)