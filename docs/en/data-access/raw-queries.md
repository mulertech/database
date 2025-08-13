# Raw Queries

Guide for executing raw SQL queries when the Query Builder is not sufficient.

## When to Use Raw SQL

Use raw SQL in these scenarios:
- Complex analytical queries with window functions
- Database-specific features not supported by Query Builder
- Performance-critical queries requiring fine-tuning
- Existing SQL that would be difficult to convert

```php
// Complex analytics query
$sql = "
    SELECT 
        user_id,
        name,
        SUM(order_total) as lifetime_value,
        RANK() OVER (ORDER BY SUM(order_total) DESC) as value_rank
    FROM users u
    JOIN orders o ON u.id = o.user_id 
    WHERE o.status = 'completed'
    GROUP BY user_id, name
    HAVING SUM(order_total) > 1000
";
```

## Executing Raw Queries

### Basic Execution

```php
class AnalyticsRepository extends EntityRepository
{
    public function getTopCustomers(int $limit = 10): array
    {
        $sql = "
            SELECT 
                c.id,
                c.name,
                c.email,
                SUM(o.total) as total_spent,
                COUNT(o.id) as order_count
            FROM customers c
            JOIN orders o ON c.id = o.customer_id
            WHERE o.status = 'completed'
            GROUP BY c.id, c.name, c.email
            ORDER BY total_spent DESC
            LIMIT ?
        ";

        $statement = $this->getEntityManager()
            ->getEmEngine()
            ->executeQuery($sql, [$limit]);

        return $statement->fetchAll();
    }
}
```

### Parameter Binding

Always use parameter binding to prevent SQL injection:

```php
class UserRepository extends EntityRepository
{
    public function findUsersByCustomCriteria(string $email, DateTime $since, array $roles): array
    {
        $sql = "
            SELECT u.* 
            FROM users u
            WHERE u.email LIKE ?
            AND u.created_at >= ?
            AND u.role IN (" . str_repeat('?,', count($roles) - 1) . "?)
            ORDER BY u.created_at DESC
        ";

        $params = [
            "%{$email}%",
            $since->format('Y-m-d H:i:s'),
            ...$roles
        ];

        $statement = $this->getEntityManager()
            ->getEmEngine()
            ->executeQuery($sql, $params);

        return $statement->fetchAll();
    }
}
```

## Complex Analytics

### Revenue Analytics

```php
class RevenueRepository extends EntityRepository
{
    public function getMonthlyRevenueWithGrowth(): array
    {
        $sql = "
            WITH monthly_revenue AS (
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    SUM(total) as revenue,
                    COUNT(*) as order_count
                FROM orders 
                WHERE status = 'completed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ),
            revenue_with_growth AS (
                SELECT 
                    month,
                    revenue,
                    order_count,
                    LAG(revenue, 1) OVER (ORDER BY month) as prev_month_revenue,
                    LAG(revenue, 12) OVER (ORDER BY month) as prev_year_revenue
                FROM monthly_revenue
            )
            SELECT 
                month,
                revenue,
                order_count,
                prev_month_revenue,
                prev_year_revenue,
                CASE 
                    WHEN prev_month_revenue > 0 THEN 
                        ROUND(((revenue - prev_month_revenue) / prev_month_revenue) * 100, 2)
                    ELSE NULL
                END as month_over_month_growth,
                CASE 
                    WHEN prev_year_revenue > 0 THEN 
                        ROUND(((revenue - prev_year_revenue) / prev_year_revenue) * 100, 2)
                    ELSE NULL
                END as year_over_year_growth
            FROM revenue_with_growth
            ORDER BY month DESC
            LIMIT 12
        ";

        $statement = $this->getEntityManager()
            ->getEmEngine()
            ->executeQuery($sql);

        return $statement->fetchAll();
    }
}
```

### Customer Segmentation

