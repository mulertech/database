# Repositories

Complete guide to using repositories for data access in MulerTech Database.

## Overview

Repositories provide a clean abstraction layer between your domain logic and data persistence. They encapsulate query logic and provide a consistent interface for accessing entities.

```php
// Basic repository usage
$userRepository = $entityManager->getRepository(User::class);
$user = $userRepository->find(1);
$users = $userRepository->findAll();
$activeUsers = $userRepository->findBy(['active' => true]);
```

## Default Repository Methods

### find()

Find a single entity by primary key.

```php
$user = $userRepository->find(1);

if ($user === null) {
    throw new UserNotFoundException('User not found');
}

echo $user->getName();
```

### findAll()

Retrieve all entities of this type.

```php
$allUsers = $userRepository->findAll();

foreach ($allUsers as $user) {
    echo $user->getName() . "\n";
}
```

**Note:** Use with caution on large tables. Consider pagination for better performance.

### findBy()

Find entities matching specific criteria.

```php
// Single criteria
$activeUsers = $userRepository->findBy(['active' => true]);

// Multiple criteria (AND condition)
$criteria = [
    'active' => true,
    'role' => 'admin'
];
$adminUsers = $userRepository->findBy($criteria);

// With ordering
$recentUsers = $userRepository->findBy(
    ['active' => true],
    ['createdAt' => 'DESC'],
    10 // limit
);
```

### findOneBy()

Find a single entity matching criteria.

```php
$user = $userRepository->findOneBy(['email' => 'john@example.com']);

if ($user === null) {
    throw new UserNotFoundException('User with this email not found');
}
```

### count()

Count entities matching criteria.

```php
$totalUsers = $userRepository->count();
$activeUsers = $userRepository->count(['active' => true]);

echo "Total users: {$totalUsers}, Active: {$activeUsers}";
```

## Custom Repositories

Create custom repositories to encapsulate domain-specific queries.

### Repository Interface

```php
interface UserRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email): ?User;
    public function findActiveUsers(): array;
    public function findByRole(string $role): array;
    public function findRecentUsers(int $days): array;
    public function searchByName(string $name): array;
}
```

### Repository Implementation

```php
class UserRepository extends EntityRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findActiveUsers(): array
    {
        return $this->findBy(['active' => true]);
    }

    public function findByRole(string $role): array
    {
        return $this->findBy(['role' => $role]);
    }

    public function findRecentUsers(int $days): array
    {
        $since = new DateTime("-{$days} days");
        
        return $this->createQueryBuilder()
            ->where('createdAt', '>=', $since)
            ->orderBy('createdAt', 'DESC')
            ->getResult();
    }

    public function searchByName(string $name): array
    {
        return $this->createQueryBuilder()
            ->where('name', 'LIKE', "%{$name}%")
            ->orderBy('name', 'ASC')
            ->getResult();
    }

    public function findWithPostCount(): array
    {
        return $this->createQueryBuilder()
            ->select('u.*', 'COUNT(p.id) as post_count')
            ->from('users', 'u')
            ->leftJoin('posts', 'p', 'u.id = p.author_id')
            ->groupBy('u.id')
            ->orderBy('post_count', 'DESC')
            ->getResult();
    }
}
```

### Registration

```php
// Register custom repository in entity
#[MtEntity(
    tableName: 'users',
    repositoryClass: UserRepository::class
)]
class User
{
    // Entity properties...
}

// Access custom methods
$userRepository = $entityManager->getRepository(User::class);
$activeUsers = $userRepository->findActiveUsers();
$user = $userRepository->findByEmail('test@example.com');
```

## Query Builder Integration

Repositories provide access to the Query Builder for complex queries.

### Basic Query Builder Usage

```php
class ProductRepository extends EntityRepository
{
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder()
            ->where('category', '=', $category)
            ->where('active', '=', true)
            ->orderBy('name', 'ASC')
            ->getResult();
    }

    public function findInPriceRange(float $minPrice, float $maxPrice): array
    {
        return $this->createQueryBuilder()
            ->where('price', '>=', $minPrice)
            ->where('price', '<=', $maxPrice)
            ->where('active', '=', true)
            ->orderBy('price', 'ASC')
            ->getResult();
    }

    public function findFeaturedProducts(int $limit = 10): array
    {
        return $this->createQueryBuilder()
            ->where('featured', '=', true)
            ->where('active', '=', true)
            ->orderBy('createdAt', 'DESC')
            ->limit($limit)
            ->getResult();
    }
}
```

### Advanced Queries with Joins

```php
class OrderRepository extends EntityRepository
{
    public function findOrdersWithCustomerInfo(): array
    {
        return $this->createQueryBuilder()
            ->select('o.*', 'c.name as customer_name', 'c.email as customer_email')
            ->from('orders', 'o')
            ->join('customers', 'c', 'o.customer_id = c.id')
            ->where('o.status', '!=', 'cancelled')
            ->orderBy('o.created_at', 'DESC')
            ->getResult();
    }

    public function findOrdersByDateRange(DateTime $startDate, DateTime $endDate): array
    {
        return $this->createQueryBuilder()
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->orderBy('created_at', 'DESC')
            ->getResult();
    }

    public function findTopCustomersByOrderValue(int $limit = 10): array
    {
        return $this->createQueryBuilder()
            ->select('c.id', 'c.name', 'SUM(o.total) as total_spent')
            ->from('customers', 'c')
            ->join('orders', 'o', 'c.id = o.customer_id')
            ->where('o.status', '=', 'completed')
            ->groupBy('c.id', 'c.name')
            ->orderBy('total_spent', 'DESC')
            ->limit($limit)
            ->getResult();
    }
}
```

