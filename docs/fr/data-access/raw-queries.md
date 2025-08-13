# Requêtes SQL Brutes

## Introduction

Le Query Builder permet d'exécuter des requêtes SQL brutes quand nécessaire. Ce guide couvre l'utilisation des requêtes SQL natives avec MulerTech Database.

## Quand Utiliser des Requêtes Brutes

**Cas d'usage appropriés :**
- Requêtes complexes non supportées par le Query Builder
- Fonctions spécifiques au SGBD
- Procédures stockées
- Optimisations de performance

**Risques à considérer :**
- Perte de portabilité
- Risque d'injection SQL
- Maintenance plus difficile

## Exécution de Requêtes Brutes

### Méthodes de Base

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

$queryBuilder = new QueryBuilder();

// Requête SELECT avec résultats multiples
$users = $queryBuilder
    ->raw('SELECT * FROM users WHERE age > ?')
    ->bind([18])
    ->execute()
    ->fetchAll();

// Requête avec un seul résultat
$user = $queryBuilder
    ->raw('SELECT * FROM users WHERE id = ?')
    ->bind([123])
    ->execute()
    ->fetchOne();

// Requête de modification
$result = $queryBuilder
    ->raw('UPDATE users SET last_login = NOW() WHERE id = ?')
    ->bind([123])
    ->execute();
```

### Méthodes Disponibles

```php
<?php
// Méthodes principales du RawQueryBuilder
$queryBuilder->raw(string $sql)      // Crée une requête brute
             ->bind(array $params)   // Lie les paramètres
             ->execute()             // Exécute la requête
             ->fetchAll()            // Récupère tous les résultats
             ->fetchOne()            // Récupère un résultat
             ->fetchScalar()         // Récupère une valeur scalaire
```

## Gestion des Paramètres

### Paramètres Positionnels

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

$queryBuilder = new QueryBuilder();

// Paramètres positionnels (recommandé)
$users = $queryBuilder
    ->raw('SELECT * FROM users WHERE age BETWEEN ? AND ? AND city = ?')
    ->bind([18, 65, 'Paris'])
    ->execute()
    ->fetchAll();

// Requête avec jointure
$orders = $queryBuilder
    ->raw('
        SELECT o.*, u.name as customer_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.created_at >= ? AND o.status = ?
        ORDER BY o.created_at DESC
    ')
    ->bind(['2024-01-01', 'completed'])
    ->execute()
    ->fetchAll();
```

### Sécurité

```php
<?php
// ❌ DANGEREUX - Injection SQL possible
$badQuery = "SELECT * FROM users WHERE name = '" . $_GET['name'] . "'";

// ✅ CORRECT - Paramètres liés
$goodQuery = $queryBuilder
    ->raw('SELECT * FROM users WHERE name = ?')
    ->bind([$_GET['name']])
    ->execute()
    ->fetchAll();
```

## Requêtes Complexes

### Requêtes avec CTE

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

$queryBuilder = new QueryBuilder();

