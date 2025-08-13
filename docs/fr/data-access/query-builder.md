# Query Builder

Le Query Builder de MulerTech Database utilise une architecture modulaire avec des builders spécialisés pour chaque type de requête.

## Vue d'ensemble

### Architecture du Query Builder

- **SelectBuilder** : Requêtes SELECT complexes avec jointures, groupements, tri
- **InsertBuilder** : Insertions simples et par lot
- **UpdateBuilder** : Mises à jour avec conditions
- **DeleteBuilder** : Suppressions avec conditions
- **RawQueryBuilder** : Requêtes SQL brutes avec paramètres

### Avantages

- **Type-Safety** : Chaque builder est optimisé pour son type de requête
- **Sécurité** : Protection automatique contre les injections SQL
- **Flexibilité** : Construction dynamique de requêtes complexes
- **Performance** : Optimisations spécifiques par type de requête

## Configuration et initialisation

```php
use MulerTech\Database\Query\Builder\QueryBuilder;

// Initialisation via EntityManager
$queryBuilder = $entityManager->createQueryBuilder();

// Ou initialisation directe
$queryBuilder = new QueryBuilder($emEngine);
```

## Requêtes SELECT

### SELECT de base

```php
// SELECT simple
$results = $queryBuilder
    ->select('id', 'name', 'email')
    ->from('users')
    ->fetchAll();

// SELECT avec alias
$results = $queryBuilder
    ->select('u.name', 'u.email')
    ->from('users', 'u')
    ->fetchAll();

// SELECT avec conditions
$user = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('id', 1)
    ->fetchOne();
```

### Conditions WHERE

```php
// Condition simple
$queryBuilder->where('status', 'active');

// Conditions multiples (AND implicite)
$queryBuilder
    ->where('status', 'active')
    ->where('age', '>', 18);

// Conditions avec opérateurs
$queryBuilder->where('price', '>=', 100);
$queryBuilder->where('name', 'LIKE', '%john%');

// Conditions IN
$queryBuilder->where('category_id', 'IN', [1, 2, 3]);

// Conditions NULL
$queryBuilder->where('deleted_at', 'IS', null);
```

### Jointures

```php
// INNER JOIN
$results = $queryBuilder
    ->select('u.name', 'p.title')
    ->from('users', 'u')
    ->join('posts', 'p', 'u.id = p.user_id')
    ->fetchAll();

// LEFT JOIN
$results = $queryBuilder
    ->select('u.name', 'COUNT(p.id) as post_count')
    ->from('users', 'u')
    ->leftJoin('posts', 'p', 'u.id = p.user_id')
    ->groupBy('u.id')
    ->fetchAll();

// Jointures multiples
$results = $queryBuilder
    ->select('u.name', 'p.title', 'c.name as category')
    ->from('users', 'u')
    ->join('posts', 'p', 'u.id = p.user_id')
    ->join('categories', 'c', 'p.category_id = c.id')
    ->fetchAll();
```

### Groupement et agrégation

```php
// GROUP BY avec COUNT
$stats = $queryBuilder
    ->select('category', 'COUNT(*) as total')
    ->from('products')
    ->groupBy('category')
    ->fetchAll();

// Agrégations multiples
$stats = $queryBuilder
    ->select('category', 'COUNT(*) as total', 'AVG(price) as avg_price')
    ->from('products')
    ->groupBy('category')
    ->having('COUNT(*)', '>', 5)
    ->fetchAll();
```

### Tri et pagination

```php
// ORDER BY
$results = $queryBuilder
    ->select('*')
    ->from('users')
    ->orderBy('created_at', 'DESC')
    ->fetchAll();

// Tri multiple
$results = $queryBuilder
    ->select('*')
    ->from('users')
    ->orderBy('status', 'ASC')
    ->orderBy('created_at', 'DESC')
    ->fetchAll();

// LIMIT et OFFSET
$results = $queryBuilder
    ->select('*')
    ->from('users')
    ->orderBy('id')
    ->limit(10)
    ->offset(20)
    ->fetchAll();
```

### Sous-requêtes

