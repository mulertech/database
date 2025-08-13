# Application E-commerce - Exemple Complet

Cette section présente une application e-commerce complète utilisant MulerTech Database ORM, démontrant une architecture moderne avec gestion des produits, commandes, paiements et inventaire.

## Vue d'ensemble

L'application e-commerce comprend :
- **Gestion des produits** avec catégories, variants et inventaire
- **Système de commandes** complet avec workflow de statuts
- **Gestion des clients** avec profils et historique
- **Panier d'achat** persistant et optimisé
- **Système de paiement** avec multiple méthodes
- **Gestion d'inventaire** en temps réel
- **API REST complète** pour intégrations tierces

## Structure du projet

```
e-commerce/
├── README.md                    # Ce fichier
├── 01-project-setup.md         # Configuration du projet
├── 02-entities.md              # Définition des entités
├── 03-catalog-management.md    # Gestion du catalogue produits
├── 04-cart-system.md           # Système de panier
├── 05-order-processing.md      # Traitement des commandes
├── 06-payment-integration.md   # Intégration des paiements
├── 07-inventory-management.md  # Gestion des stocks
├── 08-customer-management.md   # Gestion des clients
├── 09-api-endpoints.md         # API REST complète
└── 10-advanced-features.md     # Fonctionnalités avancées
```

## Fonctionnalités démontrées

### Architecture e-commerce
- **Catalogue produits** avec variants et attributs personnalisés
- **Système de prix** complexe avec promotions et réductions
- **Gestion d'inventaire** multi-entrepôts
- **Workflow de commandes** avec états et transitions
- **Intégration paiements** multi-providers

### Relations complexes
- **OneToMany** : Category → Products, Customer → Orders
- **ManyToMany** : Product ↔ Tags, Order ↔ Products (avec quantités)
- **OneToOne** : Order → Payment, Customer → Profile
- **Polymorphique** : Address (Billing/Shipping)

### Concepts avancés e-commerce
- **Variants de produits** (couleur, taille, matériau)
- **Pricing dynamique** avec règles et promotions
- **Inventaire temps réel** avec réservations
- **Cache intelligent** pour performance
- **Events** pour audit et notifications
- **Queue processing** pour tâches lourdes

## Structure de données

### Schéma principal

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│ customers   │    │   orders    │    │   products  │
├─────────────┤    ├─────────────┤    ├─────────────┤
│ id (PK)     │◄──┐│ id (PK)     │   ┌►│ id (PK)     │
│ email       │   ││ number      │   │ │ name        │
│ first_name  │   ││ status      │   │ │ slug        │
│ last_name   │   ││ customer_id ├───┘ │ description │
│ phone       │   ││ total       │     │ price       │
│ created_at  │   ││ tax_amount  │     │ category_id │
│ updated_at  │   ││ created_at  │     │ brand_id    │
└─────────────┘   │└─────────────┘     │ sku         │
                  │                    │ stock_qty   │
                  │ ┌─────────────┐    │ is_active   │
                  └►│ order_items │    │ created_at  │
                    ├─────────────┤    └─────────────┘
                    │ id (PK)     │           │
                    │ order_id    │           │
                    │ product_id  ├───────────┘
                    │ variant_id  │
                    │ quantity    │
                    │ unit_price  │
                    │ total_price │
                    └─────────────┘

┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│ categories  │    │   brands    │    │    tags     │
├─────────────┤    ├─────────────┤    ├─────────────┤
│ id (PK)     │◄───│ id (PK)     │    │ id (PK)     │
│ name        │    │ name        │    │ name        │
│ slug        │    │ slug        │    │ slug        │
│ parent_id   │    │ logo_url    │    │ color       │
│ is_active   │    │ is_active   │    └─────────────┘
└─────────────┘    └─────────────┘           │
       │                  │                  │
       │                  │         ┌─────────────┐
       │                  │         │ product_tags│
       │                  │         ├─────────────┤
       │                  │         │ product_id  │
       │                  │         │ tag_id      │
       │                  │         └─────────────┘
       │                  │
       └──────────────────┘
                │
       ┌─────────────┐
       │   variants  │
       ├─────────────┤
       │ id (PK)     │
       │ product_id  │
       │ name        │
       │ sku         │
       │ price_adj   │
       │ stock_qty   │
       │ attributes  │ (JSON)
       └─────────────┘
