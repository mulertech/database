# Repositories

🌍 **Languages:** [🇫🇷 Français](repositories.md) | [🇬🇧 English](../../en/orm/repositories.md)

---

## 📋 Table des Matières

- [Vue d'Ensemble](#vue-densemble)
- [EntityRepository de Base](#entityrepository-de-base)
- [Méthodes de Recherche](#méthodes-de-recherche)
- [Méthodes Magiques](#méthodes-magiques)
- [QueryBuilder Avancé](#querybuilder-avancé)
- [Repositories Personnalisés](#repositories-personnalisés)
- [Exemples Pratiques](#exemples-pratiques)

---

## Vue d'Ensemble

Les repositories dans MulerTech Database encapsulent la logique d'accès aux données et offrent une API riche pour interroger les entités. Chaque entité peut avoir son propre repository personnalisé qui étend les fonctionnalités de base.

### 🎯 Responsabilités des Repositories

- **Encapsulation** : Logique d'accès aux données centralisée
- **API riche** : Méthodes de recherche variées et flexibles
- **Type safety** : Retour d'objets typés
- **Performance** : Optimisations automatiques des requêtes
- **Extensibilité** : Repositories personnalisés pour logique métier

### 📦 Imports Nécessaires

```php
<?php
use MulerTech\Database\ORM\{EntityRepository, EntityManagerInterface};
use MulerTech\Database\Query\Builder\QueryBuilder;
```

---

## EntityRepository de Base

### 🏗️ Structure de Base

```php
class EntityRepository
{
    protected EntityManagerInterface $entityManager;
    private string $entityName;

    public function __construct(EntityManagerInterface $entityManager, string $entityName);
    
    // Méthodes de recherche
    public function find(string|int $id): ?object;
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;
    public function findAll(): array;
    public function count(array $criteria = []): int;
    
    // Méthodes utilitaires
    public function getEntityName(): string;
    public function getEntityManager(): EntityManagerInterface;
    protected function createQueryBuilder(): QueryBuilder;
    protected function getTableName(): string;
}
```

### 🔧 Accès aux Repositories

```php
<?php

// Obtenir un repository via EntityManager
$userRepository = $entityManager->getRepository(User::class);

// Le repository est automatiquement configuré pour l'entité
echo $userRepository->getEntityName(); // User::class
```

---

## Méthodes de Recherche

### 🔍 find() - Recherche par ID

```php
<?php

// Recherche par ID simple
$user = $userRepository->find(1);

// Recherche par ID string
$user = $userRepository->find('abc123');

if ($user) {
    echo "Utilisateur trouvé : " . $user->getName();
} else {
    echo "Utilisateur non trouvé";
}
```

### 📋 findBy() - Recherche avec Critères

```php
<?php

// Recherche simple
$activeUsers = $userRepository->findBy(['status' => 'active']);

// Avec tri
$users = $userRepository->findBy(
    ['status' => 'active'],
    ['name' => 'ASC', 'createdAt' => 'DESC']
);

// Avec limite
$recentUsers = $userRepository->findBy(
    ['status' => 'active'],
    ['createdAt' => 'DESC'],
    10  // Limite à 10 résultats
);

// Avec pagination
$users = $userRepository->findBy(
    ['status' => 'active'],
    ['name' => 'ASC'],
    20,  // Limite
    40   // Offset (page 3 si 20 par page)
);
```

### 🎯 findOneBy() - Premier Résultat

```php
<?php

// Trouver un utilisateur par email
$user = $userRepository->findOneBy(['email' => 'john@example.com']);

// Avec tri pour obtenir le plus récent
$latestUser = $userRepository->findOneBy(
    ['status' => 'active'],
    ['createdAt' => 'DESC']
);

if ($user) {
    echo "Email trouvé : " . $user->getEmail();
}
```

### 📊 findAll() - Tous les Résultats

```php
<?php

// Récupérer toutes les entités
$allUsers = $userRepository->findAll();

echo "Nombre total d'utilisateurs : " . count($allUsers);

// Attention : peut être coûteux sur de grandes tables
foreach ($allUsers as $user) {
    echo $user->getName() . "\n";
}
```

### 🔢 count() - Compter les Résultats

```php
<?php

// Compter tous les utilisateurs
$totalUsers = $userRepository->count();

// Compter avec critères
$activeUsers = $userRepository->count(['status' => 'active']);
$premiumUsers = $userRepository->count(['plan' => 'premium', 'active' => true]);

echo "Utilisateurs actifs : $activeUsers / $totalUsers";
```

---

## Méthodes Magiques

Le système de repositories supporte des méthodes magiques pour simplifier les recherches courantes :

### 🪄 findBy* - Recherche par Propriété

```php
<?php

// Equivalent à findBy(['username' => 'john'])
$users = $userRepository->findByUsername('john');

// Equivalent à findBy(['status' => 'active'])
$users = $userRepository->findByStatus('active');

// Equivalent à findBy(['plan' => 'premium'])
$users = $userRepository->findByPlan('premium');
```

### 🎯 findOneBy* - Premier Résultat par Propriété

```php
<?php

// Equivalent à findOneBy(['email' => 'john@example.com'])
$user = $userRepository->findOneByEmail('john@example.com');

// Equivalent à findOneBy(['username' => 'john'])
$user = $userRepository->findOneByUsername('john');

// Equivalent à findOneBy(['token' => 'abc123'])
$user = $userRepository->findOneByToken('abc123');

if ($user) {
    echo "Utilisateur trouvé : " . $user->getName();
}
```

### 📝 Exemples de Méthodes Magiques

```php
<?php

class UserRepository extends EntityRepository
{
    // Ces méthodes sont automatiquement disponibles via __call()
    
    // $repository->findByEmail('test@example.com')
    // $repository->findByName('John')
    // $repository->findByStatus('active')
    // $repository->findByRole('admin')
    
    // $repository->findOneByEmail('test@example.com')
    // $repository->findOneByUsername('john')
    // $repository->findOneByToken('abc123')
}

// Utilisation
$userRepository = $entityManager->getRepository(User::class);

// Méthodes magiques disponibles automatiquement
$activeUsers = $userRepository->findByStatus('active');
$user = $userRepository->findOneByEmail('john@example.com');
```

---

## QueryBuilder Avancé

### 🛠️ createQueryBuilder() - Requêtes Personnalisées

```php
<?php

class UserRepository extends EntityRepository
{
    public function findActiveUsersWithPosts(): array
    {
        $qb = $this->createQueryBuilder();
        
        return $qb->select('u.*, COUNT(p.id) as post_count')
                  ->from($this->getTableName(), 'u')
                  ->leftJoin('posts', 'p', 'u.id = p.user_id')
                  ->where('u.status', 'active')
                  ->groupBy('u.id')
                  ->having('post_count > 0')
                  ->orderBy('post_count', 'DESC')
                  ->fetchAll();
    }
    
    public function findUsersByDateRange(DateTime $start, DateTime $end): array
    {
        $qb = $this->createQueryBuilder();
        
        return $qb->select('*')
                  ->from($this->getTableName())
                  ->where('created_at >= ?', $start->format('Y-m-d'))
                  ->where('created_at <= ?', $end->format('Y-m-d'))
                  ->orderBy('created_at', 'DESC')
                  ->fetchAll();
    }
}
```

### 🔧 Méthodes Utilitaires

```php
<?php

class UserRepository extends EntityRepository
{
    public function findByComplexCriteria(): array
    {
        // Accès au nom de table dynamique
        $tableName = $this->getTableName();
        
        // Accès à l'EntityManager pour autres opérations
        $em = $this->getEntityManager();
        
        // Création de QueryBuilder personnalisé
        $qb = $this->createQueryBuilder();
        
        return $qb->select('*')
                  ->from($tableName)
                  ->where('status = ? OR premium = ?', 'active', true)
                  ->fetchAll();
    }
    
    public function getEntityClassName(): string
    {
        // Récupérer le nom de la classe d'entité
        return $this->getEntityName();
    }
}
```

---

## Repositories Personnalisés

### 🎯 Création d'un Repository Personnalisé

```php
<?php

use MulerTech\Database\ORM\EntityRepository;

class UserRepository extends EntityRepository
{
    public function findActiveUsers(): array
    {
        return $this->findBy(['status' => 'active']);
    }
    
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }
    
    public function findRecentUsers(int $days = 30): array
    {
        $qb = $this->createQueryBuilder();
        
        return $qb->select('*')
                  ->from($this->getTableName())
                  ->where('created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)', $days)
                  ->orderBy('created_at', 'DESC')
                  ->fetchAll();
    }
    
    public function countByStatus(string $status): int
    {
        return $this->count(['status' => $status]);
    }
    
    public function findTopUsers(int $limit = 10): array
    {
        $qb = $this->createQueryBuilder();
        
        return $qb->select('u.*, COUNT(p.id) as post_count')
                  ->from($this->getTableName(), 'u')
                  ->leftJoin('posts', 'p', 'u.id = p.user_id')
                  ->groupBy('u.id')
                  ->orderBy('post_count', 'DESC')
                  ->limit($limit)
                  ->fetchAll();
    }
}
```

### 🔧 Configuration dans l'Entité

```php
<?php

use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey};

#[MtEntity(
    repository: UserRepository::class,
    tableName: 'users'
)]
class User
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $name;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 320)]
    private string $email;

    // Getters et setters...
}
```

### 📋 Repository Avancé avec Logique Métier

```php
<?php

class OrderRepository extends EntityRepository
{
    public function findPendingOrders(): array
    {
        return $this->findBy(['status' => 'pending']);
    }
    
    public function findOrdersByCustomer(int $customerId): array
    {
        return $this->findBy(['customer_id' => $customerId]);
    }
    
    public function findOrdersByDateRange(DateTime $start, DateTime $end): array
    {
        $qb = $this->createQueryBuilder();
        
        return $qb->select('*')
                  ->from($this->getTableName())
                  ->where('created_at >= ?', $start->format('Y-m-d H:i:s'))
                  ->where('created_at <= ?', $end->format('Y-m-d H:i:s'))
                  ->orderBy('created_at', 'DESC')
                  ->fetchAll();
    }
    
    public function getTotalSalesByMonth(int $year, int $month): float
    {
        $qb = $this->createQueryBuilder();
        
        $result = $qb->select('SUM(total) as total_sales')
                     ->from($this->getTableName())
                     ->where('YEAR(created_at) = ?', $year)
                     ->where('MONTH(created_at) = ?', $month)
                     ->where('status = ?', 'completed')
                     ->fetchScalar();
        
        return is_numeric($result) ? (float) $result : 0.0;
    }
    
    public function findLargeOrders(float $minimumAmount): array
    {
        $qb = $this->createQueryBuilder();
        
        return $qb->select('*')
                  ->from($this->getTableName())
                  ->where('total >= ?', $minimumAmount)
                  ->orderBy('total', 'DESC')
                  ->fetchAll();
    }
}
```

---

## Exemples Pratiques

### 🔄 Service avec Repository

```php
<?php

class UserService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
    
    public function getUserRepository(): UserRepository
    {
        return $this->entityManager->getRepository(User::class);
    }
    
    public function findUserByEmail(string $email): ?User
    {
        return $this->getUserRepository()->findOneByEmail($email);
    }
    
    public function getActiveUsers(): array
    {
        return $this->getUserRepository()->findByStatus('active');
    }
    
    public function getUserStats(): array
    {
        $repo = $this->getUserRepository();
        
        return [
            'total' => $repo->count(),
            'active' => $repo->count(['status' => 'active']),
            'inactive' => $repo->count(['status' => 'inactive']),
            'premium' => $repo->count(['plan' => 'premium'])
        ];
    }
}
```

### 📊 Dashboard avec Repositories

```php
<?php

class DashboardService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
    
    public function getDashboardData(): array
    {
        $userRepo = $this->entityManager->getRepository(User::class);
        $orderRepo = $this->entityManager->getRepository(Order::class);
        $productRepo = $this->entityManager->getRepository(Product::class);
        
        return [
            'users' => [
                'total' => $userRepo->count(),
                'active' => $userRepo->count(['status' => 'active']),
                'new_today' => $this->getNewUsersToday($userRepo)
            ],
            'orders' => [
                'total' => $orderRepo->count(),
                'pending' => $orderRepo->count(['status' => 'pending']),
                'completed' => $orderRepo->count(['status' => 'completed'])
            ],
            'products' => [
                'total' => $productRepo->count(),
                'active' => $productRepo->count(['status' => 'active']),
                'out_of_stock' => $productRepo->count(['stock' => 0])
            ]
        ];
    }
    
    private function getNewUsersToday(UserRepository $userRepo): int
    {
        $qb = $userRepo->createQueryBuilder();
        
        $result = $qb->select('COUNT(*)')
                     ->from($userRepo->getTableName())
                     ->where('DATE(created_at) = CURDATE()')
                     ->fetchScalar();
        
        return is_numeric($result) ? (int) $result : 0;
    }
}
```

### 🔍 Recherche Avancée

```php
<?php

class SearchService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
    
    public function searchUsers(string $query, array $filters = []): array
    {
        $userRepo = $this->entityManager->getRepository(User::class);
        $qb = $userRepo->createQueryBuilder();
        
        $qb->select('*')
           ->from($userRepo->getTableName())
           ->where('name LIKE ? OR email LIKE ?', "%$query%", "%$query%");
        
        // Appliquer les filtres
        if (isset($filters['status'])) {
            $qb->where('status = ?', $filters['status']);
        }
        
        if (isset($filters['plan'])) {
            $qb->where('plan = ?', $filters['plan']);
        }
        
        if (isset($filters['created_after'])) {
            $qb->where('created_at >= ?', $filters['created_after']);
        }
        
        return $qb->orderBy('name', 'ASC')
                  ->limit($filters['limit'] ?? 50)
                  ->fetchAll();
    }
}
```

---

## ➡️ Étapes Suivantes

Pour approfondir votre compréhension :

1. 🗄️ [Entity Manager](entity-manager.md) - Gestion des entités
2. 🔧 [Query Builder](../../fr/query-builder/select-queries.md) - Construction de requêtes
3. 🎨 [Attributs de Mapping](../../fr/entity-mapping/attributes.md) - Configuration des entités
4. 🎯 [Exemples Pratiques](../../fr/quick-start/basic-examples.md) - Cas d'usage concrets

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../fr/../README.md)
- 📖 [Documentation Complète](../../fr/README.md)
- 🚀 [Démarrage Rapide](../../fr/quick-start/installation.md)
