# Query Builder

Complete guide to building complex database queries with MulerTech Database Query Builder.

## Overview

The Query Builder provides a fluent, expressive API for constructing SQL queries programmatically. It supports all major SQL operations while maintaining type safety and preventing SQL injection.

```php
use MulerTech\Database\Query\QueryBuilder;

$queryBuilder = new QueryBuilder($entityManager->getEmEngine());

$results = $queryBuilder
    ->select('name', 'email')
    ->from('users')
    ->where('active', '=', true)
    ->orderBy('name')
    ->getResult();
```

## Basic Queries

### SELECT Statements

```php
// Select all columns
$users = $queryBuilder
    ->select('*')
    ->from('users')
    ->getResult();

// Select specific columns
$users = $queryBuilder
    ->select('id', 'name', 'email')
    ->from('users')
    ->getResult();

// Select with aliases
$users = $queryBuilder
    ->select('u.name', 'u.email', 'p.title as post_title')
    ->from('users', 'u')
    ->join('posts', 'p', 'u.id = p.author_id')
    ->getResult();
```

### WHERE Clauses

```php
// Simple conditions
$activeUsers = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('active', '=', true)
    ->getResult();

// Multiple conditions (AND)
$results = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('active', '=', true)
    ->where('role', '=', 'admin')
    ->getResult();

// OR conditions
$results = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('role', '=', 'admin')
    ->orWhere('role', '=', 'moderator')
    ->getResult();

// Comparison operators
$users = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('age', '>=', 18)
    ->where('age', '<', 65)
    ->getResult();

// LIKE patterns
$users = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('name', 'LIKE', 'John%')
    ->getResult();

// IN clauses
$users = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('role', 'IN', ['admin', 'moderator', 'editor'])
    ->getResult();

// NULL checks
$users = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('deleted_at', 'IS', null)
    ->getResult();
```

### ORDER BY

```php
// Single column ordering
$users = $queryBuilder
    ->select('*')
    ->from('users')
    ->orderBy('name', 'ASC')
    ->getResult();

// Multiple column ordering
$users = $queryBuilder
    ->select('*')
    ->from('users')
    ->orderBy('role', 'ASC')
    ->orderBy('name', 'ASC')
    ->getResult();

// Default is ASC
$users = $queryBuilder
    ->select('*')
    ->from('users')
    ->orderBy('created_at') // ASC by default
    ->getResult();
```

### LIMIT and OFFSET

```php
// Limit results
$recentUsers = $queryBuilder
    ->select('*')
    ->from('users')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->getResult();

// Pagination with offset
$page2Users = $queryBuilder
    ->select('*')
    ->from('users')
    ->orderBy('name')
    ->limit(20)
    ->offset(20) // Skip first 20 records
    ->getResult();
```

## Advanced Queries

### JOIN Operations

```php
// INNER JOIN
$postsWithAuthors = $queryBuilder
    ->select('p.title', 'u.name as author_name')
    ->from('posts', 'p')
    ->join('users', 'u', 'p.author_id = u.id')
    ->getResult();

// LEFT JOIN
$usersWithPostCounts = $queryBuilder
    ->select('u.name', 'COUNT(p.id) as post_count')
    ->from('users', 'u')
    ->leftJoin('posts', 'p', 'u.id = p.author_id')
    ->groupBy('u.id', 'u.name')
    ->getResult();

// Multiple JOINs
$fullPostData = $queryBuilder
    ->select('p.title', 'u.name as author', 'c.name as category')
    ->from('posts', 'p')
    ->join('users', 'u', 'p.author_id = u.id')
    ->join('categories', 'c', 'p.category_id = c.id')
    ->where('p.published', '=', true)
    ->getResult();
```

### GROUP BY and HAVING