```php
class CustomerSegmentationRepository extends EntityRepository
{
    public function getCustomerSegments(): array
    {
        $sql = "
            WITH customer_metrics AS (
                SELECT 
                    c.id,
                    c.name,
                    c.email,
                    c.created_at as first_order_date,
                    COUNT(o.id) as total_orders,
                    SUM(o.total) as total_spent,
                    AVG(o.total) as avg_order_value,
                    MAX(o.created_at) as last_order_date,
                    DATEDIFF(NOW(), MAX(o.created_at)) as days_since_last_order
                FROM customers c
                LEFT JOIN orders o ON c.id = o.customer_id AND o.status = 'completed'
                GROUP BY c.id, c.name, c.email, c.created_at
            ),
            customer_segments AS (
                SELECT 
                    *,
                    CASE 
                        WHEN total_spent >= 5000 AND days_since_last_order <= 90 THEN 'VIP Active'
                        WHEN total_spent >= 5000 AND days_since_last_order > 90 THEN 'VIP At Risk'
                        WHEN total_spent >= 1000 AND days_since_last_order <= 60 THEN 'High Value Active'
                        WHEN total_spent >= 1000 AND days_since_last_order > 60 THEN 'High Value At Risk'
                        WHEN total_orders >= 3 AND days_since_last_order <= 90 THEN 'Regular Active'
                        WHEN total_orders >= 3 AND days_since_last_order > 90 THEN 'Regular At Risk'
                        WHEN total_orders > 0 AND days_since_last_order <= 180 THEN 'Occasional'
                        WHEN total_orders > 0 THEN 'Dormant'
                        ELSE 'New'
                    END as segment
                FROM customer_metrics
            )
            SELECT 
                segment,
                COUNT(*) as customer_count,
                AVG(total_spent) as avg_total_spent,
                AVG(total_orders) as avg_total_orders,
                AVG(avg_order_value) as avg_order_value
            FROM customer_segments
            GROUP BY segment
            ORDER BY avg_total_spent DESC
        ";

        $statement = $this->getEntityManager()
            ->getEmEngine()
            ->executeQuery($sql);

        return $statement->fetchAll();
    }
}
```

## Database-Specific Features

### MySQL-Specific Queries

```php
class MySQLSpecificRepository extends EntityRepository
{
    public function getFullTextSearch(string $query): array
    {
        $sql = "
            SELECT 
                id,
                title,
                content,
                MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance_score
            FROM articles 
            WHERE MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE)
            ORDER BY relevance_score DESC
            LIMIT 20
        ";

        $statement = $this->getEntityManager()
            ->getEmEngine()
            ->executeQuery($sql, [$query, $query]);

        return $statement->fetchAll();
    }

    public function getGeoSpatialResults(float $lat, float $lng, float $radiusKm): array
    {
        $sql = "
            SELECT 
                id,
                name,
                latitude,
                longitude,
                ST_Distance_Sphere(
                    POINT(longitude, latitude),
                    POINT(?, ?)
                ) / 1000 as distance_km
            FROM locations
            WHERE ST_Distance_Sphere(
                POINT(longitude, latitude),
                POINT(?, ?)
            ) / 1000 <= ?
            ORDER BY distance_km ASC
        ";

        $statement = $this->getEntityManager()
            ->getEmEngine()
            ->executeQuery($sql, [$lng, $lat, $lng, $lat, $radiusKm]);

        return $statement->fetchAll();
    }
}
```

### PostgreSQL-Specific Queries

```php
class PostgreSQLSpecificRepository extends EntityRepository
{
    public function getJsonAnalytics(): array
    {
        $sql = "
            SELECT 
                jsonb_extract_path_text(metadata, 'category') as category,
                COUNT(*) as count,
                AVG(CAST(jsonb_extract_path_text(metadata, 'rating') AS NUMERIC)) as avg_rating
            FROM products 
            WHERE metadata ? 'category'
            AND metadata ? 'rating'
            GROUP BY jsonb_extract_path_text(metadata, 'category')
            ORDER BY count DESC
        ";

        $statement = $this->getEntityManager()
            ->getEmEngine()
            ->executeQuery($sql);

        return $statement->fetchAll();
    }

    public function getTimeSeriesData(DateTime $startDate, DateTime $endDate): array
    {
        $sql = "
            SELECT 
                date_trunc('day', created_at) as day,
                COUNT(*) as orders_count,
                SUM(total) as daily_revenue
            FROM orders
            WHERE created_at BETWEEN ? AND ?
            AND status = 'completed'
            GROUP BY date_trunc('day', created_at)
            ORDER BY day
        ";

        $statement = $this->getEntityManager()
            ->getEmEngine()
            ->executeQuery($sql, [
            $startDate->format('Y-m-d H:i:s'),
            $endDate->format('Y-m-d H:i:s')
        ]);

        return $statement->fetchAll();
    }
}
```

## Performance Optimization

### Index Hints

```php
class OptimizedRepository extends EntityRepository
{
    public function getOptimizedUserOrders(int $userId): array
    {
        // MySQL index hints
        $sql = "
            SELECT o.*
            FROM orders o USE INDEX (idx_user_status_date)
            WHERE o.user_id = ?
            AND o.status IN ('completed', 'shipped')
            ORDER BY o.created_at DESC
            LIMIT 50
        ";

        $statement = $this->getEntityManager()
            ->getEmEngine()
            ->executeQuery($sql, [$userId]);

        return $statement->fetchAll();
    }
}
```

