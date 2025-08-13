# Requêtes Avancées - Query Builder

🌍 **Languages:** [🇫🇷 Français](advanced-queries.md) | [🇬🇧 English](../../en/query-builder/advanced-queries.md)

---

## 📋 Table des Matières

- [Vue d'Ensemble](#vue-densemble)
- [Architecture du Query Builder](#architecture-du-query-builder)
- [SelectBuilder Avancé](#selectbuilder-avancé)
- [Jointures](#jointures)
- [Sous-requêtes et Raw SQL](#sous-requêtes-et-raw-sql)
- [Groupements et Agrégations](#groupements-et-agrégations)
- [Tri et Pagination](#tri-et-pagination)
- [Requêtes Complexes](#requêtes-complexes)
- [Optimisations](#optimisations)

---

## Vue d'Ensemble

Le Query Builder de MulerTech Database utilise une architecture modulaire avec des builders spécialisés pour chaque type de requête. Cette approche garantit la type-safety et permet des optimisations spécifiques.

### 🎯 Builders Disponibles

- **SelectBuilder** : Requêtes SELECT complexes avec jointures, groupements, tri
- **InsertBuilder** : Insertions simples et par lot
- **UpdateBuilder** : Mises à jour avec conditions
- **DeleteBuilder** : Suppressions avec conditions
- **RawQueryBuilder** : Requêtes SQL brutes pour cas spéciaux

### 📦 Imports Nécessaires

```php
<?php
use MulerTech\Database\Query\Builder\{QueryBuilder, SelectBuilder};
use MulerTech\Database\ORM\EmEngine;
```

---

## Architecture du Query Builder

### 🏭 Factory Pattern

```php
<?php

// Le QueryBuilder est une factory qui crée des builders spécialisés
$queryBuilder = new QueryBuilder($emEngine);

// Création des différents types de builders
$selectBuilder = $queryBuilder->select('*');           // SelectBuilder
$insertBuilder = $queryBuilder->insert('users');       // InsertBuilder  
$updateBuilder = $queryBuilder->update('users');       // UpdateBuilder
$deleteBuilder = $queryBuilder->delete('users');       // DeleteBuilder
$rawBuilder = $queryBuilder->raw('SELECT 1');          // RawQueryBuilder
```

### 🔧 Configuration avec EmEngine

```php
<?php

// Avec EmEngine pour l'hydratation automatique
$emEngine = $entityManager->getEmEngine();
$queryBuilder = new QueryBuilder($emEngine);

// Sans EmEngine pour des requêtes simples
$queryBuilder = new QueryBuilder();
```

---

## SelectBuilder Avancé

### 🔍 Sélection de Colonnes

```php
<?php

$selectBuilder = $queryBuilder->select('id', 'name', 'email');

// Ajout de colonnes supplémentaires
$selectBuilder->select('created_at', 'status');

// Avec alias
$selectBuilder = $queryBuilder->select(
    'u.id',
    'u.name as user_name', 
    'u.email as user_email'
);

// Colonnes calculées
$selectBuilder = $queryBuilder->select(
    '*',
    'CONCAT(first_name, " ", last_name) as full_name',
    'YEAR(created_at) as registration_year'
);
```

### 📊 Table et Alias

```php
<?php

// Table simple
$selectBuilder = $queryBuilder->select('*')->from('users');

// Avec alias
$selectBuilder = $queryBuilder->select('u.*')->from('users', 'u');

// Sous-requête comme table (via Raw SQL)
$subquery = 'SELECT user_id, COUNT(*) as order_count FROM orders GROUP BY user_id';
$selectBuilder = $queryBuilder
    ->select('u.name', 'stats.order_count')
    ->from("($subquery)", 'stats');
```

---

## Jointures

Le SelectBuilder supporte les jointures via les traits `JoinClauseTrait` :

### 🔗 Types de Jointures

```php
<?php

// INNER JOIN
$selectBuilder = $queryBuilder
    ->select('u.name', 'p.title')
    ->from('users', 'u')
    ->join('posts', 'p', 'u.id = p.user_id');

// LEFT JOIN
$selectBuilder = $queryBuilder
    ->select('u.name', 'p.title')
    ->from('users', 'u')
    ->leftJoin('profiles', 'pr', 'u.id = pr.user_id');

// Jointures multiples
$selectBuilder = $queryBuilder
    ->select('u.name', 'p.title', 'c.name as category')
    ->from('users', 'u')
    ->join('posts', 'p', 'u.id = p.user_id')
    ->join('categories', 'c', 'p.category_id = c.id');
```

### 🎯 Jointures avec Conditions

```php
<?php

// JOIN avec conditions supplémentaires
$selectBuilder = $queryBuilder
    ->select('u.name', 'o.total')
    ->from('users', 'u')
    ->join('orders', 'o', 'u.id = o.user_id AND o.status = ?', 'completed')
    ->where('u.active = ?', true);

// Jointures complexes
$selectBuilder = $queryBuilder
    ->select('u.name', 'COUNT(o.id) as order_count')
    ->from('users', 'u')
    ->leftJoin('orders', 'o', 'u.id = o.user_id AND o.created_at >= ?', '2024-01-01')
    ->groupBy('u.id', 'u.name');
```

---

## Sous-requêtes et Raw SQL

### 🔧 RawQueryBuilder pour Requêtes Complexes

```php
<?php

// Sous-requête dans SELECT
$rawBuilder = $queryBuilder->raw("
    SELECT 
        u.name,
        u.email,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as order_count
    FROM users u
    WHERE u.status = 'active'
");

$results = $rawBuilder->fetchAll();
```

### 🎯 Sous-requêtes avec Paramètres

```php
<?php

// Requête avec paramètres
$rawBuilder = $queryBuilder->raw("
    SELECT u.*
    FROM users u
    WHERE u.id IN (
        SELECT o.user_id 
        FROM orders o 
        WHERE o.total > ? 
        AND o.created_at >= ?
    )
");

$rawBuilder->setParameter(0, 1000);
$rawBuilder->setParameter(1, '2024-01-01');

$results = $rawBuilder->fetchAll();
```

### 🏗️ Construction Dynamique

```php
<?php

// Construction dynamique avec Raw SQL
class AdvancedQueryService
{
    public function __construct(
        private QueryBuilder $queryBuilder
    ) {}
    
    public function getUsersWithOrderStats(array $filters = []): array
    {
        $sql = "
            SELECT 
                u.id,
                u.name,
                u.email,
                COALESCE(stats.order_count, 0) as order_count,
                COALESCE(stats.total_spent, 0) as total_spent
            FROM users u
            LEFT JOIN (
                SELECT 
                    user_id,
                    COUNT(*) as order_count,
                    SUM(total) as total_spent
                FROM orders
                WHERE status = 'completed'
                GROUP BY user_id
            ) stats ON u.id = stats.user_id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (isset($filters['status'])) {
            $sql .= " AND u.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['min_orders'])) {
            $sql .= " AND COALESCE(stats.order_count, 0) >= ?";
            $params[] = $filters['min_orders'];
        }
        
        $sql .= " ORDER BY stats.total_spent DESC";
        
        $rawBuilder = $this->queryBuilder->raw($sql);
        
        foreach ($params as $index => $value) {
            $rawBuilder->setParameter($index, $value);
        }
        
        return $rawBuilder->fetchAll();
    }
}
```

---

## Groupements et Agrégations

### 📊 GROUP BY et Agrégations

```php
<?php

// Groupement simple
$selectBuilder = $queryBuilder
    ->select('status', 'COUNT(*) as count')
    ->from('users')
    ->groupBy('status');

// Groupements multiples
$selectBuilder = $queryBuilder
    ->select(
        'department',
        'status', 
        'COUNT(*) as employee_count',
        'AVG(salary) as avg_salary'
    )
    ->from('employees')
    ->groupBy('department', 'status');

// Avec HAVING
$selectBuilder = $queryBuilder
    ->select('category_id', 'COUNT(*) as product_count')
    ->from('products')
    ->groupBy('category_id')
    ->having('COUNT(*) > ?', 5);
```

### 🎯 Agrégations Avancées via Raw SQL

```php
<?php

// Statistiques complexes
$rawBuilder = $queryBuilder->raw("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total_orders,
        SUM(total) as revenue,
        AVG(total) as avg_order_value,
        COUNT(DISTINCT user_id) as unique_customers
    FROM orders 
    WHERE status = 'completed'
    AND created_at >= ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
");

$rawBuilder->setParameter(0, '2024-01-01');
$monthlyStats = $rawBuilder->fetchAll();
```

---

## Tri et Pagination

### 📈 Tri (ORDER BY)

```php
<?php

// Tri simple
$selectBuilder = $queryBuilder
    ->select('*')
    ->from('users')
    ->orderBy('name', 'ASC');

// Tri multiple
$selectBuilder = $queryBuilder
    ->select('*')
    ->from('users')
    ->orderBy('status', 'ASC')
    ->orderBy('created_at', 'DESC');

// Tri avec expressions
$selectBuilder = $queryBuilder
    ->select('*')
    ->from('products')
    ->orderBy('FIELD(status, "active", "pending", "inactive")')
    ->orderBy('price', 'DESC');
```

### 📄 Pagination

```php
<?php

// LIMIT et OFFSET
$selectBuilder = $queryBuilder
    ->select('*')
    ->from('users')
    ->orderBy('created_at', 'DESC')
    ->limit(20)
    ->offset(40); // Page 3 si 20 par page

// Pagination avec comptage
class PaginationService
{
    public function __construct(
        private QueryBuilder $queryBuilder
    ) {}
    
    public function getPaginatedUsers(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        
        // Requête de données
        $users = $this->queryBuilder
            ->select('*')
            ->from('users')
            ->where('status = ?', 'active')
            ->orderBy('name', 'ASC')
            ->limit($perPage)
            ->offset($offset)
            ->fetchAll();
        
        // Comptage total
        $total = $this->queryBuilder
            ->raw("SELECT COUNT(*) as count FROM users WHERE status = ?")
            ->setParameter(0, 'active')
            ->fetchScalar();
        
        return [
            'data' => $users,
            'total' => (int) $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
}
```

---

## Requêtes Complexes

### 🔍 Recherche Avancée

```php
<?php

class SearchService
{
    public function __construct(
        private QueryBuilder $queryBuilder
    ) {}
    
    public function searchProducts(array $criteria): array
    {
        $selectBuilder = $this->queryBuilder
            ->select(
                'p.*',
                'c.name as category_name',
                'b.name as brand_name'
            )
            ->from('products', 'p')
            ->leftJoin('categories', 'c', 'p.category_id = c.id')
            ->leftJoin('brands', 'b', 'p.brand_id = b.id');
        
        // Recherche textuelle
        if (!empty($criteria['search'])) {
            $selectBuilder->where(
                'p.name LIKE ? OR p.description LIKE ?',
                "%{$criteria['search']}%",
                "%{$criteria['search']}%"
            );
        }
        
        // Filtres de prix
        if (isset($criteria['min_price'])) {
            $selectBuilder->where('p.price >= ?', $criteria['min_price']);
        }
        
        if (isset($criteria['max_price'])) {
            $selectBuilder->where('p.price <= ?', $criteria['max_price']);
        }
        
        // Filtre de catégorie
        if (!empty($criteria['category_ids'])) {
            $placeholders = str_repeat('?,', count($criteria['category_ids']) - 1) . '?';
            $selectBuilder->where("p.category_id IN ($placeholders)", ...$criteria['category_ids']);
        }
        
        // Tri
        $orderBy = $criteria['sort'] ?? 'name';
        $direction = $criteria['direction'] ?? 'ASC';
        $selectBuilder->orderBy("p.$orderBy", $direction);
        
        return $selectBuilder->fetchAll();
    }
}
```

### 📊 Rapports Complexes

```php
<?php

class ReportService
{
    public function __construct(
        private QueryBuilder $queryBuilder
    ) {}
    
    public function getSalesReport(string $startDate, string $endDate): array
    {
        // Rapport de ventes complexe avec Raw SQL
        $rawBuilder = $this->queryBuilder->raw("
            SELECT 
                DATE(o.created_at) as sale_date,
                COUNT(DISTINCT o.id) as order_count,
                COUNT(DISTINCT o.user_id) as customer_count,
                SUM(o.total) as revenue,
                AVG(o.total) as avg_order_value,
                
                -- Répartition par catégorie
                SUM(CASE WHEN c.name = 'Electronics' THEN oi.quantity * oi.price ELSE 0 END) as electronics_revenue,
                SUM(CASE WHEN c.name = 'Clothing' THEN oi.quantity * oi.price ELSE 0 END) as clothing_revenue,
                SUM(CASE WHEN c.name = 'Books' THEN oi.quantity * oi.price ELSE 0 END) as books_revenue,
                
                -- Métriques de performance
                COUNT(CASE WHEN o.total > 100 THEN 1 END) as high_value_orders,
                COUNT(CASE WHEN TIMESTAMPDIFF(HOUR, o.created_at, o.shipped_at) <= 24 THEN 1 END) as fast_shipped
                
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            JOIN categories c ON p.category_id = c.id
            WHERE o.status = 'completed'
            AND o.created_at >= ?
            AND o.created_at <= ?
            GROUP BY DATE(o.created_at)
            ORDER BY sale_date DESC
        ");
        
        $rawBuilder->setParameter(0, $startDate);
        $rawBuilder->setParameter(1, $endDate);
        
        return $rawBuilder->fetchAll();
    }
}
```

---

## Optimisations

### ⚡ Requêtes Optimisées

```php
<?php

class OptimizedQueryService
{
    public function __construct(
        private QueryBuilder $queryBuilder
    ) {}
    
    public function getOptimizedUserList(): array
    {
        // Requête optimisée avec index hints
        $rawBuilder = $this->queryBuilder->raw("
            SELECT /*+ USE_INDEX(users, idx_status_created) */
                u.id,
                u.name,
                u.email,
                u.status,
                p.avatar_url
            FROM users u
            LEFT JOIN profiles p ON u.id = p.user_id
            WHERE u.status = 'active'
            AND u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY u.created_at DESC
            LIMIT 100
        ");
        
        return $rawBuilder->fetchAll();
    }
    
    public function getBatchUserData(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        
        // Requête par lot optimisée
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        
        $rawBuilder = $this->queryBuilder->raw("
            SELECT 
                u.*,
                COUNT(o.id) as order_count,
                COALESCE(SUM(o.total), 0) as total_spent
            FROM users u
            LEFT JOIN orders o ON u.id = o.user_id AND o.status = 'completed'
            WHERE u.id IN ($placeholders)
            GROUP BY u.id
            ORDER BY u.name
        ");
        
        foreach ($userIds as $index => $userId) {
            $rawBuilder->setParameter($index, $userId);
        }
        
        return $rawBuilder->fetchAll();
    }
}
```

### 🎯 Mise en Cache

```php
<?php

class CachedQueryService
{
    public function __construct(
        private QueryBuilder $queryBuilder,
        private CacheInterface $cache
    ) {}
    
    public function getCachedStats(): array
    {
        $cacheKey = 'dashboard_stats';
        
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }
        
        $stats = $this->queryBuilder->raw("
            SELECT 
                (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
                (SELECT COUNT(*) FROM orders WHERE status = 'completed') as completed_orders,
                (SELECT SUM(total) FROM orders WHERE status = 'completed') as total_revenue,
                (SELECT COUNT(*) FROM products WHERE status = 'active') as active_products
        ")->fetch();
        
        $this->cache->set($cacheKey, $stats, 300); // 5 minutes
        
        return $stats;
    }
}
```

---

## ➡️ Étapes Suivantes

Pour approfondir votre compréhension :

1. 🔧 [Requêtes SELECT](select-queries.md) - Bases du SelectBuilder
2. 💾 [Requêtes d'Insertion](insert-queries.md) - InsertBuilder
3. ✏️ [Requêtes de Mise à Jour](update-queries.md) - UpdateBuilder
4. 🗑️ [Requêtes de Suppression](delete-queries.md) - DeleteBuilder

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../fr/../README.md)
- 📖 [Documentation Complète](../../fr/README.md)
- 🚀 [Démarrage Rapide](../../fr/quick-start/installation.md)