$hierarchicalData = $queryBuilder
    ->raw('
        WITH RECURSIVE employee_hierarchy AS (
            SELECT id, name, manager_id, salary, 1 as level
            FROM employees 
            WHERE manager_id IS NULL
            
            UNION ALL
            
            SELECT e.id, e.name, e.manager_id, e.salary, eh.level + 1
            FROM employees e
            INNER JOIN employee_hierarchy eh ON e.manager_id = eh.id
            WHERE eh.level < 10
        )
        SELECT level, name, salary
        FROM employee_hierarchy
        ORDER BY level, name
    ')
    ->execute()
    ->fetchAll();
```

### Requêtes d'Analyse

```php
<?php
declare(strict_types=1);

$timeAnalysis = $queryBuilder
    ->raw('
        SELECT 
            DATE(created_at) as date,
            SUM(total) as daily_revenue,
            COUNT(*) as order_count,
            AVG(total) as avg_order_value
        FROM orders
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ')
    ->execute()
    ->fetchAll();
```

## Procédures Stockées

### Appel de Procédures

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

$queryBuilder = new QueryBuilder();

// Procédure sans paramètres
$result = $queryBuilder
    ->raw('CALL GetMonthlyStats()')
    ->execute()
    ->fetchAll();

// Procédure avec paramètres
$result = $queryBuilder
    ->raw('CALL GetUserStats(?, ?)')
    ->bind([2024, 'active'])
    ->execute()
    ->fetchAll();
```

## Fonctions Spéciales

### Fonctions d'Agrégation

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

$queryBuilder = new QueryBuilder();

$statistics = $queryBuilder
    ->raw('
        SELECT 
            department,
            COUNT(*) as employee_count,
            AVG(salary) as avg_salary,
            MIN(salary) as min_salary,
            MAX(salary) as max_salary
        FROM employees
        GROUP BY department
        ORDER BY avg_salary DESC
    ')
    ->execute()
    ->fetchAll();
```

### Fonctions JSON

```php
<?php
declare(strict_types=1);

// Lecture de données JSON
$jsonData = $queryBuilder
    ->raw('
        SELECT 
            id,
            name,
            JSON_EXTRACT(metadata, "$.age") as age,
            JSON_EXTRACT(metadata, "$.skills") as skills
        FROM users
        WHERE JSON_EXTRACT(metadata, "$.active") = true
    ')
    ->execute()
    ->fetchAll();

// Mise à jour JSON
$queryBuilder
    ->raw('
        UPDATE users 
        SET metadata = JSON_SET(metadata, "$.last_login", ?)
        WHERE id = ?
    ')
    ->bind([date('Y-m-d H:i:s'), 123])
    ->execute();
```

## Fonctionnalités Spécifiques

### MySQL Full-Text Search

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

$queryBuilder = new QueryBuilder();

$searchResults = $queryBuilder
    ->raw('
        SELECT 
            *,
            MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
        FROM articles
        WHERE MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE)
        ORDER BY relevance DESC
    ')
    ->bind([$searchTerm, $searchTerm])
    ->execute()
    ->fetchAll();
```

## Sécurité et Bonnes Pratiques

### Validation

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

class SecureRawQuery
{
    private QueryBuilder $queryBuilder;
    private array $allowedTables = ['users', 'orders', 'products'];
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    public function secureSearch(string $table, array $conditions): array
    {
        if (!in_array($table, $this->allowedTables)) {
            throw new \InvalidArgumentException("Table non autorisée: $table");
        }
        
        $params = [];
        $whereParts = [];
        
        foreach ($conditions as $column => $value) {
            $whereParts[] = "`{$column}` = ?";
            $params[] = $value;
        }
        
        $whereClause = !empty($whereParts) ? ' WHERE ' . implode(' AND ', $whereParts) : '';
        
        return $this->queryBuilder
            ->raw("SELECT * FROM `{$table}`{$whereClause}")
            ->bind($params)
            ->execute()
            ->fetchAll();
    }
}
```

### Protection Contre l'Injection SQL

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

class SqlInjectionProtection
{
    private QueryBuilder $queryBuilder;
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    // ❌ VULNÉRABLE
    public function vulnerableSearch(string $term): array
    {
        return $this->queryBuilder
            ->raw("SELECT * FROM users WHERE name LIKE '%{$term}%'")
            ->execute()
            ->fetchAll();
    }
    
    // ✅ SÉCURISÉ
    public function secureSearch(string $term): array
    {
        return $this->queryBuilder
            ->raw('SELECT * FROM users WHERE name LIKE ?')
            ->bind(["%{$term}%"])
            ->execute()
            ->fetchAll();
    }
}
```

## Exemples Avancés

### Requête de Recommandation Simplifiée

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

class RecommendationEngine
{
    private QueryBuilder $queryBuilder;
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    public function getPopularProducts(int $limit = 10): array
    {
        return $this->queryBuilder
            ->raw('
                SELECT 
                    p.id,
                    p.name,
                    p.price,
                    COUNT(oi.product_id) as order_count,
                    AVG(oi.rating) as avg_rating
                FROM products p
                JOIN order_items oi ON p.id = oi.product_id
                GROUP BY p.id, p.name, p.price
                ORDER BY order_count DESC, avg_rating DESC
                LIMIT ?
            ')
            ->bind([$limit])
            ->execute()
            ->fetchAll();
    }
}
```

### Système d'Audit

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

class AuditSystem
{
    private QueryBuilder $queryBuilder;
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    public function getEntityHistory(string $entityType, int $entityId): array
    {
        return $this->queryBuilder
            ->raw('
                SELECT 
                    a.*,
                    u.name as user_name
                FROM audit_log a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.entity_type = ? AND a.entity_id = ?
                ORDER BY a.created_at DESC
            ')
            ->bind([$entityType, $entityId])
            ->execute()
            ->fetchAll();
    }
}
```

## Prochaines Étapes

- [Requêtes de Base](basic-queries.md)
- [Requêtes Avancées](advanced-queries.md)
- [Optimisation des Requêtes](query-optimization.md)
