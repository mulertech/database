# Entity Manager

🌍 **Languages:** [🇫🇷 Français](entity-manager.md) | [🇬🇧 English](../../en/orm/entity-manager.md)

---

## 📋 Table des Matières

- [Vue d'Ensemble](#vue-densemble)
- [Configuration de Base](#configuration-de-base)
- [Opérations CRUD](#opérations-crud)
- [Gestion du Cycle de Vie](#gestion-du-cycle-de-vie)
- [États des Entités](#états-des-entités)
- [Gestion des Relations](#gestion-des-relations)
- [Transactions](#transactions)
- [Performance et Optimisation](#performance-et-optimisation)
- [Événements](#événements)
- [API Complète](#api-complète)

---

## Vue d'Ensemble

L'**EntityManager** est le cœur de MulerTech Database. Il gère le cycle de vie des entités, le suivi des modifications et la synchronisation avec la base de données.

### 🎯 Responsabilités Principales

- **Persistance** : Sauvegarder les entités en base
- **Hydratation** : Transformer les données SQL en objets PHP
- **Change Tracking** : Détecter les modifications automatiquement
- **Relations** : Gérer les associations entre entités
- **Transactions** : Assurer la cohérence des données
- **Cache** : Optimiser les performances

### 🏗️ Architecture

```php
<?php

use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\ORM\Engine\EmEngine;

// L'EntityManager utilise EmEngine pour les opérations de base
$entityManager = new EntityManager($connection, $config);
$emEngine = $entityManager->getEmEngine();
```

---

## Configuration de Base

### 🔧 Initialisation

```php
<?php

use MulerTech\Database\Connection\DatabaseConnection;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Config\Configuration;

// Configuration de la base de données
$config = new Configuration([
    'host' => 'localhost',
    'database' => 'my_database',
    'username' => 'user',
    'password' => 'password',
    'driver' => 'mysql',
    'charset' => 'utf8mb4'
]);

// Connexion
$connection = new DatabaseConnection($config);

// EntityManager
$entityManager = new EntityManager($connection, $config);
```

### 🗂️ Configuration des Entités

```php
<?php

// Enregistrement des entités
$entityManager->addEntityPath('App\\Entity\\');
$entityManager->addEntityClass(User::class);
$entityManager->addEntityClass(Post::class);

// Scan automatique d'un dossier
$entityManager->scanEntitiesDirectory('/path/to/entities/');
```

---

## Opérations CRUD

### 💾 Create - Créer une Entité

```php
<?php

use App\Entity\User;

// Créer une nouvelle entité
$user = new User();
$user->setName('Jean Dupont')
     ->setEmail('jean@example.com');

// Marquer pour persistance
$entityManager->persist($user);

// Sauvegarder en base de données
$entityManager->flush();

echo "Utilisateur créé avec l'ID: " . $user->getId();
```

### 🔍 Read - Lire des Entités

```php
<?php

// Trouver par ID
$user = $entityManager->find(User::class, 1);

// Trouver un seul résultat
$user = $entityManager->findOneBy(User::class, ['email' => 'jean@example.com']);

// Trouver tous les résultats
$users = $entityManager->findAll(User::class);

// Trouver avec critères
$activeUsers = $entityManager->findBy(User::class, [
    'status' => 'active',
    'verified' => true
]);

// Trouver avec tri et limite
$recentUsers = $entityManager->findBy(User::class, [], [
    'createdAt' => 'DESC'
], 10);
```

### ✏️ Update - Modifier une Entité

```php
<?php

// Récupérer l'entité
$user = $entityManager->find(User::class, 1);

// Modifier les propriétés
$user->setName('Jean Martin');
$user->setUpdatedAt(new DateTime());

// Sauvegarder automatiquement (change tracking)
$entityManager->flush();

echo "Utilisateur mis à jour";
```

### 🗑️ Delete - Supprimer une Entité

```php
<?php

// Récupérer l'entité
$user = $entityManager->find(User::class, 1);

// Marquer pour suppression
$entityManager->remove($user);

// Exécuter la suppression
$entityManager->flush();

echo "Utilisateur supprimé";
```

---

## Gestion du Cycle de Vie

### 📊 États des Entités

```php
<?php

use MulerTech\Database\ORM\EntityState;

$user = new User();
echo $entityManager->getEntityState($user); // EntityState::NEW

$entityManager->persist($user);
echo $entityManager->getEntityState($user); // EntityState::MANAGED

$entityManager->flush();
echo $entityManager->getEntityState($user); // EntityState::MANAGED

$entityManager->remove($user);
echo $entityManager->getEntityState($user); // EntityState::REMOVED

$entityManager->detach($user);
echo $entityManager->getEntityState($user); // EntityState::DETACHED
```

### 🔄 Opérations de Cycle de Vie

```php
<?php

$user = $entityManager->find(User::class, 1);

// Détacher une entité (ne plus suivre les modifications)
$entityManager->detach($user);

// Réattacher une entité
$entityManager->merge($user);

// Actualiser une entité depuis la base
$entityManager->refresh($user);

// Vider le gestionnaire d'entités
$entityManager->clear();

// Vérifier si une entité est gérée
if ($entityManager->contains($user)) {
    echo "L'entité est gérée par l'EntityManager";
}
```

---

## États des Entités

### 🏷️ Enum EntityState

```php
<?php

enum EntityState: string
{
    case NEW = 'new';           // Nouvelle entité, pas encore persistée
    case MANAGED = 'managed';   // Entité gérée par l'EntityManager
    case DETACHED = 'detached'; // Entité détachée, plus suivie
    case REMOVED = 'removed';   // Entité marquée pour suppression
}
```

### 📈 Suivi des Modifications

```php
<?php

$user = $entityManager->find(User::class, 1);

// Obtenir les modifications
$changeSet = $entityManager->getChangeSet($user);

foreach ($changeSet as $property => $changes) {
    echo "Propriété '{$property}' : {$changes['old']} → {$changes['new']}\n";
}

// Vérifier si l'entité a été modifiée
if ($entityManager->hasChanges($user)) {
    echo "L'entité a des modifications non sauvegardées";
}

// Annuler les modifications
$entityManager->refresh($user);
```

---

## Gestion des Relations

### 🔗 Relations OneToMany

```php
<?php

use App\Entity\{User, Post};

$user = $entityManager->find(User::class, 1);

// Créer un nouveau post
$post = new Post();
$post->setTitle('Mon Article')
     ->setContent('Contenu de l\'article')
     ->setAuthor($user);

$entityManager->persist($post);
$entityManager->flush();

// Récupérer tous les posts de l'utilisateur
$posts = $user->getPosts();
foreach ($posts as $post) {
    echo "- " . $post->getTitle() . "\n";
}
```

### 🔗 Relations ManyToMany

```php
<?php

use App\Entity\{Post, Tag};

$post = $entityManager->find(Post::class, 1);
$tag = $entityManager->find(Tag::class, 1);

// Ajouter un tag au post
$post->getTags()->add($tag);

// L'EntityManager détecte automatiquement les modifications
$entityManager->flush();

// Supprimer un tag
$post->getTags()->removeElement($tag);
$entityManager->flush();
```

### 🔄 Chargement Lazy vs Eager

```php
<?php

// Chargement lazy (par défaut)
$user = $entityManager->find(User::class, 1);
$posts = $user->getPosts(); // Requête SQL lors de l'accès

// Chargement eager avec jointure
$queryBuilder = $entityManager->createQueryBuilder();
$usersWithPosts = $queryBuilder
    ->select('u', 'p')
    ->from(User::class, 'u')
    ->leftJoin('u.posts', 'p')
    ->where('u.id = :id')
    ->setParameter('id', 1)
    ->getResult();
```

---

## Transactions

### 💾 Transaction Manuelle

```php
<?php

try {
    // Commencer la transaction
    $entityManager->beginTransaction();
    
    $user = new User();
    $user->setName('Transaction Test')
         ->setEmail('test@example.com');
    
    $entityManager->persist($user);
    
    $post = new Post();
    $post->setTitle('Post Transactionnel')
         ->setAuthor($user);
    
    $entityManager->persist($post);
    $entityManager->flush();
    
    // Valider la transaction
    $entityManager->commit();
    
    echo "Transaction réussie";
    
} catch (Exception $e) {
    // Annuler la transaction
    $entityManager->rollback();
    echo "Erreur: " . $e->getMessage();
}
```

### 🎯 Transaction avec Callback

```php
<?php

use MulerTech\Database\Exception\DatabaseException;

$result = $entityManager->transactional(function($em) {
    $user = new User();
    $user->setName('Callback Test')
         ->setEmail('callback@example.com');
    
    $em->persist($user);
    $em->flush();
    
    // Retourner une valeur
    return $user->getId();
});

echo "Utilisateur créé avec l'ID: " . $result;
```

---

## Performance et Optimisation

### ⚡ Batch Processing

```php
<?php

// Traitement par batch pour éviter les problèmes mémoire
$batchSize = 50;
$i = 0;

foreach ($largeDataSet as $data) {
    $entity = new User();
    $entity->setName($data['name'])
           ->setEmail($data['email']);
    
    $entityManager->persist($entity);
    
    if (($i % $batchSize) === 0) {
        $entityManager->flush();
        $entityManager->clear(); // Libérer la mémoire
    }
    
    $i++;
}

// Traiter le dernier batch
$entityManager->flush();
$entityManager->clear();
```

### 🚀 Optimisation des Requêtes

```php
<?php

// Éviter N+1 queries avec des jointures
$queryBuilder = $entityManager->createQueryBuilder();

$posts = $queryBuilder
    ->select('p', 'u', 't')
    ->from(Post::class, 'p')
    ->join('p.author', 'u')
    ->leftJoin('p.tags', 't')
    ->where('p.published = :published')
    ->setParameter('published', true)
    ->getResult();

// Tous les auteurs et tags sont chargés en une seule requête
foreach ($posts as $post) {
    echo $post->getTitle() . ' par ' . $post->getAuthor()->getName() . "\n";
}
```

### 💾 Cache d'Entités

```php
<?php

// Activer le cache
$entityManager->getConfiguration()->enableResultCache();

// Requête avec cache
$users = $entityManager->findBy(User::class, ['active' => true]);

// Invalider le cache
$entityManager->getConfiguration()->getCache()->clear();

// Cache pour une requête spécifique
$queryBuilder = $entityManager->createQueryBuilder();
$result = $queryBuilder
    ->select('u')
    ->from(User::class, 'u')
    ->setCacheable(true)
    ->setCacheLifetime(3600) // 1 heure
    ->getResult();
```

---

## Événements

### 🔔 Gestionnaire d'Événements

```php
<?php

use MulerTech\Database\Event\{PrePersistEvent, PostPersistEvent, PreUpdateEvent, PostUpdateEvent};

$eventManager = $entityManager->getEventManager();

// Événement avant persistance
$eventManager->addListener(PrePersistEvent::class, function(PrePersistEvent $event) {
    $entity = $event->getEntity();
    
    if ($entity instanceof User) {
        $entity->setCreatedAt(new DateTime());
        $entity->setToken(bin2hex(random_bytes(32)));
    }
});

// Événement après persistance
$eventManager->addListener(PostPersistEvent::class, function(PostPersistEvent $event) {
    $entity = $event->getEntity();
    
    if ($entity instanceof User) {
        // Envoyer un email de bienvenue
        mail($entity->getEmail(), 'Bienvenue', 'Compte créé avec succès');
    }
});
```

### 📝 Événements Disponibles

```php
<?php

// Événements de persistance
PrePersistEvent::class;   // Avant insert
PostPersistEvent::class;  // Après insert

// Événements de mise à jour
PreUpdateEvent::class;    // Avant update
PostUpdateEvent::class;   // Après update

// Événements de suppression
PreRemoveEvent::class;    // Avant delete
PostRemoveEvent::class;   // Après delete

// Événements de chargement
PostLoadEvent::class;     // Après chargement depuis la DB
```

---

## API Complète

### 🔧 Méthodes Principales

```php
<?php

interface EntityManagerInterface
{
    // === PERSISTANCE ===
    public function persist(object $entity): void;
    public function remove(object $entity): void;
    public function flush(): void;
    public function clear(): void;
    
    // === RECHERCHE ===
    public function find(string $className, mixed $id): ?object;
    public function findOneBy(string $className, array $criteria): ?object;
    public function findBy(string $className, array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array;
    public function findAll(string $className): array;
    
    // === ÉTAT DES ENTITÉS ===
    public function contains(object $entity): bool;
    public function detach(object $entity): void;
    public function merge(object $entity): object;
    public function refresh(object $entity): void;
    public function getEntityState(object $entity): EntityState;
    
    // === MODIFICATIONS ===
    public function hasChanges(object $entity): bool;
    public function getChangeSet(object $entity): array;
    public function computeChangeSets(): void;
    
    // === TRANSACTIONS ===
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollback(): void;
    public function transactional(callable $callback): mixed;
    
    // === CONFIGURATION ===
    public function getConnection(): ConnectionInterface;
    public function getConfiguration(): Configuration;
    public function getEventManager(): EventManager;
    public function getEmEngine(): EmEngine;
    
    // === MÉTADONNÉES ===
    public function getMetadataFor(string $className): EntityMetadata;
    public function hasMetadataFor(string $className): bool;
    
    // === REPOSITORIES ===
    public function getRepository(string $className): EntityRepository;
}
```

### 📊 Méthodes de Debug

```php
<?php

// Statistiques de l'EntityManager
$stats = $entityManager->getStats();
echo "Entités gérées: " . $stats['managed_entities'] . "\n";
echo "Requêtes exécutées: " . $stats['executed_queries'] . "\n";

// Logger les requêtes SQL
$entityManager->getConfiguration()->setSQLLogger(new class {
    public function logSQL(string $sql, array $params = []): void {
        echo "SQL: " . $sql . "\n";
        echo "Params: " . json_encode($params) . "\n";
    }
});

// Mode debug
$entityManager->getConfiguration()->setDebugMode(true);
```

---

## ➡️ Étapes Suivantes

Explorez les concepts suivants :

1. 🗂️ [Repositories](repositories.md) - Repositories personnalisés
2. 🔄 [Change Tracking](change-tracking.md) - Suivi détaillé des modifications
3. 🎉 [Événements](events.md) - Système d'événements avancé
4. 💾 [Cache](caching.md) - Système de cache et performance

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../README.md)
- ⬅️ [Attributs de Mapping](../entity-mapping/attributes.md)
- ➡️ [Repositories](repositories.md)
- 📖 [Documentation Complète](../README.md)