## Repository Patterns

### Specification Pattern

Encapsulate complex query logic in specification objects.

```php
interface SpecificationInterface
{
    public function isSatisfiedBy(QueryBuilder $queryBuilder): QueryBuilder;
}

class ActiveUserSpecification implements SpecificationInterface
{
    public function isSatisfiedBy(QueryBuilder $queryBuilder): QueryBuilder
    {
        return $queryBuilder->where('active', '=', true);
    }
}

class RecentUserSpecification implements SpecificationInterface
{
    public function __construct(private readonly int $days) {}

    public function isSatisfiedBy(QueryBuilder $queryBuilder): QueryBuilder
    {
        $since = new DateTime("-{$this->days} days");
        return $queryBuilder->where('createdAt', '>=', $since);
    }
}

class UserRepository extends EntityRepository
{
    public function findBySpecification(SpecificationInterface $specification): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder = $specification->isSatisfiedBy($queryBuilder);
        
        return $queryBuilder->getResult();
    }

    public function findActiveRecentUsers(int $days): array
    {
        $queryBuilder = $this->createQueryBuilder();
        
        $activeSpec = new ActiveUserSpecification();
        $recentSpec = new RecentUserSpecification($days);
        
        $queryBuilder = $activeSpec->isSatisfiedBy($queryBuilder);
        $queryBuilder = $recentSpec->isSatisfiedBy($queryBuilder);
        
        return $queryBuilder->getResult();
    }
}
```

### Repository Factory

Create repositories dynamically based on entity type.

```php
class RepositoryFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function create(string $entityClass): EntityRepository
    {
        return $this->entityManager->getRepository($entityClass);
    }

    public function createUserRepository(): UserRepositoryInterface
    {
        return $this->entityManager->getRepository(User::class);
    }

    public function createProductRepository(): ProductRepositoryInterface
    {
        return $this->entityManager->getRepository(Product::class);
    }
}
```

## Pagination

Implement pagination for large result sets.

```php
class PaginatedResult
{
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $limit
    ) {}

    public function getTotalPages(): int
    {
        return (int) ceil($this->total / $this->limit);
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->getTotalPages();
    }

    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }
}

class UserRepository extends EntityRepository
{
    public function findPaginated(int $page = 1, int $limit = 20): PaginatedResult
    {
        $offset = ($page - 1) * $limit;
        
        // Get items for current page
        $items = $this->createQueryBuilder()
            ->orderBy('createdAt', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->getResult();
        
        // Get total count
        $total = $this->count();
        
        return new PaginatedResult($items, $total, $page, $limit);
    }

    public function findActiveUsersPaginated(int $page = 1, int $limit = 20): PaginatedResult
    {
        $offset = ($page - 1) * $limit;
        
        $items = $this->createQueryBuilder()
            ->where('active', '=', true)
            ->orderBy('createdAt', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->getResult();
        
        $total = $this->count(['active' => true]);
        
        return new PaginatedResult($items, $total, $page, $limit);
    }
}
```

## Caching in Repositories

Implement caching for frequently accessed data.

```php
class CachedUserRepository extends EntityRepository implements UserRepositoryInterface
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache
    ) {
        parent::__construct($entityManager);
    }

    public function findByEmail(string $email): ?User
    {
        $cacheKey = "user_email_{$email}";
        
        // Try cache first
        $user = $this->cache->get($cacheKey);
        if ($user !== null) {
            return $user;
        }
        
        // Load from database
        $user = $this->findOneBy(['email' => $email]);
        
        // Cache for 1 hour
        if ($user) {
            $this->cache->set($cacheKey, $user, 3600);
        }
        
        return $user;
    }

    public function findActiveUsers(): array
    {
        $cacheKey = 'active_users';
        
        $users = $this->cache->get($cacheKey);
        if ($users !== null) {
            return $users;
        }
        
        $users = $this->findBy(['active' => true]);
        $this->cache->set($cacheKey, $users, 1800); // 30 minutes
        
        return $users;
    }

    public function invalidateUserCache(User $user): void
    {
        $this->cache->delete("user_email_{$user->getEmail()}");
        $this->cache->delete('active_users');
    }
}
```

## Repository Testing

Test repositories with mock data or test database.

