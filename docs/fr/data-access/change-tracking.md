# Suivi des Changements (Change Tracking)

🌍 **Languages:** [🇫🇷 Français](change-tracking.md) | [🇬🇧 English](../../en/orm/change-tracking.md)

---

## 📋 Table des Matières

- [Vue d'Ensemble](#vue-densemble)
- [ChangeDetector - Détection des Changements](#changedetector---détection-des-changements)
- [ChangeSet - Ensemble de Changements](#changeset---ensemble-de-changements)
- [ChangeSetManager - Gestionnaire](#changesetmanager---gestionnaire)
- [PropertyChange - Changement de Propriété](#propertychange---changement-de-propriété)
- [Exemples Pratiques](#exemples-pratiques)
- [Optimisations et Performance](#optimisations-et-performance)

---

## Vue d'Ensemble

MulerTech Database intègre un système complet de suivi des changements (change tracking) qui permet de détecter, valider et gérer efficacement les modifications apportées aux entités. Ce système est au cœur du fonctionnement de l'ORM.

### 🎯 Composants Principaux

- **ChangeDetector** : Détecte les modifications en comparant l'état actuel avec l'état original
- **ChangeSet** : Représente un ensemble de changements pour une entité
- **ChangeSetManager** : Gestionnaire optimisé utilisant `SplObjectStorage`
- **PropertyChange** : Représente un changement individuel de propriété

### 📦 Imports Nécessaires

```php
<?php
use MulerTech\Database\ORM\{
    ChangeDetector, ChangeSet, ChangeSetManager, PropertyChange
};
use MulerTech\Database\Mapping\MetadataRegistry;
```

---

## ChangeDetector - Détection des Changements

La classe `ChangeDetector` est responsable de détecter les modifications apportées aux entités en comparant leur état actuel avec leur état original.

### 🏗️ Construction

```php
class ChangeDetector
{
    private ValueProcessor $valueProcessor;
    private ValueComparator $valueComparator;
    private ArrayValidator $arrayValidator;
    private MetadataRegistry $metadataRegistry;

    public function __construct(?MetadataRegistry $metadataRegistry = null);
}
```

### 🔍 Méthodes Principales

#### Extraction des Données Actuelles

```php
/**
 * Extrait les données actuelles d'une entité
 * @param object $entity
 * @return array<string, mixed>
 */
public function extractCurrentData(object $entity): array;
```

#### Calcul des Changements

```php
/**
 * Compare l'état actuel avec l'état original et génère un ChangeSet
 * @param object $entity
 * @param array<string, mixed> $originalData
 * @return ChangeSet
 */
public function computeChangeSet(object $entity, array $originalData): ChangeSet;
```

### 📝 Exemple d'Utilisation

```php
$detector = new ChangeDetector($metadataRegistry);

// Capturer l'état original
$originalData = $detector->extractCurrentData($user);

// Modifier l'entité
$user->setEmail('nouveau@example.com');
$user->setName('Nouveau Nom');

// Détecter les changements
$changeSet = $detector->computeChangeSet($user, $originalData);

if (!$changeSet->isEmpty()) {
    foreach ($changeSet->getChanges() as $property => $change) {
        echo "Propriété '{$property}' modifiée de '{$change->oldValue}' vers '{$change->newValue}'\n";
    }
}
```

---

## ChangeSet - Ensemble de Changements

La classe `ChangeSet` représente un ensemble de modifications pour une entité spécifique.

### 🏗️ Structure

```php
final readonly class ChangeSet
{
    /**
     * @param class-string $entityClass
     * @param array<string, PropertyChange> $changes
     */
    public function __construct(
        public string $entityClass,
        public array $changes
    );
}
```

### 🔧 Méthodes Disponibles

#### Vérification des Changements

```php
// Vérifier si le ChangeSet est vide
public function isEmpty(): bool;

// Obtenir tous les changements
public function getChanges(): array;

// Obtenir le changement d'un champ spécifique
public function getFieldChange(string $field): ?PropertyChange;
```

#### Filtrage des Changements

```php
// Filtrer les changements selon un critère
public function filter(callable $callback): ChangeSet;
```

### 📝 Exemple d'Utilisation

```php
// Analyser un ChangeSet
if (!$changeSet->isEmpty()) {
    echo "Classe d'entité : {$changeSet->entityClass}\n";
    
    // Vérifier un champ spécifique
    $emailChange = $changeSet->getFieldChange('email');
    if ($emailChange) {
        echo "Email modifié : {$emailChange->oldValue} → {$emailChange->newValue}\n";
    }
    
    // Filtrer seulement les changements de chaînes
    $stringChanges = $changeSet->filter(fn($change) => is_string($change->newValue));
}
```

---

## ChangeSetManager - Gestionnaire

Le `ChangeSetManager` est un gestionnaire optimisé qui utilise `SplObjectStorage` pour un suivi efficace des changements.

### 🏗️ Construction

```php
final class ChangeSetManager
{
    /** @var SplObjectStorage<object, ChangeSet> */
    private SplObjectStorage $changeSets;
    
    private EntityScheduler $scheduler;
    private EntityStateManager $stateManager;
    private EntityProcessor $entityProcessor;
    private ChangeSetValidator $validator;
    private ChangeSetOperationHandler $operationHandler;

    public function __construct(
        private readonly IdentityMap $identityMap,
        private readonly EntityRegistry $registry,
        private readonly ChangeDetector $changeDetector,
        private readonly MetadataRegistry $metadataRegistry
    );
}
```

### 🎯 Fonctionnalités

- **Gestion optimisée** avec `SplObjectStorage`
- **Intégration** avec l'EntityScheduler pour la planification
- **Validation** automatique des changements
- **Traitement** des opérations de changement

### 📝 Exemple d'Utilisation

```php
$changeSetManager = new ChangeSetManager(
    $identityMap,
    $entityRegistry,
    $changeDetector,
    $metadataRegistry
);

// Le manager est généralement utilisé en interne par l'EmEngine
// mais peut être utilisé directement pour des cas avancés
```

---

## PropertyChange - Changement de Propriété

La classe `PropertyChange` représente la modification d'une propriété individuelle.

### 🏗️ Structure

```php
final readonly class PropertyChange
{
    public function __construct(
        public string $property,
        public mixed $oldValue,
        public mixed $newValue
    );
}
```

### 📝 Exemple d'Utilisation

```php
// Analyser un changement de propriété
$change = new PropertyChange('email', 'ancien@example.com', 'nouveau@example.com');

echo "Propriété : {$change->property}\n";
echo "Ancienne valeur : {$change->oldValue}\n";
echo "Nouvelle valeur : {$change->newValue}\n";

// Vérifier le type de changement
$isStringChange = is_string($change->oldValue) && is_string($change->newValue);
$isNullToValue = $change->oldValue === null && $change->newValue !== null;
```

---

## Exemples Pratiques

### Suivi des Modifications d'un Utilisateur

```php
class UserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChangeDetector $changeDetector
    ) {}

    public function updateUserWithTracking(User $user, array $newData): array
    {
        // Capturer l'état original
        $originalData = $this->changeDetector->extractCurrentData($user);
        
        // Appliquer les modifications
        if (isset($newData['name'])) {
            $user->setName($newData['name']);
        }
        if (isset($newData['email'])) {
            $user->setEmail($newData['email']);
        }
        
        // Détecter les changements
        $changeSet = $this->changeDetector->computeChangeSet($user, $originalData);
        
        // Journaliser les changements
        $this->logChanges($user, $changeSet);
        
        // Sauvegarder
        $this->entityManager->getEmEngine()->persist($user);
        $this->entityManager->getEmEngine()->flush();
        
        return $this->formatChanges($changeSet);
    }
    
    private function logChanges(User $user, ChangeSet $changeSet): void
    {
        if ($changeSet->isEmpty()) {
            return;
        }
        
        foreach ($changeSet->getChanges() as $property => $change) {
            error_log(sprintf(
                "User %d: Property '%s' changed from '%s' to '%s'",
                $user->getId(),
                $change->property,
                $change->oldValue ?? 'NULL',
                $change->newValue ?? 'NULL'
            ));
        }
    }
    
    private function formatChanges(ChangeSet $changeSet): array
    {
        $result = [];
        foreach ($changeSet->getChanges() as $property => $change) {
            $result[] = [
                'property' => $change->property,
                'old_value' => $change->oldValue,
                'new_value' => $change->newValue
            ];
        }
        return $result;
    }
}
```

### Validation Personnalisée des Changements

```php
class ChangeValidator
{
    public function validateUserChanges(ChangeSet $changeSet): array
    {
        $errors = [];
        
        // Valider le changement d'email
        $emailChange = $changeSet->getFieldChange('email');
        if ($emailChange && !filter_var($emailChange->newValue, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalide';
        }
        
        // Valider que le nom n'est pas vide
        $nameChange = $changeSet->getFieldChange('name');
        if ($nameChange && empty(trim($nameChange->newValue))) {
            $errors['name'] = 'Le nom ne peut pas être vide';
        }
        
        return $errors;
    }
    
    public function hasSignificantChanges(ChangeSet $changeSet): bool
    {
        $significantFields = ['email', 'name', 'role'];
        
        foreach ($changeSet->getChanges() as $change) {
            if (in_array($change->property, $significantFields)) {
                return true;
            }
        }
        
        return false;
    }
}
```

---

## Optimisations et Performance

### Utilisation de SplObjectStorage

Le `ChangeSetManager` utilise `SplObjectStorage` pour des performances optimales :

```php
/** @var SplObjectStorage<object, ChangeSet> */
private SplObjectStorage $changeSets;
```

**Avantages :**
- **Performance** : Accès en O(1) par objet
- **Mémoire** : Références faibles automatiques
- **Type-safety** : Stockage typé objet → ChangeSet

### Traitement par Lots

```php
class BatchChangeProcessor
{
    public function processBatchChanges(array $entities): array
    {
        $allChangeSets = [];
        
        foreach ($entities as $entity) {
            $originalData = $this->changeDetector->extractCurrentData($entity);
            // ... modifications ...
            $changeSet = $this->changeDetector->computeChangeSet($entity, $originalData);
            
            if (!$changeSet->isEmpty()) {
                $allChangeSets[] = $changeSet;
            }
        }
        
        return $allChangeSets;
    }
}
```

### Comparaison de Valeurs Optimisée

Le système utilise des comparateurs optimisés pour différents types de données :

- **ValueComparator** : Comparaison intelligente des valeurs
- **ArrayValidator** : Validation spécialisée des tableaux
- **ValueProcessor** : Traitement optimisé des valeurs

---

## ➡️ Étapes Suivantes

Pour approfondir votre compréhension :

1. 🏗️ [Architecture](../../fr/core-concepts/architecture.md) - Vue d'ensemble du système
2. 🗄️ [Entity Manager](entity-manager.md) - Gestion des entités
3. 🎯 [Exemples Pratiques](../../fr/quick-start/basic-examples.md) - Cas d'usage concrets

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../fr/../README.md)
- 📖 [Documentation Complète](../../fr/README.md)
- 🚀 [Démarrage Rapide](../../fr/quick-start/installation.md)