### Query Plan Analysis

```php
class QueryAnalysisRepository extends EntityRepository
{
    public function analyzeQueryPerformance(string $sql, array $params = []): array
    {
        // Add EXPLAIN to analyze query performance
        $explainSql = "EXPLAIN FORMAT=JSON " . $sql;
        
        $statement = $this->getEntityManager()
            ->getEmEngine()
            ->executeQuery($explainSql, $params);

        $result = $statement->fetch();
        
        // Log or return query plan for analysis
        error_log('Query Plan: ' . $result['EXPLAIN']);
        
        return json_decode($result['EXPLAIN'], true);
    }
}
```

## Error Handling

### Safe Execution with Validation

```php
class SafeRawQueryRepository extends EntityRepository
{
    private array $allowedTables = ['users', 'orders', 'products', 'customers'];
    private array $allowedColumns = ['id', 'name', 'email', 'created_at', 'total'];

    public function executeSafeQuery(string $table, array $columns, array $conditions): array
    {
        // Validate table name
        if (!in_array($table, $this->allowedTables)) {
            throw new InvalidArgumentException("Table '{$table}' not allowed");
        }

        // Validate column names
        foreach ($columns as $column) {
            if (!in_array($column, $this->allowedColumns)) {
                throw new InvalidArgumentException("Column '{$column}' not allowed");
            }
        }

        $columnList = implode(', ', $columns);
        $whereClause = '';
        $params = [];

        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $column => $value) {
                if (!in_array($column, $this->allowedColumns)) {
                    throw new InvalidArgumentException("Condition column '{$column}' not allowed");
                }
                $whereParts[] = "{$column} = ?";
                $params[] = $value;
            }
            $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
        }

        $sql = "SELECT {$columnList} FROM {$table} {$whereClause}";

        try {
            $statement = $this->getEntityManager()
                ->getEmEngine()
                ->executeQuery($sql, $params);

            return $statement->fetchAll();

        } catch (Exception $e) {
            // Log the error with context
            error_log("Raw query failed: {$sql} with params: " . json_encode($params));
            throw new DatabaseException('Query execution failed', 0, $e);
        }
    }
}
```

## Query Caching

### Cached Raw Queries

```php
class CachedAnalyticsRepository extends EntityRepository
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache
    ) {
        parent::__construct($entityManager);
    }

    public function getDashboardMetrics(): array
    {
        $cacheKey = 'dashboard_metrics_' . date('Y-m-d-H');
        
        // Try cache first
        $metrics = $this->cache->get($cacheKey);
        if ($metrics !== null) {
            return $metrics;
        }

        // Execute expensive query
        $sql = "
            SELECT 
                (SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()) as new_users_today,
                (SELECT COUNT(*) FROM orders WHERE created_at >= CURDATE()) as orders_today,
                (SELECT COALESCE(SUM(total), 0) FROM orders WHERE created_at >= CURDATE() AND status = 'completed') as revenue_today,
                (SELECT COUNT(*) FROM products WHERE stock <= 10) as low_stock_products
        ";

        $statement = $this->getEntityManager()
            ->getEmEngine()
            ->executeQuery($sql);

        $metrics = $statement->fetch();

        // Cache for 1 hour
        $this->cache->set($cacheKey, $metrics, 3600);

        return $metrics;
    }
}
```

## Migration and Data Operations

### Bulk Data Operations

```php
class BulkDataRepository extends EntityRepository
{
    public function bulkUpdateUserStatus(array $userIds, string $status): int
    {
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        
        $sql = "
            UPDATE users 
            SET status = ?, updated_at = NOW()
            WHERE id IN ({$placeholders})
        ";

        $params = [$status, ...$userIds];

        return $this->getEntityManager()
            ->getEmEngine()
            ->executeUpdate($sql, $params);
    }

    public function archiveOldRecords(DateTime $cutoffDate): int
    {
        $sql = "
            INSERT INTO orders_archive (id, user_id, total, status, created_at)
            SELECT id, user_id, total, status, created_at
            FROM orders
            WHERE created_at < ? AND status = 'completed'
        ";

        $archiveCount = $this->getEntityManager()
            ->getEmEngine()
            ->executeUpdate($sql, [$cutoffDate->format('Y-m-d')]);

        // Delete archived records
        $deleteSql = "
            DELETE FROM orders 
            WHERE created_at < ? AND status = 'completed'
        ";

        $this->getEntityManager()
            ->getEmEngine()
            ->executeUpdate($deleteSql, [$cutoffDate->format('Y-m-d')]);

        return $archiveCount;
    }
}
```

## Best Practices

