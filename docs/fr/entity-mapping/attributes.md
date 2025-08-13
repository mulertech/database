# Attributs de Mapping

🌍 **Languages:** [🇫🇷 Français](attributes.md) | [🇬🇧 English](../../en/entity-mapping/attributes.md)

---

## 📋 Table des Matières

- [Vue d'Ensemble](#vue-densemble)
- [#[MtEntity] - Entité](#mtentity---entité)
- [#[MtColumn] - Colonne](#mtcolumn---colonne)
- [#[MtFk] - Clé Étrangère](#mtfk---clé-étrangère)
- [Attributs de Relation](#attributs-de-relation)
- [Types de Données](#types-de-données)
- [Exemples Pratiques](#exemples-pratiques)

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
    MtOneToOne, MtOneToMany, MtManyToOne, MtManyToMany
};
use MulerTech\Database\Mapping\Types\{
    ColumnType, ColumnKey, FkRule
};
```

---

## #[MtEntity] - Entité

L'attribut `#[MtEntity]` marque une classe comme entité mappée à une table de base de données.

### 🏷️ Syntaxe

```php
#[MtEntity(
    repository?: string,            // Repository personnalisé
    tableName?: string,             // Nom de la table
    autoIncrement?: int,            // Valeur de départ auto-increment
    engine?: string,                // Moteur de stockage MySQL
    charset?: string,               // Jeu de caractères
    collation?: string              // Collation
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

#### Entité avec Repository et Configuration MySQL
```php
#[MtEntity(
    repository: UserRepository::class,
    tableName: 'users',
    engine: 'InnoDB',
    charset: 'utf8mb4',
    collation: 'utf8mb4_unicode_ci'
)]
class User
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
    columnName?: string,            // Nom de colonne (défaut: nom propriété)
    columnType?: ColumnType,        // Type de colonne
    length?: int,                   // Longueur ou précision
    scale?: int,                    // Échelle pour décimaux
    isUnsigned?: bool,              // Non signé (défaut: false)
    isNullable?: bool,              // Null autorisé (défaut: true)
    extra?: string,                 // Extra SQL (auto_increment, etc.)
    columnDefault?: string,         // Valeur par défaut
    columnKey?: ColumnKey,          // Type de clé
    choices?: array                 // Choix pour ENUM
)]
```

### 📊 Types de Colonnes Disponibles

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
    case TINYTEXT = 'TINYTEXT';
    case MEDIUMTEXT = 'MEDIUMTEXT';
    case LONGTEXT = 'LONGTEXT';
    
    // Données binaires
    case BINARY = 'BINARY';
    case VARBINARY = 'VARBINARY';
    case BLOB = 'BLOB';
    case TINYBLOB = 'TINYBLOB';
    case MEDIUMBLOB = 'MEDIUMBLOB';
    case LONGBLOB = 'LONGBLOB';
    
    // Date et temps
    case DATE = 'DATE';
    case TIME = 'TIME';
    case DATETIME = 'DATETIME';
    case TIMESTAMP = 'TIMESTAMP';
    case YEAR = 'YEAR';
}
```

### 🔑 Types de Clés

```php
enum ColumnKey: string
{
    case PRIMARY_KEY = 'PRI';
    case UNIQUE_KEY = 'UNI';
    case MULTIPLE_KEY = 'MUL';
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
    columnName: 'email_address',
    columnType: ColumnType::VARCHAR,
    length: 320,
    isNullable: false,
    columnKey: ColumnKey::UNIQUE_KEY
)]
private string $email;
```

#### Prix avec Décimales
```php
#[MtColumn(
    columnType: ColumnType::DECIMAL,
    length: 10,
    scale: 2,
    isNullable: false,
    isUnsigned: true,
    columnDefault: '0.00'
)]
private float $price;
```

#### Champ ENUM avec Choix
```php
#[MtColumn(
    columnType: ColumnType::ENUM,
    choices: ['active', 'inactive', 'pending'],
    columnDefault: 'pending'
)]
private string $status;
```

---

## #[MtFk] - Clé Étrangère

L'attribut `#[MtFk]` définit une contrainte de clé étrangère.

### 🏷️ Syntaxe

```php
#[MtFk(
    constraintName?: string,        // Nom de la contrainte
    column?: string,                // Colonne locale
    referencedTable?: string,       // Table référencée
    referencedColumn?: string,      // Colonne référencée
    deleteRule?: FkRule,           // Action sur suppression
    updateRule?: FkRule            // Action sur mise à jour
)]
```

### 🔄 Règles de Clé Étrangère

```php
enum FkRule: string
{
    case CASCADE = 'CASCADE';
    case SET_NULL = 'SET NULL';
    case NO_ACTION = 'NO ACTION';
    case RESTRICT = 'RESTRICT';
    case SET_DEFAULT = 'SET DEFAULT';
}
```

### 📝 Exemples de Clés Étrangères

#### Référence Simple
```php
#[MtColumn(columnType: ColumnType::INT, isUnsigned: true)]
#[MtFk(
    referencedTable: 'categories',
    referencedColumn: 'id',
    deleteRule: FkRule::CASCADE
)]
private ?int $categoryId = null;
```

#### Contrainte Nommée avec Règles
```php
#[MtColumn(columnType: ColumnType::INT, isUnsigned: true)]
#[MtFk(
    constraintName: 'fk_product_category',
    column: 'category_id',
    referencedTable: 'categories',
    referencedColumn: 'id',
    deleteRule: FkRule::SET_NULL,
    updateRule: FkRule::CASCADE
)]
private ?int $categoryId = null;
```

---

## Attributs de Relation

Les attributs de relation définissent les associations entre entités :

- `#[MtOneToOne]` - Relation un-à-un
- `#[MtOneToMany]` - Relation un-à-plusieurs
- `#[MtManyToOne]` - Relation plusieurs-à-un
- `#[MtManyToMany]` - Relation plusieurs-à-plusieurs

---

## Types de Données

### Correspondance PHP vers MySQL

| Type PHP | ColumnType Recommandé | Exemple |
|----------|----------------------|---------|
| `int` | `ColumnType::INT` | ID, compteurs |
| `string` | `ColumnType::VARCHAR` | Noms, emails |
| `float` | `ColumnType::DECIMAL` | Prix, montants |
| `bool` | `ColumnType::TINYINT` | Statuts booléens |
| `DateTime` | `ColumnType::DATETIME` | Dates complètes |
| `array` | `ColumnType::TEXT` | JSON sérialisé |

---

## Exemples Pratiques

### Entité User Complète

```php
<?php

use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn, MtFk};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey, FkRule};

#[MtEntity(
    repository: UserRepository::class,
    tableName: 'users',
    engine: 'InnoDB'
)]
class User
{
    #[MtColumn(
        columnType: ColumnType::INT,
        isUnsigned: true,
        isNullable: false,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 100,
        isNullable: false
    )]
    private string $name;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 320,
        isNullable: false,
        columnKey: ColumnKey::UNIQUE_KEY
    )]
    private string $email;

    #[MtColumn(
        columnType: ColumnType::TIMESTAMP,
        columnDefault: 'CURRENT_TIMESTAMP'
    )]
    private DateTime $createdAt;

    #[MtColumn(
        columnType: ColumnType::INT,
        isUnsigned: true,
        isNullable: true
    )]
    #[MtFk(
        referencedTable: 'roles',
        referencedColumn: 'id',
        deleteRule: FkRule::SET_NULL
    )]
    private ?int $roleId = null;

    // Getters et setters...
}
```

---

## ➡️ Étapes Suivantes

Pour approfondir votre compréhension :

1. 🔗 [Relations](relations.md) - Gestion des relations entre entités
2. 🗄️ [Repositories](../../fr/orm/repositories.md) - Repositories personnalisés
3. 🎯 [Exemples Pratiques](../../fr/quick-start/basic-examples.md) - Cas d'usage concrets

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../fr/../README.md)
- 📖 [Documentation Complète](../../fr/README.md)
- 🚀 [Démarrage Rapide](../../fr/quick-start/installation.md)
