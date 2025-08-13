# Entity Manager

🌍 **Languages:** [🇫🇷 Français](../../fr/orm/entity-manager.md) | [🇬🇧 English](entity-manager.md)

---

## 📋 Table of Contents

- [Overview](#overview)
- [Basic Configuration](#basic-configuration)
- [CRUD Operations](#crud-operations)
- [Lifecycle Management](#lifecycle-management)
- [Entity States](#entity-states)
- [Relations Management](#relations-management)
- [Transactions](#transactions)
- [Performance and Optimization](#performance-and-optimization)
- [Events](#events)
- [Complete API](#complete-api)

---

## Overview

The **EntityManager** is the core of MulerTech Database. It manages entity lifecycle, change tracking, and synchronization with the database.

### 🎯 Main Responsibilities

- **Persistence**: Save entities to database
- **Hydration**: Transform SQL data into PHP objects
- **Change Tracking**: Automatically detect modifications
- **Relations**: Manage associations between entities
- **Transactions**: Ensure data consistency
- **Cache**: Optimize performance

### 🏗️ Architecture

```php
<?php

use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\ORM\Engine\EmEngine;

// EntityManager uses EmEngine for basic operations
$entityManager = new EntityManager($connection, $config);
$emEngine = $entityManager->getEmEngine();
```

---

## Basic Configuration

### 🔧 Initialization

```php
<?php

use MulerTech\Database\Connection\DatabaseConnection;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Config\Configuration;

// Database configuration
$config = new Configuration([
    'host' => 'localhost',
    'database' => 'my_database',
    'username' => 'user',
    'password' => 'password',
    'driver' => 'mysql',
    'charset' => 'utf8mb4'
]);

// Connection
$connection = new DatabaseConnection($config);

// EntityManager
$entityManager = new EntityManager($connection, $config);
```

### 🗂️ Entity Configuration

```php
<?php

// Register entities
$entityManager->addEntityPath('App\\Entity\\');
$entityManager->addEntityClass(User::class);
$entityManager->addEntityClass(Post::class);

// Auto-scan directory
$entityManager->scanEntitiesDirectory('/path/to/entities/');
```

---

## CRUD Operations

### 💾 Create - Creating an Entity

```php
<?php

use App\Entity\User;

// Create new entity
$user = new User();
$user->setName('John Doe')
     ->setEmail('john@example.com');

// Mark for persistence
$entityManager->persist($user);

// Save to database
$entityManager->flush();

echo "User created with ID: " . $user->getId();
```

### 🔍 Read - Reading Entities

```php
<?php

// Find by ID
$user = $entityManager->find(User::class, 1);

// Find single result
$user = $entityManager->findOneBy(User::class, ['email' => 'john@example.com']);

// Find all results
$users = $entityManager->findAll(User::class);

// Find with criteria
$activeUsers = $entityManager->findBy(User::class, [
    'status' => 'active',
    'verified' => true
]);

// Find with sorting and limit
$recentUsers = $entityManager->findBy(User::class, [], [
    'createdAt' => 'DESC'
], 10);
```

### ✏️ Update - Modifying an Entity

```php
<?php

// Retrieve entity
$user = $entityManager->find(User::class, 1);

// Modify properties
$user->setName('John Smith');
$user->setUpdatedAt(new DateTime());

// Save automatically (change tracking)
$entityManager->flush();

echo "User updated";
```

### 🗑️ Delete - Removing an Entity

```php
<?php

// Retrieve entity
$user = $entityManager->find(User::class, 1);

// Mark for removal
$entityManager->remove($user);

// Execute deletion
$entityManager->flush();

echo "User deleted";
```

---

## Lifecycle Management

### 📊 Entity States

```php
<?php

use MulerTech\Database\ORM\EntityState;

$user = new User();
echo $entityManager->getEntityState($user); // EntityState::NEW

$entityManager->persist($user);
echo $entityManager->getEntityState($user); // EntityState::MANAGED

$entityManager->flush();
echo $entityManager->getEntityState($user); // EntityState::MANAGED

$entityManager->remove($user);
echo $entityManager->getEntityState($user); // EntityState::REMOVED

$entityManager->detach($user);
echo $entityManager->getEntityState($user); // EntityState::DETACHED
```

### 🔄 Lifecycle Operations

```php
<?php

$user = $entityManager->find(User::class, 1);

// Detach entity (stop tracking changes)
$entityManager->detach($user);

// Reattach entity
$entityManager->merge($user);

// Refresh entity from database
$entityManager->refresh($user);

// Clear entity manager
$entityManager->clear();

// Check if entity is managed
if ($entityManager->contains($user)) {
    echo "Entity is managed by EntityManager";
}
```

---

## Entity States

### 🏷️ EntityState Enum

```php
<?php

enum EntityState: string
{
    case NEW = 'new';           // New entity, not yet persisted
    case MANAGED = 'managed';   // Entity managed by EntityManager
    case DETACHED = 'detached'; // Detached entity, no longer tracked
    case REMOVED = 'removed';   // Entity marked for removal
}
```

### 📈 Change Tracking

```php
<?php

$user = $entityManager->find(User::class, 1);

// Get changes
$changeSet = $entityManager->getChangeSet($user);

foreach ($changeSet as $property => $changes) {
    echo "Property '{$property}': {$changes['old']} → {$changes['new']}\n";
}

// Check if entity has changes
if ($entityManager->hasChanges($user)) {
    echo "Entity has unsaved changes";
}

// Cancel changes
$entityManager->refresh($user);
```

---

## Relations Management

### 🔗 OneToMany Relations

```php
<?php

use App\Entity\{User, Post};

$user = $entityManager->find(User::class, 1);

// Create new post
$post = new Post();
$post->setTitle('My Article')
     ->setContent('Article content')
     ->setAuthor($user);

$entityManager->persist($post);
$entityManager->flush();

// Get all user posts
$posts = $user->getPosts();
foreach ($posts as $post) {
    echo "- " . $post->getTitle() . "\n";
}
```

### 🔗 ManyToMany Relations

```php
<?php

use App\Entity\{Post, Tag};

$post = $entityManager->find(Post::class, 1);
$tag = $entityManager->find(Tag::class, 1);

// Add tag to post
$post->getTags()->add($tag);

// EntityManager automatically detects changes
$entityManager->flush();

// Remove tag
$post->getTags()->removeElement($tag);
$entityManager->flush();
```

### 🔄 Lazy vs Eager Loading

```php
<?php

// Lazy loading (default)
$user = $entityManager->find(User::class, 1);
$posts = $user->getPosts(); // SQL query on access

// Eager loading with join
$queryBuilder = $entityManager->createQueryBuilder();
$usersWithPosts = $queryBuilder
    ->select('u', 'p')
    ->from(User::class, 'u')
    ->leftJoin('u.posts', 'p')
    ->where('u.id = :id')
    ->setParameter('id', 1)
    ->getResult();
```

---

## Transactions

### 💾 Manual Transaction

```php
<?php

try {
    // Begin transaction
    $entityManager->beginTransaction();
    
    $user = new User();
    $user->setName('Transaction Test')
         ->setEmail('test@example.com');
    
    $entityManager->persist($user);
    
    $post = new Post();
    $post->setTitle('Transactional Post')
         ->setAuthor($user);
    
    $entityManager->persist($post);
    $entityManager->flush();
    
    // Commit transaction
    $entityManager->commit();
    
    echo "Transaction successful";
    
} catch (Exception $e) {
    // Rollback transaction
    $entityManager->rollback();
    echo "Error: " . $e->getMessage();
}
```

### 🎯 Transaction with Callback

```php
<?php

use MulerTech\Database\Exception\DatabaseException;

$result = $entityManager->transactional(function($em) {
    $user = new User();
    $user->setName('Callback Test')
         ->setEmail('callback@example.com');
    
    $em->persist($user);
    $em->flush();
    
    // Return value
    return $user->getId();
});

echo "User created with ID: " . $result;
```

---

## Performance and Optimization

### ⚡ Batch Processing

```php
<?php

// Batch processing to avoid memory issues
$batchSize = 50;
$i = 0;

foreach ($largeDataSet as $data) {
    $entity = new User();
    $entity->setName($data['name'])
           ->setEmail($data['email']);
    
    $entityManager->persist($entity);
    
    if (($i % $batchSize) === 0) {
        $entityManager->flush();
        $entityManager->clear(); // Free memory
    }
    
    $i++;
}

// Process last batch
$entityManager->flush();
$entityManager->clear();
```

### 🚀 Query Optimization

```php
<?php

// Avoid N+1 queries with joins
$queryBuilder = $entityManager->createQueryBuilder();

$posts = $queryBuilder
    ->select('p', 'u', 't')
    ->from(Post::class, 'p')
    ->join('p.author', 'u')
    ->leftJoin('p.tags', 't')
    ->where('p.published = :published')
    ->setParameter('published', true)
    ->getResult();

// All authors and tags loaded in single query
foreach ($posts as $post) {
    echo $post->getTitle() . ' by ' . $post->getAuthor()->getName() . "\n";
}
```

### 💾 Entity Cache

```php
<?php

// Enable cache
$entityManager->getConfiguration()->enableResultCache();

// Query with cache
$users = $entityManager->findBy(User::class, ['active' => true]);

// Clear cache
$entityManager->getConfiguration()->getCache()->clear();

// Cache for specific query
$queryBuilder = $entityManager->createQueryBuilder();
$result = $queryBuilder
    ->select('u')
    ->from(User::class, 'u')
    ->setCacheable(true)
    ->setCacheLifetime(3600) // 1 hour
    ->getResult();
```

---

## Events

### 🔔 Event Manager

```php
<?php

use MulerTech\Database\Event\{PrePersistEvent, PostPersistEvent, PreUpdateEvent, PostUpdateEvent};

$eventManager = $entityManager->getEventManager();

// Pre-persist event
$eventManager->addListener(PrePersistEvent::class, function(PrePersistEvent $event) {
    $entity = $event->getEntity();
    
    if ($entity instanceof User) {
        $entity->setCreatedAt(new DateTime());
        $entity->setToken(bin2hex(random_bytes(32)));
    }
});

// Post-persist event
$eventManager->addListener(PostPersistEvent::class, function(PostPersistEvent $event) {
    $entity = $event->getEntity();
    
    if ($entity instanceof User) {
        // Send welcome email
        mail($entity->getEmail(), 'Welcome', 'Account created successfully');
    }
});
```

### 📝 Available Events

```php
<?php

// Persistence events
PrePersistEvent::class;   // Before insert
PostPersistEvent::class;  // After insert

// Update events
PreUpdateEvent::class;    // Before update
PostUpdateEvent::class;   // After update

// Removal events
PreRemoveEvent::class;    // Before delete
PostRemoveEvent::class;   // After delete

// Loading events
PostLoadEvent::class;     // After loading from DB
```

---

## Complete API

### 🔧 Main Methods

```php
<?php

interface EntityManagerInterface
{
    // === PERSISTENCE ===
    public function persist(object $entity): void;
    public function remove(object $entity): void;
    public function flush(): void;
    public function clear(): void;
    
    // === SEARCH ===
    public function find(string $className, mixed $id): ?object;
    public function findOneBy(string $className, array $criteria): ?object;
    public function findBy(string $className, array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array;
    public function findAll(string $className): array;
    
    // === ENTITY STATE ===
    public function contains(object $entity): bool;
    public function detach(object $entity): void;
    public function merge(object $entity): object;
    public function refresh(object $entity): void;
    public function getEntityState(object $entity): EntityState;
    
    // === CHANGES ===
    public function hasChanges(object $entity): bool;
    public function getChangeSet(object $entity): array;
    public function computeChangeSets(): void;
    
    // === TRANSACTIONS ===
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollback(): void;
    public function transactional(callable $callback): mixed;
    
    // === CONFIGURATION ===
    public function getConnection(): ConnectionInterface;
    public function getConfiguration(): Configuration;
    public function getEventManager(): EventManager;
    public function getEmEngine(): EmEngine;
    
    // === METADATA ===
    public function getMetadataFor(string $className): EntityMetadata;
    public function hasMetadataFor(string $className): bool;
    
    // === REPOSITORIES ===
    public function getRepository(string $className): EntityRepository;
}
```

### 📊 Debug Methods

```php
<?php

// EntityManager statistics
$stats = $entityManager->getStats();
echo "Managed entities: " . $stats['managed_entities'] . "\n";
echo "Executed queries: " . $stats['executed_queries'] . "\n";

// Log SQL queries
$entityManager->getConfiguration()->setSQLLogger(new class {
    public function logSQL(string $sql, array $params = []): void {
        echo "SQL: " . $sql . "\n";
        echo "Params: " . json_encode($params) . "\n";
    }
});

// Debug mode
$entityManager->getConfiguration()->setDebugMode(true);
```

---

## ➡️ Next Steps

Explore the following concepts:

1. 🗂️ [Repositories](repositories.md) - Custom repositories
2. 🔄 [Change Tracking](change-tracking.md) - Detailed change tracking
3. 🎉 [Events](events.md) - Advanced event system
4. 💾 [Cache](caching.md) - Cache system and performance

---

## 🔗 Useful Links

- 🏠 [Back to README](../../README.md)
- ⬅️ [Mapping Attributes](../entity-mapping/attributes.md)
- ➡️ [Repositories](repositories.md)
- 📖 [Complete Documentation](../README.md)