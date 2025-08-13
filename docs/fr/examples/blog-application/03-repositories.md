# Repositories Personnalisés

Ce chapitre présente les repositories personnalisés avec leurs requêtes métier optimisées et leurs méthodes spécialisées.

## Table des Matières
- [Repository de base](#repository-de-base)
- [UserRepository](#userrepository)
- [PostRepository](#postrepository)
- [CategoryRepository](#categoryrepository)
- [TagRepository](#tagrepository)
- [CommentRepository](#commentrepository)
- [Patterns et optimisations](#patterns-et-optimisations)

## Repository de base

### AbstractRepository

```php
<?php

declare(strict_types=1);

namespace Blog\Repository;

use MulerTech\Database\EntityManager;
use MulerTech\Database\Repository\EntityRepository;
use MulerTech\Database\Query\QueryBuilder;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 *
 * @template T of object
 * @extends EntityRepository<T>
 */
abstract class AbstractRepository extends EntityRepository
{
    protected EntityManager $entityManager;

    /**
     * @param EntityManager $entityManager
     * @param class-string<T> $entityClass
     */
    public function __construct(EntityManager $entityManager, string $entityClass)
    {
        parent::__construct($entityManager, $entityClass);
        $this->entityManager = $entityManager;
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string>|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return array<T>
     */
    public function findByCriteria(
        array $criteria = [],
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $qb = $this->createQueryBuilder('e');

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $qb->andWhere("e.{$field} IN (:{$field})")
                   ->setParameter($field, $value);
            } else {
                $qb->andWhere("e.{$field} = :{$field}")
                   ->setParameter($field, $value);
            }
        }

        if ($orderBy) {
            foreach ($orderBy as $field => $direction) {
                $qb->addOrderBy("e.{$field}", $direction);
            }
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        if ($offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<string, mixed> $criteria
     * @return int
     */
    public function countByCriteria(array $criteria = []): int
    {
        $qb = $this->createQueryBuilder('e');
        $qb->select('COUNT(e.id)');

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $qb->andWhere("e.{$field} IN (:{$field})")
                   ->setParameter($field, $value);
            } else {
                $qb->andWhere("e.{$field} = :{$field}")
                   ->setParameter($field, $value);
            }
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param int $page
     * @param int $perPage
     * @param array<string, mixed> $criteria
     * @param array<string, string>|null $orderBy
     * @return array{data: array<T>, pagination: array<string, mixed>}
     */
    public function findPaginated(
        int $page = 1,
        int $perPage = 20,
        array $criteria = [],
        ?array $orderBy = null
    ): array {
        $offset = ($page - 1) * $perPage;
        
        $data = $this->findByCriteria($criteria, $orderBy, $perPage, $offset);
        $total = $this->countByCriteria($criteria);
        
        return [
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
                'has_next' => $page < ceil($total / $perPage),
                'has_prev' => $page > 1,
            ]
        ];
    }

    /**
     * @param array<string> $fields
     * @param string $term
     * @return QueryBuilder
     */
    protected function addSearchConditions(array $fields, string $term): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e');
        
        if (empty($fields) || empty($term)) {
            return $qb;
        }

        $conditions = [];
        foreach ($fields as $field) {
            $conditions[] = "e.{$field} LIKE :search_term";
        }

        $qb->where(implode(' OR ', $conditions))
           ->setParameter('search_term', "%{$term}%");

        return $qb;
    }

    /**
     * @param \DateTimeInterface|null $from
     * @param \DateTimeInterface|null $to
     * @param string $field
     * @return QueryBuilder
     */
    protected function addDateRangeFilter(
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
        string $field = 'createdAt'
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('e');

        if ($from) {
            $qb->andWhere("e.{$field} >= :from")
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere("e.{$field} <= :to")
               ->setParameter('to', $to);
        }

        return $qb;
    }
}
```

## UserRepository

```php
<?php

declare(strict_types=1);

namespace Blog\Repository;

use Blog\Entity\User;
use Blog\ValueObject\Email;
use Blog\Enum\UserRole;
use Blog\Enum\UserStatus;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 *
 * @extends AbstractRepository<User>
 */
class UserRepository extends AbstractRepository
{
    public function __construct(\MulerTech\Database\EntityManager $entityManager)
    {
        parent::__construct($entityManager, User::class);
    }

    /**
     * @param Email $email
     * @return User|null
     */
    public function findByEmail(Email $email): ?User
    {
        return $this->findOneBy(['email' => $email->getValue()]);
    }

    /**
     * @param string $email
     * @return User|null
     */
    public function findByEmailString(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * @return array<User>
     */
    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.status = :status')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('status', UserStatus::ACTIVE)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param UserRole $role
     * @return array<User>
     */
    public function findByRole(UserRole $role): array
    {
        return $this->findBy(['role' => $role]);
    }

    /**
     * @return array<User>
     */
    public function findAdministrators(): array
    {
        return $this->findByRole(UserRole::ADMIN);
    }

    /**
     * @param int $days
     * @return array<User>
     */
    public function findRecentlyJoined(int $days = 30): array
    {
        $date = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('u')
            ->where('u.createdAt >= :date')
            ->andWhere('u.status = :status')
            ->setParameter('date', $date)
            ->setParameter('status', UserStatus::ACTIVE)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param \DateTimeInterface $date
     * @return array<User>
     */
    public function findInactiveUsers(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.lastLoginAt < :date OR u.lastLoginAt IS NULL')
            ->andWhere('u.createdAt < :date')
            ->andWhere('u.status = :status')
            ->setParameter('date', $date)
            ->setParameter('status', UserStatus::ACTIVE)
            ->orderBy('u.lastLoginAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $term
     * @return array<User>
     */
    public function searchUsers(string $term): array
    {
        return $this->addSearchConditions(['firstName', 'lastName', 'email'], $term)
            ->andWhere('e.status = :status')
            ->andWhere('e.deletedAt IS NULL')
            ->setParameter('status', UserStatus::ACTIVE)
            ->orderBy('e.firstName', 'ASC')
            ->addOrderBy('e.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, int>
     */
    public function getUserStatistics(): array
    {
        $qb = $this->createQueryBuilder('u');

        $total = (int) $qb->select('COUNT(u.id)')
            ->where('u.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $active = (int) $qb->select('COUNT(u.id)')
            ->where('u.status = :status')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('status', UserStatus::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        $recentlyJoined = (int) $qb->select('COUNT(u.id)')
            ->where('u.createdAt >= :date')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('date', new \DateTime('-30 days'))
            ->getQuery()
            ->getSingleScalarResult();

        $withPosts = (int) $qb->select('COUNT(DISTINCT u.id)')
            ->join('u.posts', 'p')
            ->where('u.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'recently_joined' => $recentlyJoined,
            'with_posts' => $withPosts,
            'activity_rate' => $total > 0 ? round(($active / $total) * 100, 2) : 0,
        ];
    }

    /**
     * @param int $limit
     * @return array<User>
     */
    public function findTopAuthors(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->select('u', 'COUNT(p.id) as post_count')
            ->join('u.posts', 'p')
            ->where('u.status = :status')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('p.status = :post_status')
            ->andWhere('p.deletedAt IS NULL')
            ->groupBy('u.id')
            ->having('post_count > 0')
            ->orderBy('post_count', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('status', UserStatus::ACTIVE)
            ->setParameter('post_status', \Blog\Enum\PostStatus::PUBLISHED)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $domain
     * @return array<User>
     */
    public function findByEmailDomain(string $domain): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.email LIKE :domain')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('domain', "%@{$domain}")
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $inactiveDays
     * @return int
     */
    public function archiveInactiveUsers(int $inactiveDays = 365): int
    {
        $cutoffDate = new \DateTime("-{$inactiveDays} days");

        return $this->createQueryBuilder('u')
            ->update()
            ->set('u.status', ':archived_status')
            ->where('u.lastLoginAt < :cutoff OR u.lastLoginAt IS NULL')
            ->andWhere('u.createdAt < :cutoff')
            ->andWhere('u.status = :active_status')
            ->setParameter('archived_status', UserStatus::INACTIVE)
            ->setParameter('active_status', UserStatus::ACTIVE)
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();
    }
}
```

## PostRepository

```php
<?php

declare(strict_types=1);

namespace Blog\Repository;

use Blog\Entity\Post;
use Blog\Entity\User;
use Blog\Entity\Category;
use Blog\Entity\Tag;
use Blog\Enum\PostStatus;
use Blog\ValueObject\Slug;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 *
 * @extends AbstractRepository<Post>
 */
class PostRepository extends AbstractRepository
{
    public function __construct(\MulerTech\Database\EntityManager $entityManager)
    {
        parent::__construct($entityManager, Post::class);
    }

    /**
     * @param Slug $slug
     * @return Post|null
     */
    public function findBySlug(Slug $slug): ?Post
    {
        return $this->findOneBy(['slug' => $slug->getValue()]);
    }

    /**
     * @param string $slug
     * @return Post|null
     */
    public function findPublishedBySlug(string $slug): ?Post
    {
        return $this->createQueryBuilder('p')
            ->where('p.slug = :slug')
            ->andWhere('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('slug', $slug)
            ->setParameter('status', PostStatus::PUBLISHED)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param int $limit
     * @return array<Post>
     */
    public function findLatestPublished(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('status', PostStatus::PUBLISHED)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * @param User $author
     * @return array<Post>
     */
    public function findByAuthor(User $author): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.author = :author')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.createdAt', 'DESC')
            ->setParameter('author', $author)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param User $author
     * @return array<Post>
     */
    public function findPublishedByAuthor(User $author): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.author = :author')
            ->andWhere('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.publishedAt', 'DESC')
            ->setParameter('author', $author)
            ->setParameter('status', PostStatus::PUBLISHED)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Category $category
     * @return array<Post>
     */
    public function findByCategory(Category $category): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.category = :category')
            ->andWhere('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.publishedAt', 'DESC')
            ->setParameter('category', $category)
            ->setParameter('status', PostStatus::PUBLISHED)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Tag $tag
     * @return array<Post>
     */
    public function findByTag(Tag $tag): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.tags', 't')
            ->where('t = :tag')
            ->andWhere('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.publishedAt', 'DESC')
            ->setParameter('tag', $tag)
            ->setParameter('status', PostStatus::PUBLISHED)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $term
     * @return array<Post>
     */
    public function searchPublished(string $term): array
    {
        return $this->addSearchConditions(['title', 'excerpt', 'content'], $term)
            ->andWhere('e.status = :status')
            ->andWhere('e.publishedAt <= :now')
            ->andWhere('e.deletedAt IS NULL')
            ->orderBy('e.publishedAt', 'DESC')
            ->setParameter('status', PostStatus::PUBLISHED)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $limit
     * @return array<Post>
     */
    public function findMostViewed(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.viewCount', 'DESC')
            ->addOrderBy('p.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('status', PostStatus::PUBLISHED)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $limit
     * @return array<Post>
     */
    public function findMostCommented(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->select('p', 'COUNT(c.id) as comment_count')
            ->leftJoin('p.comments', 'c', 'WITH', 'c.status = :comment_status')
            ->where('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->andWhere('p.deletedAt IS NULL')
            ->groupBy('p.id')
            ->orderBy('comment_count', 'DESC')
            ->addOrderBy('p.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('status', PostStatus::PUBLISHED)
            ->setParameter('comment_status', \Blog\Enum\CommentStatus::APPROVED)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * @param \DateTimeInterface $from
     * @param \DateTimeInterface $to
     * @return array<Post>
     */
    public function findPublishedBetween(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.publishedAt BETWEEN :from AND :to')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.publishedAt', 'DESC')
            ->setParameter('status', PostStatus::PUBLISHED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Post>
     */
    public function findFeaturedPosts(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.featuredImage IS NOT NULL')
            ->orderBy('p.publishedAt', 'DESC')
            ->setMaxResults(5)
            ->setParameter('status', PostStatus::PUBLISHED)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPostStatistics(): array
    {
        $qb = $this->createQueryBuilder('p');

        $total = (int) $qb->select('COUNT(p.id)')
            ->where('p.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $published = (int) $qb->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('status', PostStatus::PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult();

        $drafts = (int) $qb->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('status', PostStatus::DRAFT)
            ->getQuery()
            ->getSingleScalarResult();

        $totalViews = (int) $qb->select('SUM(p.viewCount)')
            ->where('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('status', PostStatus::PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult();

        $thisMonth = (int) $qb->select('COUNT(p.id)')
            ->where('p.publishedAt >= :start_of_month')
            ->andWhere('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('start_of_month', new \DateTime('first day of this month'))
            ->setParameter('status', PostStatus::PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'published' => $published,
            'drafts' => $drafts,
            'archived' => $total - $published - $drafts,
            'total_views' => $totalViews,
            'average_views' => $published > 0 ? round($totalViews / $published, 2) : 0,
            'this_month' => $thisMonth,
            'publish_rate' => $total > 0 ? round(($published / $total) * 100, 2) : 0,
        ];
    }

    /**
     * @param Post $post
     * @return array<Post>
     */
    public function findRelatedPosts(Post $post, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.id != :current_post')
            ->andWhere('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('current_post', $post->getId())
            ->setParameter('status', PostStatus::PUBLISHED)
            ->setParameter('now', new \DateTime())
            ->setMaxResults($limit);

        // Priorité 1: Même catégorie
        if ($post->getCategory()) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $post->getCategory());
        }

        $related = $qb->orderBy('p.publishedAt', 'DESC')
                     ->getQuery()
                     ->getResult();

        // Si pas assez de résultats, chercher par tags
        if (count($related) < $limit && !$post->getTags()->isEmpty()) {
            $remaining = $limit - count($related);
            $relatedIds = array_map(fn(Post $p) => $p->getId(), $related);
            $relatedIds[] = $post->getId();

            $byTags = $this->createQueryBuilder('p')
                ->join('p.tags', 't')
                ->where('t IN (:tags)')
                ->andWhere('p.id NOT IN (:excluded_ids)')
                ->andWhere('p.status = :status')
                ->andWhere('p.publishedAt <= :now')
                ->andWhere('p.deletedAt IS NULL')
                ->orderBy('p.publishedAt', 'DESC')
                ->setMaxResults($remaining)
                ->setParameter('tags', $post->getTags()->toArray())
                ->setParameter('excluded_ids', $relatedIds)
                ->setParameter('status', PostStatus::PUBLISHED)
                ->setParameter('now', new \DateTime())
                ->getQuery()
                ->getResult();

            $related = array_merge($related, $byTags);
        }

        return $related;
    }

    /**
     * @param int $days
     * @return int
     */
    public function deleteOldDrafts(int $days = 90): int
    {
        $cutoffDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('p')
            ->delete()
            ->where('p.status = :status')
            ->andWhere('p.createdAt < :cutoff')
            ->andWhere('p.updatedAt < :cutoff')
            ->setParameter('status', PostStatus::DRAFT)
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();
    }
}
```

## CategoryRepository

```php
<?php

declare(strict_types=1);

namespace Blog\Repository;

use Blog\Entity\Category;
use Blog\ValueObject\Slug;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 *
 * @extends AbstractRepository<Category>
 */
class CategoryRepository extends AbstractRepository
{
    public function __construct(\MulerTech\Database\EntityManager $entityManager)
    {
        parent::__construct($entityManager, Category::class);
    }

    /**
     * @param Slug $slug
     * @return Category|null
     */
    public function findBySlug(Slug $slug): ?Category
    {
        return $this->findOneBy(['slug' => $slug->getValue()]);
    }

    /**
     * @return array<Category>
     */
    public function findRootCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.parent IS NULL')
            ->andWhere('c.isActive = :active')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Category>
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Category>
     */
    public function findHierarchical(): array
    {
        $categories = $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->orderBy('c.parent', 'ASC')
            ->addOrderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        return $this->buildHierarchy($categories);
    }

    /**
     * @param array<Category> $categories
     * @param Category|null $parent
     * @return array<Category>
     */
    private function buildHierarchy(array $categories, ?Category $parent = null): array
    {
        $result = [];
        
        foreach ($categories as $category) {
            if ($category->getParent() === $parent) {
                $category->children = $this->buildHierarchy($categories, $category);
                $result[] = $category;
            }
        }
        
        return $result;
    }

    /**
     * @param Category $parent
     * @return array<Category>
     */
    public function findByParent(Category $parent): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.parent = :parent')
            ->andWhere('c.isActive = :active')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->setParameter('parent', $parent)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Category>
     */
    public function findWithPostCounts(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'COUNT(p.id) as post_count')
            ->leftJoin('c.posts', 'p', 'WITH', 'p.status = :status AND p.deletedAt IS NULL')
            ->where('c.isActive = :active')
            ->groupBy('c.id')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->setParameter('active', true)
            ->setParameter('status', \Blog\Enum\PostStatus::PUBLISHED)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Category>
     */
    public function findPopular(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'COUNT(p.id) as post_count')
            ->join('c.posts', 'p', 'WITH', 'p.status = :status AND p.deletedAt IS NULL')
            ->where('c.isActive = :active')
            ->groupBy('c.id')
            ->having('post_count > 0')
            ->orderBy('post_count', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('active', true)
            ->setParameter('status', \Blog\Enum\PostStatus::PUBLISHED)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $term
     * @return array<Category>
     */
    public function searchCategories(string $term): array
    {
        return $this->addSearchConditions(['name', 'description'], $term)
            ->andWhere('e.isActive = :active')
            ->orderBy('e.name', 'ASC')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, mixed>
     */
    public function getCategoryStatistics(): array
    {
        $qb = $this->createQueryBuilder('c');

        $total = (int) $qb->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $active = (int) $qb->select('COUNT(c.id)')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $withPosts = (int) $qb->select('COUNT(DISTINCT c.id)')
            ->join('c.posts', 'p')
            ->where('c.isActive = :active')
            ->andWhere('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('active', true)
            ->setParameter('status', \Blog\Enum\PostStatus::PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult();

        $roots = (int) $qb->select('COUNT(c.id)')
            ->where('c.parent IS NULL')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'with_posts' => $withPosts,
            'empty' => $active - $withPosts,
            'root_categories' => $roots,
            'usage_rate' => $active > 0 ? round(($withPosts / $active) * 100, 2) : 0,
        ];
    }

    /**
     * @param Category $category
     * @return array<Category>
     */
    public function findDescendants(Category $category): array
    {
        // Récupération récursive des descendants
        $descendants = [];
        $this->collectDescendants($category, $descendants);
        return $descendants;
    }

    /**
     * @param Category $category
     * @param array<Category> $descendants
     * @return void
     */
    private function collectDescendants(Category $category, array &$descendants): void
    {
        foreach ($category->getChildren() as $child) {
            $descendants[] = $child;
            $this->collectDescendants($child, $descendants);
        }
    }

    /**
     * @param Category $category
     * @return array<Category>
     */
    public function findAncestors(Category $category): array
    {
        $ancestors = [];
        $current = $category->getParent();
        
        while ($current !== null) {
            array_unshift($ancestors, $current);
            $current = $current->getParent();
        }
        
        return $ancestors;
    }
}
```

## TagRepository

```php
<?php

declare(strict_types=1);

namespace Blog\Repository;

use Blog\Entity\Tag;
use Blog\ValueObject\Slug;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 *
 * @extends AbstractRepository<Tag>
 */
class TagRepository extends AbstractRepository
{
    public function __construct(\MulerTech\Database\EntityManager $entityManager)
    {
        parent::__construct($entityManager, Tag::class);
    }

    /**
     * @param Slug $slug
     * @return Tag|null
     */
    public function findBySlug(Slug $slug): ?Tag
    {
        return $this->findOneBy(['slug' => $slug->getValue()]);
    }

    /**
     * @param string $name
     * @return Tag|null
     */
    public function findByName(string $name): ?Tag
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * @param int $limit
     * @return array<Tag>
     */
    public function findPopular(int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->select('t', 'COUNT(p.id) as post_count')
            ->join('t.posts', 'p', 'WITH', 'p.status = :status AND p.deletedAt IS NULL')
            ->groupBy('t.id')
            ->having('post_count > 0')
            ->orderBy('post_count', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->setMaxResults($limit)
            ->setParameter('status', \Blog\Enum\PostStatus::PUBLISHED)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Tag>
     */
    public function findWithPostCounts(): array
    {
        return $this->createQueryBuilder('t')
            ->select('t', 'COUNT(p.id) as post_count')
            ->leftJoin('t.posts', 'p', 'WITH', 'p.status = :status AND p.deletedAt IS NULL')
            ->groupBy('t.id')
            ->orderBy('t.name', 'ASC')
            ->setParameter('status', \Blog\Enum\PostStatus::PUBLISHED)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $term
     * @return array<Tag>
     */
    public function searchTags(string $term): array
    {
        return $this->addSearchConditions(['name', 'description'], $term)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Tag>
     */
    public function findUnused(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.posts', 'p')
            ->where('p.id IS NULL')
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<string> $names
     * @return array<Tag>
     */
    public function findOrCreateByNames(array $names): array
    {
        $tags = [];
        
        foreach ($names as $name) {
            $name = trim($name);
            if (empty($name)) {
                continue;
            }
            
            $tag = $this->findByName($name);
            if (!$tag) {
                $tag = new Tag($name);
                $this->entityManager->persist($tag);
            }
            
            $tags[] = $tag;
        }
        
        $this->entityManager->flush();
        
        return $tags;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTagStatistics(): array
    {
        $qb = $this->createQueryBuilder('t');

        $total = (int) $qb->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $used = (int) $qb->select('COUNT(DISTINCT t.id)')
            ->join('t.posts', 'p')
            ->where('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('status', \Blog\Enum\PostStatus::PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult();

        $averagePerPost = $qb->select('AVG(post_count)')
            ->from('(' . $this->createQueryBuilder('t2')
                ->select('COUNT(p2.id) as post_count')
                ->join('t2.posts', 'p2')
                ->where('p2.status = :status')
                ->andWhere('p2.deletedAt IS NULL')
                ->groupBy('t2.id')
                ->getDQL() . ')', 'subquery')
            ->setParameter('status', \Blog\Enum\PostStatus::PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'used' => $used,
            'unused' => $total - $used,
            'usage_rate' => $total > 0 ? round(($used / $total) * 100, 2) : 0,
            'average_per_post' => round((float) $averagePerPost, 2),
        ];
    }

    /**
     * @return int
     */
    public function deleteUnusedTags(): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.id NOT IN (
                SELECT DISTINCT tag.id 
                FROM ' . Tag::class . ' tag 
                JOIN tag.posts post 
                WHERE post.deletedAt IS NULL
            )')
            ->getQuery()
            ->execute();
    }
}
```

## CommentRepository

```php
<?php

declare(strict_types=1);

namespace Blog\Repository;

use Blog\Entity\Comment;
use Blog\Entity\Post;
use Blog\Entity\User;
use Blog\Enum\CommentStatus;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 *
 * @extends AbstractRepository<Comment>
 */
class CommentRepository extends AbstractRepository
{
    public function __construct(\MulerTech\Database\EntityManager $entityManager)
    {
        parent::__construct($entityManager, Comment::class);
    }

    /**
     * @param Post $post
     * @return array<Comment>
     */
    public function findApprovedByPost(Post $post): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.post = :post')
            ->andWhere('c.status = :status')
            ->orderBy('c.createdAt', 'ASC')
            ->setParameter('post', $post)
            ->setParameter('status', CommentStatus::APPROVED)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Post $post
     * @return array<Comment>
     */
    public function findThreadedByPost(Post $post): array
    {
        $comments = $this->findApprovedByPost($post);
        return $this->buildCommentTree($comments);
    }

    /**
     * @param array<Comment> $comments
     * @param Comment|null $parent
     * @return array<Comment>
     */
    private function buildCommentTree(array $comments, ?Comment $parent = null): array
    {
        $result = [];
        
        foreach ($comments as $comment) {
            if ($comment->getParent() === $parent) {
                $comment->replies = $this->buildCommentTree($comments, $comment);
                $result[] = $comment;
            }
        }
        
        return $result;
    }

    /**
     * @param User $user
     * @return array<Comment>
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.author = :user')
            ->orderBy('c.createdAt', 'DESC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Comment>
     */
    public function findPendingModeration(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->orderBy('c.createdAt', 'ASC')
            ->setParameter('status', CommentStatus::PENDING)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $limit
     * @return array<Comment>
     */
    public function findLatestApproved(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('status', CommentStatus::APPROVED)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $term
     * @return array<Comment>
     */
    public function searchComments(string $term): array
    {
        return $this->addSearchConditions(['content'], $term)
            ->andWhere('e.status = :status')
            ->orderBy('e.createdAt', 'DESC')
            ->setParameter('status', CommentStatus::APPROVED)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param \DateTimeInterface $from
     * @param \DateTimeInterface $to
     * @return array<Comment>
     */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->addDateRangeFilter($from, $to)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, mixed>
     */
    public function getCommentStatistics(): array
    {
        $qb = $this->createQueryBuilder('c');

        $total = (int) $qb->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $approved = (int) $qb->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', CommentStatus::APPROVED)
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) $qb->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', CommentStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $spam = (int) $qb->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', CommentStatus::SPAM)
            ->getQuery()
            ->getSingleScalarResult();

        $thisMonth = (int) $qb->select('COUNT(c.id)')
            ->where('c.createdAt >= :start_of_month')
            ->setParameter('start_of_month', new \DateTime('first day of this month'))
            ->getQuery()
            ->getSingleScalarResult();

        $replies = (int) $qb->select('COUNT(c.id)')
            ->where('c.parent IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'approved' => $approved,
            'pending' => $pending,
            'spam' => $spam,
            'rejected' => $total - $approved - $pending - $spam,
            'this_month' => $thisMonth,
            'replies' => $replies,
            'top_level' => $total - $replies,
            'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
        ];
    }

    /**
     * @param array<string> $ipAddresses
     * @return int
     */
    public function markAsSpamByIpAddresses(array $ipAddresses): int
    {
        if (empty($ipAddresses)) {
            return 0;
        }

        return $this->createQueryBuilder('c')
            ->update()
            ->set('c.status', ':spam_status')
            ->where('c.ipAddress IN (:ip_addresses)')
            ->setParameter('spam_status', CommentStatus::SPAM)
            ->setParameter('ip_addresses', $ipAddresses)
            ->getQuery()
            ->execute();
    }

    /**
     * @param int $days
     * @return int
     */
    public function deleteOldSpam(int $days = 30): int
    {
        $cutoffDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('c')
            ->delete()
            ->where('c.status = :status')
            ->andWhere('c.createdAt < :cutoff')
            ->setParameter('status', CommentStatus::SPAM)
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();
    }
}
```

## Patterns et optimisations

### Query Cache

```php
<?php

declare(strict_types=1);

namespace Blog\Repository\Concern;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
trait CacheableQueries
{
    private array $queryCache = [];
    private bool $cacheEnabled = true;

    protected function getCachedQuery(string $key, callable $queryBuilder, int $ttl = 3600): mixed
    {
        if (!$this->cacheEnabled) {
            return $queryBuilder();
        }

        if (isset($this->queryCache[$key])) {
            $cached = $this->queryCache[$key];
            if ($cached['expires'] > time()) {
                return $cached['data'];
            }
        }

        $data = $queryBuilder();
        $this->queryCache[$key] = [
            'data' => $data,
            'expires' => time() + $ttl
        ];

        return $data;
    }

    public function clearQueryCache(): void
    {
        $this->queryCache = [];
    }

    public function disableCache(): void
    {
        $this->cacheEnabled = false;
    }

    public function enableCache(): void
    {
        $this->cacheEnabled = true;
    }
}
```

### Repository Factory

```php
<?php

declare(strict_types=1);

namespace Blog\Repository;

use MulerTech\Database\EntityManager;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class RepositoryFactory
{
    private EntityManager $entityManager;
    
    /** @var array<string, object> */
    private array $repositories = [];

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getUserRepository(): UserRepository
    {
        return $this->getRepository(UserRepository::class);
    }

    public function getPostRepository(): PostRepository
    {
        return $this->getRepository(PostRepository::class);
    }

    public function getCategoryRepository(): CategoryRepository
    {
        return $this->getRepository(CategoryRepository::class);
    }

    public function getTagRepository(): TagRepository
    {
        return $this->getRepository(TagRepository::class);
    }

    public function getCommentRepository(): CommentRepository
    {
        return $this->getRepository(CommentRepository::class);
    }

    /**
     * @template T
     * @param class-string<T> $repositoryClass
     * @return T
     */
    private function getRepository(string $repositoryClass): object
    {
        if (!isset($this->repositories[$repositoryClass])) {
            $this->repositories[$repositoryClass] = new $repositoryClass($this->entityManager);
        }

        return $this->repositories[$repositoryClass];
    }
}
```

---

**Prochaine étape :** [Services métier](04-services.md) - Implémentation de la couche de logique métier avec gestion des transactions et événements.
