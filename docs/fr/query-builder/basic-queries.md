# Requêtes de Base - Query Builder

🌍 **Languages:** [🇫🇷 Français](basic-queries.md) | [🇬🇧 English](../../en/query-builder/basic-queries.md)

---

## 📋 Table des Matières

- [Vue d'Ensemble](#vue-densemble)
- [Architecture du Query Builder](#architecture-du-query-builder)
- [Configuration et Initialisation](#configuration-et-initialisation)
- [Requêtes SELECT](#requêtes-select)
- [Requêtes INSERT](#requêtes-insert)
- [Requêtes UPDATE](#requêtes-update)
- [Requêtes DELETE](#requêtes-delete)
- [Requêtes Raw SQL](#requêtes-raw-sql)
- [Exemples Pratiques](#exemples-pratiques)

---

## Vue d'Ensemble

Le Query Builder de MulerTech Database utilise une **architecture modulaire** avec des builders spécialisés pour chaque type de requête. Cette approche garantit la type-safety et permet des optimisations spécifiques à chaque type d'opération.

### 🎯 Avantages du Query Builder

- **Type-Safety** : Chaque builder est optimisé pour son type de requête
- **Sécurité** : Protection automatique contre les injections SQL via les paramètres
- **Flexibilité** : Construction dynamique de requêtes complexes
- **Performance** : Optimisations spécifiques par type de requête
- **Lisibilité** : API fluide et intuitive

### 📦 Imports Nécessaires

```php
<?php
use MulerTech\Database\Query\Builder\{
    QueryBuilder, SelectBuilder, InsertBuilder, 
    UpdateBuilder, DeleteBuilder, RawQueryBuilder
};
use MulerTech\Database\ORM\EmEngine;
```

---

## Architecture du Query Builder

### 🏭 Pattern Factory

Le `QueryBuilder` est une factory qui crée des builders spécialisés :

```php
<?php

// Factory pour créer des builders spécialisés
$queryBuilder = new QueryBuilder($emEngine);

// Création des différents types de builders
$selectBuilder = $queryBuilder->select('*');           // → SelectBuilder
$insertBuilder = $queryBuilder->insert('users');       // → InsertBuilder
$updateBuilder = $queryBuilder->update('users');       // → UpdateBuilder
$deleteBuilder = $queryBuilder->delete('users');       // → DeleteBuilder
$rawBuilder = $queryBuilder->raw('SELECT 1');          // → RawQueryBuilder
```

### 🔧 Builders Spécialisés

Chaque builder a ses propres méthodes optimisées :

- **SelectBuilder** : `select()`, `from()`, `where()`, `join()`, `groupBy()`, `orderBy()`, `limit()`
- **InsertBuilder** : `into()`, `set()`, `value()`
- **UpdateBuilder** : `table()`, `set()`, `where()`
- **DeleteBuilder** : `from()`, `where()`
- **RawQueryBuilder** : `setParameter()`, `fetchAll()`, `fetchScalar()`

---

## Configuration et Initialisation

### 🚀 Initialisation Standard

```php
<?php

// Avec EmEngine (recommandé pour l'hydratation automatique)
$emEngine = $entityManager->getEmEngine();
$queryBuilder = new QueryBuilder($emEngine);

// Sans EmEngine (pour requêtes simples)
$queryBuilder = new QueryBuilder();
```

### 🔧 Utilisation dans les Services

```php
<?php

class UserService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
    
    private function getQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->entityManager->getEmEngine());
    }
}
```

---

## Requêtes SELECT

### 🔍 SELECT de Base

```php
<?php

$queryBuilder = new QueryBuilder($emEngine);

// SELECT simple
$selectBuilder = $queryBuilder->select('*');
$users = $selectBuilder->from('users')->fetchAll();

// SELECT avec colonnes spécifiques
$selectBuilder = $queryBuilder->select('id', 'name', 'email');
$users = $selectBuilder->from('users')->fetchAll();

// SELECT avec alias de table
$selectBuilder = $queryBuilder->select('u.*');
$users = $selectBuilder->from('users', 'u')->fetchAll();
```

### 🎯 Conditions WHERE

```php
<?php

// Condition simple
$selectBuilder = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('status = ?', 'active');

$activeUsers = $selectBuilder->fetchAll();

// Conditions multiples
$selectBuilder = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('age >= ?', 18)
    ->where('city = ?', 'Paris');

$users = $selectBuilder->fetchAll();

// Conditions avec opérateurs
$selectBuilder = $queryBuilder
    ->select('*')
    ->from('products')
    ->where('price BETWEEN ? AND ?', 10, 100)
    ->where('name LIKE ?', '%phone%');

$products = $selectBuilder->fetchAll();
```

### 🔗 Jointures

Les jointures utilisent les traits `JoinClauseTrait` :

```php
<?php

// INNER JOIN
$selectBuilder = $queryBuilder
    ->select('u.name', 'p.title')
    ->from('users', 'u')
    ->join('posts', 'p', 'u.id = p.user_id');

$results = $selectBuilder->fetchAll();

// LEFT JOIN
$selectBuilder = $queryBuilder
    ->select('u.name', 'pr.bio')
    ->from('users', 'u')
    ->leftJoin('profiles', 'pr', 'u.id = pr.user_id');

$results = $selectBuilder->fetchAll();

// Jointures multiples
$selectBuilder = $queryBuilder
    ->select('u.name', 'p.title', 'c.name as category')
    ->from('users', 'u')
    ->join('posts', 'p', 'u.id = p.user_id')
    ->join('categories', 'c', 'p.category_id = c.id');

$results = $selectBuilder->fetchAll();
```

### 📊 Groupement et Tri

```php
<?php

// GROUP BY avec agrégation
$selectBuilder = $queryBuilder
    ->select('department', 'COUNT(*) as count', 'AVG(salary) as avg_salary')
    ->from('employees')
    ->groupBy('department');

$stats = $selectBuilder->fetchAll();

// HAVING
$selectBuilder = $queryBuilder
    ->select('category_id', 'COUNT(*) as product_count')
    ->from('products')
    ->groupBy('category_id')
    ->having('COUNT(*) > ?', 5);

$categories = $selectBuilder->fetchAll();

// ORDER BY
$selectBuilder = $queryBuilder
    ->select('*')
    ->from('users')
    ->orderBy('name', 'ASC')
    ->orderBy('created_at', 'DESC');

$users = $selectBuilder->fetchAll();
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

$users = $selectBuilder->fetchAll();
```

---

## Requêtes INSERT

### 💾 INSERT Simple

```php
<?php

$insertBuilder = $queryBuilder->insert('users');

// Insertion d'un enregistrement
$insertBuilder
    ->set('name', 'John Doe')
    ->set('email', 'john@example.com')
    ->set('created_at', date('Y-m-d H:i:s'));

$insertBuilder->execute();
```

### 📝 INSERT avec Données Multiples

```php
<?php

$insertBuilder = $queryBuilder->insert('users');

// Insertion par lot (nécessite plusieurs appels)
$users = [
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
    ['name' => 'Charlie', 'email' => 'charlie@example.com']
];

foreach ($users as $userData) {
    $insertBuilder = $queryBuilder->insert('users');
    foreach ($userData as $field => $value) {
        $insertBuilder->set($field, $value);
    }
    $insertBuilder->execute();
}
```

---

## Requêtes UPDATE

### ✏️ UPDATE Simple

```php
<?php

$updateBuilder = $queryBuilder->update('users');

// Mise à jour d'un utilisateur
$updateBuilder
    ->set('last_login', date('Y-m-d H:i:s'))
    ->set('login_count', 'login_count + 1') // Expression SQL
    ->where('id = ?', 123);

$updateBuilder->execute();
```

### 🎯 UPDATE Conditionnel

```php
<?php

$updateBuilder = $queryBuilder->update('users');

// Mise à jour avec conditions multiples
$updateBuilder
    ->set('status', 'inactive')
    ->where('last_login < ?', '2023-01-01')
    ->where('status = ?', 'active');

$updateBuilder->execute();
```

---

## Requêtes DELETE

### 🗑️ DELETE Simple

```php
<?php

$deleteBuilder = $queryBuilder->delete('users');

// Suppression par ID
$deleteBuilder->where('id = ?', 123);
$deleteBuilder->execute();
```

### 🎯 DELETE Conditionnel

```php
<?php

$deleteBuilder = $queryBuilder->delete('users');

// Suppression avec conditions multiples
$deleteBuilder
    ->where('status = ?', 'inactive')
    ->where('last_login < ?', '2022-01-01');

$deleteBuilder->execute();
```

---

## Requêtes Raw SQL

Pour les requêtes complexes, utilisez `RawQueryBuilder` :

### 🔧 Raw SQL avec Paramètres

```php
<?php

// Requête complexe avec sous-requête
$rawBuilder = $queryBuilder->raw("
    SELECT 
        u.id,
        u.name,
        u.email,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as order_count
    FROM users u
    WHERE u.status = ?
    ORDER BY order_count DESC
");

$rawBuilder->setParameter(0, 'active');
$results = $rawBuilder->fetchAll();
```

### 📊 Requêtes d'Agrégation Complexes

```php
<?php

// Statistiques avancées
$rawBuilder = $queryBuilder->raw("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total_orders,
        SUM(total) as revenue,
        AVG(total) as avg_order_value
    FROM orders 
    WHERE status = ? 
    AND created_at >= ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
");

$rawBuilder->setParameter(0, 'completed');
$rawBuilder->setParameter(1, '2024-01-01');

$monthlyStats = $rawBuilder->fetchAll();
```

---

## Exemples Pratiques

### 🔍 Service de Recherche

```php
<?php

class UserSearchService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
    
    public function searchUsers(array $criteria): array
    {
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        
        $selectBuilder = $queryBuilder
            ->select('u.*', 'p.bio', 'p.avatar_url')
            ->from('users', 'u')
            ->leftJoin('profiles', 'p', 'u.id = p.user_id');
        
        // Filtres dynamiques
        if (!empty($criteria['name'])) {
            $selectBuilder->where('u.name LIKE ?', "%{$criteria['name']}%");
        }
        
        if (!empty($criteria['email'])) {
            $selectBuilder->where('u.email = ?', $criteria['email']);
        }
        
        if (!empty($criteria['status'])) {
            $selectBuilder->where('u.status = ?', $criteria['status']);
        }
        
        // Tri et pagination
        $orderBy = $criteria['sort'] ?? 'name';
        $direction = $criteria['direction'] ?? 'ASC';
        $selectBuilder->orderBy("u.$orderBy", $direction);
        
        if (isset($criteria['limit'])) {
            $selectBuilder->limit($criteria['limit']);
            
            if (isset($criteria['offset'])) {
                $selectBuilder->offset($criteria['offset']);
            }
        }
        
        return $selectBuilder->fetchAll();
    }
}
```

### 📊 Service de Statistiques

```php
<?php

class StatsService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
    
    public function getUserStats(): array
    {
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        
        // Statistiques simples
        $selectBuilder = $queryBuilder
            ->select(
                'COUNT(*) as total_users',
                'COUNT(CASE WHEN status = "active" THEN 1 END) as active_users',
                'COUNT(CASE WHEN created_at >= CURDATE() THEN 1 END) as new_today'
            )
            ->from('users');
        
        return $selectBuilder->fetch();
    }
    
    public function getOrderStatsByMonth(): array
    {
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        
        // Requête complexe avec Raw SQL
        $rawBuilder = $queryBuilder->raw("
            SELECT 
                DATE_FORMAT(o.created_at, '%Y-%m') as month,
                COUNT(DISTINCT o.id) as order_count,
                COUNT(DISTINCT o.user_id) as customer_count,
                SUM(o.total) as revenue,
                AVG(o.total) as avg_order_value
            FROM orders o
            WHERE o.status = 'completed'
            AND o.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
            ORDER BY month DESC
        ");
        
        return $rawBuilder->fetchAll();
    }
}
```

### 🔄 Service de Gestion des Données

```php
<?php

class DataManagementService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
    
    public function createUser(array $userData): int
    {
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        
        $insertBuilder = $queryBuilder->insert('users');
        
        foreach ($userData as $field => $value) {
            $insertBuilder->set($field, $value);
        }
        
        $insertBuilder->set('created_at', date('Y-m-d H:i:s'));
        $insertBuilder->execute();
        
        // Récupérer l'ID inséré via une requête séparée
        $rawBuilder = $queryBuilder->raw("SELECT LAST_INSERT_ID() as id");
        $result = $rawBuilder->fetch();
        
        return (int) $result['id'];
    }
    
    public function updateUserLastLogin(int $userId): void
    {
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        
        $updateBuilder = $queryBuilder->update('users');
        $updateBuilder
            ->set('last_login', date('Y-m-d H:i:s'))
            ->set('login_count', 'login_count + 1')
            ->where('id = ?', $userId);
        
        $updateBuilder->execute();
    }
    
    public function cleanupInactiveUsers(int $daysInactive): int
    {
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        
        $cutoffDate = date('Y-m-d', strtotime("-$daysInactive days"));
        
        $deleteBuilder = $queryBuilder->delete('users');
        $deleteBuilder
            ->where('status = ?', 'inactive')
            ->where('last_login < ?', $cutoffDate);
        
        $deleteBuilder->execute();
        
        // Compter les lignes affectées via une requête séparée
        $rawBuilder = $queryBuilder->raw("SELECT ROW_COUNT() as affected");
        $result = $rawBuilder->fetch();
        
        return (int) $result['affected'];
    }
}
```

---

## ➡️ Étapes Suivantes

Pour approfondir votre compréhension :

1. 🚀 [Requêtes Avancées](advanced-queries.md) - Requêtes complexes et optimisations
2. 🗂️ [Repositories](../../fr/orm/repositories.md) - Utilisation avec les repositories
3. 🎯 [Exemples Pratiques](../../fr/quick-start/basic-examples.md) - Cas d'usage concrets

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../fr/../README.md)
- 📖 [Documentation Complète](../../fr/README.md)
- 🚀 [Démarrage Rapide](../../fr/quick-start/installation.md)
