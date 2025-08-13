# API Endpoints - E-commerce

Cette section présente la documentation complète de l'API REST pour l'application e-commerce, avec tous les endpoints, paramètres, réponses et exemples d'utilisation.

## Table des matières

- [Authentification](#authentification)
- [API Catalogue](#api-catalogue)
- [API Panier](#api-panier)
- [API Commandes](#api-commandes)
- [API Paiements](#api-paiements)
- [API Clients](#api-clients)
- [API Inventaire](#api-inventaire)
- [API Administration](#api-administration)
- [Codes d'erreur](#codes-derreur)
- [Pagination](#pagination)
- [Rate Limiting](#rate-limiting)

## Configuration de base

### Base URL
```
https://api.moncommerce.fr/v1
```

### Headers requis
```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}
```

### Format de réponse standard
```json
{
  "data": {},
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "version": "1.0.0"
  },
  "errors": []
}
```

## Authentification

### POST /auth/login
Connexion client/administrateur

**Paramètres:**
```json
{
  "email": "client@example.com",
  "password": "motdepasse123"
}
```

**Réponse succès (200):**
```json
{
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "rt_abc123...",
    "expires_in": 3600,
    "user": {
      "id": 123,
      "email": "client@example.com",
      "first_name": "Jean",
      "last_name": "Dupont",
      "role": "customer"
    }
  }
}
```

### POST /auth/register
Inscription nouveau client

**Paramètres:**
```json
{
  "email": "nouveau@example.com",
  "password": "motdepasse123",
  "first_name": "Marie",
  "last_name": "Martin",
  "phone": "+33123456789",
  "accepts_marketing": true,
  "referral_code": "ABC12345"
}
```

**Réponse succès (201):**
```json
{
  "data": {
    "customer": {
      "id": 124,
      "email": "nouveau@example.com",
      "full_name": "Marie Martin",
      "loyalty_points": 100,
      "referral_code": "DEF67890"
    },
    "message": "Account created successfully. Please check your email for verification."
  }
}
```

### POST /auth/refresh
Renouvellement du token

**Paramètres:**
```json
{
  "refresh_token": "rt_abc123..."
}
```

**Réponse succès (200):**
```json
{
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 3600
  }
}
```

### POST /auth/logout
Déconnexion

**Headers:** `Authorization: Bearer {token}`

**Réponse succès (200):**
```json
{
  "data": {
    "message": "Successfully logged out"
  }
}
```

## API Catalogue

### GET /catalog/products
Liste des produits avec filtres

**Paramètres de requête:**
- `page` (int): Numéro de page (défaut: 1)
- `limit` (int): Éléments par page (défaut: 24, max: 100)
- `category_id` (int): Filtrer par catégorie
- `brand_id` (int): Filtrer par marque
- `min_price` (float): Prix minimum en euros
- `max_price` (float): Prix maximum en euros
- `in_stock` (bool): Seulement les produits en stock
- `sort` (string): Tri (`name`, `price_asc`, `price_desc`, `newest`, `featured`)
- `search` (string): Recherche textuelle

**Exemple de requête:**
```http
GET /catalog/products?category_id=5&min_price=10&max_price=100&sort=price_asc&page=1&limit=12
```

**Réponse succès (200):**
```json
{
  "data": {
    "products": [
      {
        "id": 123,
        "name": "T-shirt Premium",
        "slug": "t-shirt-premium",
        "price": 2990,
        "formatted_price": "29,90 €",
        "compare_price": 3990,
        "formatted_compare_price": "39,90 €",
        "has_discount": true,
        "discount_percentage": 25,
        "brand": "MarquePremium",
        "category": "Vêtements",
        "images": [
          {
            "url": "https://cdn.example.com/products/123/main.jpg",
            "alt": "T-shirt Premium vue principale",
            "is_main": true
          }
        ],
        "variants": [
          {
            "id": 456,
            "name": "Rouge, L",
            "attributes": {
              "color": "Rouge",
              "size": "L"
            },
            "price": 2990,
            "stock": 15
          }
        ],
        "stock_status": "in_stock",
        "average_rating": 4.5,
        "reviews_count": 23,
        "is_featured": false
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 12,
      "total": 156,
      "total_pages": 13,
      "has_next": true,
      "has_previous": false
    },
    "filters": {
      "categories": [
        {"id": 5, "name": "Vêtements", "count": 45}
      ],
      "brands": [
        {"id": 10, "name": "MarquePremium", "count": 12}
      ],
      "price_range": {
        "min": 990,
        "max": 19990
      }
    }
  }
}
```

### GET /catalog/products/{id}
Détails d'un produit

**Réponse succès (200):**
```json
{
  "data": {
    "product": {
      "id": 123,
      "name": "T-shirt Premium",
      "slug": "t-shirt-premium",
      "description": "T-shirt en coton bio de haute qualité...",
      "short_description": "T-shirt confortable et durable",
      "price": 2990,
      "formatted_price": "29,90 €",
      "sku": "TSH-PREM-001",
      "brand": {
        "id": 10,
        "name": "MarquePremium",
        "slug": "marque-premium"
      },
      "category": {
        "id": 5,
        "name": "Vêtements",
        "slug": "vetements",
        "breadcrumb": "Accueil > Mode > Vêtements"
      },
      "images": [
        {
          "id": 789,
          "url": "https://cdn.example.com/products/123/main.jpg",
          "alt": "T-shirt Premium vue principale",
          "is_main": true,
          "sort_order": 0
        }
      ],
      "variants": [
        {
          "id": 456,
          "name": "Rouge, L",
          "sku": "TSH-PREM-001-R-L",
          "price": 2990,
          "price_adjustment": 0,
          "attributes": {
            "color": "Rouge",
            "size": "L"
          },
          "stock": 15,
          "is_active": true
        }
      ],
      "attributes": {
        "material": "100% Coton Bio",
        "care_instructions": "Lavage à 30°C",
        "origin": "France"
      },
      "specifications": {
        "weight": 180,
        "dimensions": {
          "length": 70,
          "width": 50
        }
      },
      "seo": {
        "meta_title": "T-shirt Premium en Coton Bio - MarquePremium",
        "meta_description": "Découvrez notre t-shirt premium..."
      },
      "related_products": [
        {
          "id": 124,
          "name": "Jean Slim",
          "price": 5990,
          "formatted_price": "59,90 €"
        }
      ],
      "reviews_summary": {
        "average_rating": 4.5,
        "total_reviews": 23,
        "rating_distribution": {
          "5": 12,
          "4": 8,
          "3": 2,
          "2": 1,
          "1": 0
        }
      }
    }
  }
}
```

### GET /catalog/categories
Liste des catégories

**Paramètres de requête:**
- `parent_id` (int): ID de la catégorie parent (null pour racines)
- `include_products_count` (bool): Inclure le nombre de produits

**Réponse succès (200):**
```json
{
  "data": {
    "categories": [
      {
        "id": 1,
        "name": "Mode",
        "slug": "mode",
        "description": "Vêtements et accessoires",
        "image_url": "https://cdn.example.com/categories/mode.jpg",
        "parent_id": null,
        "level": 0,
        "sort_order": 1,
        "products_count": 245,
        "children": [
          {
            "id": 5,
            "name": "Vêtements",
            "slug": "vetements",
            "parent_id": 1,
            "level": 1,
            "products_count": 189
          }
        ]
      }
    ]
  }
}
```

### GET /catalog/search/suggestions
Suggestions de recherche

**Paramètres de requête:**
- `q` (string, requis): Terme de recherche (min 2 caractères)
- `limit` (int): Nombre de suggestions (défaut: 10)

**Réponse succès (200):**
```json
{
  "data": {
    "suggestions": [
      "t-shirt",
      "t-shirt premium",
      "t-shirt coton"
    ]
  }
}
```

## API Panier

### GET /cart
Contenu du panier actuel

**Headers:** `Authorization: Bearer {token}` (optionnel pour invités)

**Réponse succès (200):**
```json
{
  "data": {
    "cart": {
      "id": 789,
      "items": [
        {
          "id": 101,
          "product": {
            "id": 123,
            "name": "T-shirt Premium",
            "slug": "t-shirt-premium",
            "image_url": "https://cdn.example.com/products/123/thumb.jpg"
          },
          "variant": {
            "id": 456,
            "name": "Rouge, L",
            "attributes": {
              "color": "Rouge",
              "size": "L"
            }
          },
          "quantity": 2,
          "unit_price": 2990,
          "total_price": 5980,
          "formatted_unit_price": "29,90 €",
          "formatted_total_price": "59,80 €",
          "max_quantity": 15
        }
      ],
      "totals": {
        "items_count": 2,
        "subtotal": 5980,
        "tax_amount": 1196,
        "shipping_amount": 590,
        "discount_amount": 0,
        "total": 7766,
        "formatted_subtotal": "59,80 €",
        "formatted_tax_amount": "11,96 €",
        "formatted_shipping_amount": "5,90 €",
        "formatted_discount_amount": "0,00 €",
        "formatted_total": "77,66 €"
      },
      "currency": "EUR",
      "expires_at": "2024-01-15T11:30:00Z",
      "applied_coupon": null
    },
    "issues": []
  }
}
```

### POST /cart/items
Ajouter un article au panier

**Paramètres:**
```json
{
  "product_id": 123,
  "variant_id": 456,
  "quantity": 2,
  "custom_attributes": {
    "engraving": "Nom personnalisé"
  }
}
```

**Réponse succès (201):**
```json
{
  "data": {
    "message": "Product added to cart",
    "cart_item": {
      "id": 102,
      "product_name": "T-shirt Premium (Rouge, L)",
      "quantity": 2,
      "unit_price": 2990,
      "total_price": 5980
    },
    "cart": {
      "items_count": 3,
      "total": 8756,
      "formatted_total": "87,56 €"
    }
  }
}
```

### PUT /cart/items/{itemId}
Modifier la quantité d'un article

**Paramètres:**
```json
{
  "quantity": 3
}
```

**Réponse succès (200):**
```json
{
  "data": {
    "message": "Quantity updated",
    "cart": {
      "items_count": 4,
      "total": 9746,
      "formatted_total": "97,46 €"
    }
  }
}
```

### DELETE /cart/items/{itemId}
Supprimer un article du panier

**Réponse succès (200):**
```json
{
  "data": {
    "message": "Item removed from cart",
    "cart": {
      "items_count": 2,
      "total": 7766,
      "formatted_total": "77,66 €"
    }
  }
}
```

### POST /cart/coupon
Appliquer un code de réduction

**Paramètres:**
```json
{
  "code": "REDUCTION10"
}
```

**Réponse succès (200):**
```json
{
  "data": {
    "message": "Coupon applied successfully",
    "discount": {
      "code": "REDUCTION10",
      "amount": 776,
      "formatted_amount": "7,76 €",
      "type": "percentage",
      "value": 10
    },
    "cart": {
      "discount_amount": 776,
      "total": 6990,
      "formatted_total": "69,90 €"
    }
  }
}
```

## API Commandes

### POST /orders
Créer une nouvelle commande

**Paramètres:**
```json
{
  "shipping_address": {
    "first_name": "Jean",
    "last_name": "Dupont",
    "company": "Entreprise SARL",
    "address_line_1": "123 Rue de la Paix",
    "address_line_2": "Appartement 4B",
    "city": "Paris",
    "postal_code": "75001",
    "country_code": "FR",
    "phone": "+33123456789"
  },
  "billing_address": {
    // Même structure que shipping_address
    // Optionnel, utilise shipping_address si absent
  },
  "guest_email": "invite@example.com", // Seulement pour les invités
  "notes": "Livraison en point relais"
}
```

**Réponse succès (201):**
```json
{
  "data": {
    "order": {
      "id": 1001,
      "number": "ORD-2024-001001",
      "status": {
        "value": "pending",
        "label": "En attente",
        "color": "yellow"
      },
      "customer": {
        "id": 123,
        "name": "Jean Dupont",
        "email": "jean.dupont@example.com"
      },
      "items": [
        {
          "id": 2001,
          "product_name": "T-shirt Premium (Rouge, L)",
          "quantity": 2,
          "unit_price": 2990,
          "total_price": 5980,
          "formatted_unit_price": "29,90 €",
          "formatted_total_price": "59,80 €"
        }
      ],
      "totals": {
        "subtotal": 5980,
        "tax_amount": 1196,
        "shipping_amount": 590,
        "discount_amount": 0,
        "total": 7766,
        "formatted_subtotal": "59,80 €",
        "formatted_tax_amount": "11,96 €",
        "formatted_shipping_amount": "5,90 €",
        "formatted_total": "77,66 €"
      },
      "addresses": {
        "shipping": {
          "full_name": "Jean Dupont",
          "formatted_address": "123 Rue de la Paix\\nAppartement 4B\\n75001 Paris\\nFrance"
        },
        "billing": {
          "full_name": "Jean Dupont",
          "formatted_address": "123 Rue de la Paix\\nAppartement 4B\\n75001 Paris\\nFrance"
        }
      },
      "dates": {
        "created_at": "2024-01-15T10:30:00Z",
        "estimated_delivery": "2024-01-17T18:00:00Z"
      },
      "available_actions": [
        {
          "action": "pay",
          "label": "Payer",
          "confirm_required": false
        },
        {
          "action": "cancel",
          "label": "Annuler",
          "confirm_required": true
        }
      ]
    }
  }
}
```

### GET /orders
Liste des commandes du client

**Headers:** `Authorization: Bearer {token}`

**Paramètres de requête:**
- `page` (int): Numéro de page
- `limit` (int): Éléments par page
- `status` (string): Filtrer par statut

**Réponse succès (200):**
```json
{
  "data": {
    "orders": [
      {
        "id": 1001,
        "number": "ORD-2024-001001",
        "status": {
          "value": "completed",
          "label": "Terminée",
          "color": "green"
        },
        "total": 7766,
        "formatted_total": "77,66 €",
        "items_count": 2,
        "created_at": "2024-01-15T10:30:00Z",
        "shipped_at": "2024-01-16T14:20:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 5,
      "total_pages": 1
    }
  }
}
```

### GET /orders/{id}
Détails d'une commande

**Headers:** `Authorization: Bearer {token}`

**Réponse succès (200):**
```json
{
  "data": {
    "order": {
      // Structure complète comme dans POST /orders
      "tracking": {
        "carrier": "Colissimo",
        "tracking_number": "1Z999AA1234567890",
        "tracking_url": "https://www.laposte.fr/outils/suivre-vos-envois?code=1Z999AA1234567890",
        "estimated_delivery": "2024-01-17T18:00:00Z"
      },
      "status_history": [
        {
          "status": "pending",
          "label": "En attente",
          "date": "2024-01-15T10:30:00Z",
          "comment": "Commande créée"
        },
        {
          "status": "confirmed",
          "label": "Confirmée",
          "date": "2024-01-15T11:00:00Z",
          "comment": "Paiement validé"
        }
      ]
    }
  }
}
```

### POST /orders/{id}/cancel
Annuler une commande

**Headers:** `Authorization: Bearer {token}`

**Paramètres:**
```json
{
  "reason": "Changement d'avis"
}
```

**Réponse succès (200):**
```json
{
  "data": {
    "message": "Order cancelled successfully",
    "order": {
      "id": 1001,
      "status": {
        "value": "cancelled",
        "label": "Annulée",
        "color": "red"
      }
    }
  }
}
```

### GET /orders/by-number/{orderNumber}
Rechercher une commande par numéro

**Paramètres de requête:**
- `email` (string): Email (requis pour les invités)

**Exemple:**
```http
GET /orders/by-number/ORD-2024-001001?email=invite@example.com
```

## API Paiements

### POST /payments/intent
Créer une intention de paiement

**Paramètres:**
```json
{
  "order_id": 1001,
  "gateway": "stripe",
  "return_url": "https://monsite.com/payment/success",
  "cancel_url": "https://monsite.com/payment/cancel"
}
```

**Réponse succès (201):**
```json
{
  "data": {
    "payment_id": 5001,
    "client_secret": "pi_1234567890_secret_abcd",
    "intent_id": "pi_1234567890",
    "gateway": "stripe",
    "amount": 7766,
    "currency": "EUR"
  }
}
```

### POST /payments/{id}/confirm
Confirmer un paiement

**Paramètres:**
```json
{
  "payment_method": "pm_card_visa",
  "confirmation_token": "token_abc123"
}
```

**Réponse succès (200):**
```json
{
  "data": {
    "success": true,
    "status": "completed",
    "transaction_id": "txn_1234567890",
    "message": "Payment completed successfully"
  }
}
```

### POST /payments/webhook/{provider}
Webhook des providers de paiement

**Exemple Stripe:**
```http
POST /payments/webhook/stripe
Stripe-Signature: t=1234567890,v1=abc123...
```

**Réponse succès (200):**
```json
{
  "data": {
    "success": true,
    "message": "Webhook processed"
  }
}
```

## API Clients

### GET /customers/profile
Profil du client connecté

**Headers:** `Authorization: Bearer {token}`

**Réponse succès (200):**
```json
{
  "data": {
    "customer": {
      "id": 123,
      "email": "jean.dupont@example.com",
      "full_name": "Jean Dupont",
      "phone": "+33123456789",
      "created_at": "2023-06-15T10:00:00Z",
      "email_verified": true,
      "is_active": true,
      "total_spent": 25643,
      "total_spent_formatted": "256,43 €",
      "orders_count": 12,
      "loyalty_points": 1250,
      "customer_group": "VIP",
      "profile": {
        "avatar_url": "https://cdn.example.com/avatars/123.jpg",
        "bio": "Passionné de mode",
        "interests": ["mode", "technologie", "sport"],
        "language": "fr",
        "timezone": "Europe/Paris"
      },
      "recent_orders": [
        {
          "id": 1001,
          "number": "ORD-2024-001001",
          "status": "Terminée",
          "total": "77,66 €",
          "created_at": "2024-01-15T10:30:00Z"
        }
      ]
    },
    "lifetime_value": {
      "total_spent": 25643,
      "total_orders": 12,
      "avg_order_value": 2137,
      "avg_days_between_orders": 28.5,
      "predicted_clv": 35000,
      "customer_since_days": 214,
      "segment": "loyal"
    }
  }
}
```

### PUT /customers/profile
Mettre à jour le profil

**Headers:** `Authorization: Bearer {token}`

**Paramètres:**
```json
{
  "first_name": "Jean",
  "last_name": "Dupont",
  "phone": "+33123456789",
  "date_of_birth": "1985-03-15",
  "profile": {
    "bio": "Amateur de mode et technologie",
    "interests": ["mode", "technologie", "sport"],
    "preferences": {
      "newsletter": true,
      "sms_notifications": false,
      "theme": "dark"
    },
    "communication_preferences": {
      "email": true,
      "sms": false,
      "push": true
    }
  }
}
```

### GET /customers/loyalty/points
Points de fidélité

**Headers:** `Authorization: Bearer {token}`

**Réponse succès (200):**
```json
{
  "data": {
    "current_points": 1250,
    "points_value": 1250,
    "expiring_soon": 200,
    "history": [
      {
        "id": 3001,
        "points": 77,
        "type": "order_purchase",
        "description": "Achat commande #ORD-2024-001001",
        "created_at": "2024-01-15T10:30:00Z",
        "expires_at": "2025-01-15T10:30:00Z"
      },
      {
        "id": 3000,
        "points": -500,
        "type": "redemption",
        "description": "Utilisation pour commande #ORD-2024-001000",
        "created_at": "2024-01-10T15:20:00Z",
        "expires_at": null
      }
    ]
  }
}
```

### GET /customers/addresses
Adresses du client

**Headers:** `Authorization: Bearer {token}`

**Réponse succès (200):**
```json
{
  "data": {
    "addresses": [
      {
        "id": 401,
        "type": "both",
        "label": "Domicile",
        "full_name": "Jean Dupont",
        "company": null,
        "formatted_address": "123 Rue de la Paix\\n75001 Paris\\nFrance",
        "is_default": true
      }
    ]
  }
}
```

### GET /customers/wishlist
Liste de souhaits

**Headers:** `Authorization: Bearer {token}`

**Réponse succès (200):**
```json
{
  "data": {
    "items": [
      {
        "id": 501,
        "product": {
          "id": 125,
          "name": "Jean Slim",
          "slug": "jean-slim",
          "price": 5990,
          "formatted_price": "59,90 €"
        },
        "variant": {
          "id": 458,
          "name": "Bleu, 32",
          "attributes": {
            "color": "Bleu",
            "size": "32"
          }
        },
        "added_at": "2024-01-10T15:30:00Z",
        "notes": "Pour l'été prochain"
      }
    ]
  }
}
```

## API Inventaire

### GET /inventory/stock/{productId}
Stock d'un produit

**Paramètres de requête:**
- `warehouse_id` (int): ID entrepôt spécifique
- `variant_id` (int): ID variant spécifique

**Réponse succès (200):**
```json
{
  "data": {
    "product_id": 123,
    "variant_id": 456,
    "warehouse_id": null,
    "available_quantity": 25,
    "is_available": true
  }
}
```

### GET /inventory/movements/{productId}
Historique des mouvements

**Headers:** `Authorization: Bearer {admin_token}`

**Paramètres de requête:**
- `variant_id` (int): ID variant
- `warehouse_id` (int): ID entrepôt
- `from` (date): Date de début (YYYY-MM-DD)
- `to` (date): Date de fin (YYYY-MM-DD)

**Réponse succès (200):**
```json
{
  "data": {
    "movements": [
      {
        "id": 7001,
        "type": "in",
        "quantity": 50,
        "quantity_before": 10,
        "quantity_after": 60,
        "reason": "Réapprovisionnement",
        "reference": "PO-2024-001",
        "created_at": "2024-01-15T10:00:00Z",
        "notes": "Livraison fournisseur"
      }
    ]
  }
}
```

## Codes d'erreur

### Format des erreurs
```json
{
  "errors": [
    {
      "code": "VALIDATION_ERROR",
      "message": "The given data was invalid",
      "details": {
        "email": ["The email field is required."],
        "password": ["The password must be at least 8 characters."]
      }
    }
  ]
}
```

### Codes d'erreur courants

| Code HTTP | Code Erreur | Description |
|-----------|-------------|-------------|
| 400 | `BAD_REQUEST` | Requête malformée |
| 401 | `UNAUTHORIZED` | Token manquant ou invalide |
| 403 | `FORBIDDEN` | Accès refusé |
| 404 | `NOT_FOUND` | Ressource non trouvée |
| 409 | `CONFLICT` | Conflit (ex: email déjà utilisé) |
| 422 | `VALIDATION_ERROR` | Erreurs de validation |
| 429 | `RATE_LIMIT_EXCEEDED` | Limite de taux dépassée |
| 500 | `INTERNAL_ERROR` | Erreur serveur interne |

### Erreurs métier spécifiques

| Code | Description |
|------|-------------|
| `PRODUCT_NOT_AVAILABLE` | Produit non disponible |
| `INSUFFICIENT_STOCK` | Stock insuffisant |
| `CART_EMPTY` | Panier vide |
| `PAYMENT_FAILED` | Échec de paiement |
| `COUPON_INVALID` | Code de réduction invalide |
| `ORDER_CANNOT_BE_CANCELLED` | Commande ne peut être annulée |
| `EMAIL_ALREADY_EXISTS` | Email déjà utilisé |
| `INVALID_CREDENTIALS` | Identifiants incorrects |

## Pagination

### Format standard
```json
{
  "data": {
    "items": [...],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 156,
      "total_pages": 8,
      "has_next": true,
      "has_previous": false,
      "next_page": 2,
      "previous_page": null
    }
  }
}
```

### Paramètres de pagination
- `page`: Numéro de page (défaut: 1)
- `limit` ou `per_page`: Éléments par page (défaut: 20)
- Maximum par page: 100

## Rate Limiting

### Limites par défaut
- **Invités**: 100 requêtes/heure
- **Clients authentifiés**: 500 requêtes/heure
- **API admin**: 1000 requêtes/heure

### Headers de réponse
```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1705320600
```

### Erreur de limite dépassée (429)
```json
{
  "errors": [
    {
      "code": "RATE_LIMIT_EXCEEDED",
      "message": "Too many requests. Please try again later.",
      "retry_after": 3600
    }
  ]
}
```

## Webhooks sortants

### Configuration
Pour recevoir les webhooks, configurez les endpoints dans votre panel admin.

### Événements disponibles
- `order.created`
- `order.paid`
- `order.shipped`
- `order.completed`
- `order.cancelled`
- `customer.created`
- `product.stock_low`

### Format des webhooks
```json
{
  "event": "order.completed",
  "data": {
    "order": {
      "id": 1001,
      "number": "ORD-2024-001001",
      // ... données complètes de la commande
    }
  },
  "timestamp": "2024-01-15T10:30:00Z",
  "signature": "sha256=abc123..."
}
```

### Vérification de signature
```php
$signature = hash_hmac('sha256', $payload, $webhook_secret);
$expected = 'sha256=' . $signature;
if (!hash_equals($expected, $received_signature)) {
    // Signature invalide
}
```

---

Cette documentation API complète fournit tous les endpoints nécessaires pour intégrer et utiliser l'application e-commerce, avec des exemples détaillés et une gestion d'erreurs robuste.