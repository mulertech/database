# Basic Queries

🌍 **Languages:** [🇫🇷 Français](../../fr/query-builder/basic-queries.md) | [🇬🇧 English](basic-queries.md)

---

## 📋 Table of Contents

- [Overview](#overview)
- [QueryBuilder Configuration](#querybuilder-configuration)
- [SELECT Queries](#select-queries)
- [Filters and Conditions](#filters-and-conditions)
- [Joins](#joins)
- [Sorting and Limiting](#sorting-and-limiting)
- [Aggregations and Functions](#aggregations-and-functions)
- [INSERT Queries](#insert-queries)
- [UPDATE Queries](#update-queries)
- [DELETE Queries](#delete-queries)
- [Parameters and Security](#parameters-and-security)
- [Performance Optimization](#performance-optimization)

---

## Overview

The **QueryBuilder** in MulerTech Database provides a fluent and intuitive API to build complex SQL queries programmatically.

### 🎯 QueryBuilder Advantages

- **Fluent API**: Chained and readable syntax
- **Type-Safe**: Type and column validation
- **SQL Protection**: Automatic injection prevention
- **Flexibility**: From simple to complex queries
- **Performance**: Automatic query optimization

### 🏗️ Architecture

```php
<?php

use MulerTech\Database\Query\Builder\QueryBuilder;
use MulerTech\Database\ORM\EntityManager;

// Creation via EntityManager (recommended)
$queryBuilder = $entityManager->createQueryBuilder();

// Or direct creation
$queryBuilder = new QueryBuilder($entityManager->getEmEngine());
```

---

## QueryBuilder Configuration

### 🔧 Initialization

```php
<?php

use MulerTech\Database\Query\Builder\QueryBuilder;

// Via EntityManager
$em = $entityManager;
$qb = $em->createQueryBuilder();

// Configure options
$qb->setMaxResults(1000)
   ->setFirstResult(0)
   ->setCacheable(true)
   ->setCacheLifetime(3600);
```

### ⚙️ Configuration Options

```php
<?php

$queryBuilder
    ->setMaxResults(50)        // LIMIT clause
    ->setFirstResult(100)      // Offset
    ->setCacheable(true)       // Enable cache
    ->setCacheLifetime(1800)   // Cache TTL (30 min)
    ->setFetchMode('ASSOC');   // Fetch mode
```

---

## SELECT Queries

### 📊 Basic SELECT

```php
<?php

// Simple selection
$users = $queryBuilder
    ->select('*')
    ->from('users')
    ->getResult();

// Specific selection
$userData = $queryBuilder
    ->select(['id', 'name', 'email'])
    ->from('users')
    ->getResult();
```

### 🎯 SELECT with Aliases

```php
<?php

// Columns with aliases
$results = $queryBuilder
    ->select([
        'u.id',
        'u.name as user_name',
        'u.email as user_email',
        'COUNT(p.id) as post_count'
    ])
    ->from('users', 'u')
    ->leftJoin('posts', 'p', 'u.id = p.user_id')
    ->groupBy('u.id')
    ->getResult();
```

### 🔍 SELECT with Subqueries

```php
<?php

// Subquery in SELECT
$queryBuilder
    ->select([
        'u.*',
        '(SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id) as post_count'
    ])
    ->from('users', 'u');

// Subquery as table
$subQuery = $entityManager->createQueryBuilder()
    ->select('user_id, COUNT(*) as count')
    ->from('posts')
    ->groupBy('user_id');

$results = $queryBuilder
    ->select('u.name, stats.count')
    ->from('users', 'u')
    ->join("({$subQuery->getSQL()})", 'stats', 'u.id = stats.user_id')
    ->getResult();
```

---

## Filters and Conditions

### 🔎 Basic Conditions

```php
<?php

// Simple WHERE
$activeUsers = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('status', '=', 'active')
    ->getResult();

// Multiple conditions
$filteredUsers = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('status', '=', 'active')
    ->andWhere('age', '>=', 18)
    ->andWhere('verified', '=', true)
    ->getResult();
```

### 🎭 Comparison Operators

```php
<?php

$queryBuilder
    ->select('*')
    ->from('products')
    ->where('price', '>', 100)           // Greater than
    ->andWhere('stock', '<=', 50)        // Less than or equal
    ->andWhere('name', 'LIKE', '%phone%') // LIKE pattern
    ->andWhere('category_id', 'IN', [1, 2, 3]) // IN list
    ->andWhere('discount', 'BETWEEN', [10, 50]) // BETWEEN range
    ->andWhere('description', 'IS NOT NULL') // NULL check
    ->getResult();
```

### 🔗 OR Conditions

```php
<?php

// Simple OR
$results = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('role', '=', 'admin')
    ->orWhere('role', '=', 'moderator')
    ->getResult();

// Condition groups
$results = $queryBuilder
    ->select('*')
    ->from('users')
    ->where(function($query) {
        $query->where('status', '=', 'premium')
              ->orWhere('credits', '>', 1000);
    })
    ->andWhere('verified', '=', true)
    ->getResult();
```

### 📅 Date Conditions

```php
<?php

// Dates
$recentPosts = $queryBuilder
    ->select('*')
    ->from('posts')
    ->where('created_at', '>=', '2024-01-01')
    ->andWhere('published_at', 'BETWEEN', ['2024-01-01', '2024-12-31'])
    ->orderBy('created_at', 'DESC')
    ->getResult();

// Date functions
$monthlyStats = $queryBuilder
    ->select([
        'MONTH(created_at) as month',
        'COUNT(*) as count'
    ])
    ->from('posts')
    ->where('YEAR(created_at)', '=', 2024)
    ->groupBy('MONTH(created_at)')
    ->getResult();
```

---

## Joins

### 🔗 INNER JOIN

```php
<?php

// Posts with their authors
$postsWithAuthors = $queryBuilder
    ->select([
        'p.title',
        'p.content',
        'u.name as author_name'
    ])
    ->from('posts', 'p')
    ->join('users', 'u', 'p.user_id = u.id')
    ->where('p.published', '=', true)
    ->getResult();
```

### 🔗 LEFT JOIN

```php
<?php

// Users with post count (including those without posts)
$usersWithPostCount = $queryBuilder
    ->select([
        'u.name',
        'u.email',
        'COUNT(p.id) as post_count'
    ])
    ->from('users', 'u')
    ->leftJoin('posts', 'p', 'u.id = p.user_id')
    ->groupBy(['u.id', 'u.name', 'u.email'])
    ->getResult();
```

### 🔗 Multiple Joins

```php
<?php

// Posts with authors and categories
$completeData = $queryBuilder
    ->select([
        'p.title',
        'p.content',
        'u.name as author_name',
        'c.name as category_name',
        'GROUP_CONCAT(t.name) as tags'
    ])
    ->from('posts', 'p')
    ->join('users', 'u', 'p.user_id = u.id')
    ->leftJoin('categories', 'c', 'p.category_id = c.id')
    ->leftJoin('post_tags', 'pt', 'p.id = pt.post_id')
    ->leftJoin('tags', 't', 'pt.tag_id = t.id')
    ->where('p.published', '=', true)
    ->groupBy(['p.id', 'p.title', 'p.content', 'u.name', 'c.name'])
    ->getResult();
```

---

## Sorting and Limiting

### 📊 ORDER BY Sorting

```php
<?php

// Simple sorting
$sortedUsers = $queryBuilder
    ->select('*')
    ->from('users')
    ->orderBy('name', 'ASC')
    ->getResult();

// Multiple sorting
$complexSort = $queryBuilder
    ->select('*')
    ->from('posts')
    ->orderBy('featured', 'DESC')     // Featured posts first
    ->addOrderBy('published_at', 'DESC') // Then by date
    ->addOrderBy('title', 'ASC')      // Then by title
    ->getResult();
```

### 📄 Pagination

```php
<?php

// Simple pagination
$page = 2;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$paginatedPosts = $queryBuilder
    ->select('*')
    ->from('posts')
    ->orderBy('created_at', 'DESC')
    ->limit($perPage)
    ->offset($offset)
    ->getResult();

// Or with setters
$results = $queryBuilder
    ->select('*')
    ->from('users')
    ->setMaxResults(25)
    ->setFirstResult(50)
    ->getResult();
```

### 🔢 Count for Pagination

```php
<?php

// Count total for pagination
$totalCount = $queryBuilder
    ->select('COUNT(*) as total')
    ->from('posts')
    ->where('published', '=', true)
    ->getResult()[0]['total'];

// Calculate pagination info
$totalPages = ceil($totalCount / $perPage);
$hasNextPage = $page < $totalPages;
$hasPrevPage = $page > 1;
```

---

## Aggregations and Functions

### 🧮 Aggregation Functions

```php
<?php

// Basic statistics
$stats = $queryBuilder
    ->select([
        'COUNT(*) as total_posts',
        'AVG(views) as avg_views',
        'MAX(views) as max_views',
        'MIN(views) as min_views',
        'SUM(views) as total_views'
    ])
    ->from('posts')
    ->where('published', '=', true)
    ->getResult()[0];
```

### 📊 GROUP BY and HAVING

```php
<?php

// Statistics by category
$categoryStats = $queryBuilder
    ->select([
        'c.name as category',
        'COUNT(p.id) as post_count',
        'AVG(p.views) as avg_views'
    ])
    ->from('categories', 'c')
    ->leftJoin('posts', 'p', 'c.id = p.category_id')
    ->groupBy('c.id', 'c.name')
    ->having('post_count', '>', 5)  // Only categories with more than 5 posts
    ->orderBy('post_count', 'DESC')
    ->getResult();
```

### 🔤 String Functions

```php
<?php

// Text manipulation
$formattedData = $queryBuilder
    ->select([
        'UPPER(name) as name_upper',
        'LOWER(email) as email_lower',
        'CONCAT(first_name, " ", last_name) as full_name',
        'LENGTH(bio) as bio_length',
        'SUBSTRING(bio, 1, 100) as bio_excerpt'
    ])
    ->from('users')
    ->getResult();
```

---

## INSERT Queries

### ➕ Simple INSERT

```php
<?php

// Insert single record
$queryBuilder
    ->insertInto('users')
    ->values([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => password_hash('secret', PASSWORD_DEFAULT),
        'created_at' => date('Y-m-d H:i:s')
    ])
    ->execute();
```

### ➕ Multiple INSERT

```php
<?php

// Batch insertion
$usersData = [
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
    ['name' => 'Charlie', 'email' => 'charlie@example.com']
];

$queryBuilder
    ->insertInto('users')
    ->columns(['name', 'email', 'created_at'])
    ->values($usersData)
    ->addValue('created_at', date('Y-m-d H:i:s')) // Common value
    ->execute();
```

### ➕ INSERT with SELECT

```php
<?php

// Copy data from table to table
$queryBuilder
    ->insertInto('user_archive')
    ->columns(['name', 'email', 'archived_at'])
    ->fromSelect(
        $entityManager->createQueryBuilder()
            ->select(['name', 'email', 'NOW()'])
            ->from('users')
            ->where('last_login', '<', '2023-01-01')
    )
    ->execute();
```

---

## UPDATE Queries

### ✏️ Simple UPDATE

```php
<?php

// Update a user
$affectedRows = $queryBuilder
    ->update('users')
    ->set([
        'name' => 'John Smith',
        'updated_at' => date('Y-m-d H:i:s')
    ])
    ->where('id', '=', 123)
    ->execute();

echo "Records updated: " . $affectedRows;
```

### ✏️ UPDATE with Conditions

```php
<?php

// Batch update
$queryBuilder
    ->update('posts')
    ->set([
        'status' => 'archived',
        'archived_at' => date('Y-m-d H:i:s')
    ])
    ->where('published_at', '<', '2023-01-01')
    ->andWhere('views', '<', 100)
    ->execute();
```

### ✏️ UPDATE with JOIN

```php
<?php

// Update with join
$queryBuilder
    ->update('posts', 'p')
    ->join('users', 'u', 'p.user_id = u.id')
    ->set([
        'p.author_name' => 'u.name' // Reference to column
    ])
    ->where('p.author_name', 'IS NULL')
    ->execute();
```

---

## DELETE Queries

### 🗑️ Simple DELETE

```php
<?php

// Delete a user
$affectedRows = $queryBuilder
    ->deleteFrom('users')
    ->where('id', '=', 123)
    ->execute();

echo "Records deleted: " . $affectedRows;
```

### 🗑️ DELETE with Conditions

```php
<?php

// Clean old data
$queryBuilder
    ->deleteFrom('sessions')
    ->where('expires_at', '<', date('Y-m-d H:i:s'))
    ->execute();

// Batch deletion with limit
$queryBuilder
    ->deleteFrom('logs')
    ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-30 days')))
    ->limit(1000)  // Limit to avoid locks
    ->execute();
```

### 🗑️ DELETE with JOIN

```php
<?php

// Delete with join
$queryBuilder
    ->deleteFrom('posts', 'p')
    ->join('users', 'u', 'p.user_id = u.id')
    ->where('u.status', '=', 'banned')
    ->execute();
```

---

## Parameters and Security

### 🔒 Bound Parameters

```php
<?php

// SQL injection protection
$queryBuilder
    ->select('*')
    ->from('users')
    ->where('email', '=', ':email')
    ->andWhere('status', '=', ':status')
    ->setParameter('email', $userEmail)
    ->setParameter('status', 'active')
    ->getResult();
```

### 🔒 Multiple Parameters

```php
<?php

// Multiple parameters at once
$params = [
    'min_age' => 18,
    'max_age' => 65,
    'status' => 'active'
];

$results = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('age', 'BETWEEN', ':min_age AND :max_age')
    ->andWhere('status', '=', ':status')
    ->setParameters($params)
    ->getResult();
```

### 🛡️ Manual Escaping

```php
<?php

// For complex cases (use with caution)
$dynamicTable = $queryBuilder->escapeName($tableName);
$dynamicColumn = $queryBuilder->escapeName($columnName);

$queryBuilder
    ->select('*')
    ->from($dynamicTable)
    ->where($dynamicColumn, '=', ':value')
    ->setParameter('value', $value)
    ->getResult();
```

---

## Performance Optimization

### ⚡ Query Cache

```php
<?php

// Query-level cache
$cachedResults = $queryBuilder
    ->select('*')
    ->from('categories')
    ->setCacheable(true)
    ->setCacheLifetime(3600) // 1 hour
    ->setCacheKey('all_categories')
    ->getResult();
```

### 🔍 Index Hints

```php
<?php

// Force index usage
$queryBuilder
    ->select('*')
    ->from('users')
    ->useIndex('idx_email')  // Use specific index
    ->where('email', '=', ':email')
    ->setParameter('email', $email)
    ->getResult();
```

### 📊 Query Profiling

```php
<?php

// Enable profiling
$queryBuilder->enableProfiling();

$results = $queryBuilder
    ->select('*')
    ->from('posts')
    ->join('users', 'u', 'posts.user_id = u.id')
    ->getResult();

// Get statistics
$profile = $queryBuilder->getProfile();
echo "Execution time: " . $profile['execution_time'] . "ms\n";
echo "SQL query: " . $profile['sql'] . "\n";
```

### 🚀 Advanced Optimizations

```php
<?php

// Optimized query with explain
$explained = $queryBuilder
    ->select('*')
    ->from('posts')
    ->where('status', '=', 'published')
    ->explain(); // Returns execution plan

foreach ($explained as $row) {
    echo "Type: " . $row['type'] . ", Key: " . $row['key'] . "\n";
}

// Batch processing for large data
$batchSize = 1000;
$offset = 0;

do {
    $batch = $queryBuilder
        ->select('*')
        ->from('large_table')
        ->limit($batchSize)
        ->offset($offset)
        ->getResult();
    
    // Process batch
    processBatch($batch);
    
    $offset += $batchSize;
} while (count($batch) === $batchSize);
```

---

## ➡️ Next Steps

Explore the following concepts:

1. 🔧 [Advanced Queries](advanced-queries.md) - Complex joins and subqueries
2. 🛠️ [Raw SQL Queries](raw-queries.md) - Native SQL when needed
3. ⚡ [Optimization](query-optimization.md) - Performance and best practices
4. 🗄️ [Entity Manager](../orm/entity-manager.md) - Back to ORM

---

## 🔗 Useful Links

- 🏠 [Back to README](../../README.md)
- ⬅️ [Entity Manager](../orm/entity-manager.md)
- ➡️ [Advanced Queries](advanced-queries.md)
- 📖 [Complete Documentation](../README.md)