```php
// Sous-requête dans WHERE
$results = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('id', 'IN', function ($subQuery) {
        return $subQuery
            ->select('user_id')
            ->from('posts')
            ->where('published', true);
    })
    ->fetchAll();

// Sous-requête dans SELECT
$results = $queryBuilder
    ->select('u.name')
    ->selectRaw('(SELECT COUNT(*) FROM posts WHERE user_id = u.id) as post_count')
    ->from('users', 'u')
    ->fetchAll();
```

## Requêtes INSERT

### INSERT simple

```php
// Insertion d'un enregistrement
$queryBuilder
    ->insert('users')
    ->set('name', 'John Doe')
    ->set('email', 'john@example.com')
    ->set('created_at', date('Y-m-d H:i:s'))
    ->execute();

// Avec tableau associatif
$queryBuilder
    ->insert('users')
    ->values([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'created_at' => date('Y-m-d H:i:s')
    ])
    ->execute();
```

### INSERT par lot

```php
// Insertion multiple
$users = [
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
    ['name' => 'Charlie', 'email' => 'charlie@example.com']
];

foreach ($users as $userData) {
    $queryBuilder
        ->insert('users')
        ->values($userData)
        ->execute();
}
```

## Requêtes UPDATE

### UPDATE simple

```php
// Mise à jour d'un enregistrement
$queryBuilder
    ->update('users')
    ->set('name', 'John Smith')
    ->set('updated_at', date('Y-m-d H:i:s'))
    ->where('id', 1)
    ->execute();

// Avec tableau associatif
$queryBuilder
    ->update('users')
    ->values([
        'name' => 'Jane Smith',
        'updated_at' => date('Y-m-d H:i:s')
    ])
    ->where('email', 'jane@example.com')
    ->execute();
```

### UPDATE conditionnel

```php
// Mise à jour avec conditions multiples
$queryBuilder
    ->update('posts')
    ->set('status', 'published')
    ->set('published_at', date('Y-m-d H:i:s'))
    ->where('status', 'draft')
    ->where('scheduled_at', '<=', date('Y-m-d H:i:s'))
    ->execute();
```

## Requêtes DELETE

### DELETE simple

```php
// Suppression d'un enregistrement
$queryBuilder
    ->delete('users')
    ->where('id', 1)
    ->execute();

// Suppression conditionnelle
$queryBuilder
    ->delete('posts')
    ->where('status', 'draft')
    ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-30 days')))
    ->execute();
```

## Requêtes complexes

### Requête avec jointures et agrégations

```php
// Statistiques des utilisateurs
$userStats = $queryBuilder
    ->select([
        'u.id',
        'u.name',
        'COUNT(p.id) as total_posts',
        'COUNT(CASE WHEN p.status = "published" THEN 1 END) as published_posts',
        'AVG(p.views) as avg_views'
    ])
    ->from('users', 'u')
    ->leftJoin('posts', 'p', 'u.id = p.user_id')
    ->where('u.active', true)
    ->groupBy('u.id')
    ->having('COUNT(p.id)', '>', 0)
    ->orderBy('total_posts', 'DESC')
    ->limit(10)
    ->fetchAll();
```

### Requête avec conditions complexes

```php
// Recherche avancée de produits
$products = $queryBuilder
    ->select('p.*', 'c.name as category_name')
    ->from('products', 'p')
    ->join('categories', 'c', 'p.category_id = c.id')
    ->where('p.active', true)
    ->where(function ($query) use ($searchTerm) {
        $query->where('p.name', 'LIKE', "%{$searchTerm}%")
              ->orWhere('p.description', 'LIKE', "%{$searchTerm}%");
    })
    ->where('p.price', 'BETWEEN', [10, 100])
    ->orderBy('p.featured', 'DESC')
    ->orderBy('p.created_at', 'DESC')
    ->fetchAll();
```

## Optimisations

### Cache et réutilisation

