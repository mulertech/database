# Basic Examples

🌍 **Languages:** [🇫🇷 Français](../../fr/quick-start/basic-examples.md) | [🇬🇧 English](basic-examples.md)

---

## 📋 Table of Contents

- [Simple Blog](#simple-blog)
- [User Management](#user-management)
- [OneToMany Relations](#onetomany-relations)
- [ManyToMany Relations](#manytomany-relations)
- [Complex Queries](#complex-queries)
- [Transactions and Performance](#transactions-and-performance)
- [Validation and Events](#validation-and-events)

---

## Simple Blog

### 📝 Entity Structure

```php
<?php

namespace App\Entity;

use DateTime;
use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn, MtFk, MtManyToOne};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey, FkRule};

// User Entity
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

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void { $this->email = $email; }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
}

// Post Entity
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

    // Getters and Setters
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

### 🚀 Blog Usage

```php
<?php
require_once 'bootstrap.php';

use App\Entity\{User, Post};

// Create an author
$author = new User();
$author->setName('Alice Dupont')
       ->setEmail('alice@example.com');

$entityManager->persist($author);
$entityManager->flush();

// Create a post
$post = new Post();
$post->setTitle('My first post')
     ->setContent('This is the content of my first blog post.')
     ->setAuthor($author);

$entityManager->persist($post);
$entityManager->flush();

echo "✅ Post created with ID: " . $post->getId() . "\n";
echo "👤 Author: " . $post->getAuthor()->getName() . "\n";

// Publish the post
$post->setPublished(true);
$entityManager->flush();

echo "📰 Post published on: " . $post->getPublishedAt()->format('Y-m-d H:i:s') . "\n";
```

---

## User Management

### 👥 Custom Repository

```php
<?php

namespace App\Repository;

use App\Entity\User;
use MulerTech\Database\ORM\EntityRepository;
use MulerTech\Database\Query\Builder\QueryBuilder;

class UserRepository extends EntityRepository
{
    /**
     * Find recent users
     */
    public function findRecentUsers(int $days = 7): array
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $queryBuilder = new QueryBuilder($this->getEntityManager()->getEmEngine());
        
        $results = $queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->where('u.created_at', '>=', $date)
            ->orderBy('u.created_at', 'DESC')
            ->getResult();
        
        return $this->hydrateResults($results);
    }

    /**
     * Search by name or email
     */
    public function searchUsers(string $query): array
    {
        $queryBuilder = new QueryBuilder($this->getEntityManager()->getEmEngine());
        
        $results = $queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->where('u.name', 'LIKE', "%{$query}%")
            ->orWhere('u.email', 'LIKE', "%{$query}%")
            ->orderBy('u.name', 'ASC')
            ->getResult();
        
        return $this->hydrateResults($results);
    }

    /**
     * User statistics
     */
    public function getUserStats(): array
    {
        $queryBuilder = new QueryBuilder($this->getEntityManager()->getEmEngine());
        
        $total = $queryBuilder
            ->select('COUNT(*) as total')
            ->from('users', 'u')
            ->getResult()[0]['total'];

        $recent = $queryBuilder
            ->select('COUNT(*) as recent')
            ->from('users', 'u')
            ->where('u.created_at', '>=', date('Y-m-d H:i:s', strtotime('-30 days')))
            ->getResult()[0]['recent'];

        return [
            'total' => (int)$total,
            'recent_30_days' => (int)$recent,
            'growth_rate' => $total > 0 ? round(($recent / $total) * 100, 2) : 0
        ];
    }

    private function hydrateResults(array $results): array
    {
        $entities = [];
        foreach ($results as $result) {
            $entities[] = $this->getEntityManager()->getEmEngine()->hydrateEntity(User::class, $result);
        }
        return $entities;
    }
}
```

---

## OneToMany Relations

### 📝 Entity with Collection

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

    // Getters and Setters
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

---

## ManyToMany Relations

### 🏷️ Tag System

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

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getColor(): string { return $this->color; }
    public function setColor(string $color): void { $this->color = $color; }
    public function getPosts(): DatabaseCollection { return $this->posts ?? new DatabaseCollection(); }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
}
```

---

## Complex Queries

### 🔍 Advanced Query Builder

```php
<?php
require_once 'bootstrap.php';

use MulerTech\Database\Query\Builder\QueryBuilder;

$queryBuilder = new QueryBuilder($entityManager->getEmEngine());

// Published posts with author and category
$publishedPosts = $queryBuilder
    ->select([
        'p.title',
        'u.name as author_name',
        'c.name as category_name',
        'p.published_at'
    ])
    ->from('posts', 'p')
    ->join('users', 'u', 'p.user_id = u.id')
    ->leftJoin('categories', 'c', 'p.category_id = c.id')
    ->where('p.published', '=', true)
    ->orderBy('p.published_at', 'DESC')
    ->limit(5)
    ->getResult();

foreach ($publishedPosts as $post) {
    echo "- {$post['title']} by {$post['author_name']}\n";
}
```

---

## Transactions and Performance

### 💾 Transaction Management

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

// Secure transaction
try {
    $entityManager->beginTransaction();
    
    // Create users in batches
    for ($i = 1; $i <= 100; $i++) {
        $user = new User();
        $user->setName("User {$i}")
             ->setEmail("user{$i}@example.com");
        
        $entityManager->persist($user);
        
        if ($i % 20 === 0) {
            $entityManager->flush();
            $entityManager->clear();
        }
    }
    
    $entityManager->flush();
    $entityManager->commit();
    
    echo "✅ 100 users created successfully\n";
    
} catch (Exception $e) {
    $entityManager->rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
}
```

---

## Validation and Events

### 🔔 Event System

```php
<?php
require_once 'bootstrap.php';

use App\Entity\{User, Post};
use MulerTech\Database\Event\{PrePersistEvent, PostPersistEvent};

$eventManager = $entityManager->getEventManager();

// Validation before save
$eventManager->addListener(PrePersistEvent::class, function(PrePersistEvent $event) {
    $entity = $event->getEntity();
    
    if ($entity instanceof User) {
        if (!filter_var($entity->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email");
        }
        echo "🔍 User validation OK\n";
    }
});

// Actions after save
$eventManager->addListener(PostPersistEvent::class, function(PostPersistEvent $event) {
    $entity = $event->getEntity();
    
    if ($entity instanceof Post) {
        echo "📧 Notification sent for: {$entity->getTitle()}\n";
    }
});

// Test events
$user = new User();
$user->setName('Diana Prince')
     ->setEmail('diana@example.com');

$entityManager->persist($user);
$entityManager->flush();
```

---

## ➡️ Next Steps

Continue your learning with:

1. 🎨 [Mapping Attributes](../entity-mapping/attributes.md) - Advanced mapping
2. 🗄️ [Entity Manager](../orm/entity-manager.md) - Complete API
3. 🔧 [Query Builder](../query-builder/basic-queries.md) - Advanced queries
4. 🏗️ [Architecture](../core-concepts/architecture.md) - Technical concepts

---

## 🔗 Useful Links

- 🏠 [Back to README](../../README.md)
- ⬅️ [First Steps](first-steps.md)
- ➡️ [Mapping Attributes](../entity-mapping/attributes.md)
- 📖 [Complete Documentation](../README.md)