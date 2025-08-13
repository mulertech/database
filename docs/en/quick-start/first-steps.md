# First Steps

This guide will walk you through creating your first entity and performing basic CRUD operations.

## Creating Your First Entity

### 1. Define a User Entity

```php
<?php

declare(strict_types=1);

use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn};
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

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255)]
    private string $email;

    #[MtColumn(columnType: ColumnType::DATETIME)]
    private DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
```

## Basic CRUD Operations

### Create (Insert)

```php
// Create a new user
$user = new User();
$user->setName('John Doe');
$user->setEmail('john.doe@example.com');

// Persist and save to database
$entityManager->persist($user);
$entityManager->flush();

echo "User created with ID: " . $user->getId();
```

### Read (Select)

```php
// Find by ID
$user = $entityManager->getRepository(User::class)->find(1);

// Find all users
$users = $entityManager->getRepository(User::class)->findAll();

// Find by criteria
$users = $entityManager->getRepository(User::class)->findBy(['name' => 'John']);

// Find one by criteria
$user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'john.doe@example.com']);
```

### Update

```php
// Fetch user
$user = $entityManager->getRepository(User::class)->find(1);

if ($user) {
    // Modify properties
    $user->setEmail('john.updated@example.com');
    
    // Changes are automatically tracked
    $entityManager->flush();
    
    echo "User updated successfully!";
}
```

### Delete

```php
// Fetch user
$user = $entityManager->getRepository(User::class)->find(1);

if ($user) {
    // Mark for removal
    $entityManager->remove($user);
    
    // Execute deletion
    $entityManager->flush();
    
    echo "User deleted successfully!";
}
```

## Working with Collections

### Finding Multiple Records

```php
$repository = $entityManager->getRepository(User::class);

// Get all active users
$activeUsers = $repository->findBy(['status' => 'active']);

// Get users created in the last 30 days
$recentUsers = $repository->createQueryBuilder()
    ->where('createdAt', '>', new DateTime('-30 days'))
    ->getResult();
```

### Counting Records

```php
$userCount = $entityManager->getRepository(User::class)->count();
echo "Total users: " . $userCount;
```

## Error Handling

```php
try {
    $user = new User();
    $user->setName('Jane Doe');
    $user->setEmail('jane@example.com');
    
    $entityManager->persist($user);
    $entityManager->flush();
    
} catch (Exception $e) {
    echo "Error saving user: " . $e->getMessage();
}
```

## Best Practices

1. **Always use transactions** for multiple operations
2. **Call flush() only when needed** to optimize performance
3. **Handle exceptions** properly in production code
4. **Use repositories** for complex queries
5. **Validate data** before persisting

## Next Steps

- [Basic Examples](basic-examples.md) - More practical examples
- [Entity Mapping](../entity-mapping/attributes.md) - Advanced entity configuration
- [Repositories](../data-access/repositories.md) - Custom repository methods