```php
// Réutiliser un builder de base
$baseQuery = $queryBuilder
    ->select('u.*')
    ->from('users', 'u')
    ->where('u.active', true);

// Ajouter des conditions spécifiques
$adminUsers = clone $baseQuery;
$adminUsers->where('u.role', 'admin');

$recentUsers = clone $baseQuery;
$recentUsers->where('u.created_at', '>=', date('Y-m-d', strtotime('-7 days')));
```

### Pagination efficace

```php
/**
 * Pagination avec comptage optimisé
 */
class PaginatedResult
{
    public function __construct(
        public array $data,
        public int $total,
        public int $page,
        public int $perPage
    ) {}
}

function getPaginatedUsers(int $page = 1, int $perPage = 20): PaginatedResult
{
    $queryBuilder = new QueryBuilder($emEngine);
    
    // Requête des données
    $data = $queryBuilder
        ->select('*')
        ->from('users')
        ->where('active', true)
        ->orderBy('created_at', 'DESC')
        ->limit($perPage)
        ->offset(($page - 1) * $perPage)
        ->fetchAll();
    
    // Comptage total (optimisé)
    $total = $queryBuilder
        ->select('COUNT(*) as total')
        ->from('users')
        ->where('active', true)
        ->fetchScalar();
    
    return new PaginatedResult($data, (int)$total, $page, $perPage);
}
```

## Requêtes SQL brutes

Quand le Query Builder n'est pas suffisant, utilisez des requêtes SQL brutes :

```php
// Requête brute simple
$results = $queryBuilder
    ->raw('SELECT * FROM users WHERE created_at > ?')
    ->bind([date('Y-m-d', strtotime('-30 days'))])
    ->execute()
    ->fetchAll();

// Requête brute complexe
$stats = $queryBuilder
    ->raw('
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as registrations,
            COUNT(CASE WHEN email_verified_at IS NOT NULL THEN 1 END) as verified
        FROM users 
        WHERE created_at >= ?
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ')
    ->bind([date('Y-m-d', strtotime('-30 days'))])
    ->execute()
    ->fetchAll();
```

## Exemples pratiques

### Système de blog

```php
// Articles publiés avec auteur et catégorie
$publishedPosts = $queryBuilder
    ->select([
        'p.id',
        'p.title',
        'p.excerpt',
        'p.published_at',
        'u.name as author_name',
        'c.name as category_name'
    ])
    ->from('posts', 'p')
    ->join('users', 'u', 'p.user_id = u.id')
    ->join('categories', 'c', 'p.category_id = c.id')
    ->where('p.status', 'published')
    ->where('p.published_at', '<=', date('Y-m-d H:i:s'))
    ->orderBy('p.published_at', 'DESC')
    ->limit(10)
    ->fetchAll();
```

### Système d'e-commerce

```php
// Produits avec stock et prix
$availableProducts = $queryBuilder
    ->select([
        'p.*',
        'c.name as category',
        'CASE WHEN p.sale_price IS NOT NULL THEN p.sale_price ELSE p.regular_price END as final_price'
    ])
    ->from('products', 'p')
    ->join('categories', 'c', 'p.category_id = c.id')
    ->where('p.active', true)
    ->where('p.stock_quantity', '>', 0)
    ->orderBy('p.featured', 'DESC')
    ->orderBy('final_price', 'ASC')
    ->fetchAll();
```

### Rapports et analytics

```php
// Rapport mensuel des ventes
$monthlySales = $queryBuilder
    ->select([
        'DATE_FORMAT(created_at, "%Y-%m") as month',
        'COUNT(*) as total_orders',
        'SUM(total_amount) as total_revenue',
        'AVG(total_amount) as avg_order_value'
    ])
    ->from('orders')
    ->where('status', 'completed')
    ->where('created_at', '>=', date('Y-01-01'))
    ->groupBy('DATE_FORMAT(created_at, "%Y-%m")')
    ->orderBy('month', 'DESC')
    ->fetchAll();
```

---

**Voir aussi :**
- [Requêtes SQL brutes](raw-queries.md) - SQL direct avec paramètres
- [Entity Manager](entity-manager.md) - ORM et gestion d'entités
- [Repositories](repositories.md) - Couche d'accès aux données