```

### Schéma de panier et paiement

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│    carts    │    │ cart_items  │    │  payments   │
├─────────────┤    ├─────────────┤    ├─────────────┤
│ id (PK)     │◄───│ id (PK)     │    │ id (PK)     │
│ session_id  │    │ cart_id     │    │ order_id    │
│ customer_id │    │ product_id  │    │ amount      │
│ created_at  │    │ variant_id  │    │ method      │
│ updated_at  │    │ quantity    │    │ status      │
│ expires_at  │    │ unit_price  │    │ gateway_ref │
└─────────────┘    │ added_at    │    │ processed_at│
                   └─────────────┘    └─────────────┘

┌─────────────┐    ┌─────────────┐
│  addresses  │    │   coupons   │
├─────────────┤    ├─────────────┤
│ id (PK)     │    │ id (PK)     │
│ type        │    │ code        │
│ customer_id │    │ type        │
│ first_name  │    │ value       │
│ last_name   │    │ min_amount  │
│ company     │    │ expires_at  │
│ street      │    │ usage_limit │
│ city        │    │ used_count  │
│ postal_code │    │ is_active   │
│ country     │    └─────────────┘
│ is_default  │
└─────────────┘
```

## Quick Start

1. **Installation du projet**
   ```bash
   composer create-project mulertech/database-ecommerce-example ecommerce
   cd ecommerce
   ```

2. **Configuration de la base de données**
   ```php
   // config/database.php
   return [
       'driver' => 'mysql',
       'host' => 'localhost',
       'database' => 'ecommerce_example',
       'username' => 'root',
       'password' => '',
       'options' => [
           PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
           PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
           PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
       ]
   ];
   ```

3. **Exécution des migrations**
   ```bash
   php bin/console mt:migration:migrate
   ```

4. **Chargement des données de démonstration**
   ```bash
   php bin/console fixtures:load
   ```

5. **Configuration des services tiers**
   ```php
   // config/services.php
   return [
       'payment_gateways' => [
           'stripe' => [
               'secret_key' => 'sk_test_...',
               'public_key' => 'pk_test_...'
           ],
           'paypal' => [
               'client_id' => 'your_client_id',
               'client_secret' => 'your_client_secret',
               'mode' => 'sandbox'
           ]
       ],
       'shipping' => [
           'providers' => ['ups', 'fedex', 'dhl']
       ]
   ];
   ```

6. **Démarrage du serveur**
   ```bash
   php -S localhost:8000 -t public/
   ```

## Navigation

- **[Configuration du projet](01-project-setup.md)** - Installation et configuration
- **[Entités](02-entities.md)** - Modèles de données e-commerce
- **[Gestion du catalogue](03-catalog-management.md)** - Produits, catégories, variants
- **[Système de panier](04-cart-system.md)** - Panier persistant et optimisé
- **[Traitement des commandes](05-order-processing.md)** - Workflow complet
- **[Intégration des paiements](06-payment-integration.md)** - Multi-providers
- **[Gestion d'inventaire](07-inventory-management.md)** - Stocks temps réel
- **[Gestion des clients](08-customer-management.md)** - Profils et préférences
- **[API REST](09-api-endpoints.md)** - Interface programmatique complète
- **[Fonctionnalités avancées](10-advanced-features.md)** - Analytics, reporting, etc.

## Concepts clés démontrés

### 🏪 Architecture E-commerce
- Structure modulaire orientée domaine
- Séparation claire des responsabilités
- Patterns e-commerce éprouvés
- Évolutivité et maintenance

### 💰 Gestion des prix
- Prix dynamiques avec règles
- Promotions et codes de réduction
- Calculs de taxes automatisés
- Multi-devises support

### 📦 Gestion des stocks
- Inventaire temps réel
- Réservations temporaires
- Multi-entrepôts
- Alertes de stock bas

### 🛒 Expérience d'achat
- Panier persistant cross-device
- Checkout optimisé
- Multiple méthodes de paiement
- Suivi de commande en temps réel

### 🚀 Performance
- Cache intelligent multi-niveaux
- Optimisations de requêtes
- CDN pour les images
- Pagination efficace

### 🔐 Sécurité
- Validation stricte des données
- Protection contre la fraude
- Chiffrement des données sensibles
- Audit complet des transactions

### 📊 Analytics
- Suivi des conversions
- Analytics des ventes
- Rapports de performance
- KPIs métier

---

Cette application e-commerce sert d'exemple de référence pour construire une plateforme de vente en ligne complète et moderne avec MulerTech Database ORM.
