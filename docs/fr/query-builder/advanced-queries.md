# Requêtes Avancées - Query Builder

## Table des Matières
- [Introduction](#introduction)
- [Jointures (JOINS)](#jointures-joins)
- [Sous-requêtes (Subqueries)](#sous-requêtes-subqueries)
- [Expressions de Table Commune (CTE)](#expressions-de-table-commune-cte)
- [Fonctions d'Agrégation](#fonctions-dagrégation)
- [Expressions Conditionnelles](#expressions-conditionnelles)
- [Union et Intersections](#union-et-intersections)
- [Requêtes Récursives](#requêtes-récursives)
- [Fonctions de Fenêtrage](#fonctions-de-fenêtrage)
- [Exemples Complexes](#exemples-complexes)

## Introduction

Ce guide couvre les fonctionnalités avancées du Query Builder pour construire des requêtes SQL complexes. Vous apprendrez à utiliser les jointures, sous-requêtes, fonctions d'agrégation et autres fonctionnalités SQL avancées.

## Jointures (JOINS)

### Types de Jointures

```php
<?php
declare(strict_types=1);

// INNER JOIN
$users = $queryBuilder
    ->select(['u.name', 'p.bio', 'r.name as role_name'])
    ->from('users', 'u')
    ->innerJoin('u', 'profiles', 'p', 'u.id = p.user_id')
    ->innerJoin('u', 'roles', 'r', 'u.role_id = r.id')
    ->execute()
    ->fetchAll();

// LEFT JOIN
$users = $queryBuilder
    ->select(['u.name', 'p.bio'])
    ->from('users', 'u')
    ->leftJoin('u', 'profiles', 'p', 'u.id = p.user_id')
    ->execute()
    ->fetchAll();

// RIGHT JOIN
$data = $queryBuilder
    ->select(['u.name', 'o.total'])
    ->from('users', 'u')
    ->rightJoin('u', 'orders', 'o', 'u.id = o.user_id')
    ->execute()
    ->fetchAll();
```

### Jointures avec Conditions

```php
<?php
declare(strict_types=1);

// JOIN avec conditions supplémentaires
$activeUserOrders = $queryBuilder
    ->select(['u.name', 'o.total', 'o.status'])
    ->from('users', 'u')
    ->innerJoin('u', 'orders', 'o', 'u.id = o.user_id AND o.status = ?', ['active'])
    ->where('u.status = ?', ['active'])
    ->execute()
    ->fetchAll();

// JOIN avec OR dans la condition
$result = $queryBuilder
    ->select(['u.name', 'c.name as company'])
    ->from('users', 'u')
    ->leftJoin('u', 'companies', 'c', 'u.company_id = c.id OR u.partner_company_id = c.id')
    ->execute()
    ->fetchAll();
```

### Jointures Auto-référentielles

```php
<?php
declare(strict_types=1);

// Hiérarchie utilisateur (manager/employé)
$hierarchy = $queryBuilder
    ->select(['e.name as employee', 'm.name as manager'])
    ->from('employees', 'e')
    ->leftJoin('e', 'employees', 'm', 'e.manager_id = m.id')
    ->orderBy('m.name', 'ASC')
    ->addOrderBy('e.name', 'ASC')
    ->execute()
    ->fetchAll();
```

## Sous-requêtes (Subqueries)

### Sous-requêtes dans SELECT

```php
<?php
declare(strict_types=1);

// Sous-requête scalaire
$users = $queryBuilder
    ->select([
        'u.name',
        'u.email',
        '(SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as order_count'
    ])
    ->from('users', 'u')
    ->execute()
    ->fetchAll();

// Avec Query Builder imbriqué
$subQuery = $queryBuilder->createSubQuery()
    ->select(['COUNT(*)'])
    ->from('orders')
    ->where('user_id = u.id');

$users = $queryBuilder
    ->select(['u.name', 'u.email'])
    ->addSelect('(' . $subQuery->getSQL() . ') as order_count')
    ->from('users', 'u')
    ->setParameters(array_merge($queryBuilder->getParameters(), $subQuery->getParameters()))
    ->execute()
    ->fetchAll();
```

### Sous-requêtes dans WHERE

```php
<?php
declare(strict_types=1);

// EXISTS
$usersWithOrders = $queryBuilder
    ->select(['*'])
    ->from('users', 'u')
    ->where('EXISTS (SELECT 1 FROM orders o WHERE o.user_id = u.id)')
    ->execute()
    ->fetchAll();

// IN avec sous-requête
$topCustomers = $queryBuilder
    ->select(['*'])
    ->from('users')
    ->where('id IN (
        SELECT user_id 
        FROM orders 
        WHERE total > 1000 
        GROUP BY user_id 
        HAVING COUNT(*) > 5
    )')
    ->execute()
    ->fetchAll();

// Comparaison avec sous-requête
$aboveAverage = $queryBuilder
    ->select(['*'])
    ->from('products')
    ->where('price > (SELECT AVG(price) FROM products)')
    ->execute()
    ->fetchAll();
```

### Sous-requêtes dans FROM

```php
<?php
declare(strict_types=1);

// Table dérivée
$monthlyStats = $queryBuilder
    ->select(['monthly.month', 'monthly.total_sales', 'monthly.order_count'])
    ->from('(
        SELECT 
            DATE_FORMAT(created_at, "%Y-%m") as month,
            SUM(total) as total_sales,
            COUNT(*) as order_count
        FROM orders 
        WHERE created_at >= "2024-01-01"
        GROUP BY DATE_FORMAT(created_at, "%Y-%m")
    )', 'monthly')
    ->orderBy('monthly.month', 'DESC')
    ->execute()
    ->fetchAll();
```

## Expressions de Table Commune (CTE)

### CTE Simple

```php
<?php
declare(strict_types=1);

// Common Table Expression
$result = $queryBuilder
    ->with('sales_summary', '
        SELECT 
            user_id,
            SUM(total) as total_sales,
            COUNT(*) as order_count
        FROM orders 
        WHERE created_at >= "2024-01-01"
        GROUP BY user_id
    ')
    ->select(['u.name', 's.total_sales', 's.order_count'])
    ->from('users', 'u')
    ->innerJoin('u', 'sales_summary', 's', 'u.id = s.user_id')
    ->where('s.total_sales > ?', [10000])
    ->execute()
    ->fetchAll();
```

### CTE Récursive

```php
<?php
declare(strict_types=1);

// Hiérarchie organisationnelle
$hierarchy = $queryBuilder
    ->withRecursive('employee_hierarchy', '
        SELECT id, name, manager_id, 1 as level
        FROM employees 
        WHERE manager_id IS NULL
        
        UNION ALL
        
        SELECT e.id, e.name, e.manager_id, eh.level + 1
        FROM employees e
        INNER JOIN employee_hierarchy eh ON e.manager_id = eh.id
    ')
    ->select(['*'])
    ->from('employee_hierarchy')
    ->orderBy('level', 'ASC')
    ->addOrderBy('name', 'ASC')
    ->execute()
    ->fetchAll();
```

## Fonctions d'Agrégation

### Agrégations Basiques

```php
<?php
declare(strict_types=1);

// Statistiques de base
$stats = $queryBuilder
    ->select([
        'COUNT(*) as total_users',
        'COUNT(DISTINCT city) as unique_cities',
        'AVG(age) as average_age',
        'MIN(created_at) as first_user',
        'MAX(created_at) as last_user'
    ])
    ->from('users')
    ->execute()
    ->fetch();
```

### Agrégations Conditionnelles

```php
<?php
declare(strict_types=1);

// Comptage conditionnel
$stats = $queryBuilder
    ->select([
        'department',
        'COUNT(*) as total_employees',
        'SUM(CASE WHEN gender = "F" THEN 1 ELSE 0 END) as female_count',
        'SUM(CASE WHEN gender = "M" THEN 1 ELSE 0 END) as male_count',
        'AVG(CASE WHEN active = 1 THEN salary ELSE NULL END) as avg_active_salary'
    ])
    ->from('employees')
    ->groupBy('department')
    ->execute()
    ->fetchAll();
```

### Fonctions de Chaîne et Date

```php
<?php
declare(strict_types=1);

// Manipulation de chaînes et dates
$report = $queryBuilder
    ->select([
        'CONCAT(first_name, " ", last_name) as full_name',
        'UPPER(email) as email_upper',
        'DATE_FORMAT(created_at, "%Y-%m") as registration_month',
        'DATEDIFF(NOW(), created_at) as days_since_registration',
        'CASE 
            WHEN age < 25 THEN "Young"
            WHEN age < 50 THEN "Adult" 
            ELSE "Senior"
         END as age_group'
    ])
    ->from('users')
    ->execute()
    ->fetchAll();
```

## Expressions Conditionnelles

### CASE Statements

```php
<?php
declare(strict_types=1);

// Classification des utilisateurs
$userClasses = $queryBuilder
    ->select([
        'name',
        'total_spent',
        'CASE 
            WHEN total_spent >= 10000 THEN "VIP"
            WHEN total_spent >= 5000 THEN "Gold"
            WHEN total_spent >= 1000 THEN "Silver"
            ELSE "Bronze"
         END as customer_tier'
    ])
    ->from('(
        SELECT 
            u.name,
            COALESCE(SUM(o.total), 0) as total_spent
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        GROUP BY u.id, u.name
    )', 'customer_data')
    ->orderBy('total_spent', 'DESC')
    ->execute()
    ->fetchAll();
```

### Expressions NULL

```php
<?php
declare(strict_types=1);

// Gestion des valeurs NULL
$data = $queryBuilder
    ->select([
        'name',
        'COALESCE(phone, email, "No contact") as contact_info',
        'IFNULL(avatar, "/default-avatar.png") as avatar_url',
        'CASE WHEN last_login IS NULL THEN "Never" ELSE last_login END as last_activity'
    ])
    ->from('users')
    ->execute()
    ->fetchAll();
```

## Union et Intersections

### UNION

```php
<?php
declare(strict_types=1);

// Combinaison de résultats
$allContacts = $queryBuilder
    ->select(['name', 'email', '"customer" as type'])
    ->from('customers')
    ->union()
    ->select(['name', 'email', '"supplier" as type'])
    ->from('suppliers')
    ->orderBy('name', 'ASC')
    ->execute()
    ->fetchAll();

// UNION ALL (avec doublons)
$allActivities = $queryBuilder
    ->select(['user_id', 'action', 'created_at'])
    ->from('user_logins')
    ->unionAll()
    ->select(['user_id', 'action', 'created_at'])
    ->from('user_purchases')
    ->orderBy('created_at', 'DESC')
    ->execute()
    ->fetchAll();
```

## Fonctions de Fenêtrage

### Ranking Functions

```php
<?php
declare(strict_types=1);

// Classement par département
$rankings = $queryBuilder
    ->select([
        'name',
        'department',
        'salary',
        'ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) as dept_rank',
        'RANK() OVER (ORDER BY salary DESC) as overall_rank',
        'DENSE_RANK() OVER (PARTITION BY department ORDER BY salary DESC) as dense_rank'
    ])
    ->from('employees')
    ->execute()
    ->fetchAll();
```

### Analytical Functions

```php
<?php
declare(strict_types=1);

// Fonctions analytiques
$analytics = $queryBuilder
    ->select([
        'order_date',
        'total',
        'SUM(total) OVER (ORDER BY order_date) as running_total',
        'AVG(total) OVER (ORDER BY order_date ROWS BETWEEN 6 PRECEDING AND CURRENT ROW) as moving_avg_7days',
        'LAG(total, 1) OVER (ORDER BY order_date) as previous_day_total',
        'LEAD(total, 1) OVER (ORDER BY order_date) as next_day_total'
    ])
    ->from('daily_sales')
    ->orderBy('order_date', 'ASC')
    ->execute()
    ->fetchAll();
```

## Exemples Complexes

### Analyse de Cohorte

```php
<?php
declare(strict_types=1);

class CohortAnalysis
{
    private QueryBuilder $queryBuilder;
    
    public function getUserCohorts(): array
    {
        return $this->queryBuilder
            ->with('user_cohorts', '
                SELECT 
                    user_id,
                    DATE_FORMAT(MIN(created_at), "%Y-%m") as cohort_month,
                    MIN(created_at) as first_purchase
                FROM orders
                GROUP BY user_id
            ')
            ->with('cohort_data', '
                SELECT 
                    uc.cohort_month,
                    DATE_FORMAT(o.created_at, "%Y-%m") as order_month,
                    COUNT(DISTINCT o.user_id) as users
                FROM user_cohorts uc
                JOIN orders o ON uc.user_id = o.user_id
                GROUP BY uc.cohort_month, DATE_FORMAT(o.created_at, "%Y-%m")
            ')
            ->select([
                'cohort_month',
                'order_month',
                'users',
                'ROUND(
                    users * 100.0 / FIRST_VALUE(users) OVER (
                        PARTITION BY cohort_month 
                        ORDER BY order_month
                    ), 2
                ) as retention_rate'
            ])
            ->from('cohort_data')
            ->orderBy('cohort_month', 'ASC')
            ->addOrderBy('order_month', 'ASC')
            ->execute()
            ->fetchAll();
    }
}
```

### Rapport de Performance Hiérarchique

```php
<?php
declare(strict_types=1);

class PerformanceReport
{
    private QueryBuilder $queryBuilder;
    
    public function getTeamPerformance(): array
    {
        return $this->queryBuilder
            ->withRecursive('team_hierarchy', '
                SELECT 
                    id, 
                    name, 
                    manager_id, 
                    department,
                    0 as level,
                    CAST(id AS CHAR(500)) as path
                FROM employees 
                WHERE manager_id IS NULL
                
                UNION ALL
                
                SELECT 
                    e.id, 
                    e.name, 
                    e.manager_id, 
                    e.department,
                    th.level + 1,
                    CONCAT(th.path, ".", e.id)
                FROM employees e
                JOIN team_hierarchy th ON e.manager_id = th.id
                WHERE th.level < 10
            ')
            ->select([
                'th.name',
                'th.level',
                'th.department',
                'COALESCE(sales.total_sales, 0) as individual_sales',
                'COALESCE(team_sales.team_total, 0) as team_sales',
                'COUNT(subordinates.id) as direct_reports'
            ])
            ->from('team_hierarchy', 'th')
            ->leftJoin('th', '(
                SELECT 
                    employee_id, 
                    SUM(amount) as total_sales
                FROM sales 
                WHERE date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
                GROUP BY employee_id
            )', 'sales', 'th.id = sales.employee_id')
            ->leftJoin('th', '(
                SELECT 
                    manager_path,
                    SUM(amount) as team_total
                FROM sales s
                JOIN team_hierarchy sub ON s.employee_id = sub.id
                WHERE s.date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
                GROUP BY manager_path
            )', 'team_sales', 'th.path = team_sales.manager_path')
            ->leftJoin('th', 'team_hierarchy', 'subordinates', 'th.id = subordinates.manager_id')
            ->groupBy(['th.id', 'th.name', 'th.level', 'th.department'])
            ->orderBy('th.level', 'ASC')
            ->addOrderBy('individual_sales', 'DESC')
            ->execute()
            ->fetchAll();
    }
}
```

### Détection d'Anomalies

```php
<?php
declare(strict_types=1);

class AnomalyDetection
{
    private QueryBuilder $queryBuilder;
    
    public function detectSalesAnomalies(): array
    {
        return $this->queryBuilder
            ->with('daily_stats', '
                SELECT 
                    DATE(created_at) as sale_date,
                    SUM(total) as daily_total,
                    COUNT(*) as order_count,
                    AVG(total) as avg_order_value
                FROM orders
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY DATE(created_at)
            ')
            ->with('stats_with_moving_avg', '
                SELECT 
                    sale_date,
                    daily_total,
                    order_count,
                    avg_order_value,
                    AVG(daily_total) OVER (
                        ORDER BY sale_date 
                        ROWS BETWEEN 13 PRECEDING AND 1 PRECEDING
                    ) as moving_avg_14d,
                    STDDEV(daily_total) OVER (
                        ORDER BY sale_date 
                        ROWS BETWEEN 13 PRECEDING AND 1 PRECEDING
                    ) as moving_stddev_14d
                FROM daily_stats
            ')
            ->select([
                'sale_date',
                'daily_total',
                'moving_avg_14d',
                'ABS(daily_total - moving_avg_14d) / NULLIF(moving_stddev_14d, 0) as z_score',
                'CASE 
                    WHEN ABS(daily_total - moving_avg_14d) / NULLIF(moving_stddev_14d, 0) > 2 
                    THEN "ANOMALY"
                    ELSE "NORMAL"
                 END as status'
            ])
            ->from('stats_with_moving_avg')
            ->where('moving_avg_14d IS NOT NULL')
            ->orderBy('sale_date', 'DESC')
            ->execute()
            ->fetchAll();
    }
}
```

## Prochaines Étapes

- [Requêtes SQL Brutes](raw-queries.md) - Quand et comment utiliser du SQL natif
- [Optimisation des Requêtes](query-optimization.md) - Performance et indexation
- [Requêtes de Base](basic-queries.md) - Retour aux fondamentaux