```php
// Group by with aggregation
$postCountsByAuthor = $queryBuilder
    ->select('u.name', 'COUNT(p.id) as post_count')
    ->from('users', 'u')
    ->leftJoin('posts', 'p', 'u.id = p.author_id')
    ->groupBy('u.id', 'u.name')
    ->orderBy('post_count', 'DESC')
    ->getResult();

// HAVING clause
$productiveAuthors = $queryBuilder
    ->select('u.name', 'COUNT(p.id) as post_count')
    ->from('users', 'u')
    ->join('posts', 'p', 'u.id = p.author_id')
    ->groupBy('u.id', 'u.name')
    ->having('COUNT(p.id)', '>', 5)
    ->orderBy('post_count', 'DESC')
    ->getResult();
```

### Subqueries

```php
// Subquery in WHERE clause
$activeAuthors = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('id', 'IN', function(QueryBuilder $subQuery) {
        return $subQuery
            ->select('DISTINCT author_id')
            ->from('posts')
            ->where('published', '=', true);
    })
    ->getResult();

// EXISTS subquery
$usersWithPosts = $queryBuilder
    ->select('*')
    ->from('users', 'u')
    ->where('EXISTS', function(QueryBuilder $subQuery) {
        return $subQuery
            ->select('1')
            ->from('posts', 'p')
            ->where('p.author_id', '=', 'u.id');
    })
    ->getResult();
```

## Repository Integration

### Using Query Builder in Repositories

```php
class ProductRepository extends EntityRepository
{
    public function findByPriceRange(float $minPrice, float $maxPrice): array
    {
        return $this->createQueryBuilder()
            ->where('price', '>=', $minPrice)
            ->where('price', '<=', $maxPrice)
            ->where('active', '=', true)
            ->orderBy('price', 'ASC')
            ->getResult();
    }

    public function findTopSellingProducts(int $limit = 10): array
    {
        return $this->createQueryBuilder()
            ->select('p.*', 'SUM(oi.quantity) as total_sold')
            ->from('products', 'p')
            ->join('order_items', 'oi', 'p.id = oi.product_id')
            ->join('orders', 'o', 'oi.order_id = o.id')
            ->where('o.status', '=', 'completed')
            ->groupBy('p.id')
            ->orderBy('total_sold', 'DESC')
            ->limit($limit)
            ->getResult();
    }

    public function searchProducts(string $query, array $categories = []): array
    {
        $qb = $this->createQueryBuilder()
            ->where('name', 'LIKE', "%{$query}%")
            ->orWhere('description', 'LIKE', "%{$query}%")
            ->where('active', '=', true);

        if (!empty($categories)) {
            $qb->where('category_id', 'IN', $categories);
        }

        return $qb->orderBy('name', 'ASC')->getResult();
    }
}
```

### Complex Business Queries

```php
class OrderRepository extends EntityRepository
{
    public function findMonthlyRevenue(int $year): array
    {
        return $this->createQueryBuilder()
            ->select(
                'MONTH(created_at) as month',
                'SUM(total) as revenue',
                'COUNT(*) as order_count'
            )
            ->where('YEAR(created_at)', '=', $year)
            ->where('status', '=', 'completed')
            ->groupBy('MONTH(created_at)')
            ->orderBy('month', 'ASC')
            ->getResult();
    }

    public function findCustomersWithHighValue(float $threshold): array
    {
        return $this->createQueryBuilder()
            ->select(
                'c.id',
                'c.name',
                'c.email',
                'SUM(o.total) as total_spent',
                'COUNT(o.id) as order_count'
            )
            ->from('customers', 'c')
            ->join('orders', 'o', 'c.id = o.customer_id')
            ->where('o.status', '=', 'completed')
            ->groupBy('c.id', 'c.name', 'c.email')
            ->having('SUM(o.total)', '>', $threshold)
            ->orderBy('total_spent', 'DESC')
            ->getResult();
    }

    public function findAbandonedCarts(int $hours = 24): array
    {
        $cutoff = new DateTime("-{$hours} hours");
        
        return $this->createQueryBuilder()
            ->select('c.*', 'u.name', 'u.email')
            ->from('carts', 'c')
            ->join('users', 'u', 'c.user_id = u.id')
            ->where('c.updated_at', '<', $cutoff)
            ->where('c.status', '=', 'active')
            ->orderBy('c.updated_at', 'ASC')
            ->getResult();
    }
}
```

