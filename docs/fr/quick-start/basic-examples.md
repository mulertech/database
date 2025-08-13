# Exemples de Base

🌍 **Languages:** [🇫🇷 Français](basic-examples.md) | [🇬🇧 English](../../en/quick-start/basic-examples.md)

---

## 📋 Table des Matières

- [Blog Simple](#blog-simple)
- [Gestion d'Utilisateurs](#gestion-dutilisateurs)
- [Relations OneToMany](#relations-onetomany)
- [Relations ManyToMany](#relations-manytomany)
- [Requêtes Complexes](#requêtes-complexes)
- [Transactions et Performance](#transactions-et-performance)
- [Validation et Événements](#validation-et-événements)

---

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

### 🚀 Utilisation du Blog

```php
<?php
require_once 'bootstrap.php';

use App\Entity\{User, Post};

// Créer un auteur
$author = new User();
$author->setName('Alice Dupont')
       ->setEmail('alice@example.com');

$entityManager->persist($author);
$entityManager->flush();

// Créer un article
$post = new Post();
$post->setTitle('Mon premier article')
     ->setContent('Ceci est le contenu de mon premier article sur ce blog.')
     ->setAuthor($author);

$entityManager->persist($post);
$entityManager->flush();

echo "✅ Article créé avec l'ID: " . $post->getId() . "\n";
echo "👤 Auteur: " . $post->getAuthor()->getName() . "\n";

// Publier l'article
$post->setPublished(true);
$entityManager->flush();

echo "📰 Article publié le: " . $post->getPublishedAt()->format('Y-m-d H:i:s') . "\n";
```

---

## Gestion d'Utilisateurs

### 👥 Repository Personnalisé

```php
<?php

namespace App\Repository;

use App\Entity\User;
use MulerTech\Database\ORM\EntityRepository;
use MulerTech\Database\Query\Builder\QueryBuilder;

class UserRepository extends EntityRepository
{
    /**
     * Trouver les utilisateurs récents
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
     * Rechercher par nom ou email
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
     * Statistiques des utilisateurs
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

## Relations OneToMany

### 📝 Entité avec Collection

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

---

## Relations ManyToMany

### 🏷️ Système de Tags

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

---

## Requêtes Complexes

### 🔍 Query Builder Avancé

```php
<?php
require_once 'bootstrap.php';

use MulerTech\Database\Query\Builder\QueryBuilder;

$queryBuilder = new QueryBuilder($entityManager->getEmEngine());

// Posts publiés avec auteur et catégorie
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
    echo "- {$post['title']} par {$post['author_name']}\n";
}
```

---

## Transactions et Performance

### 💾 Gestion des Transactions

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

// Transaction sécurisée
try {
    $entityManager->beginTransaction();
    
    // Créer des utilisateurs par batch
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
    
    echo "✅ 100 utilisateurs créés avec succès\n";
    
} catch (Exception $e) {
    $entityManager->rollback();
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}
```

---

## Validation et Événements

### 🔔 Système d'Événements

```php
<?php
require_once 'bootstrap.php';

use App\Entity\{User, Post};
use MulerTech\Database\Event\{PrePersistEvent, PostPersistEvent};

$eventManager = $entityManager->getEventManager();

// Validation avant sauvegarde
$eventManager->addListener(PrePersistEvent::class, function(PrePersistEvent $event) {
    $entity = $event->getEntity();
    
    if ($entity instanceof User) {
        if (!filter_var($entity->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Email invalide");
        }
        echo "🔍 Validation utilisateur OK\n";
    }
});

// Actions après sauvegarde
$eventManager->addListener(PostPersistEvent::class, function(PostPersistEvent $event) {
    $entity = $event->getEntity();
    
    if ($entity instanceof Post) {
        echo "📧 Notification envoyée pour: {$entity->getTitle()}\n";
    }
});

// Test des événements
$user = new User();
$user->setName('Diana Prince')
     ->setEmail('diana@example.com');

$entityManager->persist($user);
$entityManager->flush();
```

---

## ➡️ Étapes Suivantes

Continuez votre apprentissage avec :

1. 🎨 [Attributs de Mapping](../entity-mapping/attributes.md) - Mapping avancé
2. 🗄️ [Entity Manager](../orm/entity-manager.md) - API complète
3. 🔧 [Query Builder](../query-builder/basic-queries.md) - Requêtes avancées
4. 🏗️ [Architecture](../core-concepts/architecture.md) - Concepts techniques

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../README.md)
- ⬅️ [Premiers Pas](first-steps.md)
- ➡️ [Attributs de Mapping](../entity-mapping/attributes.md)
- 📖 [Documentation Complète](../README.md)