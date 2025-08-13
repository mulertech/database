# Entity Manager

🌍 **Languages:** [🇫🇷 Français](entity-manager.md) | [🇬🇧 English](../../en/orm/entity-manager.md)

---

## 📋 Table des Matières

- [Vue d'Ensemble](#vue-densemble)
- [Configuration de Base](#configuration-de-base)
- [Méthodes Principales](#méthodes-principales)
- [Opérations CRUD](#opérations-crud)
- [Gestion des Repositories](#gestion-des-repositories)
- [Composants Internes](#composants-internes)
- [Exemples Pratiques](#exemples-pratiques)

---

## Vue d'Ensemble

L'**EntityManager** est le point d'entrée principal de MulerTech Database. Il fournit une interface simple pour interagir avec les entités et délègue les opérations complexes à l'**EmEngine**.

### 🎯 Responsabilités Principales

- **Point d'entrée** : Interface simplifiée pour l'utilisateur
- **Delegation** : Transfert des opérations vers EmEngine
- **Repositories** : Accès aux repositories d'entités
- **Métadonnées** : Accès au registre des métadonnées
- **Base de données** : Interface avec le driver de base de données

### 📦 Imports Nécessaires

```php
<?php
use MulerTech\Database\ORM\{EntityManager, EntityManagerInterface};
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\Mapping\MetadataRegistry;
```

---

## Configuration de Base

### 🔧 Initialisation Standard

```php
<?php
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\Mapping\MetadataRegistry;

// Configuration de la base de données
$driver = new MySQLDriver([
    'host' => 'localhost',
    'database' => 'my_database',
    'username' => 'user',
    'password' => 'password'
]);

// Registre des métadonnées avec chargement automatique
$metadataRegistry = new MetadataRegistry('/path/to/entities');

// EntityManager
$entityManager = new EntityManager($driver, $metadataRegistry);
```

### 🔧 Avec Gestionnaire d'Événements

```php
<?php
use MulerTech\EventManager\EventManager;

// Gestionnaire d'événements (optionnel)
$eventManager = new EventManager();

// EntityManager avec événements
$entityManager = new EntityManager($driver, $metadataRegistry, $eventManager);
```

---

## Méthodes Principales

L'interface `EntityManagerInterface` définit les méthodes réellement disponibles :

### 🔍 Méthodes de Recherche

```php
/**
 * Rechercher une entité par ID ou critère WHERE
 * @param class-string $entity
 * @param string|int $idOrWhere
 * @return object|null
 */
public function find(string $entity, string|int $idOrWhere): ?object;

/**
 * Vérifier l'unicité d'une propriété
 * @param class-string $entity
 * @param string $property
 * @param int|string $search
 * @param int|string|null $id
 * @param bool $matchCase
 * @return bool
 */
public function isUnique(
    string $entity,
    string $property,
    int|string $search,
    int|string|null $id = null,
    bool $matchCase = false
): bool;
```

### 💾 Méthodes de Persistance

```php
/**
 * Marquer une entité pour persistance
 */
public function persist(object $entity): void;

/**
 * Marquer une entité pour suppression
 */
public function remove(object $entity): void;

/**
 * Synchroniser toutes les modifications avec la base de données
 */
public function flush(): void;
```

### 🔄 Méthodes de Gestion d'État

```php
/**
 * Fusionner une entité détachée
 */
public function merge(object $entity): object;

/**
 * Détacher une entité du contexte de persistance
 */
public function detach(object $entity): void;

/**
 * Recharger une entité depuis la base de données
 */
public function refresh(object $entity): void;
```

### 🗂️ Méthodes d'Accès aux Composants

```php
/**
 * Obtenir le moteur ORM
 */
public function getEmEngine(): EmEngine;

/**
 * Obtenir le driver de base de données
 */
public function getPdm(): PhpDatabaseInterface;

/**
 * Obtenir le registre des métadonnées
 */
public function getMetadataRegistry(): MetadataRegistry;

/**
 * Obtenir le gestionnaire d'événements
 */
public function getEventManager(): ?EventManager;

/**
 * Obtenir un repository d'entité
 */
public function getRepository(string $entity): EntityRepository;
```

---

## Opérations CRUD

### 💾 Create - Créer une Entité

```php
<?php
use App\Entity\User;

// Créer une nouvelle entité
$user = new User();
$user->setName('Jean Dupont');
$user->setEmail('jean@example.com');

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

// Trouver avec condition WHERE personnalisée
$user = $entityManager->find(User::class, "email = 'jean@example.com'");

// Vérifier l'unicité d'un email
$isUnique = $entityManager->isUnique(User::class, 'email', 'test@example.com');

if ($isUnique) {
    echo "L'email est disponible";
}

// Vérifier l'unicité en excluant un ID (pour les mises à jour)
$isUnique = $entityManager->isUnique(User::class, 'email', 'nouveau@example.com', 1);
```

### ✏️ Update - Modifier une Entité

```php
<?php

// Récupérer l'entité
$user = $entityManager->find(User::class, 1);

if ($user) {
    // Modifier les propriétés
    $user->setName('Jean Martin');
    $user->setUpdatedAt(new DateTime());
    
    // Les modifications sont automatiquement détectées
    $entityManager->flush();
    
    echo "Utilisateur mis à jour";
}
```

### 🗑️ Delete - Supprimer une Entité

```php
<?php

// Récupérer l'entité
$user = $entityManager->find(User::class, 1);

if ($user) {
    // Marquer pour suppression
    $entityManager->remove($user);
    
    // Exécuter la suppression
    $entityManager->flush();
    
    echo "Utilisateur supprimé";
}
```

---

## Gestion des Repositories

### 🗂️ Accès aux Repositories

```php
<?php

// Obtenir un repository
$userRepository = $entityManager->getRepository(User::class);

// Utiliser les méthodes du repository
$user = $userRepository->find(1);
$users = $userRepository->findBy(['status' => 'active']);
```

### 🎯 Repository Personnalisé

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
        $result = $this->entityManager->find($this->entityName, "email = '{$email}'");
        return $result;
    }
}

// Configuration dans l'entité
#[MtEntity(repository: UserRepository::class, tableName: 'users')]
class User
{
    // ...
}

// Utilisation
$userRepository = $entityManager->getRepository(User::class);
$activeUsers = $userRepository->findActiveUsers();
```

---

## Composants Internes

### ⚙️ EmEngine - Moteur ORM

```php
<?php

// Accès direct au moteur pour des opérations avancées
$emEngine = $entityManager->getEmEngine();

// Le moteur gère les opérations complexes
$emEngine->persist($entity);
$emEngine->flush();
```

### 📊 MetadataRegistry - Métadonnées

```php
<?php

// Accès aux métadonnées
$metadataRegistry = $entityManager->getMetadataRegistry();

// Obtenir les métadonnées d'une entité
$metadata = $metadataRegistry->getEntityMetadata(User::class);

echo "Nom de table: " . $metadata->tableName;
echo "Repository: " . $metadata->getRepository();
```

### 🎯 PhpDatabaseInterface - Driver

```php
<?php

// Accès direct au driver de base de données
$driver = $entityManager->getPdm();

// Exécuter une requête personnalisée
$result = $driver->query("SELECT COUNT(*) FROM users");
```

---

## Exemples Pratiques

### 🔄 Gestion Complète d'une Entité

```php
<?php

class UserService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
    
    public function createUser(string $name, string $email): User
    {
        // Vérifier l'unicité de l'email
        if (!$this->entityManager->isUnique(User::class, 'email', $email)) {
            throw new Exception("L'email existe déjà");
        }
        
        // Créer l'utilisateur
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setCreatedAt(new DateTime());
        
        // Persister
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        return $user;
    }
    
    public function updateUser(int $id, string $newEmail): User
    {
        $user = $this->entityManager->find(User::class, $id);
        
        if (!$user) {
            throw new Exception("Utilisateur non trouvé");
        }
        
        // Vérifier l'unicité en excluant l'utilisateur actuel
        if (!$this->entityManager->isUnique(User::class, 'email', $newEmail, $id)) {
            throw new Exception("L'email est déjà utilisé");
        }
        
        // Mettre à jour
        $user->setEmail($newEmail);
        $user->setUpdatedAt(new DateTime());
        
        $this->entityManager->flush();
        
        return $user;
    }
    
    public function deleteUser(int $id): bool
    {
        $user = $this->entityManager->find(User::class, $id);
        
        if (!$user) {
            return false;
        }
        
        $this->entityManager->remove($user);
        $this->entityManager->flush();
        
        return true;
    }
}
```

### 🎛️ Gestion d'État des Entités

```php
<?php

class EntityStateService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
    
    public function cloneAndDetachEntity(object $entity): object
    {
        // Créer une copie
        $clone = clone $entity;
        
        // Détacher du contexte de persistance
        $this->entityManager->detach($clone);
        
        return $clone;
    }
    
    public function refreshFromDatabase(object $entity): object
    {
        // Recharger depuis la base de données
        $this->entityManager->refresh($entity);
        
        return $entity;
    }
    
    public function mergeDetachedEntity(object $detachedEntity): object
    {
        // Fusionner une entité détachée
        return $this->entityManager->merge($detachedEntity);
    }
}
```

### 📊 Utilisation avec Événements

```php
<?php

// Configuration des événements
$eventManager = $entityManager->getEventManager();

if ($eventManager) {
    $eventManager->addListener('pre.persist', function($event) {
        $entity = $event->getEntity();
        
        if ($entity instanceof User && !$entity->getCreatedAt()) {
            $entity->setCreatedAt(new DateTime());
        }
    });
}

// Les événements se déclenchent automatiquement lors des opérations
$user = new User();
$user->setName('Test');
$entityManager->persist($user); // Déclenche pre.persist
$entityManager->flush();
```

---

## ➡️ Étapes Suivantes

Pour approfondir votre compréhension :

1. 🔄 [Suivi des Changements](change-tracking.md) - Système de change tracking
2. 🗂️ [Repositories](repositories.md) - Repositories personnalisés
3. 🎨 [Attributs de Mapping](../../fr/entity-mapping/attributes.md) - Configuration des entités
4. 🎯 [Exemples Pratiques](../../fr/quick-start/basic-examples.md) - Cas d'usage concrets

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../fr/../README.md)
- 📖 [Documentation Complète](../../fr/README.md)
- 🚀 [Démarrage Rapide](../../fr/quick-start/installation.md)
