# Requêtes SQL Brutes

## Table des Matières
- [Introduction](#introduction)
- [Quand Utiliser des Requêtes Brutes](#quand-utiliser-des-requêtes-brutes)
- [Exécution de Requêtes Brutes](#exécution-de-requêtes-brutes)
- [Gestion des Paramètres](#gestion-des-paramètres)
- [Requêtes Complexes](#requêtes-complexes)
- [Procédures Stockées](#procédures-stockées)
- [Fonctions de Base de Données](#fonctions-de-base-de-données)
- [Requêtes Spécifiques au SGBD](#requêtes-spécifiques-au-sgbd)
- [Sécurité et Bonnes Pratiques](#sécurité-et-bonnes-pratiques)
- [Exemples Avancés](#exemples-avancés)

## Introduction

Bien que le Query Builder offre une interface élégante pour construire des requêtes, il existe des situations où l'utilisation de SQL brut est nécessaire ou plus appropriée. Ce guide couvre ces cas d'usage et les meilleures pratiques pour exécuter des requêtes SQL natives.

## Quand Utiliser des Requêtes Brutes

### Cas d'Usage Appropriés

1. **Requêtes très complexes** non supportées par le Query Builder
2. **Fonctions spécifiques au SGBD** (MySQL, PostgreSQL, etc.)
3. **Procédures stockées** et fonctions
4. **Optimisations de performance** critiques
5. **Requêtes existantes** déjà écrites en SQL
6. **Fonctionnalités avancées** du SGBD

### Inconvénients à Considérer

- Perte de la **portabilité** entre SGBD
- **Sécurité** : risque d'injection SQL
- **Maintenance** plus difficile
- Pas de **validation** automatique

## Exécution de Requêtes Brutes

### Méthodes de Base

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

$queryBuilder = new QueryBuilder($databaseDriver);

// Requête SELECT simple
$users = $queryBuilder
    ->raw('SELECT * FROM users WHERE age > ?', [18])
    ->fetchAll();

// Requête avec un seul résultat
$user = $queryBuilder
    ->raw('SELECT * FROM users WHERE id = ?', [123])
    ->fetch();

// Requête de modification (INSERT/UPDATE/DELETE)
$result = $queryBuilder
    ->raw('UPDATE users SET last_login = NOW() WHERE id = ?', [123])
    ->execute();
```

### Interface Raw Query

```php
<?php
declare(strict_types=1);

interface RawQueryInterface
{
    public function raw(string $sql, array $parameters = []): self;
    public function execute(): QueryResult;
    public function fetch(): ?array;
    public function fetchAll(): array;
    public function fetchColumn(int $column = 0): mixed;
    public function fetchCount(): int;
}
```

## Gestion des Paramètres

### Paramètres Nommés

```php
<?php
declare(strict_types=1);

// Paramètres nommés
$users = $queryBuilder
    ->raw('
        SELECT * FROM users 
        WHERE age BETWEEN :min_age AND :max_age 
        AND city = :city
    ', [
        'min_age' => 18,
        'max_age' => 65,
        'city' => 'Paris'
    ])
    ->fetchAll();
```

### Paramètres Positionnels

```php
<?php
declare(strict_types=1);

// Paramètres positionnels
$orders = $queryBuilder
    ->raw('
        SELECT o.*, u.name as customer_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.created_at >= ? AND o.status = ?
        ORDER BY o.created_at DESC
    ', ['2024-01-01', 'completed'])
    ->fetchAll();
```

### Échappement Manuel (À Éviter)

```php
<?php
declare(strict_types=1);

// ⚠️ DANGEREUX - Ne jamais faire cela
$badQuery = "SELECT * FROM users WHERE name = '" . $_GET['name'] . "'";

// ✅ CORRECT - Toujours utiliser des paramètres
$goodQuery = $queryBuilder
    ->raw('SELECT * FROM users WHERE name = ?', [$_GET['name']])
    ->fetchAll();
```

## Requêtes Complexes

### Requêtes avec CTE Avancées

```php
<?php
declare(strict_types=1);

$hierarchicalData = $queryBuilder
    ->raw('
        WITH RECURSIVE employee_hierarchy AS (
            -- Ancre: employés sans manager (top level)
            SELECT 
                id, 
                name, 
                manager_id, 
                salary,
                1 as level,
                CAST(name AS VARCHAR(1000)) as path,
                CAST(id AS VARCHAR(1000)) as id_path
            FROM employees 
            WHERE manager_id IS NULL
            
            UNION ALL
            
            -- Récursion: employés avec managers
            SELECT 
                e.id, 
                e.name, 
                e.manager_id, 
                e.salary,
                eh.level + 1,
                CONCAT(eh.path, " > ", e.name),
                CONCAT(eh.id_path, ".", e.id)
            FROM employees e
            INNER JOIN employee_hierarchy eh ON e.manager_id = eh.id
            WHERE eh.level < 10 -- Limite de sécurité
        )
        SELECT 
            level,
            LPAD("", (level - 1) * 2, " ") || name as indented_name,
            salary,
            path,
            (
                SELECT AVG(salary) 
                FROM employees 
                WHERE FIND_IN_SET(id, REPLACE(eh.id_path, ".", ",")) > 0
            ) as team_avg_salary
        FROM employee_hierarchy eh
        ORDER BY id_path
    ')
    ->fetchAll();
```

### Requêtes d'Analyse Temporelle

```php
<?php
declare(strict_types=1);

$timeAnalysis = $queryBuilder
    ->raw('
        SELECT 
            DATE(created_at) as date,
            SUM(total) as daily_revenue,
            COUNT(*) as order_count,
            AVG(total) as avg_order_value,
            
            -- Fenêtres glissantes
            SUM(total) OVER (
                ORDER BY DATE(created_at) 
                ROWS BETWEEN 6 PRECEDING AND CURRENT ROW
            ) as week_revenue,
            
            AVG(total) OVER (
                ORDER BY DATE(created_at) 
                ROWS BETWEEN 29 PRECEDING AND CURRENT ROW
            ) as month_avg,
            
            -- Comparaisons périodiques
            LAG(SUM(total), 1) OVER (ORDER BY DATE(created_at)) as prev_day_revenue,
            LAG(SUM(total), 7) OVER (ORDER BY DATE(created_at)) as week_ago_revenue,
            
            -- Percentiles
            PERCENT_RANK() OVER (ORDER BY SUM(total)) as revenue_percentile
            
        FROM orders
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ')
    ->fetchAll();
```

## Procédures Stockées

### Appel de Procédures Stockées

```php
<?php
declare(strict_types=1);

// Procédure sans paramètres
$result = $queryBuilder
    ->raw('CALL GetMonthlyStats()')
    ->fetchAll();

// Procédure avec paramètres d'entrée
$result = $queryBuilder
    ->raw('CALL GetUserStats(?, ?)', [2024, 'active'])
    ->fetchAll();

// Procédure avec paramètres de sortie
$queryBuilder
    ->raw('CALL CalculateBonus(?, @bonus_amount)', [123])
    ->execute();

$bonus = $queryBuilder
    ->raw('SELECT @bonus_amount as bonus')
    ->fetch()['bonus'];
```

### Gestion des Multiples Résultats

```php
<?php
declare(strict_types=1);

class StoredProcedureHelper
{
    private QueryBuilder $queryBuilder;
    
    public function callMultiResultProcedure(int $userId): array
    {
        $results = [];
        
        // Exécuter la procédure
        $stmt = $this->queryBuilder
            ->raw('CALL GetUserCompleteProfile(?)', [$userId])
            ->getStatement();
        
        // Premier jeu de résultats : informations utilisateur
        $results['user'] = $stmt->fetchAll();
        
        // Passer au jeu suivant
        $stmt->nextRowset();
        
        // Deuxième jeu : commandes
        $results['orders'] = $stmt->fetchAll();
        
        // Troisième jeu : statistiques
        $stmt->nextRowset();
        $results['stats'] = $stmt->fetchAll();
        
        return $results;
    }
}
```

## Fonctions de Base de Données

### Fonctions d'Agrégation Avancées

```php
<?php
declare(strict_types=1);

// Fonctions statistiques MySQL
$statistics = $queryBuilder
    ->raw('
        SELECT 
            department,
            COUNT(*) as employee_count,
            AVG(salary) as avg_salary,
            STDDEV(salary) as salary_stddev,
            VARIANCE(salary) as salary_variance,
            MIN(salary) as min_salary,
            MAX(salary) as max_salary,
            
            -- Percentiles
            PERCENTILE_CONT(0.25) WITHIN GROUP (ORDER BY salary) as q1,
            PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY salary) as median,
            PERCENTILE_CONT(0.75) WITHIN GROUP (ORDER BY salary) as q3,
            PERCENTILE_CONT(0.9) WITHIN GROUP (ORDER BY salary) as p90
            
        FROM employees
        GROUP BY department
        ORDER BY avg_salary DESC
    ')
    ->fetchAll();
```

### Fonctions JSON (MySQL 5.7+)

```php
<?php
declare(strict_types=1);

// Manipulation de données JSON
$jsonData = $queryBuilder
    ->raw('
        SELECT 
            id,
            name,
            JSON_EXTRACT(metadata, "$.age") as age,
            JSON_EXTRACT(metadata, "$.skills") as skills,
            JSON_LENGTH(metadata, "$.skills") as skill_count,
            JSON_CONTAINS(metadata, ?, "$.skills") as has_php
        FROM users
        WHERE JSON_EXTRACT(metadata, "$.active") = true
    ', ['"PHP"'])
    ->fetchAll();

// Mise à jour de champs JSON
$queryBuilder
    ->raw('
        UPDATE users 
        SET metadata = JSON_SET(
            metadata, 
            "$.last_login", ?,
            "$.login_count", JSON_EXTRACT(metadata, "$.login_count") + 1
        )
        WHERE id = ?
    ', [date('Y-m-d H:i:s'), 123])
    ->execute();
```

## Requêtes Spécifiques au SGBD

### MySQL Spécifique

```php
<?php
declare(strict_types=1);

// Full-text search MySQL
$searchResults = $queryBuilder
    ->raw('
        SELECT 
            *,
            MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
        FROM articles
        WHERE MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE)
        ORDER BY relevance DESC
    ', [$searchTerm, $searchTerm])
    ->fetchAll();

// Optimisations MySQL
$optimizedQuery = $queryBuilder
    ->raw('
        SELECT SQL_CALC_FOUND_ROWS *
        FROM large_table
        WHERE indexed_column = ?
        LIMIT 10
    ', [$value])
    ->fetchAll();

$totalRows = $queryBuilder
    ->raw('SELECT FOUND_ROWS() as total')
    ->fetch()['total'];
```

### PostgreSQL Spécifique

```php
<?php
declare(strict_types=1);

// Array operations PostgreSQL
$arrayResults = $queryBuilder
    ->raw('
        SELECT 
            name,
            tags,
            array_length(tags, 1) as tag_count,
            ? = ANY(tags) as has_specific_tag
        FROM products
        WHERE tags && ?::text[]
    ', ['electronics', ['electronics', 'gadgets']])
    ->fetchAll();

// Window functions avancées PostgreSQL
$analytics = $queryBuilder
    ->raw('
        SELECT 
            date,
            revenue,
            LAG(revenue, 1) OVER (ORDER BY date) as prev_revenue,
            LEAD(revenue, 1) OVER (ORDER BY date) as next_revenue,
            revenue - LAG(revenue, 1) OVER (ORDER BY date) as day_change,
            CUME_DIST() OVER (ORDER BY revenue) as cumulative_dist,
            NTILE(4) OVER (ORDER BY revenue) as quartile
        FROM daily_sales
        ORDER BY date
    ')
    ->fetchAll();
```

## Sécurité et Bonnes Pratiques

### Validation et Sanitisation

```php
<?php
declare(strict_types=1);

class SecureRawQuery
{
    private QueryBuilder $queryBuilder;
    private array $allowedColumns = ['id', 'name', 'email', 'created_at'];
    private array $allowedTables = ['users', 'orders', 'products'];
    
    public function secureSearch(string $table, array $columns, array $conditions): array
    {
        // Validation de la table
        if (!in_array($table, $this->allowedTables)) {
            throw new \InvalidArgumentException("Table non autorisée: $table");
        }
        
        // Validation des colonnes
        foreach ($columns as $column) {
            if (!in_array($column, $this->allowedColumns)) {
                throw new \InvalidArgumentException("Colonne non autorisée: $column");
            }
        }
        
        // Construction sécurisée
        $columnList = implode(', ', array_map([$this, 'escapeIdentifier'], $columns));
        $tableName = $this->escapeIdentifier($table);
        
        $whereClause = '';
        $params = [];
        
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $column => $value) {
                if (!in_array($column, $this->allowedColumns)) {
                    continue;
                }
                $whereParts[] = $this->escapeIdentifier($column) . ' = ?';
                $params[] = $value;
            }
            $whereClause = ' WHERE ' . implode(' AND ', $whereParts);
        }
        
        return $this->queryBuilder
            ->raw("SELECT {$columnList} FROM {$tableName}{$whereClause}", $params)
            ->fetchAll();
    }
    
    private function escapeIdentifier(string $identifier): string
    {
        // Échappement des identifiants pour MySQL
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
```

### Protection Contre l'Injection SQL

```php
<?php
declare(strict_types=1);

class SqlInjectionProtection
{
    private QueryBuilder $queryBuilder;
    
    // ❌ VULNÉRABLE
    public function vulnerableSearch(string $term): array
    {
        return $this->queryBuilder
            ->raw("SELECT * FROM users WHERE name LIKE '%{$term}%'")
            ->fetchAll();
    }
    
    // ✅ SÉCURISÉ
    public function secureSearch(string $term): array
    {
        return $this->queryBuilder
            ->raw('SELECT * FROM users WHERE name LIKE ?', ["%{$term}%"])
            ->fetchAll();
    }
    
    // ✅ VALIDATION SUPPLÉMENTAIRE
    public function extraSecureSearch(string $term): array
    {
        // Validation et sanitisation
        $term = trim($term);
        if (strlen($term) < 2) {
            throw new \InvalidArgumentException('Le terme de recherche doit contenir au moins 2 caractères');
        }
        
        // Limitation des caractères spéciaux
        if (preg_match('/[<>"\']/', $term)) {
            throw new \InvalidArgumentException('Caractères non autorisés dans le terme de recherche');
        }
        
        return $this->queryBuilder
            ->raw('SELECT id, name, email FROM users WHERE name LIKE ? LIMIT 100', ["%{$term}%"])
            ->fetchAll();
    }
}
```

## Exemples Avancés

### Système de Recommandation

```php
<?php
declare(strict_types=1);

class RecommendationEngine
{
    private QueryBuilder $queryBuilder;
    
    public function getProductRecommendations(int $userId, int $limit = 10): array
    {
        return $this->queryBuilder
            ->raw('
                WITH user_preferences AS (
                    SELECT DISTINCT category_id, AVG(rating) as avg_rating
                    FROM orders o
                    JOIN order_items oi ON o.id = oi.order_id
                    JOIN products p ON oi.product_id = p.id
                    WHERE o.user_id = ? AND rating IS NOT NULL
                    GROUP BY category_id
                ),
                similar_users AS (
                    SELECT 
                        o2.user_id,
                        COUNT(*) as common_purchases,
                        AVG(ABS(oi1.rating - oi2.rating)) as rating_similarity
                    FROM orders o1
                    JOIN order_items oi1 ON o1.id = oi1.order_id
                    JOIN orders o2 ON o2.user_id != o1.user_id
                    JOIN order_items oi2 ON o2.id = oi2.order_id AND oi1.product_id = oi2.product_id
                    WHERE o1.user_id = ?
                    GROUP BY o2.user_id
                    HAVING common_purchases >= 3
                    ORDER BY rating_similarity ASC, common_purchases DESC
                    LIMIT 10
                ),
                recommended_products AS (
                    SELECT 
                        p.id,
                        p.name,
                        p.price,
                        p.category_id,
                        AVG(oi.rating) as avg_rating,
                        COUNT(*) as purchase_count,
                        up.avg_rating as user_category_preference
                    FROM products p
                    JOIN order_items oi ON p.id = oi.product_id
                    JOIN orders o ON oi.order_id = o.id
                    JOIN similar_users su ON o.user_id = su.user_id
                    LEFT JOIN user_preferences up ON p.category_id = up.category_id
                    WHERE p.id NOT IN (
                        SELECT DISTINCT product_id 
                        FROM order_items oi2 
                        JOIN orders o2 ON oi2.order_id = o2.id 
                        WHERE o2.user_id = ?
                    )
                    GROUP BY p.id, p.name, p.price, p.category_id, up.avg_rating
                )
                SELECT 
                    *,
                    (
                        avg_rating * 0.3 + 
                        COALESCE(user_category_preference, 3) * 0.2 + 
                        LOG(purchase_count + 1) * 0.3 +
                        (5 - ABS(price - (
                            SELECT AVG(price) 
                            FROM products p2 
                            JOIN order_items oi3 ON p2.id = oi3.product_id 
                            JOIN orders o3 ON oi3.order_id = o3.id 
                            WHERE o3.user_id = ?
                        )) / 100) * 0.2
                    ) as recommendation_score
                FROM recommended_products
                ORDER BY recommendation_score DESC
                LIMIT ?
            ', [$userId, $userId, $userId, $userId, $limit])
            ->fetchAll();
    }
}
```

### Audit et Versioning

```php
<?php
declare(strict_types=1);

class AuditSystem
{
    private QueryBuilder $queryBuilder;
    
    public function getEntityHistory(string $entityType, int $entityId): array
    {
        return $this->queryBuilder
            ->raw('
                SELECT 
                    a.*,
                    u.name as user_name,
                    LAG(a.changes) OVER (
                        PARTITION BY a.entity_type, a.entity_id 
                        ORDER BY a.created_at
                    ) as previous_changes,
                    LEAD(a.created_at) OVER (
                        PARTITION BY a.entity_type, a.entity_id 
                        ORDER BY a.created_at
                    ) as next_change_date
                FROM audit_log a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.entity_type = ? AND a.entity_id = ?
                ORDER BY a.created_at DESC
            ', [$entityType, $entityId])
            ->fetchAll();
    }
    
    public function getChangeSummary(string $startDate, string $endDate): array
    {
        return $this->queryBuilder
            ->raw('
                SELECT 
                    entity_type,
                    action,
                    COUNT(*) as change_count,
                    COUNT(DISTINCT entity_id) as unique_entities,
                    COUNT(DISTINCT user_id) as unique_users,
                    MIN(created_at) as first_change,
                    MAX(created_at) as last_change
                FROM audit_log
                WHERE created_at BETWEEN ? AND ?
                GROUP BY entity_type, action
                WITH ROLLUP
                ORDER BY entity_type, action
            ', [$startDate, $endDate])
            ->fetchAll();
    }
}
```

## Prochaines Étapes

- [Optimisation des Requêtes](query-optimization.md) - Performance et indexation
- [Requêtes de Base](basic-queries.md) - Fondamentaux du Query Builder
- [Requêtes Avancées](advanced-queries.md) - Jointures et sous-requêtes
