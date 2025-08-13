# Architecture Interne

Guide détaillé de l'architecture interne de MulerTech Database ORM basé uniquement sur les composants réellement présents dans le code.

## Table des Matières
- [Vue d'ensemble architecturale](#vue-densemble-architecturale)
- [Couches et composants](#couches-et-composants)
- [Cycle de vie et état des entités](#cycle-de-vie-et-état-des-entités)
- [Gestion des métadonnées](#gestion-des-métadonnées)
- [Moteur ORM (EmEngine)](#moteur-orm-emengine)
- [Construction des requêtes](#construction-des-requêtes)
- [Détection et gestion des changements](#détection-et-gestion-des-changements)

## Vue d'ensemble architecturale

### Architecture en couches (réalité actuelle)

```
┌──────────────────────────────────┐
│ Application (Entités / Services) │
├──────────────────────────────────┤
│ ORM Public API                   │
│  - EntityManager (façade)        │
├──────────────────────────────────┤
│ Moteur interne                   │
│  - EmEngine                      │
│  - IdentityMap                   │
│  - ChangeDetector / ChangeSet*   │
│  - ChangeSetManager / Validator  │
│  - EntityHydrator                │
│  - Relation / State Managers*    │
├──────────────────────────────────┤
│ Mapping & Métadonnées            │
│  - MetadataRegistry              │
│  - EntityProcessor               │
│  - EntityMetadata + Mappings     │
├──────────────────────────────────┤
│ Requêtes                         │
│  - Query\Builder (Select/Insert…)│
├──────────────────────────────────┤
│ Couche Base de Données           │
│  - PhpDatabaseInterface (driver) │
└──────────────────────────────────┘
```
(* composants disponibles sous `src/ORM` / sous-espaces Engine / State, mais non exposés directement.)

## Couches et composants

| Composant | Fichier | Rôle principal |
|-----------|---------|----------------|
| EntityManager | src/ORM/EntityManager.php | API publique pour persister, trouver, supprimer, flusher |
| EmEngine | src/ORM/EmEngine.php | Cœur opérationnel (gestion identités, flush, hydration) |
| IdentityMap | src/ORM/IdentityMap.php | Gestion des instances uniques en mémoire |
| MetadataRegistry | src/Mapping/MetadataRegistry.php | Cache interne des métadonnées d'entités |
| EntityProcessor | src/Mapping/EntityProcessor.php | Analyse attributs et construit EntityMetadata |
| EntityMetadata / *Mapping | src/Mapping/*.php | Structure des colonnes, relations, clés étrangères |
| ChangeDetector | src/ORM/ChangeDetector.php | Extraction état courant + comparaison snapshots |
| ChangeSet / ChangeSetManager | src/ORM/ChangeSet*.php | Représentation & orchestration des modifications |
| EntityHydrator | src/ORM/EntityHydrator.php | Hydratation bas niveau à partir des résultats SQL |
| QueryBuilder & dérivés | src/Query/Builder/*.php | Construction fluide de requêtes SQL |
| EntityState / EntityLifecycleState | src/ORM/EntityState.php & State/* | Suivi de l'état runtime |

## Cycle de vie et état des entités

États représentés par `EntityLifecycleState` (enum) et encapsulés dans `EntityState` :
- NEW : entité créée, pas encore synchronisée
- MANAGED : suivie par l'ORM (présente dans l'IdentityMap)
- REMOVED : marquée pour suppression
- DETACHED : hors gestion (après clear/detach)

`IdentityMap` conserve :
- Références faibles (WeakReference) → permet GC naturel
- Métadonnées runtime (EntityState) → état + snapshot original

### Exemple d'utilisation (simplifié)
```php
$em = $container->get(EntityManager::class);
$user = new User('Alice');
$em->persist($user);   // NEW → MANAGED (après flush si ID généré)
$em->flush();          // INSERT exécuté / état synchronisé
```

## Gestion des métadonnées

`MetadataRegistry` charge à la demande (`getEntityMetadata()`) via `EntityProcessor` :
- Lecture des attributs (colonnes, repository, table)
- Construction d'objets immuables `EntityMetadata`
- Accès utilitaires : tableName, columns, properties→colonnes

Aucune classe `MetadataCache` distincte : le registry joue déjà le rôle de cache en mémoire du process.

## Moteur ORM (EmEngine)

Responsabilités principales de `EmEngine` :
1. Résolution / hydratation entités (`find()`, `getQueryBuilderObjectResult()`)
2. Gestion de l'IdentityMap et mise à jour sélective des entités déjà gérées
3. Orchestration du flush (via managers internes : state, persistence, relations)
4. Maintien des ChangeSets (via `ChangeDetector` + `ChangeSetManager`)

Flux simplifié `persist()` → `flush()` :
```
persist(entity)
  └─ IdentityMap.add() + état NEW
flush()
  ├─ PersistenceManager.flush()
  │   ├─ InsertionProcessor / UpdateProcessor / DeletionProcessor
  │   ├─ Application des ChangeSets
  │   └─ Mise à jour IdentityMap / états
  └─ ChangeSetManager.clear()
```

## Construction des requêtes

Le système de requêtes se base sur plusieurs builders :
- `QueryBuilder` (point d'entrée générique)
- `SelectBuilder`, `InsertBuilder`, `UpdateBuilder`, `DeleteBuilder`
- Méthodes fluides : `select()`, `from()`, `where()`, `whereRaw()`, `whereLike()`, `orderBy()`, `limit()`, etc.

Exemple :
```php
$query = new Query\Builder\QueryBuilder($em->getEmEngine())
    ->select('*')
    ->from('users')
    ->where('id', 10);
$entity = $em->getEmEngine()->getQueryBuilderObjectResult($query, User::class);
```

## Détection et gestion des changements

`ChangeDetector` :
- Prend un snapshot initial (données scalaires normalisées)
- Compare l'état courant → produit un tableau `[champ => [ancienneValeur, nouvelleValeur]]`

`ChangeSet` : Objet valeur décrivant les modifications ; orchestré par `ChangeSetManager` pour :
- Accumuler les changements multi-entités
- Fournir les diff nécessaires aux processors (INSERT/UPDATE)
- Être validé (ex : `ChangeSetValidator`)

Cycle :
```
IdentityMap.add(entity) → snapshot initial
Modification propriétés → flush()
ChangeDetector → diff → ChangeSetManager → PersistenceManager
```

Exemple récupération d'un ChangeSet (approche illustrative) :
```php
$detector = new ChangeDetector($metadataRegistry);
$detector->takeSnapshot($user);
$user->setEmail('new@example.com');
if ($detector->hasChanges($user)) {
    $changeSet = $detector->getChangeSet($user); // liste des propriétés modifiées
}
```

---

**Ressources associées :**
- [Extensibilité](extending-orm.md)
- [Types personnalisés](custom-types.md)
- [Plugins](plugins.md)