```php
class UserRepositoryTest extends PHPUnit\Framework\TestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepositoryInterface $userRepository;

    protected function setUp(): void
    {
        // Set up test database
        $this->entityManager = $this->createTestEntityManager();
        $this->userRepository = $this->entityManager->getRepository(User::class);
        
        // Create test data
        $this->createTestUsers();
    }

    public function testFindByEmail(): void
    {
        $user = $this->userRepository->findByEmail('test@example.com');
        
        $this->assertNotNull($user);
        $this->assertEquals('test@example.com', $user->getEmail());
    }

    public function testFindActiveUsers(): void
    {
        $activeUsers = $this->userRepository->findActiveUsers();
        
        $this->assertCount(2, $activeUsers);
        
        foreach ($activeUsers as $user) {
            $this->assertTrue($user->isActive());
        }
    }

    public function testFindRecentUsers(): void
    {
        $recentUsers = $this->userRepository->findRecentUsers(7);
        
        $this->assertCount(1, $recentUsers);
        
        $user = $recentUsers[0];
        $weekAgo = new DateTime('-7 days');
        $this->assertGreaterThan($weekAgo, $user->getCreatedAt());
    }

    private function createTestUsers(): void
    {
        // Create test users
        $user1 = new User();
        $user1->setName('John Doe');
        $user1->setEmail('test@example.com');
        $user1->setActive(true);
        $user1->setCreatedAt(new DateTime('-2 days'));
        
        $user2 = new User();
        $user2->setName('Jane Smith');
        $user2->setEmail('jane@example.com');
        $user2->setActive(true);
        $user2->setCreatedAt(new DateTime('-30 days'));
        
        $user3 = new User();
        $user3->setName('Bob Wilson');
        $user3->setEmail('bob@example.com');
        $user3->setActive(false);
        $user3->setCreatedAt(new DateTime('-5 days'));
        
        $this->entityManager->persist($user1);
        $this->entityManager->persist($user2);
        $this->entityManager->persist($user3);
        $this->entityManager->flush();
    }
}
```

## Best Practices

### 1. Single Responsibility

Keep repositories focused on data access for specific entities.

```php
// Good - focused on User entity
class UserRepository extends EntityRepository
{
    public function findByEmail(string $email): ?User {}
    public function findActiveUsers(): array {}
    public function searchUsers(string $query): array {}
}

// Avoid - mixed responsibilities
class UserAndPostRepository extends EntityRepository
{
    public function findUserByEmail(string $email): ?User {}
    public function findPostsByUser(User $user): array {}
}
```

### 2. Domain-Driven Design

Use domain language in repository methods.

```php
class OrderRepository extends EntityRepository
{
    // Good - domain language
    public function findPendingOrders(): array {}
    public function findOrdersReadyForShipping(): array {}
    public function findOverdueOrders(): array {}
    
    // Avoid - generic database language
    public function findByStatusAndDate(): array {}
}
```

### 3. Return Type Consistency

Be consistent with return types and null handling.

```php
class UserRepository extends EntityRepository
{
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findActiveUsers(): array
    {
        return $this->findBy(['active' => true]);
    }

    public function requireByEmail(string $email): User
    {
        $user = $this->findByEmail($email);
        if ($user === null) {
            throw new UserNotFoundException("User with email {$email} not found");
        }
        return $user;
    }
}
```

### 4. Interface Segregation

Create specific interfaces for different use cases.

```php
interface UserReadRepositoryInterface
{
    public function find(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findActiveUsers(): array;
}

interface UserWriteRepositoryInterface
{
    public function save(User $user): void;
    public function delete(User $user): void;
}

interface UserRepositoryInterface extends UserReadRepositoryInterface, UserWriteRepositoryInterface
{
    // Combined interface for full CRUD operations
}
```

## Complete Example

```php
class BlogPostRepository extends EntityRepository implements BlogPostRepositoryInterface
{
    public function findPublishedPosts(): array
    {
        return $this->findBy(['published' => true], ['createdAt' => 'DESC']);
    }

    public function findByAuthor(User $author): array
    {
        return $this->findBy(['authorId' => $author->getId()], ['createdAt' => 'DESC']);
    }

    public function findByTag(string $tag): array
    {
        return $this->createQueryBuilder()
            ->join('post_tags', 'pt', 'posts.id = pt.post_id')
            ->join('tags', 't', 'pt.tag_id = t.id')
            ->where('t.name', '=', $tag)
            ->where('posts.published', '=', true)
            ->orderBy('posts.createdAt', 'DESC')
            ->getResult();
    }

    public function findPopularPosts(int $limit = 10): array
    {
        return $this->createQueryBuilder()
            ->select('p.*', 'COUNT(c.id) as comment_count')
            ->from('posts', 'p')
            ->leftJoin('comments', 'c', 'p.id = c.post_id')
            ->where('p.published', '=', true)
            ->groupBy('p.id')
            ->orderBy('comment_count', 'DESC')
            ->limit($limit)
            ->getResult();
    }

    public function searchPosts(string $query): array
    {
        return $this->createQueryBuilder()
            ->where('title', 'LIKE', "%{$query}%")
            ->orWhere('content', 'LIKE', "%{$query}%")
            ->where('published', '=', true)
            ->orderBy('createdAt', 'DESC')
            ->getResult();
    }
}
```

## Next Steps

- [Query Builder](query-builder.md) - Master query construction
- [Change Tracking](change-tracking.md) - Understand entity changes
- [Events](events.md) - Handle repository events
