# Fonctionnalités Avancées - Application Blog

Cette section explore les fonctionnalités avancées de l'application blog, démontrant les capacités sophistiquées de MulerTech Database ORM.

## Table des matières

- [Système de Cache](#système-de-cache)
- [Événements et Listeners](#événements-et-listeners)
- [Système d'Audit](#système-daudit)
- [Optimisations Performance](#optimisations-performance)
- [Soft Delete](#soft-delete)
- [Recherche Full-Text](#recherche-full-text)
- [Versioning des Entités](#versioning-des-entités)
- [Système de Notifications](#système-de-notifications)
- [Queue Processing](#queue-processing)
- [Monitoring et Logging](#monitoring-et-logging)

## Système de Cache

### Configuration du Cache

```php
<?php

namespace App\Service;

use MulerTech\Database\ORM\Cache\CacheInterface;
use MulerTech\Database\ORM\Cache\RedisCache;
use Psr\Cache\CacheItemPoolInterface;

class BlogCacheService
{
    private CacheInterface $cache;
    private CacheItemPoolInterface $pool;
    
    public function __construct(CacheInterface $cache, CacheItemPoolInterface $pool)
    {
        $this->cache = $cache;
        $this->pool = $pool;
    }
    
    public function getPopularPosts(int $limit = 10): array
    {
        $cacheKey = "popular_posts_{$limit}";
        
        $item = $this->pool->getItem($cacheKey);
        
        if ($item->isHit()) {
            return $item->get();
        }
        
        // Requête coûteuse pour récupérer les posts populaires
        $posts = $this->calculatePopularPosts($limit);
        
        $item->set($posts);
        $item->expiresAfter(3600); // 1 heure
        $this->pool->save($item);
        
        return $posts;
    }
    
    public function getCategoryPosts(int $categoryId, int $page = 1): array
    {
        $cacheKey = "category_{$categoryId}_posts_page_{$page}";
        
        return $this->cache->remember($cacheKey, 1800, function() use ($categoryId, $page) {
            return $this->postRepository->findByCategoryPaginated($categoryId, $page);
        });
    }
    
    public function invalidatePostCache(int $postId): void
    {
        $patterns = [
            "post_{$postId}*",
            "popular_posts_*",
            "recent_posts_*",
            "category_*_posts_*"
        ];
        
        foreach ($patterns as $pattern) {
            $this->cache->deleteByPattern($pattern);
        }
    }
    
    private function calculatePopularPosts(int $limit): array
    {
        // Logique complexe pour calculer la popularité
        // (vues, commentaires, likes, partages, etc.)
        return [];
    }
}
```

### Cache au niveau Repository

```php
<?php

namespace App\Repository;

use MulerTech\Database\ORM\Repository\EntityRepository;
use MulerTech\Database\ORM\Cache\CacheableRepositoryTrait;

class PostRepository extends EntityRepository
{
    use CacheableRepositoryTrait;
    
    protected function getCachePrefix(): string
    {
        return 'post_';
    }
    
    protected function getDefaultCacheTtl(): int
    {
        return 1800; // 30 minutes
    }
    
    public function findWithCache(int $id): ?Post
    {
        return $this->cacheQuery("find_{$id}", function() use ($id) {
            return $this->createQueryBuilder()
                ->select('*')
                ->where('id = ?')
                ->setParameter(0, $id)
                ->getQuery()
                ->getSingleResult();
        });
    }
    
    public function getPostStats(int $postId): array
    {
        return $this->cacheQuery("stats_{$postId}", function() use ($postId) {
            return [
                'views' => $this->getViewCount($postId),
                'comments' => $this->getCommentCount($postId),
                'likes' => $this->getLikeCount($postId)
            ];
        }, 300); // Cache pendant 5 minutes
    }
    
    public function findTrendingPosts(int $limit = 10): array
    {
        return $this->cacheQuery("trending_{$limit}", function() use ($limit) {
            return $this->createQueryBuilder()
                ->select('p.*, 
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_comments,
                    (SELECT COUNT(*) FROM post_views pv WHERE pv.post_id = p.id AND pv.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_views'
                )
                ->from('posts', 'p')
                ->where('p.status = ?')
                ->orderBy('(recent_comments * 2 + recent_views)', 'DESC')
                ->limit($limit)
                ->setParameter(0, 'published')
                ->getQuery()
                ->getArrayResult();
        }, 900); // 15 minutes
    }
}
```

## Événements et Listeners

### Définition des Événements

```php
<?php

namespace App\Event;

use App\Entity\Post;
use Symfony\Contracts\EventDispatcher\Event;

class PostCreatedEvent extends Event
{
    public const NAME = 'post.created';
    
    private Post $post;
    private int $userId;
    
    public function __construct(Post $post, int $userId)
    {
        $this->post = $post;
        $this->userId = $userId;
    }
    
    public function getPost(): Post
    {
        return $this->post;
    }
    
    public function getUserId(): int
    {
        return $this->userId;
    }
}

class PostPublishedEvent extends Event
{
    public const NAME = 'post.published';
    
    private Post $post;
    private ?\DateTimeInterface $previousPublishedAt;
    
    public function __construct(Post $post, ?\DateTimeInterface $previousPublishedAt = null)
    {
        $this->post = $post;
        $this->previousPublishedAt = $previousPublishedAt;
    }
    
    public function getPost(): Post
    {
        return $this->post;
    }
    
    public function isFirstPublication(): bool
    {
        return $this->previousPublishedAt === null;
    }
}

class CommentAddedEvent extends Event
{
    public const NAME = 'comment.added';
    
    private Comment $comment;
    private Post $post;
    
    public function __construct(Comment $comment, Post $post)
    {
        $this->comment = $comment;
        $this->post = $post;
    }
    
    public function getComment(): Comment
    {
        return $this->comment;
    }
    
    public function getPost(): Post
    {
        return $this->post;
    }
}
```

### Event Listeners

```php
<?php

namespace App\EventListener;

use App\Event\PostCreatedEvent;
use App\Event\PostPublishedEvent;
use App\Event\CommentAddedEvent;
use App\Service\NotificationService;
use App\Service\CacheService;
use App\Service\SeoService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PostCreatedEvent::NAME)]
class PostEventListener
{
    private NotificationService $notificationService;
    private CacheService $cacheService;
    private SeoService $seoService;
    private LoggerInterface $logger;
    
    public function __construct(
        NotificationService $notificationService,
        CacheService $cacheService,
        SeoService $seoService,
        LoggerInterface $logger
    ) {
        $this->notificationService = $notificationService;
        $this->cacheService = $cacheService;
        $this->seoService = $seoService;
        $this->logger = $logger;
    }
    
    public function onPostCreated(PostCreatedEvent $event): void
    {
        $post = $event->getPost();
        
        // Log de l'événement
        $this->logger->info('Nouveau post créé', [
            'post_id' => $post->getId(),
            'title' => $post->getTitle(),
            'author_id' => $event->getUserId()
        ]);
        
        // Génération automatique du slug si nécessaire
        if (empty($post->getSlug())) {
            $slug = $this->seoService->generateSlug($post->getTitle());
            $post->setSlug($slug);
        }
        
        // Notification aux administrateurs
        if ($post->getStatus() === 'published') {
            $this->notificationService->notifyAdmins('Nouveau post publié', [
                'post' => $post,
                'author_id' => $event->getUserId()
            ]);
        }
    }
    
    public function onPostPublished(PostPublishedEvent $event): void
    {
        $post = $event->getPost();
        
        // Invalidation du cache
        $this->cacheService->invalidatePostCache($post->getId());
        $this->cacheService->invalidate(['recent_posts', 'popular_posts']);
        
        // SEO - génération du sitemap
        $this->seoService->updateSitemap();
        
        // Notifications
        if ($event->isFirstPublication()) {
            // Première publication - notification aux abonnés
            $this->notificationService->notifySubscribers('Nouvel article publié', [
                'post' => $post
            ]);
            
            // Mise à jour des flux RSS
            $this->seoService->updateRssFeeds();
        }
        
        // Analytics
        $this->logger->info('Post publié', [
            'post_id' => $post->getId(),
            'title' => $post->getTitle(),
            'first_publication' => $event->isFirstPublication()
        ]);
    }
}

#[AsEventListener(event: CommentAddedEvent::NAME)]
class CommentEventListener
{
    private NotificationService $notificationService;
    private CacheService $cacheService;
    private LoggerInterface $logger;
    
    public function __construct(
        NotificationService $notificationService,
        CacheService $cacheService,
        LoggerInterface $logger
    ) {
        $this->notificationService = $notificationService;
        $this->cacheService = $cacheService;
        $this->logger = $logger;
    }
    
    public function onCommentAdded(CommentAddedEvent $event): void
    {
        $comment = $event->getComment();
        $post = $event->getPost();
        
        // Invalidation du cache des commentaires
        $this->cacheService->invalidatePostCache($post->getId());
        
        // Notification à l'auteur du post
        $this->notificationService->notifyPostAuthor($post, 'Nouveau commentaire', [
            'comment' => $comment
        ]);
        
        // Notification aux autres commentateurs (opt-in)
        $this->notificationService->notifyOtherCommenters($post, $comment);
        
        // Modération automatique
        if ($this->requiresModeration($comment)) {
            $comment->setStatus('pending');
            $this->notificationService->notifyModerators('Commentaire en attente', [
                'comment' => $comment,
                'post' => $post
            ]);
        }
        
        $this->logger->info('Nouveau commentaire ajouté', [
            'comment_id' => $comment->getId(),
            'post_id' => $post->getId(),
            'author_id' => $comment->getUserId()
        ]);
    }
    
    private function requiresModeration(Comment $comment): bool
    {
        // Logique de modération automatique
        $content = strtolower($comment->getContent());
        
        // Mots-clés suspects
        $suspiciousWords = ['spam', 'viagra', 'casino', 'bitcoin'];
        foreach ($suspiciousWords as $word) {
            if (strpos($content, $word) !== false) {
                return true;
            }
        }
        
        // Trop de liens
        if (substr_count($content, 'http') > 2) {
            return true;
        }
        
        return false;
    }
}
```

### Integration avec ORM Events

```php
<?php

namespace App\EventListener;

use MulerTech\Database\ORM\Events\PrePersistEvent;
use MulerTech\Database\ORM\Events\PostPersistEvent;
use MulerTech\Database\ORM\Events\PreUpdateEvent;
use MulerTech\Database\ORM\Events\PostUpdateEvent;
use App\Entity\Post;
use App\Entity\Comment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class ORMEventListener
{
    private EventDispatcherInterface $eventDispatcher;
    
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }
    
    #[AsEventListener]
    public function onPrePersist(PrePersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof Post) {
            // Auto-génération du slug
            if (empty($entity->getSlug())) {
                $slug = $this->generateSlug($entity->getTitle());
                $entity->setSlug($slug);
            }
            
            // Mise à jour automatique des timestamps
            $entity->setCreatedAt(new \DateTimeImmutable());
            $entity->setUpdatedAt(new \DateTimeImmutable());
        }
        
        if ($entity instanceof Comment) {
            $entity->setCreatedAt(new \DateTimeImmutable());
        }
    }
    
    #[AsEventListener]
    public function onPostPersist(PostPersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof Post) {
            // Déclenchement de l'événement métier
            $this->eventDispatcher->dispatch(
                new PostCreatedEvent($entity, $entity->getUserId()),
                PostCreatedEvent::NAME
            );
        }
        
        if ($entity instanceof Comment) {
            $post = $event->getEntityManager()
                         ->getRepository(Post::class)
                         ->find($entity->getPostId());
            
            if ($post) {
                $this->eventDispatcher->dispatch(
                    new CommentAddedEvent($entity, $post),
                    CommentAddedEvent::NAME
                );
            }
        }
    }
    
    #[AsEventListener]
    public function onPreUpdate(PreUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        $changeSet = $event->getChangeSet();
        
        if ($entity instanceof Post) {
            $entity->setUpdatedAt(new \DateTimeImmutable());
            
            // Détection de la publication
            if (isset($changeSet['status']) 
                && $changeSet['status'][0] !== 'published' 
                && $changeSet['status'][1] === 'published') {
                
                $entity->setPublishedAt(new \DateTimeImmutable());
            }
        }
    }
    
    #[AsEventListener]
    public function onPostUpdate(PostUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        $changeSet = $event->getChangeSet();
        
        if ($entity instanceof Post && isset($changeSet['status'])) {
            $oldStatus = $changeSet['status'][0];
            $newStatus = $changeSet['status'][1];
            
            if ($oldStatus !== 'published' && $newStatus === 'published') {
                $this->eventDispatcher->dispatch(
                    new PostPublishedEvent($entity),
                    PostPublishedEvent::NAME
                );
            }
        }
    }
    
    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        return trim($slug, '-');
    }
}
```

## Système d'Audit

### Entity d'Audit

```php
<?php

namespace App\Entity;

use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;

#[MtEntity(table: 'audit_logs')]
class AuditLog
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(type: 'varchar', length: 100)]
    private string $entityType;
    
    #[MtColumn(name: 'entity_id', type: 'int')]
    private int $entityId;
    
    #[MtColumn(type: 'varchar', length: 20)]
    private string $action; // create, update, delete
    
    #[MtColumn(type: 'json', nullable: true)]
    private ?array $oldValues = null;
    
    #[MtColumn(type: 'json', nullable: true)]
    private ?array $newValues = null;
    
    #[MtColumn(name: 'user_id', type: 'int', nullable: true)]
    private ?int $userId = null;
    
    #[MtColumn(name: 'ip_address', type: 'varchar', length: 45, nullable: true)]
    private ?string $ipAddress = null;
    
    #[MtColumn(name: 'user_agent', type: 'text', nullable: true)]
    private ?string $userAgent = null;
    
    #[MtColumn(name: 'created_at', type: 'timestamp', default: 'CURRENT_TIMESTAMP')]
    private \DateTimeInterface $createdAt;
    
    // Getters et setters...
}
```

### Service d'Audit

```php
<?php

namespace App\Service;

use App\Entity\AuditLog;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\ORM\Events\PostPersistEvent;
use MulerTech\Database\ORM\Events\PostUpdateEvent;
use MulerTech\Database\ORM\Events\PreRemoveEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class AuditService
{
    private EntityManager $em;
    private RequestStack $requestStack;
    private Security $security;
    
    public function __construct(
        EntityManager $em,
        RequestStack $requestStack,
        Security $security
    ) {
        $this->em = $em;
        $this->requestStack = $requestStack;
        $this->security = $security;
    }
    
    #[AsEventListener]
    public function onPostPersist(PostPersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($this->shouldAudit($entity)) {
            $this->createAuditLog($entity, 'create', null, $this->extractEntityData($entity));
        }
    }
    
    #[AsEventListener]
    public function onPostUpdate(PostUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        $changeSet = $event->getChangeSet();
        
        if ($this->shouldAudit($entity)) {
            $oldValues = [];
            $newValues = [];
            
            foreach ($changeSet as $property => $change) {
                $oldValues[$property] = $change[0];
                $newValues[$property] = $change[1];
            }
            
            $this->createAuditLog($entity, 'update', $oldValues, $newValues);
        }
    }
    
    #[AsEventListener]
    public function onPreRemove(PreRemoveEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($this->shouldAudit($entity)) {
            $this->createAuditLog($entity, 'delete', $this->extractEntityData($entity), null);
        }
    }
    
    private function createAuditLog(
        object $entity,
        string $action,
        ?array $oldValues,
        ?array $newValues
    ): void {
        $request = $this->requestStack->getCurrentRequest();
        $user = $this->security->getUser();
        
        $audit = new AuditLog();
        $audit->setEntityType(get_class($entity))
              ->setEntityId($this->getEntityId($entity))
              ->setAction($action)
              ->setOldValues($oldValues)
              ->setNewValues($newValues)
              ->setUserId($user ? $user->getId() : null)
              ->setIpAddress($request ? $request->getClientIp() : null)
              ->setUserAgent($request ? $request->headers->get('User-Agent') : null)
              ->setCreatedAt(new \DateTimeImmutable());
        
        $this->em->persist($audit);
    }
    
    private function shouldAudit(object $entity): bool
    {
        // Définir quelles entités doivent être auditées
        return $entity instanceof Post 
            || $entity instanceof Comment 
            || $entity instanceof User
            || $entity instanceof Category;
    }
    
    private function extractEntityData(object $entity): array
    {
        // Extraire les données de l'entité pour l'audit
        $reflection = new \ReflectionClass($entity);
        $data = [];
        
        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($entity);
            
            // Sérialiser les valeurs complexes
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif (is_object($value)) {
                $value = get_class($value) . ':' . ($value->getId() ?? 'no-id');
            }
            
            $data[$property->getName()] = $value;
        }
        
        return $data;
    }
    
    private function getEntityId(object $entity): int
    {
        $reflection = new \ReflectionClass($entity);
        
        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(\MulerTech\Database\ORM\Attribute\MtColumn::class);
            
            foreach ($attributes as $attribute) {
                $columnConfig = $attribute->newInstance();
                if ($columnConfig->primaryKey) {
                    $property->setAccessible(true);
                    return $property->getValue($entity);
                }
            }
        }
        
        throw new \RuntimeException('No primary key found for entity ' . get_class($entity));
    }
}
```

## Optimisations Performance

### Stratégies de Chargement

```php
<?php

namespace App\Service;

use App\Repository\PostRepository;
use MulerTech\Database\ORM\EntityManager;

class PerformanceOptimizedPostService
{
    private PostRepository $postRepository;
    private EntityManager $em;
    
    public function __construct(PostRepository $postRepository, EntityManager $em)
    {
        $this->postRepository = $postRepository;
        $this->em = $em;
    }
    
    public function getPostsWithOptimizedLoading(int $page = 1, int $limit = 10): array
    {
        // Stratégie 1: Chargement eager avec JOIN
        $posts = $this->postRepository->createQueryBuilder()
            ->select('p, u, c, t')
            ->from('posts', 'p')
            ->leftJoin('users', 'u', 'u.id = p.user_id')
            ->leftJoin('categories', 'c', 'c.id = p.category_id')
            ->leftJoin('post_tags', 'pt', 'pt.post_id = p.id')
            ->leftJoin('tags', 't', 't.id = pt.tag_id')
            ->where('p.status = ?')
            ->orderBy('p.created_at', 'DESC')
            ->limit($limit)
            ->offset(($page - 1) * $limit)
            ->setParameter(0, 'published')
            ->getQuery()
            ->getResult();
        
        return $posts;
    }
    
    public function getBatchPostsWithStats(array $postIds): array
    {
        // Stratégie 2: Batch loading pour éviter N+1
        $posts = $this->postRepository->findByIds($postIds);
        
        // Charger tous les stats en une seule requête
        $stats = $this->em->createQueryBuilder()
            ->select('
                p.id,
                COUNT(DISTINCT c.id) as comment_count,
                COUNT(DISTINCT pv.id) as view_count,
                COUNT(DISTINCT pl.id) as like_count
            ')
            ->from('posts', 'p')
            ->leftJoin('comments', 'c', 'c.post_id = p.id AND c.status = "published"')
            ->leftJoin('post_views', 'pv', 'pv.post_id = p.id')
            ->leftJoin('post_likes', 'pl', 'pl.post_id = p.id')
            ->where('p.id IN (?)')
            ->groupBy('p.id')
            ->setParameter(0, $postIds)
            ->getQuery()
            ->getArrayResult();
        
        // Associer les stats aux posts
        $statsById = array_column($stats, null, 'id');
        
        foreach ($posts as $post) {
            $postStats = $statsById[$post->getId()] ?? [];
            $post->setStats($postStats);
        }
        
        return $posts;
    }
    
    public function getPostsWithProjection(array $fields = null): array
    {
        // Stratégie 3: Projection pour limiter les données
        $fields = $fields ?? ['id', 'title', 'slug', 'excerpt', 'created_at', 'user.name', 'category.name'];
        
        $qb = $this->em->createQueryBuilder();
        
        // Construction dynamique de la projection
        $select = [];
        $joins = [];
        
        foreach ($fields as $field) {
            if (strpos($field, '.') !== false) {
                [$relation, $relationField] = explode('.', $field, 2);
                $joins[$relation] = true;
                $select[] = "{$relation}.{$relationField} as {$relation}_{$relationField}";
            } else {
                $select[] = "p.{$field}";
            }
        }
        
        $qb->select(implode(', ', $select))
           ->from('posts', 'p');
        
        if (isset($joins['user'])) {
            $qb->leftJoin('users', 'user', 'user.id = p.user_id');
        }
        
        if (isset($joins['category'])) {
            $qb->leftJoin('categories', 'category', 'category.id = p.category_id');
        }
        
        return $qb->getQuery()->getArrayResult();
    }
}
```

### Connection Pooling et Optimisations

```php
<?php

namespace App\Service;

use MulerTech\Database\Connection\ConnectionPool;
use MulerTech\Database\ORM\EntityManager;

class DatabaseOptimizationService
{
    private ConnectionPool $connectionPool;
    private EntityManager $em;
    
    public function __construct(ConnectionPool $connectionPool, EntityManager $em)
    {
        $this->connectionPool = $connectionPool;
        $this->em = $em;
    }
    
    public function optimizeForReading(): void
    {
        // Configuration optimisée pour la lecture
        $readConnection = $this->connectionPool->getReadConnection();
        
        $readConnection->exec('SET SESSION query_cache_type = ON');
        $readConnection->exec('SET SESSION query_cache_size = 67108864'); // 64MB
        $readConnection->exec('SET SESSION read_buffer_size = 2097152'); // 2MB
        $readConnection->exec('SET SESSION sort_buffer_size = 4194304'); // 4MB
    }
    
    public function batchInsertOptimized(array $entities): void
    {
        $writeConnection = $this->connectionPool->getWriteConnection();
        
        // Désactiver l'autocommit pour les insertions en lot
        $writeConnection->exec('SET SESSION autocommit = 0');
        $writeConnection->exec('SET SESSION bulk_insert_buffer_size = 67108864'); // 64MB
        $writeConnection->exec('SET SESSION max_allowed_packet = 1073741824'); // 1GB
        
        try {
            $writeConnection->beginTransaction();
            
            // Insertion par batch de 1000
            $batch = [];
            $batchSize = 1000;
            
            foreach ($entities as $entity) {
                $batch[] = $entity;
                
                if (count($batch) >= $batchSize) {
                    $this->insertBatch($batch);
                    $batch = [];
                }
            }
            
            // Insérer le dernier batch
            if (!empty($batch)) {
                $this->insertBatch($batch);
            }
            
            $writeConnection->commit();
        } catch (\Exception $e) {
            $writeConnection->rollback();
            throw $e;
        } finally {
            // Restaurer l'autocommit
            $writeConnection->exec('SET SESSION autocommit = 1');
        }
    }
    
    private function insertBatch(array $entities): void
    {
        if (empty($entities)) {
            return;
        }
        
        // Construire une requête INSERT en lot
        $entityClass = get_class($entities[0]);
        $metadata = $this->em->getClassMetadata($entityClass);
        
        $columns = array_keys($metadata->getColumnMapping());
        $placeholders = [];
        $values = [];
        
        foreach ($entities as $entity) {
            $entityValues = [];
            foreach ($columns as $column) {
                $value = $metadata->getFieldValue($entity, $column);
                $entityValues[] = $value;
                $values[] = $value;
            }
            $placeholders[] = '(' . str_repeat('?,', count($columns) - 1) . '?)';
        }
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $metadata->getTableName(),
            implode(',', $columns),
            implode(',', $placeholders)
        );
        
        $this->em->getConnection()->prepare($sql)->execute($values);
    }
}
```

## Soft Delete

### Trait Soft Deletable

```php
<?php

namespace App\Trait;

use MulerTech\Database\ORM\Attribute\MtColumn;

trait SoftDeletableTrait
{
    #[MtColumn(name: 'deleted_at', type: 'timestamp', nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;
    
    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }
    
    public function setDeletedAt(?\DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }
    
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
    
    public function softDelete(): self
    {
        $this->deletedAt = new \DateTimeImmutable();
        return $this;
    }
    
    public function restore(): self
    {
        $this->deletedAt = null;
        return $this;
    }
}
```

### Repository avec Soft Delete

```php
<?php

namespace App\Repository;

use MulerTech\Database\ORM\Repository\EntityRepository;
use MulerTech\Database\Query\QueryBuilder;

class SoftDeletableRepository extends EntityRepository
{
    protected function createQueryBuilder(): QueryBuilder
    {
        return parent::createQueryBuilder()
                    ->where('deleted_at IS NULL');
    }
    
    public function findWithDeleted(): array
    {
        return $this->em->createQueryBuilder()
                       ->select('*')
                       ->from($this->getTableName())
                       ->getQuery()
                       ->getResult();
    }
    
    public function findOnlyDeleted(): array
    {
        return $this->em->createQueryBuilder()
                       ->select('*')
                       ->from($this->getTableName())
                       ->where('deleted_at IS NOT NULL')
                       ->getQuery()
                       ->getResult();
    }
    
    public function forceDelete(object $entity): void
    {
        // Suppression physique définitive
        $this->em->remove($entity);
        $this->em->flush();
    }
    
    public function restore(object $entity): void
    {
        if (method_exists($entity, 'restore')) {
            $entity->restore();
            $this->em->flush();
        }
    }
}
```

## Recherche Full-Text

### Configuration de la recherche

```php
<?php

namespace App\Service;

use MulerTech\Database\ORM\EntityManager;

class FullTextSearchService
{
    private EntityManager $em;
    
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }
    
    public function searchPosts(string $query, array $filters = []): array
    {
        $qb = $this->em->createQueryBuilder();
        
        // Recherche full-text avec scoring
        $qb->select('
                p.*,
                u.name as author_name,
                c.name as category_name,
                MATCH(p.title, p.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance_score
            ')
           ->from('posts', 'p')
           ->leftJoin('users', 'u', 'u.id = p.user_id')
           ->leftJoin('categories', 'c', 'c.id = p.category_id')
           ->where('MATCH(p.title, p.content) AGAINST(? IN NATURAL LANGUAGE MODE)')
           ->andWhere('p.status = ?')
           ->orderBy('relevance_score', 'DESC')
           ->addOrderBy('p.created_at', 'DESC')
           ->setParameter(0, $query)
           ->setParameter(1, $query)
           ->setParameter(2, 'published');
        
        // Filtres additionnels
        if (isset($filters['category_id'])) {
            $qb->andWhere('p.category_id = ?')
               ->setParameter(3, $filters['category_id']);
        }
        
        if (isset($filters['date_from'])) {
            $qb->andWhere('p.created_at >= ?')
               ->setParameter(4, $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $qb->andWhere('p.created_at <= ?')
               ->setParameter(5, $filters['date_to']);
        }
        
        return $qb->getQuery()->getArrayResult();
    }
    
    public function searchWithHighlighting(string $query): array
    {
        // Recherche avec mise en surbrillance des termes
        $results = $this->searchPosts($query);
        
        foreach ($results as &$result) {
            $result['highlighted_title'] = $this->highlightText($result['title'], $query);
            $result['highlighted_content'] = $this->highlightText(
                $this->extractExcerpt($result['content'], $query), 
                $query
            );
        }
        
        return $results;
    }
    
    public function getSuggestions(string $query): array
    {
        // Suggestions de recherche basées sur les termes populaires
        $suggestions = $this->em->createQueryBuilder()
            ->select('
                DISTINCT SUBSTRING_INDEX(
                    SUBSTRING_INDEX(title, " ", numbers.n), 
                    " ", 
                    -1
                ) as word,
                COUNT(*) as frequency
            ')
            ->from('posts', 'p')
            ->crossJoin('(
                SELECT 1 as n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
            ) numbers')
            ->where('LENGTH(title) - LENGTH(REPLACE(title, " ", "")) >= numbers.n - 1')
            ->andWhere('SUBSTRING_INDEX(SUBSTRING_INDEX(title, " ", numbers.n), " ", -1) LIKE ?')
            ->andWhere('p.status = ?')
            ->groupBy('word')
            ->orderBy('frequency', 'DESC')
            ->limit(10)
            ->setParameter(0, $query . '%')
            ->setParameter(1, 'published')
            ->getQuery()
            ->getArrayResult();
        
        return array_column($suggestions, 'word');
    }
    
    private function highlightText(string $text, string $query): string
    {
        $words = explode(' ', $query);
        
        foreach ($words as $word) {
            if (strlen(trim($word)) > 2) {
                $text = preg_replace(
                    '/\b(' . preg_quote($word, '/') . ')\b/i',
                    '<mark>$1</mark>',
                    $text
                );
            }
        }
        
        return $text;
    }
    
    private function extractExcerpt(string $content, string $query, int $maxLength = 300): string
    {
        $words = explode(' ', strtolower($query));
        $content = strip_tags($content);
        
        // Trouver la position du premier mot de la requête
        $firstWordPos = false;
        foreach ($words as $word) {
            $pos = stripos($content, $word);
            if ($pos !== false && ($firstWordPos === false || $pos < $firstWordPos)) {
                $firstWordPos = $pos;
            }
        }
        
        if ($firstWordPos === false) {
            // Aucun mot trouvé, retourner le début
            return substr($content, 0, $maxLength) . '...';
        }
        
        // Extraire un extrait centré sur le premier mot trouvé
        $start = max(0, $firstWordPos - $maxLength / 2);
        $excerpt = substr($content, $start, $maxLength);
        
        if ($start > 0) {
            $excerpt = '...' . $excerpt;
        }
        
        if (strlen($content) > $start + $maxLength) {
            $excerpt .= '...';
        }
        
        return $excerpt;
    }
}
```

## Versioning des Entités

### Entity de Version

```php
<?php

namespace App\Entity;

use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;

#[MtEntity(table: 'post_versions')]
class PostVersion
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(name: 'post_id', type: 'int')]
    private int $postId;
    
    #[MtColumn(name: 'version_number', type: 'int')]
    private int $versionNumber;
    
    #[MtColumn(type: 'varchar', length: 255)]
    private string $title;
    
    #[MtColumn(type: 'text')]
    private string $content;
    
    #[MtColumn(type: 'varchar', length: 20)]
    private string $status;
    
    #[MtColumn(name: 'created_by', type: 'int')]
    private int $createdBy;
    
    #[MtColumn(name: 'created_at', type: 'timestamp', default: 'CURRENT_TIMESTAMP')]
    private \DateTimeInterface $createdAt;
    
    #[MtColumn(type: 'text', nullable: true)]
    private ?string $changeNotes = null;
    
    // Getters et setters...
}
```

### Service de Versioning

```php
<?php

namespace App\Service;

use App\Entity\Post;
use App\Entity\PostVersion;
use MulerTech\Database\ORM\EntityManager;

class PostVersioningService
{
    private EntityManager $em;
    
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }
    
    public function createVersion(Post $post, int $userId, ?string $changeNotes = null): PostVersion
    {
        $lastVersion = $this->getLastVersion($post->getId());
        $versionNumber = $lastVersion ? $lastVersion->getVersionNumber() + 1 : 1;
        
        $version = new PostVersion();
        $version->setPostId($post->getId())
                ->setVersionNumber($versionNumber)
                ->setTitle($post->getTitle())
                ->setContent($post->getContent())
                ->setStatus($post->getStatus())
                ->setCreatedBy($userId)
                ->setCreatedAt(new \DateTimeImmutable())
                ->setChangeNotes($changeNotes);
        
        $this->em->persist($version);
        $this->em->flush();
        
        return $version;
    }
    
    public function getVersions(int $postId): array
    {
        return $this->em->getRepository(PostVersion::class)
                       ->createQueryBuilder()
                       ->select('*')
                       ->where('post_id = ?')
                       ->orderBy('version_number', 'DESC')
                       ->setParameter(0, $postId)
                       ->getQuery()
                       ->getResult();
    }
    
    public function revertToVersion(Post $post, int $versionNumber, int $userId): void
    {
        $version = $this->em->getRepository(PostVersion::class)
                           ->createQueryBuilder()
                           ->select('*')
                           ->where('post_id = ? AND version_number = ?')
                           ->setParameter(0, $post->getId())
                           ->setParameter(1, $versionNumber)
                           ->getQuery()
                           ->getSingleResult();
        
        if (!$version) {
            throw new \InvalidArgumentException("Version {$versionNumber} not found");
        }
        
        // Sauvegarder l'état actuel avant de revenir
        $this->createVersion($post, $userId, "Revert to version {$versionNumber}");
        
        // Appliquer les changements de la version
        $post->setTitle($version->getTitle())
             ->setContent($version->getContent())
             ->setStatus($version->getStatus());
        
        $this->em->flush();
    }
    
    public function compareVersions(int $postId, int $version1, int $version2): array
    {
        $versions = $this->em->getRepository(PostVersion::class)
                            ->createQueryBuilder()
                            ->select('*')
                            ->where('post_id = ? AND version_number IN (?, ?)')
                            ->setParameter(0, $postId)
                            ->setParameter(1, $version1)
                            ->setParameter(2, $version2)
                            ->getQuery()
                            ->getResult();
        
        if (count($versions) !== 2) {
            throw new \InvalidArgumentException("One or both versions not found");
        }
        
        $v1 = $versions[0]->getVersionNumber() === $version1 ? $versions[0] : $versions[1];
        $v2 = $versions[0]->getVersionNumber() === $version2 ? $versions[0] : $versions[1];
        
        return [
            'version1' => $v1,
            'version2' => $v2,
            'title_diff' => $this->generateDiff($v1->getTitle(), $v2->getTitle()),
            'content_diff' => $this->generateDiff($v1->getContent(), $v2->getContent())
        ];
    }
    
    private function getLastVersion(int $postId): ?PostVersion
    {
        return $this->em->getRepository(PostVersion::class)
                       ->createQueryBuilder()
                       ->select('*')
                       ->where('post_id = ?')
                       ->orderBy('version_number', 'DESC')
                       ->limit(1)
                       ->setParameter(0, $postId)
                       ->getQuery()
                       ->getSingleResult();
    }
    
    private function generateDiff(string $text1, string $text2): array
    {
        // Implémentation simple de diff
        $lines1 = explode("\n", $text1);
        $lines2 = explode("\n", $text2);
        
        $diff = [];
        $maxLines = max(count($lines1), count($lines2));
        
        for ($i = 0; $i < $maxLines; $i++) {
            $line1 = $lines1[$i] ?? '';
            $line2 = $lines2[$i] ?? '';
            
            if ($line1 !== $line2) {
                $diff[] = [
                    'line' => $i + 1,
                    'old' => $line1,
                    'new' => $line2,
                    'type' => empty($line1) ? 'added' : (empty($line2) ? 'removed' : 'changed')
                ];
            }
        }
        
        return $diff;
    }
}
```

## Système de Notifications

### Service de Notifications

```php
<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Post;
use App\Entity\Comment;

class NotificationService
{
    private EmailService $emailService;
    private PushNotificationService $pushService;
    private EntityManager $em;
    
    public function __construct(
        EmailService $emailService,
        PushNotificationService $pushService,
        EntityManager $em
    ) {
        $this->emailService = $emailService;
        $this->pushService = $pushService;
        $this->em = $em;
    }
    
    public function notifyPostAuthor(Post $post, string $message, array $data = []): void
    {
        $author = $this->em->getRepository(User::class)->find($post->getUserId());
        
        if (!$author) {
            return;
        }
        
        // Vérifier les préférences de notification
        $preferences = $this->getUserNotificationPreferences($author->getId());
        
        if ($preferences['email_comments'] ?? false) {
            $this->emailService->send(
                $author->getEmail(),
                $message,
                'notification/comment_added',
                array_merge($data, ['user' => $author, 'post' => $post])
            );
        }
        
        if ($preferences['push_comments'] ?? false) {
            $this->pushService->send($author->getId(), $message, $data);
        }
        
        // Notification in-app
        $this->createInAppNotification($author->getId(), $message, $data);
    }
    
    public function notifySubscribers(string $message, array $data = []): void
    {
        $post = $data['post'] ?? null;
        
        if (!$post) {
            return;
        }
        
        // Récupérer les abonnés
        $subscribers = $this->getPostSubscribers($post);
        
        // Traitement par batch pour éviter la surcharge
        $batches = array_chunk($subscribers, 100);
        
        foreach ($batches as $batch) {
            foreach ($batch as $subscriber) {
                $preferences = $this->getUserNotificationPreferences($subscriber['id']);
                
                if ($preferences['email_new_posts'] ?? false) {
                    $this->emailService->queue(
                        $subscriber['email'],
                        $message,
                        'notification/new_post',
                        array_merge($data, ['user' => $subscriber])
                    );
                }
                
                if ($preferences['push_new_posts'] ?? false) {
                    $this->pushService->queue($subscriber['id'], $message, $data);
                }
            }
        }
    }
    
    private function createInAppNotification(int $userId, string $message, array $data): void
    {
        $notification = [
            'user_id' => $userId,
            'message' => $message,
            'data' => json_encode($data),
            'type' => $data['type'] ?? 'general',
            'read' => false,
            'created_at' => new \DateTimeImmutable()
        ];
        
        $this->em->createQueryBuilder()
                 ->insert('notifications')
                 ->values($notification)
                 ->getQuery()
                 ->execute();
    }
    
    private function getUserNotificationPreferences(int $userId): array
    {
        $preferences = $this->em->createQueryBuilder()
                               ->select('preferences')
                               ->from('user_notification_preferences')
                               ->where('user_id = ?')
                               ->setParameter(0, $userId)
                               ->getQuery()
                               ->getSingleScalarResult();
        
        return $preferences ? json_decode($preferences, true) : [];
    }
    
    private function getPostSubscribers(Post $post): array
    {
        // Récupérer les utilisateurs abonnés aux nouvelles publications
        return $this->em->createQueryBuilder()
                       ->select('u.id, u.email, u.name')
                       ->from('users', 'u')
                       ->innerJoin('user_subscriptions', 'us', 'us.user_id = u.id')
                       ->where('us.type = ? AND us.is_active = ?')
                       ->andWhere('u.id != ?') // Exclure l'auteur
                       ->setParameter(0, 'new_posts')
                       ->setParameter(1, true)
                       ->setParameter(2, $post->getUserId())
                       ->getQuery()
                       ->getArrayResult();
    }
}
```

---

Ces fonctionnalités avancées démontrent la puissance et la flexibilité de MulerTech Database ORM dans un contexte d'application réelle. Elles couvrent les aspects critiques de performance, sécurité, maintenabilité et expérience utilisateur nécessaires pour une application de production.
