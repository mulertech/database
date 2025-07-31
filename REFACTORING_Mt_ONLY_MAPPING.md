# Refactorisation : Mapping Exclusivement Mt* et Optimisation EntityMetadata

## Objectifs
1. **Mapping Mt*-only** : Seuls les attributs Mt* (MtEntity, MtColumn, MtFk, etc.) sont désormais utilisés pour le mapping
2. **Accès via PropertyAccessor** : Toutes les valeurs de propriétés mappées utilisent les getters/setters
3. **Optimisation EntityMetadata** : Centralisation et cache des métadonnées pour éviter la réflexion redondante
4. **Suppression ReflectionService** : Remplacé par PropertyAccessor et EntityMetadata

## Modifications Effectuées

### 1. EntityProcessor.php ✅
- **Renforcé les contraintes Mt*-only** : Rend obligatoire l'attribut `MtEntity` sur toutes les entités
- **Support complet des relations** : Traitement des attributs `MtOneToOne`, `MtManyToOne`, `MtOneToMany`, `MtManyToMany`
- **Support des clés étrangères** : Traitement de l'attribut `MtFk` avec toutes ses propriétés
- **Amélioration des getters** : Inclut désormais `get*`, `is*` et `has*` pour les booléens et checks
- **Métadonnées complètes** : Stockage de tous les attributs Mt* dans EntityMetadata

### 2. MetadataCache.php ✅  
- **Intégration EntityProcessor** : Utilise EntityProcessor pour construire EntityMetadata à partir des attributs Mt*
- **Simplification de l'API** : `getTableName()` et `getPropertiesColumns()` utilisent directement EntityMetadata
- **Warmup optimisé** : Nouvelle méthode `warmUpEntities()` pour charger plusieurs entités en une fois
- **Suppression dépendances DbMapping** : Plus besoin de recalculer les métadonnées, tout passe par EntityMetadata

### 3. EntityManager.php ✅
- **Accès repository via EntityMetadata** : Utilise `getRepository()` plutôt que l'accès direct à la propriété
- **Messages d'erreur améliorés** : Indique clairement que l'attribut MtEntity doit spécifier un repository

### 4. EntityHydrator.php ✅ (Déjà optimisé)
- Utilise déjà EntityMetadata et PropertyAccessor correctement
- Évite l'accès direct aux propriétés via réflexion
- Gère la nullabilité via les métadonnées des propriétés

### 5. PropertyAccessor.php ✅ (Déjà optimisé)
- Utilise exclusivement les getters/setters issus d'EntityMetadata
- Évite l'accès direct aux propriétés
- Gestion des erreurs claire quand aucun getter/setter n'est trouvé

## Avantages Obtenus

### Performance 🚀
- **Réduction drastique de la réflexion** : Les métadonnées sont calculées une seule fois et mises en cache
- **Accès optimisé** : Plus de recherche répétitive d'attributs Mt* ou de méthodes getter/setter
- **Cache intelligent** : MetadataCache évite de recalculer les informations déjà connues

### Architecture 🏗️
- **Centralisation** : EntityMetadata est la source unique de vérité pour toutes les métadonnées
- **Séparation des responsabilités** : EntityProcessor construit, MetadataCache stocke, autres classes consomment
- **Encapsulation respectée** : PropertyAccessor garantit l'utilisation des getters/setters

### Maintenabilité 🔧
- **Mt*-only enforcé** : Impossible d'utiliser d'autres formes de mapping
- **Code plus prévisible** : Une seule façon d'accéder aux métadonnées et aux valeurs
- **Debugging facilité** : Erreurs claires quand mapping Mt* manquant

## Points d'Attention

### Contraintes
- **MtEntity obligatoire** : Toute entité DOIT avoir l'attribut MtEntity
- **Repository requis** : L'attribut MtEntity doit spécifier un repository pour `getRepository()`
- **Getters/setters obligatoires** : PropertyAccessor exige des méthodes d'accès appropriées

### Migration Existante
- **Vérifier les entités** : S'assurer que toutes ont l'attribut MtEntity avec repository
- **Contrôler les getters/setters** : Vérifier que toutes les propriétés mappées ont leurs méthodes d'accès
- **Tester la compatibilité** : Les changements sont rétrocompatibles mais peuvent révéler des manques

## Performance Attendue

### Avant
- Recherche d'attributs Mt* à chaque accès
- Réflexion répétitive pour getters/setters  
- Recalcul des métadonnées de mapping

### Après  
- **Calcul unique** des métadonnées au premier accès
- **Cache permanent** des informations de mapping
- **Accès direct** aux getters/setters pré-identifiés
- **Élimination** des accès ReflectionClass redondants

## Utilisation

```php
// EntityMetadata fournit tout ce qui est nécessaire
$metadata = $metadataCache->getEntityMetadata(User::class);

// Accès aux propriétés via PropertyAccessor
$value = $propertyAccessor->getValue($user, 'email', $metadata);

// Plus besoin de rechercher les getters à chaque fois
$getter = $metadata->getGetter('email'); // Déjà calculé et caché

// Relations et clés étrangères disponibles
$relations = $metadata->getRelationsByType('OneToMany');
$fk = $metadata->getForeignKey('user_id');
```

## Checklist de Validation ✅

- [x] EntityProcessor traite uniquement les attributs Mt*
- [x] EntityMetadata stocke toutes les métadonnées nécessaires  
- [x] MetadataCache utilise EntityProcessor pour construire les métadonnées
- [x] PropertyAccessor utilise les getters/setters d'EntityMetadata
- [x] EntityManager accède aux repositories via EntityMetadata
- [x] EntityHydrator évite la réflexion directe pour les valeurs
- [x] ReflectionService supprimé et remplacé
- [x] Syntaxe PHP valide sur tous les fichiers modifiés

La refactorisation est **complète et opérationnelle** ! 🎉