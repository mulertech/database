# Échantillons de Code - MulerTech Database

Cette section contient une collection d'échantillons de code pratiques pour différents cas d'usage courants avec MulerTech Database ORM.

## 📋 Table des matières

- [Entités de base](#entités-de-base)
- [Relations](#relations)
- [Requêtes communes](#requêtes-communes)
- [Transactions](#transactions)
- [Cache et Performance](#cache-et-performance)
- [Validation et Events](#validation-et-events)
- [Types personnalisés](#types-personnalisés)
- [Patterns avancés](#patterns-avancés)

## Entités de base

### Entité User simple

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\Mapping\Types\ColumnKey;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[MtEntity(table: 'users')]
class User
{
    #[MtColumn(columnType: ColumnType::INT, columnKey: ColumnKey::PRIMARY_KEY, extra: 'AUTO_INCREMENT')]
    private int $id;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $name;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255, isUnique: true)]
    private string $email;

    #[MtColumn(columnName: 'created_at', columnType: ColumnType::TIMESTAMP, columnDefault: 'CURRENT_TIMESTAMP')]
    private \DateTimeInterface $createdAt;

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
```

### Entité Product avec énumération

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtId;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\Mapping\Types\ColumnKey;

enum ProductStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[MtEntity(table: 'products')]
class Product
{
    #[MtId]
    #[MtColumn(columnType: ColumnType::INT, columnKey: ColumnKey::PRIMARY_KEY, extra: 'AUTO_INCREMENT')]
    private int $id;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255)]
    private string $name;

    #[MtColumn(columnType: ColumnType::TEXT, isNullable: true)]
    private ?string $description = null;

    #[MtColumn(columnType: ColumnType::DECIMAL, precision: 10, scale: 2)]
    private float $price;

    #[MtColumn(columnType: ColumnType::ENUM, choices: ProductStatus::class)]
    private ProductStatus $status = ProductStatus::DRAFT;

    #[MtColumn(columnName: 'stock_quantity', columnType: ColumnType::INT, columnDefault: '0')]
    private int $stockQuantity = 0;

    public function __construct(string $name, float $price)
    {
        $this->name = $name;
        $this->price = $price;
    }

    // Getters et setters...
    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getPrice(): float { return $this->price; }
    public function setPrice(float $price): self { $this->price = $price; return $this; }
    public function getStatus(): ProductStatus { return $this->status; }
    public function setStatus(ProductStatus $status): self { $this->status = $status; return $this; }
    public function getStockQuantity(): int { return $this->stockQuantity; }
    public function setStockQuantity(int $quantity): self { $this->stockQuantity = $quantity; return $this; }
}
```

## Relations

### Relation OneToMany (User → Posts)

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;
use MulerTech\Database\ORM\Attribute\MtId;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\Mapping\Types\ColumnKey;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[MtEntity(table: 'posts')]
class Post
{
    #[MtId]
    #[MtColumn(columnType: ColumnType::INT, columnKey: ColumnKey::PRIMARY_KEY, extra: 'AUTO_INCREMENT')]
    private int $id;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255)]
    private string $title;

    #[MtColumn(columnType: ColumnType::TEXT)]
    private string $content;

    #[MtColumn(columnName: 'user_id', columnType: ColumnType::INT)]
    private int $userId;

    #[MtRelation('ManyToOne', targetEntity: User::class, inversedBy: 'posts')]
    private User $author;

    public function __construct(string $title, string $content, User $author)
    {
        $this->title = $title;
        $this->content = $content;
        $this->author = $author;
        $this->userId = $author->getId();
    }

    // Getters et setters...
}

// Modification de User pour ajouter la relation inverse
class User
{
    // ... propriétés existantes ...

    #[MtRelation('OneToMany', targetEntity: Post::class, mappedBy: 'author')]
    private array $posts = [];

    /**
     * @return array<Post>
     */
    public function getPosts(): array
    {
        return $this->posts;
    }

    public function addPost(Post $post): self
    {
        $this->posts[] = $post;
        return $this;
    }
}
```

### Relation ManyToMany (Product ↔ Category)

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;
use MulerTech\Database\ORM\Attribute\MtId;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\Mapping\Types\ColumnKey;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[MtEntity(table: 'categories')]
class Category
{
    #[MtId]
    #[MtColumn(columnType: ColumnType::INT, columnKey: ColumnKey::PRIMARY_KEY, extra: 'AUTO_INCREMENT')]
    private int $id;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $name;

    #[MtRelation(
        'ManyToMany',
        targetEntity: Product::class,
        mappedBy: 'categories',
        joinTable: 'product_categories',
        joinColumns: ['category_id' => 'id'],
        inverseJoinColumns: ['product_id' => 'id']
    )]
    private array $products = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return array<Product>
     */
    public function getProducts(): array
    {
        return $this->products;
    }

    public function addProduct(Product $product): self
    {
        if (!in_array($product, $this->products, true)) {
            $this->products[] = $product;
        }
        return $this;
    }
}

// Modification de Product pour la relation inverse
class Product
{
    // ... propriétés existantes ...

    #[MtRelation(
        'ManyToMany',
        targetEntity: Category::class,
        inversedBy: 'products',
        joinTable: 'product_categories',
        joinColumns: ['product_id' => 'id'],
        inverseJoinColumns: ['category_id' => 'id']
    )]
    private array $categories = [];

    /**
     * @return array<Category>
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    public function addCategory(Category $category): self
    {
        if (!in_array($category, $this->categories, true)) {
            $this->categories[] = $category;
            $category->addProduct($this);
        }
        return $this;
    }
}
```

## Requêtes communes

### CRUD de base

```php
<?php

declare(strict_types=1);

use MulerTech\Database\ORM\EmEngine;
use App\Entity\User;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class UserService
{
    public function __construct(
        private readonly EmEngine $em
    ) {}

    /**
     * Créer un nouvel utilisateur
     */
    public function createUser(string $name, string $email): User
    {
        $user = new User($name, $email);
        
        $this->em->persist($user);
        $this->em->flush();
        
        return $user;
    }

    /**
     * Trouver un utilisateur par ID
     */
    public function findUserById(int $id): ?User
    {
        return $this->em->getRepository(User::class)->find($id);
    }

    /**
     * Trouver un utilisateur par email
     */
    public function findUserByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)
                       ->createQueryBuilder()
                       ->select('*')
                       ->where('email = ?')
                       ->setParameter(0, $email)
                       ->getQuery()
                       ->getSingleResult();
    }

    /**
     * Mettre à jour un utilisateur
     */
    public function updateUser(User $user, string $name = null, string $email = null): User
    {
        if ($name !== null) {
            $user->setName($name);
        }
        
        if ($email !== null) {
            $user->setEmail($email);
        }
        
        $this->em->flush();
        
        return $user;
    }

    /**
     * Supprimer un utilisateur
     */
    public function deleteUser(User $user): void
    {
        $this->em->remove($user);
        $this->em->flush();
    }

    /**
     * Lister tous les utilisateurs avec pagination
     *
     * @return array<User>
     */
    public function listUsers(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        
        return $this->em->getRepository(User::class)
                       ->createQueryBuilder()
                       ->select('*')
                       ->orderBy('created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->getQuery()
                       ->getResult();
    }
}
```

### Requêtes avec jointures

```php
<?php

declare(strict_types=1);

use MulerTech\Database\ORM\EmEngine;

/**
 * Service pour les requêtes complexes
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PostQueryService
{
    public function __construct(
        private readonly EmEngine $em
    ) {}

    /**
     * Trouver les posts avec leurs auteurs
     *
     * @return array<array<string, mixed>>
     */
    public function findPostsWithAuthors(): array
    {
        return $this->em->createQueryBuilder()
                       ->select('p.title, p.content, p.created_at, u.name as author_name, u.email as author_email')
                       ->from('posts', 'p')
                       ->innerJoin('users', 'u', 'u.id = p.user_id')
                       ->orderBy('p.created_at', 'DESC')
                       ->getQuery()
                       ->getArrayResult();
    }

    /**
     * Compter les posts par utilisateur
     *
     * @return array<array<string, mixed>>
     */
    public function countPostsByUser(): array
    {
        return $this->em->createQueryBuilder()
                       ->select('u.name, u.email, COUNT(p.id) as post_count')
                       ->from('users', 'u')
                       ->leftJoin('posts', 'p', 'p.user_id = u.id')
                       ->groupBy('u.id', 'u.name', 'u.email')
                       ->orderBy('post_count', 'DESC')
                       ->getQuery()
                       ->getArrayResult();
    }

    /**
     * Recherche de posts par mot-clé
     *
     * @return array<array<string, mixed>>
     */
    public function searchPosts(string $keyword): array
    {
        return $this->em->createQueryBuilder()
                       ->select('p.*, u.name as author_name')
                       ->from('posts', 'p')
                       ->innerJoin('users', 'u', 'u.id = p.user_id')
                       ->where('p.title LIKE ? OR p.content LIKE ?')
                       ->setParameter(0, "%{$keyword}%")
                       ->setParameter(1, "%{$keyword}%")
                       ->orderBy('p.created_at', 'DESC')
                       ->getQuery()
                       ->getArrayResult();
    }
}
```

## Transactions

### Transaction simple

```php
<?php

declare(strict_types=1);

use MulerTech\Database\ORM\EmEngine;
use App\Entity\User;
use App\Entity\Post;

/**
 * Service avec gestion de transactions
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class UserPostService
{
    public function __construct(
        private readonly EmEngine $em
    ) {}

    /**
     * Créer un utilisateur avec son premier post en une transaction
     */
    public function createUserWithPost(string $name, string $email, string $postTitle, string $postContent): User
    {
        try {
            $this->em->beginTransaction();

            // Créer l'utilisateur
            $user = new User($name, $email);
            $this->em->persist($user);
            $this->em->flush(); // Nécessaire pour obtenir l'ID

            // Créer le post
            $post = new Post($postTitle, $postContent, $user);
            $this->em->persist($post);

            $this->em->flush();
            $this->em->commit();

            return $user;

        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    /**
     * Transférer tous les posts d'un utilisateur à un autre
     */
    public function transferPosts(User $fromUser, User $toUser): void
    {
        try {
            $this->em->beginTransaction();

            $this->em->createQueryBuilder()
                     ->update('posts')
                     ->set('user_id', '?')
                     ->where('user_id = ?')
                     ->setParameter(0, $toUser->getId())
                     ->setParameter(1, $fromUser->getId())
                     ->getQuery()
                     ->execute();

            $this->em->commit();

        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }
}
```

## Cache et Performance

### Service avec cache

```php
<?php

declare(strict_types=1);

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Core\Cache\CacheInterface;

/**
 * Service utilisant le cache pour optimiser les performances
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class CachedUserService
{
    public function __construct(
        private readonly EmEngine $em,
        private readonly CacheInterface $cache
    ) {}

    /**
     * Obtenir un utilisateur avec cache
     */
    public function getUserById(int $id): ?User
    {
        $cacheKey = "user_{$id}";
        
        // Vérifier le cache
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Charger depuis la base de données
        $user = $this->em->getRepository(User::class)->find($id);
        
        if ($user) {
            // Mettre en cache pour 1 heure
            $this->cache->set($cacheKey, $user, 3600);
        }

        return $user;
    }

    /**
     * Invalider le cache d'un utilisateur
     */
    public function invalidateUserCache(int $userId): void
    {
        $this->cache->delete("user_{$userId}");
    }

    /**
     * Statistiques utilisateurs avec cache
     *
     * @return array<string, mixed>
     */
    public function getUserStats(): array
    {
        $cacheKey = 'user_stats';
        
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $stats = [
            'total_users' => $this->em->createQueryBuilder()
                                     ->select('COUNT(*)')
                                     ->from('users')
                                     ->getQuery()
                                     ->getSingleScalarResult(),
            
            'users_with_posts' => $this->em->createQueryBuilder()
                                          ->select('COUNT(DISTINCT u.id)')
                                          ->from('users', 'u')
                                          ->innerJoin('posts', 'p', 'p.user_id = u.id')
                                          ->getQuery()
                                          ->getSingleScalarResult(),
        ];

        // Cache pour 30 minutes
        $this->cache->set($cacheKey, $stats, 1800);

        return $stats;
    }
}
```

## Validation et Events

### Utilisation des événements

```php
<?php

declare(strict_types=1);

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Event\PrePersistEvent;
use MulerTech\Database\Event\PostPersistEvent;
use MulerTech\Database\Event\PreUpdateEvent;
use Psr\Log\LoggerInterface;

/**
 * Listener d'événements pour audit et validation
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class UserEventListener
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function onPrePersist(PrePersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof User) {
            // Validation avant persistence
            $this->validateUser($entity);
            
            // Log de création
            $this->logger->info('Creating new user', [
                'name' => $entity->getName(),
                'email' => $entity->getEmail()
            ]);
        }
    }

    public function onPostPersist(PostPersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof User) {
            // Actions après création
            $this->logger->info('User created successfully', [
                'user_id' => $entity->getId(),
                'name' => $entity->getName()
            ]);
            
            // Envoyer email de bienvenue (exemple)
            // $this->emailService->sendWelcomeEmail($entity);
        }
    }

    public function onPreUpdate(PreUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        $changeSet = $event->getChangeSet();
        
        if ($entity instanceof User) {
            $this->logger->info('User updated', [
                'user_id' => $entity->getId(),
                'changes' => $changeSet
            ]);
        }
    }

    /**
     * Validation personnalisée
     */
    private function validateUser(User $user): void
    {
        if (empty($user->getName())) {
            throw new \InvalidArgumentException('User name cannot be empty');
        }

        if (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }
    }
}
```

## Types personnalisés

### Type Money pour montants monétaires

```php
<?php

declare(strict_types=1);

namespace App\Types;

use MulerTech\Database\Mapping\Types\AbstractType;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * Type personnalisé pour gérer les montants monétaires
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MoneyType extends AbstractType
{
    public const NAME = 'money';

    public function getSQLDeclaration(array $fieldDeclaration): string
    {
        return 'DECIMAL(10,2)';
    }

    /**
     * @param mixed $value
     */
    public function convertToPHPValue($value): ?Money
    {
        if ($value === null) {
            return null;
        }

        return new Money((float) $value);
    }

    /**
     * @param mixed $value
     */
    public function convertToDatabaseValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return (string) $value->getAmount();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}

/**
 * Classe représentant un montant monétaire
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class Money
{
    public function __construct(
        private readonly float $amount
    ) {}

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function add(Money $other): Money
    {
        return new Money($this->amount + $other->amount);
    }

    public function subtract(Money $other): Money
    {
        return new Money($this->amount - $other->amount);
    }

    public function multiply(float $factor): Money
    {
        return new Money($this->amount * $factor);
    }

    public function format(string $currency = 'EUR'): string
    {
        return number_format($this->amount, 2, ',', ' ') . ' ' . $currency;
    }

    public function equals(Money $other): bool
    {
        return abs($this->amount - $other->amount) < 0.01;
    }
}

// Usage dans une entité
class Order
{
    #[MtColumn(columnType: MoneyType::class)]
    private Money $total;

    public function __construct(Money $total)
    {
        $this->total = $total;
    }

    public function getTotal(): Money
    {
        return $this->total;
    }
}
```

## Patterns avancés

### Repository Pattern avec interface

```php
<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\Entity\User;

/**
 * Interface pour le repository User
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    
    /**
     * @return array<User>
     */
    public function findActive(): array;
    
    /**
     * @return array<User>
     */
    public function findByNamePattern(string $pattern): array;
}

namespace App\Repository;

use App\Entity\User;
use App\Repository\Interface\UserRepositoryInterface;
use MulerTech\Database\ORM\Repository\EntityRepository;

/**
 * Repository personnalisé pour User
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class UserRepository extends EntityRepository implements UserRepositoryInterface
{
    protected function getEntityClass(): string
    {
        return User::class;
    }

    public function findById(int $id): ?User
    {
        return $this->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->createQueryBuilder()
                   ->select('*')
                   ->where('email = ?')
                   ->setParameter(0, $email)
                   ->getQuery()
                   ->getSingleResult();
    }

    /**
     * @return array<User>
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder()
                   ->select('*')
                   ->where('deleted_at IS NULL')
                   ->orderBy('created_at', 'DESC')
                   ->getQuery()
                   ->getResult();
    }

    /**
     * @return array<User>
     */
    public function findByNamePattern(string $pattern): array
    {
        return $this->createQueryBuilder()
                   ->select('*')
                   ->where('name LIKE ?')
                   ->setParameter(0, "%{$pattern}%")
                   ->orderBy('name', 'ASC')
                   ->getQuery()
                   ->getResult();
    }
}
```

### Service Layer Pattern

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\Interface\UserRepositoryInterface;
use MulerTech\Database\ORM\EmEngine;
use Psr\Log\LoggerInterface;

/**
 * Service métier pour la gestion des utilisateurs
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class UserManagementService
{
    public function __construct(
        private readonly EmEngine $em,
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Créer un nouvel utilisateur avec validation métier
     */
    public function createUser(string $name, string $email): User
    {
        // Validation métier
        $this->validateUserCreation($name, $email);

        // Vérifier l'unicité de l'email
        if ($this->userRepository->findByEmail($email)) {
            throw new \DomainException('Email already exists');
        }

        // Créer l'utilisateur
        $user = new User($name, $email);
        
        try {
            $this->em->persist($user);
            $this->em->flush();

            $this->logger->info('User created successfully', [
                'user_id' => $user->getId(),
                'email' => $email
            ]);

            return $user;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create user', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Activer/désactiver un utilisateur
     */
    public function toggleUserStatus(int $userId): User
    {
        $user = $this->userRepository->findById($userId);
        
        if (!$user) {
            throw new \DomainException('User not found');
        }

        // Logique métier pour le changement de statut
        // (exemple simplifié)
        
        $this->em->flush();

        $this->logger->info('User status toggled', [
            'user_id' => $userId
        ]);

        return $user;
    }

    /**
     * Validation métier
     */
    private function validateUserCreation(string $name, string $email): void
    {
        if (strlen($name) < 2) {
            throw new \DomainException('Name must be at least 2 characters long');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException('Invalid email format');
        }

        // Autres validations métier...
    }
}
```

---

Ces échantillons de code montrent les patterns et techniques courantes avec MulerTech Database ORM. Ils servent de référence rapide pour implémenter des fonctionnalités communes dans vos applications.
