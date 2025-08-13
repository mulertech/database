# Système d'Événements

🌍 **Languages:** [🇫🇷 Français](events.md) | [🇬🇧 English](../../en/orm/events.md)

---

## 📋 Table des Matières

- [Vue d'Ensemble](#vue-densemble)
- [Architecture des Événements](#architecture-des-événements)
- [Types d'Événements Disponibles](#types-dévénements-disponibles)
- [Événements du Cycle de Vie](#événements-du-cycle-de-vie)
- [Événements de Transition d'État](#événements-de-transition-détat)
- [Utilisation avec EventManager](#utilisation-avec-eventmanager)
- [Exemples Pratiques](#exemples-pratiques)

---

## Vue d'Ensemble

MulerTech Database intègre un système d'événements basé sur `MulerTech\EventManager` qui permet de réagir aux opérations de l'ORM et d'implémenter des comportements transversaux comme l'audit, la validation, ou la synchronisation.

### 🎯 Principe de Base

Le système d'événements utilise un gestionnaire d'événements externe et déclenche automatiquement des événements lors des opérations de l'ORM.

### 📦 Imports Nécessaires

```php
<?php
use MulerTech\Database\Event\{
    AbstractEntityEvent, DbEvents, PrePersistEvent, PostPersistEvent,
    PreUpdateEvent, PostUpdateEvent, StateTransitionEvent
};
use MulerTech\EventManager\EventManager;
use MulerTech\Database\ORM\State\EntityLifecycleState;
```

---

## Architecture des Événements

### 🏗️ Classe de Base AbstractEntityEvent

```php
abstract class AbstractEntityEvent extends Event
{
    protected object $entity;
    protected EntityManagerInterface $entityManager;

    public function __construct(object $entity, EntityManagerInterface $entityManager, DbEvents $eventName);
    
    public function getEntity(): object;
    public function getEntityManager(): EntityManagerInterface;
}
```

### 📋 Enum DbEvents

L'enum `DbEvents` définit tous les types d'événements disponibles :

```php
enum DbEvents: string
{
    case preRemove = 'preRemove';
    case postRemove = 'postRemove';
    case prePersist = 'prePersist';
    case postPersist = 'postPersist';
    case preUpdate = 'preUpdate';
    case postUpdate = 'postUpdate';
    case postLoad = 'postLoad';
    case loadClassMetadata = 'loadClassMetadata';
    case onClassMetadataNotFound = 'onClassMetadataNotFound';
    case preFlush = 'preFlush';
    case onFlush = 'onFlush';
    case postFlush = 'postFlush';
    case onClear = 'onClear';
    case preStateTransition = 'preStateTransition';
    case postStateTransition = 'postStateTransition';
}
```

---

## Types d'Événements Disponibles

### 🔄 Événements de Persistance

- **PrePersistEvent** : Avant l'insertion d'une nouvelle entité
- **PostPersistEvent** : Après l'insertion d'une nouvelle entité

### ✏️ Événements de Mise à Jour

- **PreUpdateEvent** : Avant la mise à jour d'une entité
- **PostUpdateEvent** : Après la mise à jour d'une entité

### 🗑️ Événements de Suppression

- **PreRemoveEvent** : Avant la suppression d'une entité
- **PostRemoveEvent** : Après la suppression d'une entité

### 💾 Événements de Flush

- **PreFlushEvent** : Avant la synchronisation avec la base de données
- **PostFlushEvent** : Après la synchronisation avec la base de données

### 📊 Événements de Chargement et Métadonnées

- **PostLoadEvent** : Après le chargement d'une entité depuis la base
- **LoadClassMetadataEvent** : Lors du chargement des métadonnées d'une classe
- **OnClassMetadataNotFoundEvent** : Quand les métadonnées d'une classe ne sont pas trouvées

### 🔄 Événements de Transition d'État

- **StateTransitionEvent** : Lors des changements d'état des entités

---

## Événements du Cycle de Vie

### 💾 PrePersistEvent / PostPersistEvent

```php
class PrePersistEvent extends AbstractEntityEvent
{
    public function __construct(object $entity, EntityManagerInterface $entityManager)
    {
        parent::__construct($entity, $entityManager, DbEvents::prePersist);
    }
}

class PostPersistEvent extends AbstractEntityEvent
{
    public function __construct(object $entity, EntityManagerInterface $entityManager)
    {
        parent::__construct($entity, $entityManager, DbEvents::postPersist);
    }
}
```

### ✏️ PreUpdateEvent / PostUpdateEvent

```php
class PreUpdateEvent extends AbstractEntityEvent
{
    public function __construct(object $entity, EntityManagerInterface $entityManager)
    {
        parent::__construct($entity, $entityManager, DbEvents::preUpdate);
    }
}

class PostUpdateEvent extends AbstractEntityEvent
{
    public function __construct(object $entity, EntityManagerInterface $entityManager)
    {
        parent::__construct($entity, $entityManager, DbEvents::postUpdate);
    }
}
```

### 🗑️ PreRemoveEvent / PostRemoveEvent

```php
class PreRemoveEvent extends AbstractEntityEvent
{
    public function __construct(object $entity, EntityManagerInterface $entityManager)
    {
        parent::__construct($entity, $entityManager, DbEvents::preRemove);
    }
}

class PostRemoveEvent extends AbstractEntityEvent
{
    public function __construct(object $entity, EntityManagerInterface $entityManager)
    {
        parent::__construct($entity, $entityManager, DbEvents::postRemove);
    }
}
```

### 💾 PreFlushEvent / PostFlushEvent

```php
class PreFlushEvent extends AbstractEntityEvent
{
    public function __construct(object $entity, EntityManagerInterface $entityManager)
    {
        parent::__construct($entity, $entityManager, DbEvents::preFlush);
    }
}

class PostFlushEvent extends AbstractEntityEvent
{
    public function __construct(object $entity, EntityManagerInterface $entityManager)
    {
        parent::__construct($entity, $entityManager, DbEvents::postFlush);
    }
}
```

---

## Événements de Transition d'État

### 🔄 StateTransitionEvent

La classe `StateTransitionEvent` gère les transitions d'état des entités avec le système `EntityLifecycleState` :

```php
final class StateTransitionEvent extends Event
{
    public function __construct(
        private readonly object $entity,
        private readonly EntityLifecycleState $fromState,
        private readonly EntityLifecycleState $toState,
        private readonly string $phase  // 'pre' ou 'post'
    );
    
    public function getEntity(): object;
    public function getFromState(): EntityLifecycleState;
    public function getToState(): EntityLifecycleState;
    public function getPhase(): string;
}
```

### 📊 États Disponibles

```php
enum EntityLifecycleState: string
{
    case NEW = 'new';
    case MANAGED = 'managed'; 
    case REMOVED = 'removed';
    case DETACHED = 'detached';
}
```

---

## Utilisation avec EventManager

### 🔧 Configuration de Base

```php
<?php
use MulerTech\EventManager\EventManager;
use MulerTech\Database\ORM\EntityManager;

// Créer l'EntityManager avec EventManager
$eventManager = new EventManager();
$entityManager = new EntityManager($driver, $metadataRegistry, $eventManager);

// L'EventManager est maintenant disponible
$em = $entityManager->getEventManager();
```

### 👂 Ajout de Listeners

```php
<?php

// Obtenir l'EventManager
$eventManager = $entityManager->getEventManager();

if ($eventManager) {
    // Listener pour PrePersistEvent
    $eventManager->addListener('prePersist', function($event) {
        $entity = $event->getEntity();
        
        if ($entity instanceof User) {
            $entity->setCreatedAt(new DateTime());
            $entity->setToken(bin2hex(random_bytes(16)));
        }
    });
    
    // Listener pour PostPersistEvent
    $eventManager->addListener('postPersist', function($event) {
        $entity = $event->getEntity();
        
        if ($entity instanceof User) {
            // Envoyer email de bienvenue
            mail($entity->getEmail(), 'Bienvenue', 'Compte créé avec succès');
        }
    });
}
```

---

## Exemples Pratiques

### 🕒 Système d'Horodatage Automatique

```php
<?php

class TimestampListener
{
    public function handlePrePersist($event): void
    {
        $entity = $event->getEntity();
        
        if (method_exists($entity, 'setCreatedAt')) {
            $entity->setCreatedAt(new DateTime());
        }
        
        if (method_exists($entity, 'setUpdatedAt')) {
            $entity->setUpdatedAt(new DateTime());
        }
    }
    
    public function handlePreUpdate($event): void
    {
        $entity = $event->getEntity();
        
        if (method_exists($entity, 'setUpdatedAt')) {
            $entity->setUpdatedAt(new DateTime());
        }
    }
}

// Configuration
$timestampListener = new TimestampListener();
$eventManager = $entityManager->getEventManager();

if ($eventManager) {
    $eventManager->addListener('prePersist', [$timestampListener, 'handlePrePersist']);
    $eventManager->addListener('preUpdate', [$timestampListener, 'handlePreUpdate']);
}
```

### 📝 Système d'Audit

```php
<?php

class AuditLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
    
    public function logEntityChange($event): void
    {
        $entity = $event->getEntity();
        $eventName = $event->getName();
        
        $auditLog = new AuditLog();
        $auditLog->setEntityClass($entity::class);
        $auditLog->setEntityId($this->getEntityId($entity));
        $auditLog->setAction($eventName);
        $auditLog->setTimestamp(new DateTime());
        $auditLog->setUserId($this->getCurrentUserId());
        
        // Persister le log d'audit
        $this->entityManager->persist($auditLog);
    }
    
    private function getEntityId(object $entity): ?int
    {
        if (method_exists($entity, 'getId')) {
            return $entity->getId();
        }
        return null;
    }
    
    private function getCurrentUserId(): ?int
    {
        // Logique pour obtenir l'ID de l'utilisateur actuel
        return $_SESSION['user_id'] ?? null;
    }
}

// Configuration
$auditLogger = new AuditLogger($entityManager);
$eventManager = $entityManager->getEventManager();

if ($eventManager) {
    $eventManager->addListener('postPersist', [$auditLogger, 'logEntityChange']);
    $eventManager->addListener('postUpdate', [$auditLogger, 'logEntityChange']);
    $eventManager->addListener('postRemove', [$auditLogger, 'logEntityChange']);
}
```

### 🔄 Gestion des Transitions d'État

```php
<?php

class StateTransitionLogger
{
    public function handleStateTransition($event): void
    {
        if ($event instanceof StateTransitionEvent) {
            $entity = $event->getEntity();
            $fromState = $event->getFromState();
            $toState = $event->getToState();
            $phase = $event->getPhase();
            
            error_log(sprintf(
                "%s state transition for %s: %s -> %s (phase: %s)",
                $phase,
                $entity::class,
                $fromState->value,
                $toState->value,
                $phase
            ));
            
            // Actions spécifiques selon la transition
            if ($fromState === EntityLifecycleState::NEW && 
                $toState === EntityLifecycleState::MANAGED) {
                // Entité nouvellement persistée
                $this->handleNewEntity($entity);
            }
        }
    }
    
    private function handleNewEntity(object $entity): void
    {
        if ($entity instanceof User) {
            // Initialiser les données par défaut
            $entity->setStatus('active');
        }
    }
}

// Configuration
$stateLogger = new StateTransitionLogger();
$eventManager = $entityManager->getEventManager();

if ($eventManager) {
    $eventManager->addListener('preStateTransition', [$stateLogger, 'handleStateTransition']);
    $eventManager->addListener('postStateTransition', [$stateLogger, 'handleStateTransition']);
}
```

### 📧 Notifications par Email

```php
<?php

class EmailNotificationService
{
    public function handleUserRegistration($event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof User && $event->getName() === 'postPersist') {
            $this->sendWelcomeEmail($entity);
        }
    }
    
    public function handleUserUpdate($event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof User && $event->getName() === 'postUpdate') {
            // Vérifier si l'email a changé
            $this->sendProfileUpdateNotification($entity);
        }
    }
    
    private function sendWelcomeEmail(User $user): void
    {
        $subject = 'Bienvenue sur notre plateforme';
        $message = "Bonjour {$user->getName()}, votre compte a été créé avec succès.";
        
        mail($user->getEmail(), $subject, $message);
    }
    
    private function sendProfileUpdateNotification(User $user): void
    {
        $subject = 'Profil mis à jour';
        $message = "Bonjour {$user->getName()}, votre profil a été mis à jour.";
        
        mail($user->getEmail(), $subject, $message);
    }
}

// Configuration
$emailService = new EmailNotificationService();
$eventManager = $entityManager->getEventManager();

if ($eventManager) {
    $eventManager->addListener('postPersist', [$emailService, 'handleUserRegistration']);
    $eventManager->addListener('postUpdate', [$emailService, 'handleUserUpdate']);
}
```

---

## ➡️ Étapes Suivantes

Pour approfondir votre compréhension :

1. 🏗️ [Architecture](../../fr/core-concepts/architecture.md) - Vue d'ensemble du système
2. 🗄️ [Entity Manager](entity-manager.md) - Gestion des entités
3. 🔄 [Suivi des Changements](change-tracking.md) - Change tracking
4. 🎯 [Exemples Pratiques](../../fr/quick-start/basic-examples.md) - Cas d'usage concrets

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../fr/../README.md)
- 📖 [Documentation Complète](../../fr/README.md)
- 🚀 [Démarrage Rapide](../../fr/quick-start/installation.md)
