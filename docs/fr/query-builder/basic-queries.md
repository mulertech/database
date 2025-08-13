# Requêtes de Base - Query Builder

## Table des Matières
- [Introduction](#introduction)
- [Configuration et Initialisation](#configuration-et-initialisation)
- [Requêtes SELECT](#requêtes-select)
- [Requêtes INSERT](#requêtes-insert)
- [Requêtes UPDATE](#requêtes-update)
- [Requêtes DELETE](#requêtes-delete)
- [Méthodes de Base](#méthodes-de-base)
- [Exemples Pratiques](#exemples-pratiques)

## Introduction

Le Query Builder de MulerTech Database offre une interface fluide et intuitive pour construire des requêtes SQL de manière programmatique. Il combine la flexibilité du SQL brut avec la sécurité et la lisibilité du code orienté objet.

### Avantages du Query Builder

- **Sécurité** : Protection automatique contre les injections SQL
- **Lisibilité** : Code plus maintenir et compréhensible
- **Flexibilité** : Construction dynamique de requêtes
- **Type Safety** : Validation des types à l'exécution

## Configuration et Initialisation

### Obtenir une Instance du Query Builder

```php
<?php
declare(strict_types=1);

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Query\Builder\QueryBuilder;

// Via l'EntityManager
$em = new EmEngine($databaseDriver);
$queryBuilder = $em->createQueryBuilder();

// Directement avec le driver
$queryBuilder = new QueryBuilder($databaseDriver);
```

### Configuration de Base

```php
<?php
declare(strict_types=1);

// Configuration avec options
$queryBuilder = new QueryBuilder($driver, [
    'fetchMode' => \PDO::FETCH_ASSOC,
    'debug' => true,
    'timeout' => 30
]);
```

## Requêtes SELECT

### SELECT Simple

```php
<?php
declare(strict_types=1);

// SELECT basique
$users = $queryBuilder
    ->select(['id', 'name', 'email'])
    ->from('users')
    ->execute()
    ->fetchAll();

// SELECT avec alias
$users = $queryBuilder
    ->select(['u.id', 'u.name as username', 'u.email'])
    ->from('users', 'u')
    ->execute()
    ->fetchAll();
```

### SELECT avec WHERE

```php
<?php
declare(strict_types=1);

// Conditions simples
$activeUsers = $queryBuilder
    ->select(['*'])
    ->from('users')
    ->where('status = ?', ['active'])
    ->execute()
    ->fetchAll();

// Conditions multiples
$users = $queryBuilder
    ->select(['*'])
    ->from('users')
    ->where('age >= ?', [18])
    ->andWhere('city = ?', ['Paris'])
    ->execute()
    ->fetchAll();

// Conditions OR
$users = $queryBuilder
    ->select(['*'])
    ->from('users')
    ->where('role = ?', ['admin'])
    ->orWhere('role = ?', ['moderator'])
    ->execute()
    ->fetchAll();
```

### SELECT avec ORDER BY et LIMIT

```php
<?php
declare(strict_types=1);

// Tri et pagination
$users = $queryBuilder
    ->select(['*'])
    ->from('users')
    ->where('status = ?', ['active'])
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->offset(20)
    ->execute()
    ->fetchAll();

// Tri multiple
$users = $queryBuilder
    ->select(['*'])
    ->from('users')
    ->orderBy('last_name', 'ASC')
    ->addOrderBy('first_name', 'ASC')
    ->execute()
    ->fetchAll();
```

### SELECT avec GROUP BY et HAVING

```php
<?php
declare(strict_types=1);

// Groupement avec agrégation
$stats = $queryBuilder
    ->select(['department', 'COUNT(*) as count', 'AVG(salary) as avg_salary'])
    ->from('employees')
    ->groupBy('department')
    ->having('COUNT(*) > ?', [5])
    ->execute()
    ->fetchAll();
```

## Requêtes INSERT

### INSERT Simple

```php
<?php
declare(strict_types=1);

// Insert d'un enregistrement
$result = $queryBuilder
    ->insert('users')
    ->values([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'created_at' => date('Y-m-d H:i:s')
    ])
    ->execute();

$insertId = $result->getLastInsertId();
```

### INSERT Multiple

```php
<?php
declare(strict_types=1);

// Insert multiple
$users = [
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
    ['name' => 'Charlie', 'email' => 'charlie@example.com']
];

$result = $queryBuilder
    ->insert('users')
    ->values($users)
    ->execute();
```

### INSERT avec SELECT

```php
<?php
declare(strict_types=1);

// Insert depuis une autre table
$result = $queryBuilder
    ->insert('archived_users')
    ->columns(['name', 'email', 'archived_at'])
    ->select(['name', 'email', 'NOW()'])
    ->from('users')
    ->where('last_login < ?', ['2023-01-01'])
    ->execute();
```

## Requêtes UPDATE

### UPDATE Simple

```php
<?php
declare(strict_types=1);

// Update basique
$result = $queryBuilder
    ->update('users')
    ->set([
        'last_login' => date('Y-m-d H:i:s'),
        'login_count' => 'login_count + 1'
    ])
    ->where('id = ?', [123])
    ->execute();

$affectedRows = $result->getAffectedRows();
```

### UPDATE Conditionnel

```php
<?php
declare(strict_types=1);

// Update avec conditions multiples
$result = $queryBuilder
    ->update('users')
    ->set(['status' => 'inactive'])
    ->where('last_login < ?', ['2023-01-01'])
    ->andWhere('status = ?', ['active'])
    ->execute();
```

### UPDATE avec JOIN

```php
<?php
declare(strict_types=1);

// Update avec jointure
$result = $queryBuilder
    ->update('users', 'u')
    ->innerJoin('u', 'profiles', 'p', 'u.id = p.user_id')
    ->set(['u.status' => 'verified'])
    ->where('p.verification_status = ?', ['approved'])
    ->execute();
```

## Requêtes DELETE

### DELETE Simple

```php
<?php
declare(strict_types=1);

// Delete basique
$result = $queryBuilder
    ->delete('users')
    ->where('id = ?', [123])
    ->execute();
```

### DELETE Conditionnel

```php
<?php
declare(strict_types=1);

// Delete avec conditions multiples
$result = $queryBuilder
    ->delete('users')
    ->where('status = ?', ['inactive'])
    ->andWhere('last_login < ?', ['2022-01-01'])
    ->execute();
```

### DELETE avec LIMIT

```php
<?php
declare(strict_types=1);

// Delete avec limitation
$result = $queryBuilder
    ->delete('old_logs')
    ->where('created_at < ?', ['2023-01-01'])
    ->orderBy('created_at', 'ASC')
    ->limit(1000)
    ->execute();
```

## Méthodes de Base

### Méthodes de Construction

```php
<?php
declare(strict_types=1);

interface QueryBuilderInterface
{
    // SELECT
    public function select(array $columns = ['*']): self;
    public function from(string $table, ?string $alias = null): self;
    
    // Conditions
    public function where(string $condition, array $params = []): self;
    public function andWhere(string $condition, array $params = []): self;
    public function orWhere(string $condition, array $params = []): self;
    
    // Tri et groupement
    public function orderBy(string $column, string $direction = 'ASC'): self;
    public function groupBy(string $column): self;
    public function having(string $condition, array $params = []): self;
    
    // Limitation
    public function limit(int $limit): self;
    public function offset(int $offset): self;
    
    // INSERT/UPDATE/DELETE
    public function insert(string $table): self;
    public function update(string $table, ?string $alias = null): self;
    public function delete(string $table, ?string $alias = null): self;
    
    // Exécution
    public function execute(): QueryResult;
    public function getSQL(): string;
    public function getParameters(): array;
}
```

### Méthodes Utilitaires

```php
<?php
declare(strict_types=1);

// Obtenir la requête SQL générée
$sql = $queryBuilder
    ->select(['*'])
    ->from('users')
    ->where('age > ?', [18])
    ->getSQL();

// Obtenir les paramètres
$params = $queryBuilder->getParameters();

// Debug de la requête
$queryBuilder->debug(); // Affiche SQL + paramètres

// Reset du builder
$queryBuilder->reset(); // Remet à zéro pour une nouvelle requête
```

## Exemples Pratiques

### Recherche d'Utilisateurs

```php
<?php
declare(strict_types=1);

class UserService
{
    private QueryBuilder $queryBuilder;
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    public function searchUsers(array $criteria): array
    {
        $qb = $this->queryBuilder
            ->select(['u.id', 'u.name', 'u.email', 'p.avatar'])
            ->from('users', 'u')
            ->leftJoin('u', 'profiles', 'p', 'u.id = p.user_id');
        
        if (!empty($criteria['name'])) {
            $qb->andWhere('u.name LIKE ?', ['%' . $criteria['name'] . '%']);
        }
        
        if (!empty($criteria['email'])) {
            $qb->andWhere('u.email = ?', [$criteria['email']]);
        }
        
        if (!empty($criteria['status'])) {
            $qb->andWhere('u.status = ?', [$criteria['status']]);
        }
        
        return $qb->orderBy('u.name', 'ASC')
                 ->execute()
                 ->fetchAll();
    }
}
```

### Pagination

```php
<?php
declare(strict_types=1);

class PaginationHelper
{
    public function paginate(QueryBuilder $qb, int $page, int $perPage): array
    {
        // Compter le total
        $countQb = clone $qb;
        $total = $countQb
            ->select(['COUNT(*) as count'])
            ->execute()
            ->fetch()['count'];
        
        // Récupérer les données paginées
        $offset = ($page - 1) * $perPage;
        $data = $qb
            ->limit($perPage)
            ->offset($offset)
            ->execute()
            ->fetchAll();
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
}
```

### Transactions avec Query Builder

```php
<?php
declare(strict_types=1);

class TransactionExample
{
    private QueryBuilder $queryBuilder;
    
    public function transferMoney(int $fromUserId, int $toUserId, float $amount): bool
    {
        $this->queryBuilder->beginTransaction();
        
        try {
            // Débiter le compte source
            $this->queryBuilder
                ->update('accounts')
                ->set(['balance' => 'balance - ?'])
                ->where('user_id = ? AND balance >= ?', [$fromUserId, $amount])
                ->execute();
            
            // Créditer le compte destination
            $this->queryBuilder
                ->update('accounts')
                ->set(['balance' => 'balance + ?'])
                ->where('user_id = ?', [$toUserId])
                ->execute();
            
            // Enregistrer la transaction
            $this->queryBuilder
                ->insert('transactions')
                ->values([
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                    'amount' => $amount,
                    'created_at' => date('Y-m-d H:i:s')
                ])
                ->execute();
            
            $this->queryBuilder->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->queryBuilder->rollback();
            throw $e;
        }
    }
}
```

## Prochaines Étapes

- [Requêtes Avancées](advanced-queries.md) - Jointures, sous-requêtes, CTE
- [Requêtes SQL Brutes](raw-queries.md) - Quand utiliser du SQL natif
- [Optimisation des Requêtes](query-optimization.md) - Performance et bonnes pratiques
