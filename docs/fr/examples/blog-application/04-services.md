# Services Métier

Ce chapitre présente la couche de services qui encapsule la logique métier, gère les transactions et coordonne les repositories.

## Table des Matières
- [Architecture des services](#architecture-des-services)
- [UserService](#userservice)
- [PostService](#postservice)
- [CommentService](#commentservice)
- [CategoryService](#categoryservice)
- [AuthenticationService](#authenticationservice)
- [NotificationService](#notificationservice)

## Architecture des services

### Service de base

```php
<?php

declare(strict_types=1);

namespace Blog\Service;

use MulerTech\Database\EntityManager;
use Blog\Repository\RepositoryFactory;
use Psr\Log\LoggerInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
abstract class AbstractService
{
    protected EntityManager $entityManager;
    protected RepositoryFactory $repositoryFactory;
    protected LoggerInterface $logger;

    public function __construct(
        EntityManager $entityManager,
        RepositoryFactory $repositoryFactory,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->repositoryFactory = $repositoryFactory;
        $this->logger = $logger;
    }

    /**
     * @param callable $operation
     * @return mixed
     * @throws \Throwable
     */
    protected function executeInTransaction(callable $operation): mixed
    {
        $connection = $this->entityManager->getConnection();
        
        if ($connection->isTransactionActive()) {
            // Si déjà dans une transaction, exécuter directement
            return $operation();
        }

        $connection->beginTransaction();
        
        try {
            $result = $operation();
            $connection->commit();
            return $result;
        } catch (\Throwable $e) {
            $connection->rollback();
            $this->logger->error('Transaction rolled back: ' . $e->getMessage(), [
                'exception' => $e,
                'service' => static::class
            ]);
            throw $e;
        }
    }

    /**
     * @param object $entity
     * @param string $action
     * @return void
     */
    protected function logEntityAction(object $entity, string $action): void
    {
        $this->logger->info("Entity {$action}", [
            'entity_class' => get_class($entity),
            'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : null,
            'action' => $action
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string> $requiredFields
     * @throws \InvalidArgumentException
     */
    protected function validateRequiredFields(array $data, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Field '{$field}' is required");
            }
        }
    }
}
```

### Interface des événements

```php
<?php

declare(strict_types=1);

namespace Blog\Service\Event;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
interface EventDispatcherInterface
{
    /**
     * @param string $eventName
     * @param object $event
     * @return void
     */
    public function dispatch(string $eventName, object $event): void;
}

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ServiceEvent
{
    private object $entity;
    private array $metadata;

    /**
     * @param object $entity
     * @param array<string, mixed> $metadata
     */
    public function __construct(object $entity, array $metadata = [])
    {
        $this->entity = $entity;
        $this->metadata = $metadata;
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }
}
```

## UserService

```php
<?php

declare(strict_types=1);

namespace Blog\Service;

use Blog\Entity\User;
use Blog\ValueObject\Email;
use Blog\Enum\UserRole;
use Blog\Enum\UserStatus;
use Blog\Service\Event\EventDispatcherInterface;
use Blog\Service\Event\ServiceEvent;
use Blog\Exception\UserNotFoundException;
use Blog\Exception\DuplicateEmailException;
use Blog\Exception\InvalidCredentialsException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class UserService extends AbstractService
{
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        \MulerTech\Database\EntityManager $entityManager,
        \Blog\Repository\RepositoryFactory $repositoryFactory,
        \Psr\Log\LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($entityManager, $repositoryFactory, $logger);
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param array<string, mixed> $userData
     * @return User
     * @throws DuplicateEmailException
     */
    public function createUser(array $userData): User
    {
        $this->validateRequiredFields($userData, ['email', 'firstName', 'lastName', 'password']);

        return $this->executeInTransaction(function () use ($userData): User {
            $email = new Email($userData['email']);
            
            // Vérifier l'unicité de l'email
            if ($this->repositoryFactory->getUserRepository()->findByEmail($email)) {
                throw new DuplicateEmailException("Email {$email->getValue()} already exists");
            }

            $user = new User(
                $email,
                $userData['firstName'],
                $userData['lastName']
            );

            $user->setPasswordHash(password_hash($userData['password'], PASSWORD_DEFAULT));
            
            if (isset($userData['role'])) {
                $user->setRole(UserRole::from($userData['role']));
            }

            if (isset($userData['bio'])) {
                $user->setBio($userData['bio']);
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->logEntityAction($user, 'created');
            $this->eventDispatcher->dispatch('user.created', new ServiceEvent($user));

            return $user;
        });
    }

    /**
     * @param int $userId
     * @param array<string, mixed> $userData
     * @return User
     * @throws UserNotFoundException
     */
    public function updateUser(int $userId, array $userData): User
    {
        return $this->executeInTransaction(function () use ($userId, $userData): User {
            $user = $this->findUserById($userId);
            $originalData = [
                'email' => $user->getEmail()->getValue(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName()
            ];

            if (isset($userData['email'])) {
                $newEmail = new Email($userData['email']);
                if (!$user->getEmail()->equals($newEmail)) {
                    // Vérifier l'unicité du nouvel email
                    $existingUser = $this->repositoryFactory->getUserRepository()->findByEmail($newEmail);
                    if ($existingUser && $existingUser->getId() !== $user->getId()) {
                        throw new DuplicateEmailException("Email {$newEmail->getValue()} already exists");
                    }
                    $user->setEmail($newEmail);
                }
            }

            if (isset($userData['firstName'])) {
                $user->setFirstName($userData['firstName']);
            }

            if (isset($userData['lastName'])) {
                $user->setLastName($userData['lastName']);
            }

            if (isset($userData['bio'])) {
                $user->setBio($userData['bio']);
            }

            if (isset($userData['avatar'])) {
                $user->setAvatar($userData['avatar']);
            }

            if (isset($userData['role']) && UserRole::tryFrom($userData['role'])) {
                $user->setRole(UserRole::from($userData['role']));
            }

            $this->entityManager->flush();

            $this->logEntityAction($user, 'updated');
            $this->eventDispatcher->dispatch('user.updated', new ServiceEvent($user, [
                'original_data' => $originalData,
                'updated_fields' => array_keys($userData)
            ]));

            return $user;
        });
    }

    /**
     * @param string $email
     * @param string $password
     * @return User
     * @throws InvalidCredentialsException
     */
    public function authenticateUser(string $email, string $password): User
    {
        $user = $this->repositoryFactory->getUserRepository()->findByEmailString($email);
        
        if (!$user || !$user->isActive() || !$user->verifyPassword($password)) {
            throw new InvalidCredentialsException('Invalid email or password');
        }

        $user->recordLogin();
        $this->entityManager->flush();

        $this->logEntityAction($user, 'authenticated');
        $this->eventDispatcher->dispatch('user.authenticated', new ServiceEvent($user));

        return $user;
    }

    /**
     * @param int $userId
     * @param string $newPassword
     * @return void
     * @throws UserNotFoundException
     */
    public function changePassword(int $userId, string $newPassword): void
    {
        $this->executeInTransaction(function () use ($userId, $newPassword): void {
            $user = $this->findUserById($userId);
            $user->setPasswordHash(password_hash($newPassword, PASSWORD_DEFAULT));
            
            $this->entityManager->flush();

            $this->logEntityAction($user, 'password_changed');
            $this->eventDispatcher->dispatch('user.password_changed', new ServiceEvent($user));
        });
    }

    /**
     * @param int $userId
     * @return void
     * @throws UserNotFoundException
     */
    public function deactivateUser(int $userId): void
    {
        $this->executeInTransaction(function () use ($userId): void {
            $user = $this->findUserById($userId);
            $user->setStatus(UserStatus::INACTIVE);
            
            $this->entityManager->flush();

            $this->logEntityAction($user, 'deactivated');
            $this->eventDispatcher->dispatch('user.deactivated', new ServiceEvent($user));
        });
    }

    /**
     * @param int $userId
     * @return void
     * @throws UserNotFoundException
     */
    public function deleteUser(int $userId): void
    {
        $this->executeInTransaction(function () use ($userId): void {
            $user = $this->findUserById($userId);
            $user->delete(); // Soft delete
            
            $this->entityManager->flush();

            $this->logEntityAction($user, 'deleted');
            $this->eventDispatcher->dispatch('user.deleted', new ServiceEvent($user));
        });
    }

    /**
     * @param int $userId
     * @return User
     * @throws UserNotFoundException
     */
    public function findUserById(int $userId): User
    {
        $user = $this->repositoryFactory->getUserRepository()->find($userId);
        
        if (!$user) {
            throw new UserNotFoundException("User with ID {$userId} not found");
        }

        return $user;
    }

    /**
     * @param string $email
     * @return User
     * @throws UserNotFoundException
     */
    public function findUserByEmail(string $email): User
    {
        $user = $this->repositoryFactory->getUserRepository()->findByEmailString($email);
        
        if (!$user) {
            throw new UserNotFoundException("User with email {$email} not found");
        }

        return $user;
    }

    /**
     * @param int $page
     * @param int $perPage
     * @param array<string, mixed> $filters
     * @return array{data: array<User>, pagination: array<string, mixed>}
     */
    public function getUsers(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $criteria = [];
        
        if (isset($filters['status'])) {
            $criteria['status'] = UserStatus::from($filters['status']);
        }

        if (isset($filters['role'])) {
            $criteria['role'] = UserRole::from($filters['role']);
        }

        $orderBy = ['createdAt' => 'DESC'];
        if (isset($filters['sort'])) {
            $orderBy = [$filters['sort'] => $filters['direction'] ?? 'ASC'];
        }

        return $this->repositoryFactory->getUserRepository()->findPaginated(
            $page,
            $perPage,
            $criteria,
            $orderBy
        );
    }

    /**
     * @param string $term
     * @return array<User>
     */
    public function searchUsers(string $term): array
    {
        return $this->repositoryFactory->getUserRepository()->searchUsers($term);
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserStatistics(): array
    {
        return $this->repositoryFactory->getUserRepository()->getUserStatistics();
    }

    /**
     * @param int $inactiveDays
     * @return int
     */
    public function archiveInactiveUsers(int $inactiveDays = 365): int
    {
        return $this->executeInTransaction(function () use ($inactiveDays): int {
            $archivedCount = $this->repositoryFactory->getUserRepository()->archiveInactiveUsers($inactiveDays);
            
            $this->logger->info("Archived {$archivedCount} inactive users", [
                'inactive_days' => $inactiveDays
            ]);

            return $archivedCount;
        });
    }
}
```

## PostService

```php
<?php

declare(strict_types=1);

namespace Blog\Service;

use Blog\Entity\Post;
use Blog\Entity\User;
use Blog\Entity\Category;
use Blog\Entity\Tag;
use Blog\Enum\PostStatus;
use Blog\ValueObject\Slug;
use Blog\Service\Event\EventDispatcherInterface;
use Blog\Service\Event\ServiceEvent;
use Blog\Exception\PostNotFoundException;
use Blog\Exception\UnauthorizedActionException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PostService extends AbstractService
{
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        \MulerTech\Database\EntityManager $entityManager,
        \Blog\Repository\RepositoryFactory $repositoryFactory,
        \Psr\Log\LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($entityManager, $repositoryFactory, $logger);
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param array<string, mixed> $postData
     * @param User $author
     * @return Post
     */
    public function createPost(array $postData, User $author): Post
    {
        $this->validateRequiredFields($postData, ['title', 'content']);

        return $this->executeInTransaction(function () use ($postData, $author): Post {
            $post = new Post(
                $postData['title'],
                $postData['content'],
                $author
            );

            if (isset($postData['excerpt'])) {
                $post->setExcerpt($postData['excerpt']);
            }

            if (isset($postData['featuredImage'])) {
                $post->setFeaturedImage($postData['featuredImage']);
            }

            if (isset($postData['categoryId'])) {
                $category = $this->repositoryFactory->getCategoryRepository()->find($postData['categoryId']);
                if ($category) {
                    $post->setCategory($category);
                }
            }

            if (isset($postData['tags']) && is_array($postData['tags'])) {
                $tags = $this->repositoryFactory->getTagRepository()->findOrCreateByNames($postData['tags']);
                foreach ($tags as $tag) {
                    $post->addTag($tag);
                }
            }

            if (isset($postData['metadata'])) {
                $post->setMetadata($postData['metadata']);
            }

            if (isset($postData['allowComments'])) {
                $post->setAllowComments((bool) $postData['allowComments']);
            }

            // Si le statut est publié, définir la date de publication
            if (isset($postData['status']) && $postData['status'] === PostStatus::PUBLISHED->value) {
                $post->setStatus(PostStatus::PUBLISHED);
            }

            $this->entityManager->persist($post);
            $this->entityManager->flush();

            $this->logEntityAction($post, 'created');
            $this->eventDispatcher->dispatch('post.created', new ServiceEvent($post));

            return $post;
        });
    }

    /**
     * @param int $postId
     * @param array<string, mixed> $postData
     * @param User $user
     * @return Post
     * @throws PostNotFoundException
     * @throws UnauthorizedActionException
     */
    public function updatePost(int $postId, array $postData, User $user): Post
    {
        return $this->executeInTransaction(function () use ($postId, $postData, $user): Post {
            $post = $this->findPostById($postId);
            
            // Vérifier les autorisations
            if (!$this->canUserEditPost($user, $post)) {
                throw new UnauthorizedActionException('User cannot edit this post');
            }

            $originalStatus = $post->getStatus();

            if (isset($postData['title'])) {
                $post->setTitle($postData['title']);
            }

            if (isset($postData['content'])) {
                $post->setContent($postData['content']);
            }

            if (isset($postData['excerpt'])) {
                $post->setExcerpt($postData['excerpt']);
            }

            if (isset($postData['featuredImage'])) {
                $post->setFeaturedImage($postData['featuredImage']);
            }

            if (isset($postData['categoryId'])) {
                $category = $this->repositoryFactory->getCategoryRepository()->find($postData['categoryId']);
                $post->setCategory($category);
            }

            if (isset($postData['tags']) && is_array($postData['tags'])) {
                // Supprimer tous les tags actuels
                foreach ($post->getTags() as $tag) {
                    $post->removeTag($tag);
                }
                
                // Ajouter les nouveaux tags
                $tags = $this->repositoryFactory->getTagRepository()->findOrCreateByNames($postData['tags']);
                foreach ($tags as $tag) {
                    $post->addTag($tag);
                }
            }

            if (isset($postData['status'])) {
                $newStatus = PostStatus::from($postData['status']);
                $post->setStatus($newStatus);
            }

            if (isset($postData['allowComments'])) {
                $post->setAllowComments((bool) $postData['allowComments']);
            }

            if (isset($postData['metadata'])) {
                $post->setMetadata($postData['metadata']);
            }

            $this->entityManager->flush();

            $this->logEntityAction($post, 'updated');
            $this->eventDispatcher->dispatch('post.updated', new ServiceEvent($post, [
                'original_status' => $originalStatus,
                'updated_fields' => array_keys($postData)
            ]));

            // Événement spécifique si le statut a changé
            if ($originalStatus !== $post->getStatus()) {
                $this->eventDispatcher->dispatch('post.status_changed', new ServiceEvent($post, [
                    'from_status' => $originalStatus,
                    'to_status' => $post->getStatus()
                ]));
            }

            return $post;
        });
    }

    /**
     * @param int $postId
     * @param User $user
     * @return void
     * @throws PostNotFoundException
     * @throws UnauthorizedActionException
     */
    public function publishPost(int $postId, User $user): void
    {
        $this->executeInTransaction(function () use ($postId, $user): void {
            $post = $this->findPostById($postId);
            
            if (!$this->canUserPublishPost($user, $post)) {
                throw new UnauthorizedActionException('User cannot publish this post');
            }

            $originalStatus = $post->getStatus();
            $post->publish();
            
            $this->entityManager->flush();

            $this->logEntityAction($post, 'published');
            $this->eventDispatcher->dispatch('post.published', new ServiceEvent($post, [
                'original_status' => $originalStatus,
                'published_by' => $user
            ]));
        });
    }

    /**
     * @param int $postId
     * @param User $user
     * @return void
     * @throws PostNotFoundException
     * @throws UnauthorizedActionException
     */
    public function deletePost(int $postId, User $user): void
    {
        $this->executeInTransaction(function () use ($postId, $user): void {
            $post = $this->findPostById($postId);
            
            if (!$this->canUserDeletePost($user, $post)) {
                throw new UnauthorizedActionException('User cannot delete this post');
            }

            $post->delete(); // Soft delete
            
            $this->entityManager->flush();

            $this->logEntityAction($post, 'deleted');
            $this->eventDispatcher->dispatch('post.deleted', new ServiceEvent($post));
        });
    }

    /**
     * @param string $slug
     * @return Post
     * @throws PostNotFoundException
     */
    public function findPublishedPostBySlug(string $slug): Post
    {
        $post = $this->repositoryFactory->getPostRepository()->findPublishedBySlug($slug);
        
        if (!$post) {
            throw new PostNotFoundException("Published post with slug '{$slug}' not found");
        }

        // Incrémenter le compteur de vues
        $post->incrementViewCount();
        $this->entityManager->flush();

        return $post;
    }

    /**
     * @param int $postId
     * @return Post
     * @throws PostNotFoundException
     */
    public function findPostById(int $postId): Post
    {
        $post = $this->repositoryFactory->getPostRepository()->find($postId);
        
        if (!$post || $post->isDeleted()) {
            throw new PostNotFoundException("Post with ID {$postId} not found");
        }

        return $post;
    }

    /**
     * @param int $page
     * @param int $perPage
     * @param array<string, mixed> $filters
     * @return array{data: array<Post>, pagination: array<string, mixed>}
     */
    public function getPublishedPosts(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $criteria = [
            'status' => PostStatus::PUBLISHED,
            'deletedAt' => null
        ];

        if (isset($filters['categoryId'])) {
            $criteria['category'] = $filters['categoryId'];
        }

        if (isset($filters['authorId'])) {
            $criteria['author'] => $filters['authorId'];
        }

        return $this->repositoryFactory->getPostRepository()->findPaginated(
            $page,
            $perPage,
            $criteria,
            ['publishedAt' => 'DESC']
        );
    }

    /**
     * @param string $term
     * @return array<Post>
     */
    public function searchPosts(string $term): array
    {
        return $this->repositoryFactory->getPostRepository()->searchPublished($term);
    }

    /**
     * @param Post $post
     * @param int $limit
     * @return array<Post>
     */
    public function getRelatedPosts(Post $post, int $limit = 5): array
    {
        return $this->repositoryFactory->getPostRepository()->findRelatedPosts($post, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPostStatistics(): array
    {
        return $this->repositoryFactory->getPostRepository()->getPostStatistics();
    }

    /**
     * @param User $user
     * @param Post $post
     * @return bool
     */
    private function canUserEditPost(User $user, Post $post): bool
    {
        return $user->isAdmin() || 
               $user->getRole() === \Blog\Enum\UserRole::EDITOR || 
               $post->getAuthor()->getId() === $user->getId();
    }

    /**
     * @param User $user
     * @param Post $post
     * @return bool
     */
    private function canUserPublishPost(User $user, Post $post): bool
    {
        return $user->isAdmin() || 
               $user->getRole() === \Blog\Enum\UserRole::EDITOR ||
               ($post->getAuthor()->getId() === $user->getId() && $user->getRole() === \Blog\Enum\UserRole::EDITOR);
    }

    /**
     * @param User $user
     * @param Post $post
     * @return bool
     */
    private function canUserDeletePost(User $user, Post $post): bool
    {
        return $user->isAdmin() || $post->getAuthor()->getId() === $user->getId();
    }

    /**
     * @param int $days
     * @return int
     */
    public function cleanupOldDrafts(int $days = 90): int
    {
        return $this->executeInTransaction(function () use ($days): int {
            $deletedCount = $this->repositoryFactory->getPostRepository()->deleteOldDrafts($days);
            
            $this->logger->info("Deleted {$deletedCount} old draft posts", [
                'older_than_days' => $days
            ]);

            return $deletedCount;
        });
    }
}
```

## CommentService

```php
<?php

declare(strict_types=1);

namespace Blog\Service;

use Blog\Entity\Comment;
use Blog\Entity\Post;
use Blog\Entity\User;
use Blog\Enum\CommentStatus;
use Blog\Service\Event\EventDispatcherInterface;
use Blog\Service\Event\ServiceEvent;
use Blog\Exception\CommentNotFoundException;
use Blog\Exception\UnauthorizedActionException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class CommentService extends AbstractService
{
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        \MulerTech\Database\EntityManager $entityManager,
        \Blog\Repository\RepositoryFactory $repositoryFactory,
        \Psr\Log\LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($entityManager, $repositoryFactory, $logger);
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param array<string, mixed> $commentData
     * @param Post $post
     * @param User $author
     * @param Comment|null $parent
     * @return Comment
     */
    public function createComment(array $commentData, Post $post, User $author, ?Comment $parent = null): Comment
    {
        $this->validateRequiredFields($commentData, ['content']);

        if (!$post->getAllowComments()) {
            throw new UnauthorizedActionException('Comments are not allowed on this post');
        }

        return $this->executeInTransaction(function () use ($commentData, $post, $author, $parent): Comment {
            $comment = new Comment(
                $commentData['content'],
                $post,
                $author,
                $parent
            );

            // Définir automatiquement les informations de tracking
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $comment->setIpAddress($_SERVER['REMOTE_ADDR']);
            }

            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $comment->setUserAgent($_SERVER['HTTP_USER_AGENT']);
            }

            // Auto-approbation pour les utilisateurs de confiance
            if ($this->shouldAutoApproveComment($author)) {
                $comment->approve();
            }

            $this->entityManager->persist($comment);
            $this->entityManager->flush();

            $this->logEntityAction($comment, 'created');
            $this->eventDispatcher->dispatch('comment.created', new ServiceEvent($comment, [
                'post' => $post,
                'author' => $author,
                'parent' => $parent
            ]));

            return $comment;
        });
    }

    /**
     * @param int $commentId
     * @param User $moderator
     * @return void
     * @throws CommentNotFoundException
     * @throws UnauthorizedActionException
     */
    public function approveComment(int $commentId, User $moderator): void
    {
        $this->executeInTransaction(function () use ($commentId, $moderator): void {
            $comment = $this->findCommentById($commentId);
            
            if (!$this->canUserModerateComments($moderator)) {
                throw new UnauthorizedActionException('User cannot moderate comments');
            }

            $originalStatus = $comment->getStatus();
            $comment->approve();
            
            $this->entityManager->flush();

            $this->logEntityAction($comment, 'approved');
            $this->eventDispatcher->dispatch('comment.approved', new ServiceEvent($comment, [
                'original_status' => $originalStatus,
                'approved_by' => $moderator
            ]));
        });
    }

    /**
     * @param int $commentId
     * @param User $moderator
     * @return void
     * @throws CommentNotFoundException
     * @throws UnauthorizedActionException
     */
    public function rejectComment(int $commentId, User $moderator): void
    {
        $this->executeInTransaction(function () use ($commentId, $moderator): void {
            $comment = $this->findCommentById($commentId);
            
            if (!$this->canUserModerateComments($moderator)) {
                throw new UnauthorizedActionException('User cannot moderate comments');
            }

            $originalStatus = $comment->getStatus();
            $comment->reject();
            
            $this->entityManager->flush();

            $this->logEntityAction($comment, 'rejected');
            $this->eventDispatcher->dispatch('comment.rejected', new ServiceEvent($comment, [
                'original_status' => $originalStatus,
                'rejected_by' => $moderator
            ]));
        });
    }

    /**
     * @param int $commentId
     * @param User $moderator
     * @return void
     * @throws CommentNotFoundException
     * @throws UnauthorizedActionException
     */
    public function markCommentAsSpam(int $commentId, User $moderator): void
    {
        $this->executeInTransaction(function () use ($commentId, $moderator): void {
            $comment = $this->findCommentById($commentId);
            
            if (!$this->canUserModerateComments($moderator)) {
                throw new UnauthorizedActionException('User cannot moderate comments');
            }

            $originalStatus = $comment->getStatus();
            $comment->markAsSpam();
            
            $this->entityManager->flush();

            $this->logEntityAction($comment, 'marked_as_spam');
            $this->eventDispatcher->dispatch('comment.marked_as_spam', new ServiceEvent($comment, [
                'original_status' => $originalStatus,
                'marked_by' => $moderator
            ]));
        });
    }

    /**
     * @param Post $post
     * @return array<Comment>
     */
    public function getCommentsForPost(Post $post): array
    {
        return $this->repositoryFactory->getCommentRepository()->findThreadedByPost($post);
    }

    /**
     * @return array<Comment>
     */
    public function getPendingComments(): array
    {
        return $this->repositoryFactory->getCommentRepository()->findPendingModeration();
    }

    /**
     * @param int $commentId
     * @return Comment
     * @throws CommentNotFoundException
     */
    public function findCommentById(int $commentId): Comment
    {
        $comment = $this->repositoryFactory->getCommentRepository()->find($commentId);
        
        if (!$comment) {
            throw new CommentNotFoundException("Comment with ID {$commentId} not found");
        }

        return $comment;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCommentStatistics(): array
    {
        return $this->repositoryFactory->getCommentRepository()->getCommentStatistics();
    }

    /**
     * @param User $user
     * @return bool
     */
    private function shouldAutoApproveComment(User $user): bool
    {
        // Auto-approuver pour les admins et éditeurs
        if ($user->isAdmin() || $user->getRole() === \Blog\Enum\UserRole::EDITOR) {
            return true;
        }

        // Auto-approuver pour les utilisateurs avec des commentaires approuvés précédemment
        $userComments = $this->repositoryFactory->getCommentRepository()->findByUser($user);
        $approvedComments = array_filter($userComments, fn(Comment $c) => $c->isApproved());
        
        return count($approvedComments) >= 3;
    }

    /**
     * @param User $user
     * @return bool
     */
    private function canUserModerateComments(User $user): bool
    {
        return $user->isAdmin() || $user->getRole() === \Blog\Enum\UserRole::EDITOR;
    }

    /**
     * @param array<string> $ipAddresses
     * @return int
     */
    public function bulkMarkAsSpamByIp(array $ipAddresses): int
    {
        return $this->executeInTransaction(function () use ($ipAddresses): int {
            $markedCount = $this->repositoryFactory->getCommentRepository()->markAsSpamByIpAddresses($ipAddresses);
            
            $this->logger->info("Marked {$markedCount} comments as spam by IP", [
                'ip_addresses' => $ipAddresses
            ]);

            return $markedCount;
        });
    }

    /**
     * @param int $days
     * @return int
     */
    public function cleanupOldSpam(int $days = 30): int
    {
        return $this->executeInTransaction(function () use ($days): int {
            $deletedCount = $this->repositoryFactory->getCommentRepository()->deleteOldSpam($days);
            
            $this->logger->info("Deleted {$deletedCount} old spam comments", [
                'older_than_days' => $days
            ]);

            return $deletedCount;
        });
    }
}
```

## CategoryService

```php
<?php

declare(strict_types=1);

namespace Blog\Service;

use Blog\Entity\Category;
use Blog\ValueObject\Slug;
use Blog\Service\Event\EventDispatcherInterface;
use Blog\Service\Event\ServiceEvent;
use Blog\Exception\CategoryNotFoundException;
use Blog\Exception\DuplicateSlugException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class CategoryService extends AbstractService
{
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        \MulerTech\Database\EntityManager $entityManager,
        \Blog\Repository\RepositoryFactory $repositoryFactory,
        \Psr\Log\LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($entityManager, $repositoryFactory, $logger);
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param array<string, mixed> $categoryData
     * @return Category
     * @throws DuplicateSlugException
     */
    public function createCategory(array $categoryData): Category
    {
        $this->validateRequiredFields($categoryData, ['name']);

        return $this->executeInTransaction(function () use ($categoryData): Category {
            $slug = Slug::fromString($categoryData['name']);
            
            // Vérifier l'unicité du slug
            if ($this->repositoryFactory->getCategoryRepository()->findBySlug($slug)) {
                throw new DuplicateSlugException("Category with slug '{$slug->getValue()}' already exists");
            }

            $parent = null;
            if (isset($categoryData['parentId'])) {
                $parent = $this->repositoryFactory->getCategoryRepository()->find($categoryData['parentId']);
            }

            $category = new Category($categoryData['name'], $parent);

            if (isset($categoryData['description'])) {
                $category->setDescription($categoryData['description']);
            }

            if (isset($categoryData['color'])) {
                $category->setColor($categoryData['color']);
            }

            if (isset($categoryData['image'])) {
                $category->setImage($categoryData['image']);
            }

            if (isset($categoryData['sortOrder'])) {
                $category->setSortOrder((int) $categoryData['sortOrder']);
            }

            $this->entityManager->persist($category);
            $this->entityManager->flush();

            $this->logEntityAction($category, 'created');
            $this->eventDispatcher->dispatch('category.created', new ServiceEvent($category));

            return $category;
        });
    }

    /**
     * @param int $categoryId
     * @param array<string, mixed> $categoryData
     * @return Category
     * @throws CategoryNotFoundException
     */
    public function updateCategory(int $categoryId, array $categoryData): Category
    {
        return $this->executeInTransaction(function () use ($categoryId, $categoryData): Category {
            $category = $this->findCategoryById($categoryId);

            if (isset($categoryData['name'])) {
                $category->setName($categoryData['name']);
            }

            if (isset($categoryData['description'])) {
                $category->setDescription($categoryData['description']);
            }

            if (isset($categoryData['color'])) {
                $category->setColor($categoryData['color']);
            }

            if (isset($categoryData['image'])) {
                $category->setImage($categoryData['image']);
            }

            if (isset($categoryData['sortOrder'])) {
                $category->setSortOrder((int) $categoryData['sortOrder']);
            }

            if (isset($categoryData['isActive'])) {
                $category->setActive((bool) $categoryData['isActive']);
            }

            if (isset($categoryData['parentId'])) {
                $parent = $categoryData['parentId'] ? 
                    $this->repositoryFactory->getCategoryRepository()->find($categoryData['parentId']) : 
                    null;
                $category->setParent($parent);
            }

            $this->entityManager->flush();

            $this->logEntityAction($category, 'updated');
            $this->eventDispatcher->dispatch('category.updated', new ServiceEvent($category));

            return $category;
        });
    }

    /**
     * @param string $slug
     * @return Category
     * @throws CategoryNotFoundException
     */
    public function findCategoryBySlug(string $slug): Category
    {
        $category = $this->repositoryFactory->getCategoryRepository()->findBySlug(Slug::fromString($slug));
        
        if (!$category) {
            throw new CategoryNotFoundException("Category with slug '{$slug}' not found");
        }

        return $category;
    }

    /**
     * @param int $categoryId
     * @return Category
     * @throws CategoryNotFoundException
     */
    public function findCategoryById(int $categoryId): Category
    {
        $category = $this->repositoryFactory->getCategoryRepository()->find($categoryId);
        
        if (!$category) {
            throw new CategoryNotFoundException("Category with ID {$categoryId} not found");
        }

        return $category;
    }

    /**
     * @return array<Category>
     */
    public function getHierarchicalCategories(): array
    {
        return $this->repositoryFactory->getCategoryRepository()->findHierarchical();
    }

    /**
     * @return array<Category>
     */
    public function getActiveCategories(): array
    {
        return $this->repositoryFactory->getCategoryRepository()->findAllActive();
    }

    /**
     * @param int $limit
     * @return array<Category>
     */
    public function getPopularCategories(int $limit = 10): array
    {
        return $this->repositoryFactory->getCategoryRepository()->findPopular($limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCategoryStatistics(): array
    {
        return $this->repositoryFactory->getCategoryRepository()->getCategoryStatistics();
    }
}
```

## AuthenticationService

```php
<?php

declare(strict_types=1);

namespace Blog\Service;

use Blog\Entity\User;
use Blog\Exception\InvalidCredentialsException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class AuthenticationService extends AbstractService
{
    private UserService $userService;

    public function __construct(
        \MulerTech\Database\EntityManager $entityManager,
        \Blog\Repository\RepositoryFactory $repositoryFactory,
        \Psr\Log\LoggerInterface $logger,
        UserService $userService
    ) {
        parent::__construct($entityManager, $repositoryFactory, $logger);
        $this->userService = $userService;
    }

    /**
     * @param string $email
     * @param string $password
     * @return User
     * @throws InvalidCredentialsException
     */
    public function authenticate(string $email, string $password): User
    {
        return $this->userService->authenticateUser($email, $password);
    }

    /**
     * @param User $user
     * @return string
     */
    public function generateSessionToken(User $user): string
    {
        $payload = [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()->getValue(),
            'role' => $user->getRole()->value,
            'issued_at' => time(),
            'expires_at' => time() + (24 * 60 * 60) // 24 heures
        ];

        return base64_encode(json_encode($payload));
    }

    /**
     * @param string $token
     * @return User|null
     */
    public function validateSessionToken(string $token): ?User
    {
        try {
            $payload = json_decode(base64_decode($token), true);
            
            if (!$payload || !isset($payload['user_id'], $payload['expires_at'])) {
                return null;
            }

            if ($payload['expires_at'] < time()) {
                return null; // Token expiré
            }

            return $this->userService->findUserById($payload['user_id']);
            
        } catch (\Exception $e) {
            $this->logger->warning('Invalid session token', ['token' => $token, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * @param User $user
     * @param string $permission
     * @return bool
     */
    public function hasPermission(User $user, string $permission): bool
    {
        $permissions = $user->getRole()->getPermissions();
        
        return in_array('*', $permissions) || in_array($permission, $permissions);
    }
}
```

## NotificationService

```php
<?php

declare(strict_types=1);

namespace Blog\Service;

use Blog\Entity\User;
use Blog\Entity\Post;
use Blog\Entity\Comment;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class NotificationService extends AbstractService
{
    /**
     * @param User $user
     * @return void
     */
    public function sendWelcomeNotification(User $user): void
    {
        $this->logger->info('Sending welcome notification', ['user_id' => $user->getId()]);
        
        // Ici vous pourriez intégrer un service d'email, SMS, etc.
        // Pour l'exemple, nous loggons simplement
    }

    /**
     * @param Post $post
     * @return void
     */
    public function notifyPostPublished(Post $post): void
    {
        $this->logger->info('Post published notification', [
            'post_id' => $post->getId(),
            'author_id' => $post->getAuthor()->getId()
        ]);
    }

    /**
     * @param Comment $comment
     * @return void
     */
    public function notifyNewComment(Comment $comment): void
    {
        $postAuthor = $comment->getPost()->getAuthor();
        
        $this->logger->info('New comment notification', [
            'comment_id' => $comment->getId(),
            'post_id' => $comment->getPost()->getId(),
            'post_author_id' => $postAuthor->getId()
        ]);
    }

    /**
     * @param Comment $comment
     * @return void
     */
    public function notifyCommentApproved(Comment $comment): void
    {
        $this->logger->info('Comment approved notification', [
            'comment_id' => $comment->getId(),
            'author_id' => $comment->getAuthor()->getId()
        ]);
    }
}
```

---

**Prochaine étape :** [Contrôleurs web](05-controllers.md) - Implémentation de l'interface web avec gestion des formulaires et sessions.