## Query Optimization

### Index-Friendly Queries

```php
// Good - uses index on 'email' column
$user = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('email', '=', 'user@example.com')
    ->getSingleResult();

// Good - compound index on (status, created_at)
$recentOrders = $queryBuilder
    ->select('*')
    ->from('orders')
    ->where('status', '=', 'pending')
    ->where('created_at', '>', new DateTime('-1 week'))
    ->orderBy('created_at', 'DESC')
    ->getResult();
```

### Efficient Pagination

```php
class UserRepository extends EntityRepository
{
    public function findUsersPaginated(int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        
        return $this->createQueryBuilder()
            ->select('id', 'name', 'email', 'created_at') // Only needed columns
            ->where('active', '=', true)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->getResult();
    }

    public function countActiveUsers(): int
    {
        $result = $this->createQueryBuilder()
            ->select('COUNT(*) as count')
            ->where('active', '=', true)
            ->getSingleResult();
        
        return (int) $result['count'];
    }
}
```

### Avoiding N+1 Queries

```php
// Bad - N+1 query problem
$posts = $postRepository->findAll();
foreach ($posts as $post) {
    $author = $userRepository->find($post->getAuthorId()); // N queries
    echo $author->getName();
}

// Good - single query with JOIN
$postsWithAuthors = $queryBuilder
    ->select('p.*', 'u.name as author_name')
    ->from('posts', 'p')
    ->join('users', 'u', 'p.author_id = u.id')
    ->getResult();

foreach ($postsWithAuthors as $row) {
    echo $row['author_name']; // No additional queries
}
```

## Raw SQL Integration

### When to Use Raw SQL

Use raw SQL for complex queries that are difficult to express with Query Builder:

```php
class AnalyticsRepository extends EntityRepository
{
    public function getComplexAnalytics(): array
    {
        $sql = "
            WITH monthly_stats AS (
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as orders,
                    SUM(total) as revenue
                FROM orders 
                WHERE status = 'completed'
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ),
            growth_rates AS (
                SELECT 
                    month,
                    orders,
                    revenue,
                    LAG(revenue) OVER (ORDER BY month) as prev_revenue,
                    (revenue - LAG(revenue) OVER (ORDER BY month)) / 
                    LAG(revenue) OVER (ORDER BY month) * 100 as growth_rate
                FROM monthly_stats
            )
            SELECT * FROM growth_rates
            WHERE month >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 12 MONTH), '%Y-%m')
            ORDER BY month DESC
        ";

        return $this->getEntityManager()
            ->getEmEngine()
            ->executeQuery($sql)
            ->fetchAll();
    }
}
```

### Combining Query Builder with Raw SQL

```php
class ProductRepository extends EntityRepository
{
    public function findProductsWithCustomRanking(): array
    {
        return $this->createQueryBuilder()
            ->select(
                'p.*',
                'COALESCE(AVG(r.rating), 0) as avg_rating',
                'COUNT(r.id) as review_count',
                // Custom ranking formula
                '(COALESCE(AVG(r.rating), 0) * 0.7 + 
                  LOG(COUNT(r.id) + 1) * 0.3) as ranking_score'
            )
            ->from('products', 'p')
            ->leftJoin('reviews', 'r', 'p.id = r.product_id')
            ->where('p.active', '=', true)
            ->groupBy('p.id')
            ->orderBy('ranking_score', 'DESC')
            ->getResult();
    }
}
```

## Error Handling

### Query Validation

