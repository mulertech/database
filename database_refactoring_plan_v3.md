# Plan de Refactoring Database Repository

## Objectifs
1. Éliminer le code dupliqué
2. Améliorer l'organisation des fichiers
3. Optimiser les performances des opérations DB
4. Supprimer le code obsolète

## Phase 1 : Création des Traits et Classes Utilitaires

### 1.1 Trait ParameterHandlerTrait
- Mutualiser la gestion des paramètres entre QueryBuilder
- Méthodes : `addNamedParameter()`, `addDynamicParameter()`, `bindParameters()`

### 1.2 Trait SqlFormatterTrait
- Centraliser le formatage SQL
- Méthodes : `formatColumn()`, `formatTable()`, `formatValue()`

### 1.3 Class QueryParameterBag
- Gestion centralisée des paramètres de requête
- Support des paramètres nommés et positionnels

### 1.4 Class QueryStructureCache
- Cache optimisé pour les structures de requêtes compilées
- Réduction des recompilations

## Phase 2 : Refactoring des Query Builders

### 2.1 AbstractQueryBuilder amélioré
- Intégration des traits
- Méthodes communes extraites des builders spécifiques

### 2.2 Unification des méthodes where()
- Création d'une classe WhereClauseBuilder
- Support unifié pour tous les types de conditions

### 2.3 JoinClauseBuilder
- Extraction de la logique de jointure
- Support des jointures complexes

## Phase 3 : Optimisation des Opérations Batch

### 3.1 BatchOperationManager
- Gestion centralisée des insertions/updates batch
- Chunking automatique pour grandes quantités

### 3.2 BulkInsertOptimizer
- Optimisation des INSERT multiples
- Support des ON DUPLICATE KEY UPDATE

## Phase 4 : Réorganisation de la Structure

### 4.1 Nouvelle structure de dossiers
```
src/
├── Core/
│   ├── Traits/
│   ├── Cache/
│   └── Parameters/
├── Query/
│   ├── Builder/
│   ├── Clause/
│   └── Compiler/
├── ORM/
│   ├── Engine/
│   ├── State/
│   └── Repository/
├── Schema/
│   ├── Migration/
│   ├── Information/
│   └── Builder/
├── Mapping/
│   ├── Attributes/
│   └── Metadata/
└── Database/
    ├── Connection/
    └── Driver/
```

## Phase 5 : Nettoyage et Suppression

### 5.1 Classes à supprimer/fusionner
- Méthodes dupliquées dans SqlOperations
- Classes de test non utilisées
- Interfaces redondantes

### 5.2 Refactoring des Enums
- Consolidation des enums SQL
- Suppression des valeurs non utilisées

## Phase 6 : Optimisations Performance

### 6.1 Query Result Cache
- Cache des résultats de requêtes
- Invalidation intelligente

### 6.2 Lazy Loading Optimization
- Amélioration du chargement des relations
- Batch loading pour N+1 queries

### 6.3 Connection Pool
- Gestion optimisée des connexions PDO
- Réutilisation des connexions

## Phase 7 : Tests et Documentation

### 7.1 Tests unitaires
- Coverage complet des nouvelles classes
- Tests de performance

### 7.2 Documentation
- PHPDoc complet
- Exemples d'utilisation

## Ordre d'implémentation recommandé

1. **Phase 1** : Traits et utilitaires (base pour le reste)
2. **Phase 2** : Query Builders (utilise Phase 1)
3. **Phase 3** : Batch operations (indépendant)
4. **Phase 4** : Réorganisation (après stabilisation)
5. **Phase 5** : Nettoyage (après migration)
6. **Phase 6** : Optimisations (amélioration continue)
7. **Phase 7** : Tests (tout au long)

## Métriques de succès

- Réduction du code dupliqué de 40%
- Amélioration des performances batch de 50%
- Réduction du temps de compilation des requêtes de 30%
- Couverture de tests > 80%