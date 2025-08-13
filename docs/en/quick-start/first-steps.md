# First Steps

🌍 **Languages:** [🇫🇷 Français](../../fr/quick-start/first-steps.md) | [🇬🇧 English](first-steps.md)

---

## 📋 Table of Contents

- [Project Initialization](#project-initialization)
- [Your First Entity](#your-first-entity)
- [Basic Configuration](#basic-configuration)
- [Basic CRUD Operations](#basic-crud-operations)
- [Your First Repository](#your-first-repository)
- [Relationship Management](#relationship-management)
- [Verification and Tests](#verification-and-tests)

---

## Project Initialization

### 🚀 Minimal Setup

Create your main `bootstrap.php` file:

```php
<?php
require_once 'vendor/autoload.php';

use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Mapping\MetadataRegistry;

// Configuration
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'my_app',
    'username' => 'user',
    'password' => 'password',
    'charset' => 'utf8mb4',
    'driver' => 'mysql'
];

// Initialization
$pdm = new PhpDatabaseManager($config);
$metadataRegistry = new MetadataRegistry();
$entityManager = new EntityManager($pdm, $metadataRegistry);

// Connection test
try {
    $result = $pdm->executeQuery("SELECT 1");
    echo "✅ Connection established successfully!\\n";
} catch (Exception $e) {
    echo "❌ Connection error: " . $e->getMessage() . "\\n";
    exit(1);
}
```

### 🗂️ Recommended Project Structure

```
my-project/
├── src/
│   ├── Entity/          # Your entities
│   ├── Repository/      # Custom repositories
│   └── Service/         # Business services
├── config/
│   └── database.php     # Configuration
├── migrations/          # Migration files
├── tests/              # Unit tests
├── bootstrap.php       # Initialization
└── composer.json
```

---

## Your First Entity

### 👤 Complete User Entity

Create `src/Entity/User.php`:

```php
<?php

namespace App\Entity;

use DateTime;
use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey};

#[MtEntity(tableName: 'users')]
class User
{
    #[MtColumn(
        columnType: ColumnType::INT,
        isUnsigned: true,
        isNullable: false,
        extra: 'auto_increment',
        columnKey: ColumnKey::PRIMARY_KEY
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
        length: 255,
        isNullable: false,
        columnKey: ColumnKey::UNIQUE
    )]
    private string $email;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 255,
        isNullable: true
    )]
    private ?string $password = null;

    #[MtColumn(
        columnType: ColumnType::DATETIME,
        isNullable: false
    )]
    private DateTime $createdAt;

    #[MtColumn(
        columnType: ColumnType::DATETIME,
        isNullable: true
    )]
    private ?DateTime $updatedAt = null;

    #[MtColumn(
        columnType: ColumnType::TINYINT,
        isUnsigned: true,
        isNullable: false,
        columnDefault: '1'
    )]
    private int $isActive = 1;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function isActive(): bool
    {
        return $this->isActive === 1;
    }

    // Setters
    public function setName(string $name): self
    {
        $this->name = $name;
        $this->touch();
        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        $this->touch();
        return $this;
    }

    public function setPassword(string $password): self
    {
        $this->password = password_hash($password, PASSWORD_DEFAULT);
        $this->touch();
        return $this;
    }

    public function setActive(bool $isActive): self
    {
        $this->isActive = $isActive ? 1 : 0;
        $this->touch();
        return $this;
    }

    // Utility methods
    private function touch(): void
    {
        $this->updatedAt = new DateTime();
    }

    public function verifyPassword(string $password): bool
    {
        return $this->password && password_verify($password, $this->password);
    }
}
```

### 📝 Entity Key Points

1. **Namespace**: Clear code organization
2. **#[MtEntity] Attributes**: Defines the table
3. **#[MtColumn] Attributes**: Configures each column
4. **Strict types**: PHP 8.4+ with return types
5. **Fluent methods**: Setters return `$this`
6. **Business logic**: Data validation and transformation

---

## Basic Configuration

### 🔧 Entity Registration

```php
<?php
// In bootstrap.php, after initialization

// Register your entities in MetadataRegistry
$metadataRegistry->registerEntity(App\Entity\User::class);

// Or automatic folder registration
$metadataRegistry->autoRegisterEntities(__DIR__ . '/src/Entity');
```

### 🗄️ Table Creation

You can create the table manually or use the migration system:

```sql
-- Manual creation (for quick tests)
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1
);
```

---

## Basic CRUD Operations

### 🆕 Create - Create a User

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

try {
    // Create a new user
    $user = new User();
    $user->setName('John Doe')
         ->setEmail('john.doe@example.com')
         ->setPassword('password123')
         ->setActive(true);

    // Persist to database
    $entityManager->persist($user);
    $entityManager->flush();

    echo "✅ User created with ID: " . $user->getId() . "\\n";
    echo "📧 Email: " . $user->getEmail() . "\\n";
    echo "📅 Created on: " . $user->getCreatedAt()->format('Y-m-d H:i:s') . "\\n";

} catch (Exception $e) {
    echo "❌ Error during creation: " . $e->getMessage() . "\\n";
}
```

### 🔍 Read - Read Users

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

try {
    // Get by ID
    $user = $entityManager->find(User::class, 1);
    if ($user) {
        echo "👤 User found: " . $user->getName() . "\\n";
    } else {
        echo "❌ User not found\\n";
    }

    // Get all users
    $users = $entityManager->getRepository(User::class)->findAll();
    echo "📊 Total users: " . count($users) . "\\n";

    // Search by criteria
    $activeUsers = $entityManager->getRepository(User::class)->findBy([
        'isActive' => 1
    ]);
    echo "✅ Active users: " . count($activeUsers) . "\\n";

    // Search by email
    $userByEmail = $entityManager->getRepository(User::class)->findOneBy([
        'email' => 'john.doe@example.com'
    ]);
    
    if ($userByEmail) {
        echo "📧 User found by email: " . $userByEmail->getName() . "\\n";
    }

} catch (Exception $e) {
    echo "❌ Error during read: " . $e->getMessage() . "\\n";
}
```

### ✏️ Update - Modify a User

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

try {
    // Get the user
    $user = $entityManager->find(User::class, 1);
    
    if ($user) {
        // Modify data
        $user->setName('John Smith')
             ->setEmail('john.smith@example.com');
        
        // EntityManager automatically detects changes
        $entityManager->flush();
        
        echo "✅ User updated\\n";
        echo "📝 New name: " . $user->getName() . "\\n";
        echo "🕒 Updated on: " . $user->getUpdatedAt()->format('Y-m-d H:i:s') . "\\n";
    } else {
        echo "❌ User not found\\n";
    }

} catch (Exception $e) {
    echo "❌ Error during update: " . $e->getMessage() . "\\n";
}
```

### 🗑️ Delete - Delete a User

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

try {
    // Get the user
    $user = $entityManager->find(User::class, 1);
    
    if ($user) {
        $userName = $user->getName();
        
        // Delete
        $entityManager->remove($user);
        $entityManager->flush();
        
        echo "✅ User '$userName' deleted\\n";
    } else {
        echo "❌ User not found\\n";
    }

} catch (Exception $e) {
    echo "❌ Error during deletion: " . $e->getMessage() . "\\n";
}
```

---

## Your First Repository

### 🗂️ Custom Repository

Create `src/Repository/UserRepository.php`:

```php
<?php

namespace App\Repository;

use App\Entity\User;
use MulerTech\Database\ORM\EntityRepository;
use MulerTech\Database\Query\Builder\QueryBuilder;

class UserRepository extends EntityRepository
{
    /**
     * Find active users
     */
    public function findActiveUsers(): array
    {
        return $this->findBy(['isActive' => 1]);
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Count active users
     */
    public function countActiveUsers(): int
    {
        $queryBuilder = new QueryBuilder($this->getEntityManager()->getEmEngine());
        
        $result = $queryBuilder
            ->select('COUNT(*) as total')
            ->from('users', 'u')
            ->where('u.is_active', '=', 1)
            ->getResult();
            
        return (int)$result[0]['total'];
    }

    /**
     * Search by name (LIKE)
     */
    public function searchByName(string $name): array
    {
        $queryBuilder = new QueryBuilder($this->getEntityManager()->getEmEngine());
        
        $results = $queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->where('u.name', 'LIKE', "%$name%")
            ->orderBy('u.name', 'ASC')
            ->getResult();
            
        return $this->hydrateResults($results);
    }

    /**
     * Recently created users
     */
    public function findRecentUsers(int $days = 7): array
    {
        $queryBuilder = new QueryBuilder($this->getEntityManager()->getEmEngine());
        
        $results = $queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->where('u.created_at', '>=', date('Y-m-d H:i:s', strtotime("-$days days")))
            ->orderBy('u.created_at', 'DESC')
            ->getResult();
            
        return $this->hydrateResults($results);
    }

    /**
     * Hydrate raw results to entities
     */
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

### 🎯 Repository Usage

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;
use App\Repository\UserRepository;

// Update the entity to use custom repository
// In User.php, modify the attribute:
// #[MtEntity(tableName: 'users', repository: UserRepository::class)]

try {
    /** @var UserRepository $userRepo */
    $userRepo = $entityManager->getRepository(User::class);
    
    // Use custom methods
    $activeUsers = $userRepo->findActiveUsers();
    echo "👥 Active users: " . count($activeUsers) . "\\n";
    
    $totalActive = $userRepo->countActiveUsers();
    echo "📊 Total active: $totalActive\\n";
    
    $user = $userRepo->findByEmail('john.smith@example.com');
    if ($user) {
        echo "📧 Found: " . $user->getName() . "\\n";
    }
    
    $recentUsers = $userRepo->findRecentUsers(30);
    echo "🆕 Recent users (30d): " . count($recentUsers) . "\\n";
    
    $searchResults = $userRepo->searchByName('John');
    echo "🔍 Search 'John': " . count($searchResults) . "\\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\\n";
}
```

---

## Relationship Management

### 📝 Simple Post Entity

Create `src/Entity/Post.php`:

```php
<?php

namespace App\Entity;

use DateTime;
use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn, MtFk, MtManyToOne};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey, FkRule};

#[MtEntity(tableName: 'posts')]
class Post
{
    #[MtColumn(
        columnType: ColumnType::INT,
        isUnsigned: true,
        isNullable: false,
        extra: 'auto_increment',
        columnKey: ColumnKey::PRIMARY_KEY
    )]
    private ?int $id = null;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 255,
        isNullable: false
    )]
    private string $title;

    #[MtColumn(
        columnType: ColumnType::TEXT,
        isNullable: false
    )]
    private string $content;

    #[MtColumn(
        columnType: ColumnType::INT,
        isUnsigned: true,
        isNullable: false
    )]
    #[MtFk(
        referencedTable: 'users',
        referencedColumn: 'id',
        onDelete: FkRule::CASCADE,
        onUpdate: FkRule::CASCADE
    )]
    private int $userId;

    #[MtManyToOne(targetEntity: User::class, joinColumn: 'userId')]
    private ?User $user = null;

    #[MtColumn(
        columnType: ColumnType::DATETIME,
        isNullable: false
    )]
    private DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getContent(): string { return $this->content; }
    public function getUserId(): int { return $this->userId; }
    public function getUser(): ?User { return $this->user; }
    public function getCreatedAt(): DateTime { return $this->createdAt; }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        $this->userId = $user->getId();
        return $this;
    }
}
```

### 🔗 Using Relationships

```php
<?php
require_once 'bootstrap.php';

use App\Entity\{User, Post};

try {
    // Get a user
    $user = $entityManager->find(User::class, 1);
    
    if ($user) {
        // Create a post
        $post = new Post();
        $post->setTitle('My first post')
             ->setContent('This is the content of my first post...')
             ->setUser($user);
        
        $entityManager->persist($post);
        $entityManager->flush();
        
        echo "✅ Post created with ID: " . $post->getId() . "\\n";
        echo "👤 Author: " . $post->getUser()->getName() . "\\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\\n";
}
```

---

## Verification and Tests

### 🧪 Complete Test Script

Create `test-first-steps.php`:

```php
<?php
require_once 'bootstrap.php';

use App\Entity\{User, Post};

echo "🚀 First steps test\\n";
echo "========================\\n\\n";

try {
    // 1. Create a user
    echo "1️⃣ Creating a user...\\n";
    $user = new User();
    $user->setName('Alice Test')
         ->setEmail('alice.test@example.com')
         ->setPassword('test123');
    
    $entityManager->persist($user);
    $entityManager->flush();
    echo "   ✅ User created (ID: " . $user->getId() . ")\\n\\n";

    // 2. Modify the user
    echo "2️⃣ Modifying user...\\n";
    $user->setName('Alice Modified');
    $entityManager->flush();
    echo "   ✅ Name modified: " . $user->getName() . "\\n\\n";

    // 3. Create a post
    echo "3️⃣ Creating a post...\\n";
    $post = new Post();
    $post->setTitle('Test post')
         ->setContent('Content of the test post')
         ->setUser($user);
    
    $entityManager->persist($post);
    $entityManager->flush();
    echo "   ✅ Post created (ID: " . $post->getId() . ")\\n\\n";

    // 4. Searches
    echo "4️⃣ Search tests...\\n";
    $foundUser = $entityManager->find(User::class, $user->getId());
    echo "   ✅ User found by ID: " . $foundUser->getName() . "\\n";
    
    $allUsers = $entityManager->getRepository(User::class)->findAll();
    echo "   ✅ Total users: " . count($allUsers) . "\\n";
    
    $userByEmail = $entityManager->getRepository(User::class)->findOneBy([
        'email' => 'alice.test@example.com'
    ]);
    echo "   ✅ User found by email: " . $userByEmail->getName() . "\\n\\n";

    // 5. Cleanup (optional)
    echo "5️⃣ Cleanup...\\n";
    $entityManager->remove($post);
    $entityManager->remove($user);
    $entityManager->flush();
    echo "   ✅ Test data deleted\\n\\n";

    echo "🎉 All tests passed successfully!\\n";

} catch (Exception $e) {
    echo "❌ Error during tests: " . $e->getMessage() . "\\n";
    echo "📍 Trace: " . $e->getTraceAsString() . "\\n";
}
```

Run the test:
```bash
php test-first-steps.php
```

---

## ➡️ Next Steps

Congratulations! You now master the basics. Continue with:

1. 🎯 [Basic Examples](basic-examples.md) - More complex use cases
2. 🏗️ [Architecture](../core-concepts/architecture.md) - Understand the architecture
3. 🎨 [Mapping Attributes](../entity-mapping/attributes.md) - Advanced mapping
4. 🔧 [Query Builder](../query-builder/basic-queries.md) - Custom queries

---

## 🔗 Useful Links

- 🏠 [Back to README](../../README.md)
- ⬅️ [Installation](installation.md)
- ➡️ [Basic Examples](basic-examples.md)
- 📖 [Complete Documentation](../README.md)