```php
class SafeQueryBuilder
{
    public function __construct(
        private readonly QueryBuilder $queryBuilder
    ) {}

    public function safeWhere(string $column, string $operator, mixed $value): self
    {
        // Validate column name to prevent injection
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new InvalidArgumentException('Invalid column name');
        }

        // Validate operator
        $allowedOperators = ['=', '!=', '<', '<=', '>', '>=', 'LIKE', 'IN', 'IS'];
        if (!in_array($operator, $allowedOperators)) {
            throw new InvalidArgumentException('Invalid operator');
        }

        $this->queryBuilder->where($column, $operator, $value);
        return $this;
    }
}
```

### Exception Handling

```php
class UserRepository extends EntityRepository
{
    public function findUsersSafely(array $criteria): array
    {
        try {
            return $this->createQueryBuilder()
                ->where('active', '=', true)
                ->getResult();
                
        } catch (DatabaseException $e) {
            // Log the error
            error_log('Database error in findUsersSafely: ' . $e->getMessage());
            
            // Return empty array or throw domain exception
            throw new UserRepositoryException('Failed to fetch users', 0, $e);
        }
    }
}
```

## Performance Monitoring

### Query Timing

```php
class InstrumentedQueryBuilder
{
    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private readonly LoggerInterface $logger
    ) {}

    public function getResult(): array
    {
        $startTime = microtime(true);
        
        try {
            $result = $this->queryBuilder->getResult();
            
            $executionTime = microtime(true) - $startTime;
            
            $this->logger->info('Query executed', [
                'sql' => $this->queryBuilder->getSQL(),
                'execution_time' => $executionTime,
                'result_count' => count($result)
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Query failed', [
                'sql' => $this->queryBuilder->getSQL(),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
}
```

## Best Practices

### 1. Use Parameter Binding

```php
// Good - automatic parameter binding
$users = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('email', '=', $userEmail) // Automatically escaped
    ->getResult();

// Avoid - string concatenation (SQL injection risk)
$sql = "SELECT * FROM users WHERE email = '{$userEmail}'";
```

### 2. Select Only Needed Columns

```php
// Good - specific columns
$users = $queryBuilder
    ->select('id', 'name', 'email')
    ->from('users')
    ->getResult();

// Avoid - unnecessary data transfer
$users = $queryBuilder
    ->select('*') // Includes large columns like 'bio', 'preferences'
    ->from('users')
    ->getResult();
```

### 3. Use Appropriate Indexes

```php
// Good - query uses index on (status, created_at)
$orders = $queryBuilder
    ->select('*')
    ->from('orders')
    ->where('status', '=', 'pending')      // First index column
    ->where('created_at', '>', $yesterday) // Second index column
    ->orderBy('created_at', 'DESC')
    ->getResult();
```

### 4. Limit Result Sets

```php
// Good - always limit potentially large results
$products = $queryBuilder
    ->select('*')
    ->from('products')
    ->where('category_id', '=', $categoryId)
    ->orderBy('name')
    ->limit(100) // Prevent memory issues
    ->getResult();
```

## Complete Example

```php
class BlogQueryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function getBlogOverview(int $authorId, int $limit = 10): array
    {
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());

        return $queryBuilder
            ->select(
                'p.id',
                'p.title',
                'p.excerpt',
                'p.created_at',
                'p.published',
                'COUNT(c.id) as comment_count',
                'AVG(c.rating) as avg_rating'
            )
            ->from('posts', 'p')
            ->leftJoin('comments', 'c', 'p.id = c.post_id AND c.approved = 1')
            ->where('p.author_id', '=', $authorId)
            ->where('p.deleted_at', 'IS', null)
            ->groupBy('p.id', 'p.title', 'p.excerpt', 'p.created_at', 'p.published')
            ->orderBy('p.created_at', 'DESC')
            ->limit($limit)
            ->getResult();
    }
}
```

## Next Steps

- [Raw Queries](raw-queries.md) - When and how to use raw SQL
- [Change Tracking](change-tracking.md) - Understand entity modifications
- [Events](events.md) - Handle query and data events
