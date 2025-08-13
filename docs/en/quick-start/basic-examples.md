# Basic Examples

Common use cases and practical examples for MulerTech Database.

## Blog Application Example

### Entity Definitions

```php
<?php

declare(strict_types=1);

// Post entity
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

    #[MtColumn(columnType: ColumnType::BOOLEAN)]
    private bool $published = false;

    #[MtColumn(columnType: ColumnType::INT)]
    private int $authorId;

    #[MtColumn(columnType: ColumnType::DATETIME)]
    private DateTime $createdAt;

    // Constructor and methods...
}

// Comment entity
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
    private int $postId;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $authorName;

    #[MtColumn(columnType: ColumnType::DATETIME)]
    private DateTime $createdAt;

    // Constructor and methods...
}
```

### Creating Blog Posts

```php
// Create a new blog post
$post = new Post();
$post->setTitle('My First Blog Post');
$post->setContent('This is the content of my first blog post...');
$post->setAuthorId(1);
$post->setPublished(true);

$entityManager->persist($post);
$entityManager->flush();

echo "Post created with ID: " . $post->getId();
```

### Querying Posts

```php
$postRepository = $entityManager->getRepository(Post::class);

// Get all published posts
$publishedPosts = $postRepository->findBy(['published' => true]);

// Get posts by author
$authorPosts = $postRepository->findBy(['authorId' => 1]);

// Get recent posts (using Query Builder)
$recentPosts = $postRepository->createQueryBuilder()
    ->where('published', '=', true)
    ->orderBy('createdAt', 'DESC')
    ->limit(10)
    ->getResult();
```

### Adding Comments

```php
// Add a comment to a post
$comment = new Comment();
$comment->setContent('Great post!');
$comment->setPostId($post->getId());
$comment->setAuthorName('Reader Name');

$entityManager->persist($comment);
$entityManager->flush();
```

## E-commerce Example

### Product Entity

```php
#[MtEntity(tableName: 'products')]
class Product
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255)]
    private string $name;

    #[MtColumn(columnType: ColumnType::DECIMAL, precision: 10, scale: 2)]
    private float $price;

    #[MtColumn(columnType: ColumnType::INT)]
    private int $stock;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 50)]
    private string $category;

    #[MtColumn(columnType: ColumnType::BOOLEAN)]
    private bool $active = true;

    // Constructor and methods...
}
```

### Product Management

```php
// Create a product
$product = new Product();
$product->setName('Laptop Computer');
$product->setPrice(999.99);
$product->setStock(50);
$product->setCategory('Electronics');

$entityManager->persist($product);
$entityManager->flush();

// Update stock after sale
$product = $entityManager->getRepository(Product::class)->find(1);
$product->setStock($product->getStock() - 1);
$entityManager->flush();

// Find products by category
$electronics = $entityManager->getRepository(Product::class)
    ->findBy(['category' => 'Electronics', 'active' => true]);
```

## User Management System

### User with Profile

```php
#[MtEntity(tableName: 'user_profiles')]
class UserProfile
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::INT)]
    private int $userId;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 50)]
    private ?string $firstName = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 50)]
    private ?string $lastName = null;

    #[MtColumn(columnType: ColumnType::DATE)]
    private ?DateTime $birthDate = null;

    // Constructor and methods...
}
```

### Creating User with Profile

```php
// Create user
$user = new User();
$user->setName('john_doe');
$user->setEmail('john@example.com');

$entityManager->persist($user);
$entityManager->flush(); // This assigns the ID

// Create profile
$profile = new UserProfile();
$profile->setUserId($user->getId());
$profile->setFirstName('John');
$profile->setLastName('Doe');
$profile->setBirthDate(new DateTime('1990-01-01'));

$entityManager->persist($profile);
$entityManager->flush();
```

## Advanced Queries

### Complex Search

```php
$queryBuilder = new QueryBuilder($entityManager->getEmEngine());

// Search posts with author information
$results = $queryBuilder
    ->select('p.title', 'p.content', 'u.name as author')
    ->from('posts', 'p')
    ->join('users', 'u', 'p.authorId = u.id')
    ->where('p.published', '=', true)
    ->where('p.title', 'LIKE', '%database%')
    ->orderBy('p.createdAt', 'DESC')
    ->getResult();
```

### Aggregated Data

```php
// Count posts by author
$postCounts = $queryBuilder
    ->select('u.name', 'COUNT(p.id) as post_count')
    ->from('users', 'u')
    ->leftJoin('posts', 'p', 'u.id = p.authorId')
    ->groupBy('u.id', 'u.name')
    ->having('COUNT(p.id)', '>', 0)
    ->getResult();
```

## Transaction Example

```php
try {
    $entityManager->beginTransaction();
    
    // Create user
    $user = new User();
    $user->setName('new_user');
    $user->setEmail('new@example.com');
    $entityManager->persist($user);
    
    // Create profile
    $profile = new UserProfile();
    $profile->setUserId($user->getId());
    $profile->setFirstName('New');
    $profile->setLastName('User');
    $entityManager->persist($profile);
    
    // Commit all changes
    $entityManager->commit();
    $entityManager->flush();
    
    echo "User and profile created successfully!";
    
} catch (Exception $e) {
    $entityManager->rollback();
    echo "Error: " . $e->getMessage();
}
```

## Best Practices from Examples

1. **Use meaningful entity names** and table names
2. **Set appropriate column lengths** for VARCHAR fields
3. **Use proper data types** (DECIMAL for money, DATETIME for timestamps)
4. **Handle foreign keys** properly with integer fields
5. **Use transactions** for related operations
6. **Validate data** before persistence
7. **Use Query Builder** for complex queries

## Next Steps

- [Entity Mapping](../entity-mapping/attributes.md) - Learn about advanced mapping features
- [Relationships](../entity-mapping/relationships.md) - Set up entity relationships
- [Query Builder](../data-access/query-builder.md) - Master query construction
