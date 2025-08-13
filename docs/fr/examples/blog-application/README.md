# Application Blog - Exemple Complet

Cette section présente une application de blog complète utilisant MulerTech Database ORM, démontrant les concepts et fonctionnalités dans un contexte réel.

## Vue d'ensemble

L'application blog comprend :
- **Gestion des utilisateurs** avec authentification
- **Articles** avec catégories et tags
- **Système de commentaires** hiérarchique
- **Administration** des contenus
- **API REST** pour l'accès programmatique

## Structure du projet

```
blog-application/
├── README.md                    # Ce fichier
├── 01-project-setup.md         # Configuration du projet
├── 02-entities.md              # Définition des entités
├── 03-repositories.md          # Repositories personnalisés
├── 04-services.md              # Couche de services
├── 05-controllers.md           # Contrôleurs web
├── 06-migrations.md            # Migrations de base de données
├── 07-api.md                   # API REST
└── 08-advanced-features.md     # Fonctionnalités avancées
```

## Fonctionnalités démontrées

### Relations complexes
- **OneToMany** : User → Posts, Post → Comments
- **ManyToMany** : Post ↔ Tags
- **ManyToOne** : Post → Category, Comment → User
- **Relation auto-référentielle** : Comment → Parent Comment

### Concepts avancés
- **Repositories personnalisés** avec requêtes métier
- **Types personnalisés** (Email, Slug, Status)
- **Événements** pour audit et notifications
- **Cache** pour optimiser les performances
- **Soft delete** pour les suppressions logiques

### Patterns d'architecture
- **Service Layer** pour la logique métier
- **Repository Pattern** pour l'accès aux données
- **DTO/Value Objects** pour le transfert de données
- **Events & Listeners** pour le découplage

## Structure de données

### Schéma principal

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│    users    │    │    posts    │    │  categories │
├─────────────┤    ├─────────────┤    ├─────────────┤
│ id (PK)     │◄──┐│ id (PK)     │   ┌►│ id (PK)     │
│ email       │   ││ title       │   │ │ name        │
│ name        │   ││ slug        │   │ │ slug        │
│ password    │   ││ content     │   │ │ description │
│ role        │   ││ status      │   │ │ color       │
│ created_at  │   ││ user_id (FK)├───┘ │ created_at  │
│ updated_at  │   ││ category_id │─────┘ │ updated_at  │
│ deleted_at  │   ││ created_at  │       └─────────────┘
└─────────────┘   ││ updated_at  │
                  ││ deleted_at  │
                  │└─────────────┘
                  │
                  │ ┌─────────────┐
                  └►│  comments   │
                    ├─────────────┤
                    │ id (PK)     │
                    │ content     │
                    │ post_id (FK)│
                    │ user_id (FK)│
                    │ parent_id   │
                    │ status      │
                    │ created_at  │
                    │ updated_at  │
                    └─────────────┘

        ┌─────────────┐    ┌─────────────┐    ┌─────────────┐
        │    tags     │    │  post_tags  │    │    posts    │
        ├─────────────┤    ├─────────────┤    ├─────────────┤
        │ id (PK)     │◄───│ tag_id (FK) │───►│ id (PK)     │
        │ name        │    │ post_id (FK)│    │ title       │
        │ slug        │    └─────────────┘    │ ...         │
        │ color       │                       └─────────────┘
        │ created_at  │
        └─────────────┘
```

## Quick Start

1. **Installation du projet**
   ```bash
   composer create-project mulertech/database-blog-example blog
   cd blog
   ```

2. **Configuration de la base de données**
   ```php
   // config/database.php
   return [
       'driver' => 'mysql',
       'host' => 'localhost',
       'database' => 'blog_example',
       'username' => 'root',
       'password' => '',
   ];
   ```

3. **Exécution des migrations**
   ```bash
   php bin/console mt:migration:migrate
   ```

4. **Chargement des données de test**
   ```bash
   php bin/console fixtures:load
   ```

5. **Démarrage du serveur**
   ```bash
   php -S localhost:8000 -t public/
   ```

## Navigation

- **[Configuration du projet](01-project-setup.md)** - Installation et configuration
- **[Entités](02-entities.md)** - Définition des modèles de données
- **[Repositories](03-repositories.md)** - Couche d'accès aux données
- **[Services](04-services.md)** - Logique métier
- **[Contrôleurs](05-controllers.md)** - Interface web
- **[Migrations](06-migrations.md)** - Évolution du schéma
- **[API REST](07-api.md)** - Interface programmatique
- **[Fonctionnalités avancées](08-advanced-features.md)** - Cache, événements, etc.

## Concepts clés démontrés

### 🏗️ Architecture
- Structure MVC avec couche service
- Séparation des responsabilités
- Injection de dépendances

### 🗄️ Base de données
- Relations complexes et optimisées
- Migrations évolutives
- Indexes pour la performance

### 🔧 ORM
- Mapping avec attributs PHP 8+
- Repositories avec logique métier
- Change tracking et événements

### 🚀 Performance
- Cache intelligent
- Requêtes optimisées
- Pagination efficace

### 🛡️ Sécurité
- Validation des données
- Protection CSRF
- Authentification sécurisée

---

Cette application blog sert d'exemple de référence pour apprendre et maîtriser MulerTech Database ORM dans un contexte réel et complet.
