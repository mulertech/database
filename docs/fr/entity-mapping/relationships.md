# Relations entre Entités

🌍 **Languages:** [🇫🇷 Français](relationships.md) | [🇬🇧 English](../../en/entity-mapping/relationships.md)

---

## 📋 Table des Matières

- [Vue d'Ensemble](#vue-densemble)
- [#[MtOneToOne] - Relation Un-à-Un](#mtonetoone---relation-un-à-un)
- [#[MtOneToMany] - Relation Un-à-Plusieurs](#mtonetomany---relation-un-à-plusieurs)
- [#[MtManyToOne] - Relation Plusieurs-à-Un](#mtmanytoone---relation-plusieurs-à-un)
- [#[MtManyToMany] - Relation Plusieurs-à-Plusieurs](#mtmanytomany---relation-plusieurs-à-plusieurs)
- [Exemples Pratiques](#exemples-pratiques)
- [Bonnes Pratiques](#bonnes-pratiques)

---

## Vue d'Ensemble

MulerTech Database propose 4 attributs pour définir les relations entre entités. Ces attributs permettent de mapper les associations classiques des bases de données relationnelles.

### 🔗 Types de Relations Disponibles

- **One-to-One** : Une entité liée à une seule autre entité
- **One-to-Many** : Une entité liée à plusieurs autres entités
- **Many-to-One** : Plusieurs entités liées à une seule entité
- **Many-to-Many** : Plusieurs entités liées à plusieurs autres entités

### 📦 Imports Nécessaires

```php
<?php
use MulerTech\Database\Mapping\Attributes\{
    MtOneToOne, MtOneToMany, MtManyToOne, MtManyToMany
};
```

---

## #[MtOneToOne] - Relation Un-à-Un

L'attribut `#[MtOneToOne]` définit une relation où une entité est associée à une seule autre entité.

### 🏷️ Syntaxe

```php
#[MtOneToOne(
    targetEntity?: string          // Classe de l'entité cible
)]
```

### 📝 Exemple

```php
<?php

use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn, MtOneToOne};
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

    #[MtOneToOne(targetEntity: Profile::class)]
    private ?Profile $profile = null;

    // Getters et setters...
}

#[MtEntity(tableName: 'profiles')]
class Profile
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::INT)]
    private int $userId;

    #[MtColumn(columnType: ColumnType::TEXT)]
    private ?string $biography = null;

    // Getters et setters...
}
```

---

## #[MtOneToMany] - Relation Un-à-Plusieurs

L'attribut `#[MtOneToMany]` définit une relation où une entité peut être associée à plusieurs autres entités.

### 🏷️ Syntaxe

```php
#[MtOneToMany(
    entity?: string,               // Entité courante (automatique)
    targetEntity?: string,         // Classe de l'entité cible
    inverseJoinProperty?: string   // Propriété inverse dans l'entité cible
)]
```

### 📝 Exemple

```php
<?php

use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn, MtOneToMany};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey};

#[MtEntity(tableName: 'categories')]
class Category
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $name;

    #[MtOneToMany(
        targetEntity: Product::class,
        inverseJoinProperty: 'category'
    )]
    private array $products = [];

    // Getters et setters...
}

#[MtEntity(tableName: 'products')]
class Product
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 200)]
    private string $name;

    #[MtColumn(columnType: ColumnType::INT)]
    private int $categoryId;

    // Référence vers la catégorie
    private ?Category $category = null;

    // Getters et setters...
}
```

---

## #[MtManyToOne] - Relation Plusieurs-à-Un

L'attribut `#[MtManyToOne]` définit une relation où plusieurs entités peuvent être associées à une seule entité.

### 🏷️ Syntaxe

```php
#[MtManyToOne(
    targetEntity?: string          // Classe de l'entité cible
)]
```

### 📝 Exemple

```php
<?php

use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn, MtManyToOne, MtFk};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey, FkRule};

#[MtEntity(tableName: 'orders')]
class Order
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::INT)]
    #[MtFk(
        referencedTable: 'users',
        referencedColumn: 'id',
        deleteRule: FkRule::CASCADE
    )]
    private int $userId;

    #[MtColumn(columnType: ColumnType::DECIMAL, length: 10, scale: 2)]
    private float $total;

    #[MtManyToOne(targetEntity: User::class)]
    private ?User $user = null;

    // Getters et setters...
}
```

---

## #[MtManyToMany] - Relation Plusieurs-à-Plusieurs

L'attribut `#[MtManyToMany]` définit une relation où plusieurs entités peuvent être associées à plusieurs autres entités via une table de liaison.

### 🏷️ Syntaxe

```php
#[MtManyToMany(
    entity?: string,               // Entité courante (automatique)
    targetEntity?: string,         // Classe de l'entité cible
    mappedBy?: string,             // Entité pivot
    joinProperty?: string,         // Propriété de jointure
    inverseJoinProperty?: string   // Propriété de jointure inverse
)]
```

### 📝 Exemple

```php
<?php

use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn, MtManyToMany};
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

    #[MtManyToMany(
        targetEntity: Role::class,
        mappedBy: UserRole::class,
        joinProperty: 'user',
        inverseJoinProperty: 'role'
    )]
    private array $roles = [];

    // Getters et setters...
}

#[MtEntity(tableName: 'roles')]
class Role
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 50)]
    private string $name;

    #[MtManyToMany(
        targetEntity: User::class,
        mappedBy: UserRole::class,
        joinProperty: 'role',
        inverseJoinProperty: 'user'
    )]
    private array $users = [];

    // Getters et setters...
}

// Table de liaison (entité pivot)
#[MtEntity(tableName: 'user_roles')]
class UserRole
{
    #[MtColumn(columnType: ColumnType::INT)]
    #[MtFk(referencedTable: 'users', referencedColumn: 'id')]
    private int $userId;

    #[MtColumn(columnType: ColumnType::INT)]
    #[MtFk(referencedTable: 'roles', referencedColumn: 'id')]
    private int $roleId;

    #[MtManyToOne(targetEntity: User::class)]
    private ?User $user = null;

    #[MtManyToOne(targetEntity: Role::class)]
    private ?Role $role = null;

    // Getters et setters...
}
```

---

## Exemples Pratiques

### Blog avec Articles et Commentaires

```php
<?php

// Entité Blog
#[MtEntity(tableName: 'blogs')]
class Blog
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 200)]
    private string $title;

    #[MtOneToMany(
        targetEntity: Article::class,
        inverseJoinProperty: 'blog'
    )]
    private array $articles = [];
}

// Entité Article
#[MtEntity(tableName: 'articles')]
class Article
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 300)]
    private string $title;

    #[MtColumn(columnType: ColumnType::INT)]
    private int $blogId;

    #[MtManyToOne(targetEntity: Blog::class)]
    private ?Blog $blog = null;

    #[MtOneToMany(
        targetEntity: Comment::class,
        inverseJoinProperty: 'article'
    )]
    private array $comments = [];
}

// Entité Comment
#[MtEntity(tableName: 'comments')]
class Comment
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::TEXT)]
    private string $content;

    #[MtColumn(columnType: ColumnType::INT)]
    private int $articleId;

    #[MtManyToOne(targetEntity: Article::class)]
    private ?Article $article = null;
}
```

---

## Bonnes Pratiques

### 1. Nommage des Propriétés de Relation

```php
// ✅ Bon - Noms clairs et explicites
#[MtOneToMany(targetEntity: Order::class)]
private array $orders = [];

#[MtManyToOne(targetEntity: Customer::class)]
private ?Customer $customer = null;

// ❌ Éviter - Noms ambigus
#[MtOneToMany(targetEntity: Order::class)]
private array $data = [];
```

### 2. Initialisation des Collections

```php
// ✅ Bon - Initialiser les tableaux pour OneToMany et ManyToMany
#[MtOneToMany(targetEntity: Product::class)]
private array $products = [];

#[MtManyToMany(targetEntity: Tag::class)]
private array $tags = [];
```

### 3. Cohérence des Relations Bidirectionnelles

```php
// ✅ Bon - Relations cohérentes
class User
{
    #[MtOneToMany(
        targetEntity: Order::class,
        inverseJoinProperty: 'user'
    )]
    private array $orders = [];
}

class Order
{
    #[MtManyToOne(targetEntity: User::class)]
    private ?User $user = null;
}
```

### 4. Documentation des Relations Complexes

```php
/**
 * Relation Many-to-Many avec table pivot personnalisée
 * Table de liaison : user_permissions avec colonnes supplémentaires
 */
#[MtManyToMany(
    targetEntity: Permission::class,
    mappedBy: UserPermission::class,
    joinProperty: 'user',
    inverseJoinProperty: 'permission'
)]
private array $permissions = [];
```

---

## ➡️ Étapes Suivantes

Pour approfondir votre compréhension :

1. 🎨 [Attributs de Mapping](attributes.md) - Attributs de base
2. 🗄️ [Repositories](../../fr/orm/repositories.md) - Gestion des requêtes
3. 🎯 [Exemples Pratiques](../../fr/quick-start/basic-examples.md) - Cas d'usage concrets

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../fr/../README.md)
- 📖 [Documentation Complète](../../fr/README.md)
- 🚀 [Démarrage Rapide](../../fr/quick-start/installation.md)