### 1. Always Use Parameter Binding

```php
// Good - parameter binding
$sql = "SELECT * FROM users WHERE email = ? AND status = ?";
$result = $em->executeQuery($sql, [$email, $status]);

// Bad - string concatenation (SQL injection risk)
$sql = "SELECT * FROM users WHERE email = '{$email}' AND status = '{$status}'";
```

### 2. Validate Input

```php
public function getCustomReport(string $table, string $column): array
{
    // Whitelist validation
    $allowedTables = ['users', 'orders', 'products'];
    $allowedColumns = ['id', 'name', 'email', 'total'];

    if (!in_array($table, $allowedTables)) {
        throw new InvalidArgumentException('Invalid table');
    }

    if (!in_array($column, $allowedColumns)) {
        throw new InvalidArgumentException('Invalid column');
    }

    $sql = "SELECT {$column} FROM {$table} WHERE active = 1";
    // ... execute query
}
```

### 3. Use Transactions for Multiple Operations

```php
public function complexDataOperation(): void
{
    try {
        $this->getEntityManager()->beginTransaction();

        // Multiple raw SQL operations
        $this->getEntityManager()->getEmEngine()->executeUpdate($sql1, $params1);
        $this->getEntityManager()->getEmEngine()->executeUpdate($sql2, $params2);
        $this->getEntityManager()->getEmEngine()->executeUpdate($sql3, $params3);

        $this->getEntityManager()->commit();

    } catch (Exception $e) {
        $this->getEntityManager()->rollback();
        throw $e;
    }
}
```

### 4. Document Complex Queries

```php
/**
 * Calculates customer lifetime value with advanced segmentation
 * 
 * This query performs the following operations:
 * 1. Calculates total spent per customer
 * 2. Determines customer segments based on RFM analysis
 * 3. Projects future value based on historical patterns
 * 
 * Performance: Uses indexes on (customer_id, created_at) and (status)
 * Expected execution time: ~200ms for 1M orders
 */
public function calculateCustomerLifetimeValue(): array
{
    $sql = "
        WITH customer_rfm AS (
            SELECT 
                customer_id,
                -- Recency: days since last order
                DATEDIFF(NOW(), MAX(created_at)) as recency,
                -- Frequency: number of orders
                COUNT(*) as frequency,
                -- Monetary: total spent
                SUM(total) as monetary
            FROM orders 
            WHERE status = 'completed'
            GROUP BY customer_id
        )
        -- ... rest of complex query
    ";
    
    // ... execute and return results
}
```

## Complete Example

```php
class ComprehensiveAnalyticsRepository extends EntityRepository
{
    public function getBusinessIntelligenceReport(DateTime $startDate, DateTime $endDate): array
    {
        $sql = "
            WITH daily_metrics AS (
                SELECT 
                    DATE(o.created_at) as report_date,
                    COUNT(DISTINCT o.customer_id) as unique_customers,
                    COUNT(o.id) as total_orders,
                    SUM(o.total) as daily_revenue,
                    AVG(o.total) as avg_order_value
                FROM orders o
                WHERE o.created_at BETWEEN ? AND ?
                AND o.status = 'completed'
                GROUP BY DATE(o.created_at)
            ),
            product_performance AS (
                SELECT 
                    DATE(o.created_at) as report_date,
                    p.category,
                    SUM(oi.quantity) as units_sold,
                    SUM(oi.quantity * oi.price) as category_revenue
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                WHERE o.created_at BETWEEN ? AND ?
                AND o.status = 'completed'
                GROUP BY DATE(o.created_at), p.category
            )
            SELECT 
                dm.report_date,
                dm.unique_customers,
                dm.total_orders,
                dm.daily_revenue,
                dm.avg_order_value,
                JSON_OBJECTAGG(
                    pp.category, 
                    JSON_OBJECT(
                        'units_sold', pp.units_sold,
                        'revenue', pp.category_revenue
                    )
                ) as category_breakdown
            FROM daily_metrics dm
            LEFT JOIN product_performance pp ON dm.report_date = pp.report_date
            GROUP BY dm.report_date, dm.unique_customers, dm.total_orders, 
                     dm.daily_revenue, dm.avg_order_value
            ORDER BY dm.report_date DESC
        ";

        $params = [
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        ];

        $statement = $this->getEntityManager()
            ->getEmEngine()
            ->executeQuery($sql, $params);

        return $statement->fetchAll();
    }
}
```

## Next Steps

- [Change Tracking](change-tracking.md) - Understand entity modifications
- [Events](events.md) - Handle database events
- [Schema Migrations](../schema-migrations/migrations.md) - Manage database schema changes
