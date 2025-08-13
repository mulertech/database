# Fonctionnalités Avancées E-commerce

Cette section présente les fonctionnalités avancées de l'application e-commerce, exploitant pleinement les capacités de MulerTech Database ORM pour des besoins complexes et des optimisations de performance.

## Table des matières

- [Analytics et Reporting](#analytics-et-reporting)
- [Système de Recommandations](#système-de-recommandations)
- [Gestion Multi-tenant](#gestion-multi-tenant)
- [Cache Avancé](#cache-avancé)
- [Events et Notifications](#events-et-notifications)
- [Queue Processing](#queue-processing)
- [Audit et Traçabilité](#audit-et-traçabilité)
- [Internationalization](#internationalization)
- [API Rate Limiting](#api-rate-limiting)
- [Optimisations Performance](#optimisations-performance)
- [Sécurité Avancée](#sécurité-avancée)
- [Monitoring et Observabilité](#monitoring-et-observabilité)

## Analytics et Reporting

### Entité Analytics

```php
<?php

namespace App\Entity\Analytics;

use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;

#[MtEntity(table: 'analytics_events')]
class AnalyticsEvent
{
    #[MtColumn(name: 'id', type: 'bigint', autoIncrement: true)]
    private int $id;

    #[MtColumn(name: 'event_type', type: 'varchar', length: 50)]
    private string $eventType;

    #[MtColumn(name: 'entity_type', type: 'varchar', length: 50)]
    private string $entityType;

    #[MtColumn(name: 'entity_id', type: 'bigint')]
    private int $entityId;

    #[MtColumn(name: 'user_id', type: 'bigint', nullable: true)]
    private ?int $userId = null;

    #[MtColumn(name: 'session_id', type: 'varchar', length: 128)]
    private string $sessionId;

    #[MtColumn(name: 'properties', type: 'json')]
    private array $properties = [];

    #[MtColumn(name: 'occurred_at', type: 'timestamp', default: 'CURRENT_TIMESTAMP')]
    private \DateTimeInterface $occurredAt;

    // Getters et setters...
}
```

### Service Analytics

```php
<?php

namespace App\Service\Analytics;

use MulerTech\Database\ORM\EmEngine;
use App\Entity\Analytics\AnalyticsEvent;
use App\Enum\AnalyticsEventType;

/**
 * Service de gestion des analytics
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class AnalyticsService
{
    public function __construct(
        private readonly EmEngine $em
    ) {}

    /**
     * @param AnalyticsEventType $eventType
     * @param string $entityType
     * @param int $entityId
     * @param array<string, mixed> $properties
     * @param int|null $userId
     * @param string|null $sessionId
     */
    public function track(
        AnalyticsEventType $eventType,
        string $entityType,
        int $entityId,
        array $properties = [],
        ?int $userId = null,
        ?string $sessionId = null
    ): void {
        $event = new AnalyticsEvent();
        $event->setEventType($eventType->value);
        $event->setEntityType($entityType);
        $event->setEntityId($entityId);
        $event->setUserId($userId);
        $event->setSessionId($sessionId ?? session_id());
        $event->setProperties($properties);
        $event->setOccurredAt(new \DateTimeImmutable());

        $this->em->persist($event);
        $this->em->flush();
    }

    /**
     * @param \DateTimeInterface $from
     * @param \DateTimeInterface $to
     * @return array<string, mixed>
     */
    public function getSalesReport(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $qb = $this->em->createQueryBuilder();
        
        $result = $qb
            ->select([
                'DATE(occurred_at) as date',
                'COUNT(*) as total_events',
                'COUNT(DISTINCT user_id) as unique_users',
                'SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as conversions'
            ])
            ->from('analytics_events')
            ->where('occurred_at BETWEEN ? AND ?')
            ->groupBy('DATE(occurred_at)')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->execute([
                AnalyticsEventType::ORDER_COMPLETED->value,
                $from->format('Y-m-d H:i:s'),
                $to->format('Y-m-d H:i:s')
            ]);

        return $result;
    }

    /**
     * @param int $productId
     * @return array<string, mixed>
     */
    public function getProductAnalytics(int $productId): array
    {
        $qb = $this->em->createQueryBuilder();
        
        return [
            'views' => $qb->select('COUNT(*)')
                ->from('analytics_events')
                ->where('entity_type = ? AND entity_id = ? AND event_type = ?')
                ->getQuery()
                ->getSingleScalarResult([
                    'Product',
                    $productId,
                    AnalyticsEventType::PRODUCT_VIEW->value
                ]),
            
            'cart_additions' => $qb->select('COUNT(*)')
                ->from('analytics_events')
                ->where('entity_type = ? AND entity_id = ? AND event_type = ?')
                ->getQuery()
                ->getSingleScalarResult([
                    'Product',
                    $productId,
                    AnalyticsEventType::CART_ADD->value
                ]),
                
            'purchases' => $qb->select('COUNT(*)')
                ->from('analytics_events')
                ->where('entity_type = ? AND entity_id = ? AND event_type = ?')
                ->getQuery()
                ->getSingleScalarResult([
                    'Product',
                    $productId,
                    AnalyticsEventType::PRODUCT_PURCHASE->value
                ])
        ];
    }
}
```

### Énumération des événements

```php
<?php

namespace App\Enum;

enum AnalyticsEventType: string
{
    case PRODUCT_VIEW = 'product_view';
    case CART_ADD = 'cart_add';
    case CART_REMOVE = 'cart_remove';
    case CHECKOUT_START = 'checkout_start';
    case PAYMENT_START = 'payment_start';
    case ORDER_COMPLETED = 'order_completed';
    case PRODUCT_PURCHASE = 'product_purchase';
    case SEARCH = 'search';
    case CATEGORY_VIEW = 'category_view';
    case USER_REGISTER = 'user_register';
    case USER_LOGIN = 'user_login';
}
```

## Système de Recommandations

### Service de Recommandations

```php
<?php

namespace App\Service\Recommendation;

use MulerTech\Database\ORM\EmEngine;
use App\Entity\Product;
use App\Entity\Customer;

/**
 * Service de recommandation de produits
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class RecommendationService
{
    public function __construct(
        private readonly EmEngine $em
    ) {}

    /**
     * Recommandations basées sur l'historique d'achat
     *
     * @param Customer $customer
     * @param int $limit
     * @return array<Product>
     */
    public function getPersonalizedRecommendations(Customer $customer, int $limit = 10): array
    {
        $qb = $this->em->createQueryBuilder();
        
        // Produits achetés par des clients ayant des goûts similaires
        $similarProducts = $qb
            ->select('p.*')
            ->from('products p')
            ->join('order_items oi', 'oi.product_id = p.id')
            ->join('orders o', 'o.id = oi.order_id')
            ->join('customers c', 'c.id = o.customer_id')
            ->where('c.id IN (
                SELECT DISTINCT o2.customer_id 
                FROM orders o2 
                JOIN order_items oi2 ON oi2.order_id = o2.id 
                WHERE oi2.product_id IN (
                    SELECT oi3.product_id 
                    FROM order_items oi3 
                    JOIN orders o3 ON o3.id = oi3.order_id 
                    WHERE o3.customer_id = ?
                )
                AND o2.customer_id != ?
            )')
            ->andWhere('p.id NOT IN (
                SELECT oi4.product_id 
                FROM order_items oi4 
                JOIN orders o4 ON o4.id = oi4.order_id 
                WHERE o4.customer_id = ?
            )')
            ->andWhere('p.is_active = 1')
            ->groupBy('p.id')
            ->orderBy('COUNT(*)', 'DESC')
            ->limit($limit)
            ->getQuery()
            ->execute([$customer->getId(), $customer->getId(), $customer->getId()]);

        return array_map(fn($row) => $this->em->hydrate(Product::class, $row), $similarProducts);
    }

    /**
     * Produits fréquemment achetés ensemble
     *
     * @param Product $product
     * @param int $limit
     * @return array<Product>
     */
    public function getFrequentlyBoughtTogether(Product $product, int $limit = 5): array
    {
        $qb = $this->em->createQueryBuilder();
        
        $relatedProducts = $qb
            ->select('p2.*, COUNT(*) as frequency')
            ->from('products p2')
            ->join('order_items oi2', 'oi2.product_id = p2.id')
            ->where('oi2.order_id IN (
                SELECT oi1.order_id 
                FROM order_items oi1 
                WHERE oi1.product_id = ?
            )')
            ->andWhere('p2.id != ?')
            ->andWhere('p2.is_active = 1')
            ->groupBy('p2.id')
            ->orderBy('frequency', 'DESC')
            ->limit($limit)
            ->getQuery()
            ->execute([$product->getId(), $product->getId()]);

        return array_map(fn($row) => $this->em->hydrate(Product::class, $row), $relatedProducts);
    }

    /**
     * Produits populaires dans une catégorie
     *
     * @param int $categoryId
     * @param int $limit
     * @return array<Product>
     */
    public function getTrendingInCategory(int $categoryId, int $limit = 10): array
    {
        $qb = $this->em->createQueryBuilder();
        
        $trendingProducts = $qb
            ->select('p.*, COUNT(oi.id) as sales_count')
            ->from('products p')
            ->leftJoin('order_items oi', 'oi.product_id = p.id')
            ->leftJoin('orders o', 'o.id = oi.order_id')
            ->where('p.category_id = ?')
            ->andWhere('p.is_active = 1')
            ->andWhere('o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')
            ->groupBy('p.id')
            ->orderBy('sales_count', 'DESC')
            ->limit($limit)
            ->getQuery()
            ->execute([$categoryId]);

        return array_map(fn($row) => $this->em->hydrate(Product::class, $row), $trendingProducts);
    }
}
```

## Gestion Multi-tenant

### Trait Multi-tenant

```php
<?php

namespace App\Trait;

use MulerTech\Database\ORM\Attribute\MtColumn;

trait MultiTenantTrait
{
    #[MtColumn(name: 'tenant_id', type: 'varchar', length: 50)]
    private string $tenantId;

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function setTenantId(string $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }
}
```

### Service Multi-tenant

```php
<?php

namespace App\Service\MultiTenant;

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Event\PrePersistEvent;
use MulerTech\Database\Event\PreUpdateEvent;
use App\Trait\MultiTenantTrait;

/**
 * Service de gestion multi-tenant
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class TenantService
{
    private ?string $currentTenantId = null;

    public function __construct(
        private readonly EmEngine $em
    ) {
        $this->setupEventListeners();
    }

    public function setCurrentTenant(string $tenantId): void
    {
        $this->currentTenantId = $tenantId;
        
        // Application du filtre global pour toutes les requêtes
        $this->em->addGlobalFilter('tenant', function($qb) {
            $qb->where('tenant_id = ?', [$this->currentTenantId]);
        });
    }

    public function getCurrentTenant(): ?string
    {
        return $this->currentTenantId;
    }

    private function setupEventListeners(): void
    {
        $this->em->getEventDispatcher()->addListener(
            PrePersistEvent::class,
            [$this, 'onPrePersist']
        );
        
        $this->em->getEventDispatcher()->addListener(
            PreUpdateEvent::class,
            [$this, 'onPreUpdate']
        );
    }

    public function onPrePersist(PrePersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($this->usesTrait($entity, MultiTenantTrait::class)) {
            if ($this->currentTenantId === null) {
                throw new \RuntimeException('Tenant ID must be set before persisting entities');
            }
            
            $entity->setTenantId($this->currentTenantId);
        }
    }

    public function onPreUpdate(PreUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($this->usesTrait($entity, MultiTenantTrait::class)) {
            if ($entity->getTenantId() !== $this->currentTenantId) {
                throw new \RuntimeException('Cannot modify entity from different tenant');
            }
        }
    }

    /**
     * @param object $entity
     * @param string $traitName
     * @return bool
     */
    private function usesTrait(object $entity, string $traitName): bool
    {
        return in_array($traitName, class_uses_recursive($entity::class), true);
    }
}
```

## Cache Avancé

### Service de Cache Intelligent

```php
<?php

namespace App\Service\Cache;

use MulerTech\Database\Core\Cache\CacheInterface;

/**
 * Service de cache intelligent avec invalidation automatique
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SmartCacheService
{
    private array $dependencies = [];

    public function __construct(
        private readonly CacheInterface $cache
    ) {}

    /**
     * @param string $key
     * @param callable $callback
     * @param int $ttl
     * @param array<string> $tags
     * @return mixed
     */
    public function remember(string $key, callable $callback, int $ttl = 3600, array $tags = []): mixed
    {
        $cached = $this->cache->get($key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $value = $callback();
        $this->cache->set($key, $value, $ttl);
        
        // Enregistrement des dépendances
        foreach ($tags as $tag) {
            $this->dependencies[$tag][] = $key;
        }
        
        return $value;
    }

    /**
     * @param string $tag
     */
    public function invalidateByTag(string $tag): void
    {
        if (isset($this->dependencies[$tag])) {
            foreach ($this->dependencies[$tag] as $key) {
                $this->cache->delete($key);
            }
            unset($this->dependencies[$tag]);
        }
    }

    /**
     * @param int $productId
     * @return array<string, mixed>
     */
    public function getProductWithCache(int $productId): array
    {
        return $this->remember(
            "product:{$productId}",
            fn() => $this->loadProductData($productId),
            3600,
            ["product", "product:{$productId}"]
        );
    }

    /**
     * @param int $categoryId
     * @return array<array<string, mixed>>
     */
    public function getCategoryProductsWithCache(int $categoryId): array
    {
        return $this->remember(
            "category_products:{$categoryId}",
            fn() => $this->loadCategoryProducts($categoryId),
            1800,
            ["category", "product", "category:{$categoryId}"]
        );
    }

    /**
     * Invalidation automatique lors de la modification d'un produit
     *
     * @param int $productId
     */
    public function invalidateProduct(int $productId): void
    {
        $this->invalidateByTag("product:{$productId}");
        $this->invalidateByTag("product");
    }

    /**
     * @param int $productId
     * @return array<string, mixed>
     */
    private function loadProductData(int $productId): array
    {
        // Logique de chargement des données produit
        return [];
    }

    /**
     * @param int $categoryId
     * @return array<array<string, mixed>>
     */
    private function loadCategoryProducts(int $categoryId): array
    {
        // Logique de chargement des produits de catégorie
        return [];
    }
}
```

## Events et Notifications

### Système d'événements métier

```php
<?php

namespace App\Event;

use App\Entity\Order;

class OrderStatusChangedEvent
{
    public function __construct(
        private readonly Order $order,
        private readonly string $previousStatus,
        private readonly string $newStatus
    ) {}

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getPreviousStatus(): string
    {
        return $this->previousStatus;
    }

    public function getNewStatus(): string
    {
        return $this->newStatus;
    }
}
```

### Listener de notifications

```php
<?php

namespace App\EventListener;

use App\Event\OrderStatusChangedEvent;
use App\Service\NotificationService;
use App\Enum\OrderStatus;

/**
 * Listener pour les notifications de commandes
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class OrderNotificationListener
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function onOrderStatusChanged(OrderStatusChangedEvent $event): void
    {
        $order = $event->getOrder();
        $newStatus = $event->getNewStatus();

        match ($newStatus) {
            OrderStatus::CONFIRMED->value => $this->sendOrderConfirmationEmail($order),
            OrderStatus::SHIPPED->value => $this->sendShippingNotification($order),
            OrderStatus::DELIVERED->value => $this->sendDeliveryConfirmation($order),
            OrderStatus::CANCELLED->value => $this->sendCancellationNotification($order),
            default => null
        };
    }

    private function sendOrderConfirmationEmail(Order $order): void
    {
        $this->notificationService->sendEmail(
            $order->getCustomer()->getEmail(),
            'Confirmation de commande',
            'emails/order-confirmation',
            ['order' => $order]
        );
    }

    private function sendShippingNotification(Order $order): void
    {
        $this->notificationService->sendEmail(
            $order->getCustomer()->getEmail(),
            'Votre commande a été expédiée',
            'emails/order-shipped',
            ['order' => $order]
        );
        
        // Notification SMS si le client a fourni son numéro
        if ($order->getCustomer()->getPhone()) {
            $this->notificationService->sendSms(
                $order->getCustomer()->getPhone(),
                "Votre commande #{$order->getNumber()} a été expédiée."
            );
        }
    }

    private function sendDeliveryConfirmation(Order $order): void
    {
        $this->notificationService->sendEmail(
            $order->getCustomer()->getEmail(),
            'Commande livrée',
            'emails/order-delivered',
            ['order' => $order]
        );
    }

    private function sendCancellationNotification(Order $order): void
    {
        $this->notificationService->sendEmail(
            $order->getCustomer()->getEmail(),
            'Annulation de commande',
            'emails/order-cancelled',
            ['order' => $order]
        );
    }
}
```

## Queue Processing

### Service de Queue

```php
<?php

namespace App\Service\Queue;

use MulerTech\Database\ORM\EmEngine;

/**
 * Service de gestion des tâches en arrière-plan
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueueService
{
    public function __construct(
        private readonly EmEngine $em
    ) {}

    /**
     * @param string $jobClass
     * @param array<string, mixed> $payload
     * @param int $delay
     */
    public function dispatch(string $jobClass, array $payload = [], int $delay = 0): void
    {
        $qb = $this->em->createQueryBuilder();
        
        $qb->insert('queue_jobs')
            ->values([
                'job_class' => '?',
                'payload' => '?',
                'available_at' => '?',
                'created_at' => 'NOW()'
            ])
            ->getQuery()
            ->execute([
                $jobClass,
                json_encode($payload),
                date('Y-m-d H:i:s', time() + $delay)
            ]);
    }

    public function processJobs(): void
    {
        $qb = $this->em->createQueryBuilder();
        
        $jobs = $qb->select('*')
            ->from('queue_jobs')
            ->where('available_at <= NOW()')
            ->andWhere('processed_at IS NULL')
            ->orderBy('created_at', 'ASC')
            ->limit(10)
            ->getQuery()
            ->execute();

        foreach ($jobs as $job) {
            $this->processJob($job);
        }
    }

    /**
     * @param array<string, mixed> $job
     */
    private function processJob(array $job): void
    {
        try {
            $jobClass = $job['job_class'];
            $payload = json_decode($job['payload'], true);
            
            if (class_exists($jobClass)) {
                $jobInstance = new $jobClass();
                $jobInstance->handle($payload);
                
                // Marquer comme traité
                $this->markJobAsProcessed($job['id']);
            }
        } catch (\Exception $e) {
            $this->markJobAsFailed($job['id'], $e->getMessage());
        }
    }

    private function markJobAsProcessed(int $jobId): void
    {
        $qb = $this->em->createQueryBuilder();
        
        $qb->update('queue_jobs')
            ->set('processed_at', 'NOW()')
            ->where('id = ?')
            ->getQuery()
            ->execute([$jobId]);
    }

    private function markJobAsFailed(int $jobId, string $error): void
    {
        $qb = $this->em->createQueryBuilder();
        
        $qb->update('queue_jobs')
            ->set('failed_at', 'NOW()')
            ->set('error_message', '?')
            ->where('id = ?')
            ->getQuery()
            ->execute([$error, $jobId]);
    }
}
```

### Job d'exemple

```php
<?php

namespace App\Job;

use App\Service\EmailService;

class SendWelcomeEmailJob
{
    public function __construct(
        private readonly EmailService $emailService = new EmailService()
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload): void
    {
        $customerEmail = $payload['customer_email'];
        $customerName = $payload['customer_name'];
        
        $this->emailService->send(
            $customerEmail,
            'Bienvenue !',
            'emails/welcome',
            ['name' => $customerName]
        );
    }
}
```

## Audit et Traçabilité

### Trait d'audit

```php
<?php

namespace App\Trait;

use MulerTech\Database\ORM\Attribute\MtColumn;

trait AuditableTrait
{
    #[MtColumn(name: 'created_by', type: 'bigint', nullable: true)]
    private ?int $createdBy = null;

    #[MtColumn(name: 'updated_by', type: 'bigint', nullable: true)]
    private ?int $updatedBy = null;

    #[MtColumn(name: 'version', type: 'int', default: 1)]
    private int $version = 1;

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?int $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function incrementVersion(): self
    {
        $this->version++;
        return $this;
    }
}
```

### Service d'audit

```php
<?php

namespace App\Service\Audit;

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Event\PostUpdateEvent;
use MulerTech\Database\Event\PostPersistEvent;
use MulerTech\Database\Event\PostRemoveEvent;

/**
 * Service d'audit pour tracer les modifications
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class AuditService
{
    public function __construct(
        private readonly EmEngine $em
    ) {
        $this->setupEventListeners();
    }

    private function setupEventListeners(): void
    {
        $this->em->getEventDispatcher()->addListener(
            PostPersistEvent::class,
            [$this, 'onPostPersist']
        );
        
        $this->em->getEventDispatcher()->addListener(
            PostUpdateEvent::class,
            [$this, 'onPostUpdate']
        );
        
        $this->em->getEventDispatcher()->addListener(
            PostRemoveEvent::class,
            [$this, 'onPostRemove']
        );
    }

    public function onPostPersist(PostPersistEvent $event): void
    {
        $this->logAuditEvent('CREATE', $event->getEntity());
    }

    public function onPostUpdate(PostUpdateEvent $event): void
    {
        $this->logAuditEvent('UPDATE', $event->getEntity(), $event->getChangeSet());
    }

    public function onPostRemove(PostRemoveEvent $event): void
    {
        $this->logAuditEvent('DELETE', $event->getEntity());
    }

    /**
     * @param string $action
     * @param object $entity
     * @param array<string, mixed>|null $changes
     */
    private function logAuditEvent(string $action, object $entity, ?array $changes = null): void
    {
        $qb = $this->em->createQueryBuilder();
        
        $qb->insert('audit_log')
            ->values([
                'entity_type' => '?',
                'entity_id' => '?',
                'action' => '?',
                'changes' => '?',
                'user_id' => '?',
                'ip_address' => '?',
                'user_agent' => '?',
                'created_at' => 'NOW()'
            ])
            ->getQuery()
            ->execute([
                $entity::class,
                $this->getEntityId($entity),
                $action,
                $changes ? json_encode($changes) : null,
                $this->getCurrentUserId(),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
    }

    /**
     * @param object $entity
     * @return mixed
     */
    private function getEntityId(object $entity): mixed
    {
        $metadata = $this->em->getMetadataRegistry()->getMetadata($entity::class);
        $idField = $metadata->getIdField();
        
        $getter = 'get' . ucfirst($idField);
        return method_exists($entity, $getter) ? $entity->$getter() : null;
    }

    private function getCurrentUserId(): ?int
    {
        // Récupération de l'utilisateur actuel depuis la session ou le token JWT
        return $_SESSION['user_id'] ?? null;
    }
}
```

## Internationalization

### Service i18n

```php
<?php

namespace App\Service\I18n;

/**
 * Service d'internationalisation
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class I18nService
{
    private array $translations = [];
    private string $currentLocale = 'fr';

    public function __construct(
        private readonly string $translationsPath = 'translations'
    ) {}

    public function setLocale(string $locale): void
    {
        $this->currentLocale = $locale;
        $this->loadTranslations($locale);
    }

    public function getLocale(): string
    {
        return $this->currentLocale;
    }

    /**
     * @param string $key
     * @param array<string, mixed> $parameters
     * @return string
     */
    public function translate(string $key, array $parameters = []): string
    {
        $translation = $this->translations[$key] ?? $key;
        
        foreach ($parameters as $param => $value) {
            $translation = str_replace('{' . $param . '}', $value, $translation);
        }
        
        return $translation;
    }

    /**
     * @param string $key
     * @param array<string, mixed> $parameters
     * @return string
     */
    public function t(string $key, array $parameters = []): string
    {
        return $this->translate($key, $parameters);
    }

    private function loadTranslations(string $locale): void
    {
        $filePath = $this->translationsPath . '/' . $locale . '.php';
        
        if (file_exists($filePath)) {
            $this->translations = require $filePath;
        }
    }
}
```

### Trait traduisible

```php
<?php

namespace App\Trait;

use MulerTech\Database\ORM\Attribute\MtColumn;

trait TranslatableTrait
{
    #[MtColumn(name: 'translations', type: 'json')]
    private array $translations = [];

    /**
     * @param string $field
     * @param string $locale
     * @return string|null
     */
    public function getTranslation(string $field, string $locale): ?string
    {
        return $this->translations[$locale][$field] ?? null;
    }

    /**
     * @param string $field
     * @param string $locale
     * @param string $value
     */
    public function setTranslation(string $field, string $locale, string $value): self
    {
        $this->translations[$locale][$field] = $value;
        return $this;
    }

    /**
     * @param string $field
     * @param string|null $fallbackLocale
     * @return string|null
     */
    public function getLocalizedValue(string $field, ?string $fallbackLocale = 'fr'): ?string
    {
        $currentLocale = $this->getCurrentLocale();
        
        return $this->getTranslation($field, $currentLocale) 
            ?? $this->getTranslation($field, $fallbackLocale)
            ?? null;
    }

    private function getCurrentLocale(): string
    {
        // Récupération de la locale actuelle
        return $_SESSION['locale'] ?? 'fr';
    }
}
```

## API Rate Limiting

### Service de Rate Limiting

```php
<?php

namespace App\Service\Security;

use MulerTech\Database\Core\Cache\CacheInterface;

/**
 * Service de limitation du taux de requêtes
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class RateLimitService
{
    public function __construct(
        private readonly CacheInterface $cache
    ) {}

    /**
     * @param string $identifier
     * @param int $maxRequests
     * @param int $windowSeconds
     * @return bool
     */
    public function isAllowed(string $identifier, int $maxRequests = 100, int $windowSeconds = 3600): bool
    {
        $key = "rate_limit:{$identifier}";
        $currentCount = (int) $this->cache->get($key, 0);
        
        if ($currentCount >= $maxRequests) {
            return false;
        }
        
        $this->cache->set($key, $currentCount + 1, $windowSeconds);
        return true;
    }

    /**
     * @param string $identifier
     * @return int
     */
    public function getRemainingRequests(string $identifier, int $maxRequests = 100): int
    {
        $key = "rate_limit:{$identifier}";
        $currentCount = (int) $this->cache->get($key, 0);
        
        return max(0, $maxRequests - $currentCount);
    }

    /**
     * @param string $identifier
     * @return int
     */
    public function getResetTime(string $identifier): int
    {
        $key = "rate_limit:{$identifier}";
        return $this->cache->getTtl($key) ?? 0;
    }
}
```

## Optimisations Performance

### Service de Profiling

```php
<?php

namespace App\Service\Performance;

/**
 * Service de profilage des performances
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ProfilingService
{
    private array $timers = [];
    private array $queries = [];
    private int $queryCount = 0;

    public function startTimer(string $name): void
    {
        $this->timers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ];
    }

    public function stopTimer(string $name): float
    {
        if (!isset($this->timers[$name])) {
            return 0.0;
        }
        
        $timer = $this->timers[$name];
        $duration = microtime(true) - $timer['start'];
        $memoryUsed = memory_get_usage(true) - $timer['memory_start'];
        
        $this->timers[$name] = [
            'duration' => $duration,
            'memory_used' => $memoryUsed
        ];
        
        return $duration;
    }

    public function logQuery(string $sql, array $params = [], float $duration = 0.0): void
    {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
            'duration' => $duration,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];
        
        $this->queryCount++;
    }

    /**
     * @return array<string, mixed>
     */
    public function getReport(): array
    {
        $totalQueryTime = array_sum(array_column($this->queries, 'duration'));
        $slowQueries = array_filter($this->queries, fn($q) => $q['duration'] > 0.1);
        
        return [
            'timers' => $this->timers,
            'query_count' => $this->queryCount,
            'total_query_time' => $totalQueryTime,
            'slow_queries' => count($slowQueries),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_current' => memory_get_usage(true)
        ];
    }
}
```

### Optimiseur de requêtes

```php
<?php

namespace App\Service\Performance;

use MulerTech\Database\Query\Builder\QueryBuilder;

/**
 * Service d'optimisation des requêtes
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryOptimizer
{
    /**
     * @param QueryBuilder $qb
     * @return QueryBuilder
     */
    public function optimizeProductListing(QueryBuilder $qb): QueryBuilder
    {
        return $qb
            ->select([
                'p.id',
                'p.name',
                'p.slug',
                'p.price',
                'p.stock_qty',
                'c.name as category_name',
                'b.name as brand_name'
            ])
            ->from('products p')
            ->leftJoin('categories c', 'c.id = p.category_id')
            ->leftJoin('brands b', 'b.id = p.brand_id')
            ->where('p.is_active = 1')
            ->addHint('USE_INDEX', 'idx_products_active_category');
    }

    /**
     * @param QueryBuilder $qb
     * @param int $customerId
     * @return QueryBuilder
     */
    public function optimizeOrderHistory(QueryBuilder $qb, int $customerId): QueryBuilder
    {
        return $qb
            ->select([
                'o.id',
                'o.number',
                'o.status',
                'o.total',
                'o.created_at',
                'COUNT(oi.id) as item_count'
            ])
            ->from('orders o')
            ->leftJoin('order_items oi', 'oi.order_id = o.id')
            ->where('o.customer_id = ?')
            ->groupBy('o.id')
            ->orderBy('o.created_at', 'DESC')
            ->addHint('USE_INDEX', 'idx_orders_customer_created');
    }
}
```

## Sécurité Avancée

### Service de sécurité

```php
<?php

namespace App\Service\Security;

/**
 * Service de sécurité avancée
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SecurityService
{
    /**
     * @param string $input
     * @return string
     */
    public function sanitizeInput(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param string $sql
     * @return bool
     */
    public function detectSqlInjection(string $sql): bool
    {
        $patterns = [
            '/(\bunion\b|\bselect\b|\binsert\b|\bupdate\b|\bdelete\b|\bdrop\b)/i',
            '/(\bor\b|\band\b)\s+\d+\s*=\s*\d+/i',
            '/\'\s*(or|and)\s+\'/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * @param string $password
     * @return bool
     */
    public function isPasswordSecure(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }

    /**
     * @param string $token
     * @return bool
     */
    public function validateCsrfToken(string $token): bool
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        return hash_equals($sessionToken, $token);
    }

    public function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }
}
```

## Monitoring et Observabilité

### Service de monitoring

```php
<?php

namespace App\Service\Monitoring;

/**
 * Service de monitoring et métriques
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MonitoringService
{
    private array $metrics = [];

    public function incrementCounter(string $name, array $tags = []): void
    {
        $key = $this->buildMetricKey($name, $tags);
        $this->metrics[$key] = ($this->metrics[$key] ?? 0) + 1;
    }

    public function recordTiming(string $name, float $duration, array $tags = []): void
    {
        $key = $this->buildMetricKey($name, $tags);
        $this->metrics[$key] = $duration;
    }

    public function recordGauge(string $name, float $value, array $tags = []): void
    {
        $key = $this->buildMetricKey($name, $tags);
        $this->metrics[$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function sendToStatsD(string $host = 'localhost', int $port = 8125): void
    {
        $socket = fsockopen("udp://{$host}", $port);
        
        if ($socket) {
            foreach ($this->metrics as $key => $value) {
                $packet = "{$key}:{$value}|c";
                fwrite($socket, $packet);
            }
            fclose($socket);
        }
    }

    /**
     * @param string $name
     * @param array<string, string> $tags
     * @return string
     */
    private function buildMetricKey(string $name, array $tags): string
    {
        $tagString = '';
        if (!empty($tags)) {
            $tagPairs = [];
            foreach ($tags as $key => $value) {
                $tagPairs[] = "{$key}:{$value}";
            }
            $tagString = ',' . implode(',', $tagPairs);
        }
        
        return $name . $tagString;
    }
}
```

## Conclusion

Cette section présente les fonctionnalités avancées qui transforment une application e-commerce basique en une plateforme robuste et professionnelle. Ces fonctionnalités exploitent pleinement les capacités de MulerTech Database ORM pour :

- **Performance** : Cache intelligent, optimisation de requêtes, profiling
- **Sécurité** : Rate limiting, audit, validation avancée
- **Scalabilité** : Multi-tenant, queue processing, monitoring
- **Expérience utilisateur** : Recommandations, notifications, i18n

Ces implémentations servent de base pour construire des fonctionnalités métier complexes tout en maintenant les performances et la sécurité.

---

**Navigation** :
- **[← Retour au sommaire](README.md)**
- **[← API REST](09-api-endpoints.md)**

Cette documentation complète l'exemple e-commerce en montrant comment implémenter des fonctionnalités de niveau enterprise avec MulerTech Database ORM.
