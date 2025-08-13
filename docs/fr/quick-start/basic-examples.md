# Exemples de Base

Exemples pratiques d'utilisation de MulerTech Database.

## Blog Simple

### 📝 Structure des Entités

```php
<?php

namespace App\Entity;

use DateTime;
use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn, MtFk, MtManyToOne};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey, FkRule};

// Entité User
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

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255, columnKey: ColumnKey::UNIQUE)]
    private string $email;

    #[MtColumn(columnType: ColumnType::DATETIME)]
    private DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    // Getters et Setters
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void { $this->email = $email; }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
}

// Entité Post
#[MtEntity(tableName: 'posts')]
class Post
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255)]
    private string $title;

    #[MtColumn(columnType: ColumnType::TEXT)]
    private string $content;

    #[MtColumn(columnType: ColumnType::INT)]
    #[MtFk(
        referencedTable: 'users',
        referencedColumn: 'id',
        onDelete: FkRule::CASCADE
    )]
    private int $userId;

    #[MtManyToOne(targetEntity: User::class, joinColumn: 'userId')]
    private ?User $author = null;

    #[MtColumn(columnType: ColumnType::BOOLEAN, columnDefault: '0')]
    private bool $published = false;

    #[MtColumn(columnType: ColumnType::DATETIME)]
    private DateTime $createdAt;

    #[MtColumn(columnType: ColumnType::DATETIME, isNullable: true)]
    private ?DateTime $publishedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    // Getters et Setters
    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function getContent(): string { return $this->content; }
    public function setContent(string $content): void { $this->content = $content; }
    public function getUserId(): int { return $this->userId; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(User $author): void {
        $this->author = $author;
        $this->userId = $author->getId();
    }
    public function isPublished(): bool { return $this->published; }
    public function setPublished(bool $published): void {
        $this->published = $published;
        if ($published && !$this->publishedAt) {
            $this->publishedAt = new DateTime();
        }
    }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
    public function getPublishedAt(): ?DateTime { return $this->publishedAt; }
}
```

### Utilisation du Blog

```php
<?php
require_once 'bootstrap.php';

use App\Entity\{User, Post};

// Créer un auteur
$author = new User();
$author->setName('Alice Dupont');
$author->setEmail('alice@example.com');

$entityManager->persist($author);
$entityManager->flush();

// Créer un article
$post = new Post();
$post->setTitle('Mon premier article');
$post->setContent('Ceci est le contenu de mon premier article.');
$post->setAuthor($author);

$entityManager->persist($post);
$entityManager->flush();

echo "Article créé avec l'ID: " . $post->getId() . "\n";
```

## Repository Personnalisé

```php
<?php

namespace App\Repository;

use App\Entity\User;
use MulerTech\Database\ORM\EntityRepository;

class UserRepository extends EntityRepository
{
    public function findRecentUsers(int $days = 7): array
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $queryBuilder = $this->createQueryBuilder();
        
        return $queryBuilder
            ->raw('SELECT * FROM users WHERE created_at >= ? ORDER BY created_at DESC')
            ->bind([$date])
            ->execute()
            ->fetchAll();
    }

    public function searchUsers(string $query): array
    {
        $queryBuilder = $this->createQueryBuilder();
        
        return $queryBuilder
            ->raw('SELECT * FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY name')
            ->bind(["%{$query}%", "%{$query}%"])
            ->execute()
            ->fetchAll();
    }

    public function getUserStats(): array
    {
        $queryBuilder = $this->createQueryBuilder();
        
        $total = $queryBuilder
            ->raw('SELECT COUNT(*) as total FROM users')
            ->execute()
            ->fetchScalar();

        $recent = $queryBuilder
            ->raw('SELECT COUNT(*) as recent FROM users WHERE created_at >= ?')
            ->bind([date('Y-m-d H:i:s', strtotime('-30 days'))])
            ->execute()
            ->fetchScalar();

        return [
            'total' => (int)$total,
            'recent_30_days' => (int)$recent
        ];
    }
}
```

## Relations OneToMany

### Entité avec Collection

```php
<?php

namespace App\Entity;

use DateTime;
use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn, MtOneToMany};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey};
use MulerTech\Database\ORM\DatabaseCollection;

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

    #[MtColumn(columnType: ColumnType::TEXT, isNullable: true)]
    private ?string $description = null;

    #[MtOneToMany(targetEntity: Post::class, mappedBy: 'categoryId')]
    private ?DatabaseCollection $posts = null;

    #[MtColumn(columnType: ColumnType::DATETIME)]
    private DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->posts = new DatabaseCollection();
    }

    // Getters et Setters
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = $description; }
    public function getPosts(): DatabaseCollection { return $this->posts ?? new DatabaseCollection(); }
    public function addPost(Post $post): void {
        $this->getPosts()->add($post);
        $post->setCategory($this);
    }
    public function removePost(Post $post): void {
        $this->getPosts()->removeElement($post);
    }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
}
```

## Relations ManyToMany

### Système de Tags

```php
<?php

namespace App\Entity;

use DateTime;
use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn, MtManyToMany};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey};
use MulerTech\Database\ORM\DatabaseCollection;

#[MtEntity(tableName: 'tags')]
class Tag
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 50, columnKey: ColumnKey::UNIQUE)]
    private string $name;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 7)]
    private string $color = '#007bff';

    #[MtManyToMany(
        targetEntity: Post::class,
        joinTable: 'post_tags',
        joinColumn: 'tag_id',
        inverseJoinColumn: 'post_id'
    )]
    private ?DatabaseCollection $posts = null;

    #[MtColumn(columnType: ColumnType::DATETIME)]
    private DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->posts = new DatabaseCollection();
    }

    // Getters et Setters
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getColor(): string { return $this->color; }
    public function setColor(string $color): void { $this->color = $color; }
    public function getPosts(): DatabaseCollection { return $this->posts ?? new DatabaseCollection(); }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
}
```

## Requêtes Complexes

### Query Builder

```php
<?php
require_once 'bootstrap.php';

use MulerTech\Database\Query\Builder\QueryBuilder;

$queryBuilder = new QueryBuilder();

// Posts publiés avec auteur
$publishedPosts = $queryBuilder
    ->raw('
        SELECT p.title, u.name as author_name, p.published_at
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.published = ?
        ORDER BY p.published_at DESC
        LIMIT 5
    ')
    ->bind([true])
    ->execute()
    ->fetchAll();

foreach ($publishedPosts as $post) {
    echo "- {$post['title']} par {$post['author_name']}\n";
}
```

## Transactions

### Gestion des Transactions

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

// Transaction sécurisée
try {
    // Créer des utilisateurs par batch
    for ($i = 1; $i <= 100; $i++) {
        $user = new User();
        $user->setName("User {$i}");
        $user->setEmail("user{$i}@example.com");
        
        $entityManager->persist($user);
        
        if ($i % 20 === 0) {
            $entityManager->flush();
            $entityManager->clear();
        }
    }
    
    $entityManager->flush();
    
    echo "100 utilisateurs créés avec succès\n";
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
```

## Validation

### Validation Simple

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

function validateUser(User $user): bool
{
    if (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException("Email invalide");
    }
    return true;
}

// Test de validation
$user = new User();
$user->setName('Diana Prince');
$user->setEmail('diana@example.com');

if (validateUser($user)) {
    $entityManager->persist($user);
    $entityManager->flush();
    echo "Utilisateur créé avec succès\n";
}
```

## Étapes Suivantes

- [Attributs de Mapping](../entity-mapping/attributes.md)
- [Entity Manager](../orm/entity-manager.md)
- [Query Builder](../query-builder/basic-queries.md)