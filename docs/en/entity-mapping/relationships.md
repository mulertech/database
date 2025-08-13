# Relationships

Guide for defining and managing entity relationships in MulerTech Database.

## Overview

MulerTech Database supports standard ORM relationships through foreign keys and mapping configuration. While the current version focuses on explicit foreign key relationships, it provides a solid foundation for complex data models.

## Foreign Key Relationships

### Basic Foreign Key

Define a foreign key relationship using integer fields and proper indexing.

```php
#[MtEntity(tableName: 'posts')]
class Post
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255)]
    private string $title;

    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::INDEX,
        nullable: false
    )]
    private int $authorId;

    #[MtColumn(columnType: ColumnType::DATETIME)]
    private DateTime $createdAt;

    // Getters and setters
    public function getAuthorId(): int
    {
        return $this->authorId;
    }

    public function setAuthorId(int $authorId): void
    {
        $this->authorId = $authorId;
    }
}
```

### Working with Related Entities

Use repositories to load related entities when needed.

```php
class PostService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function getPostWithAuthor(int $postId): ?array
    {
        $post = $this->entityManager->getRepository(Post::class)->find($postId);
        if (!$post) {
            return null;
        }

        $author = $this->entityManager->getRepository(User::class)->find($post->getAuthorId());

        return [
            'post' => $post,
            'author' => $author
        ];
    }

    public function createPost(string $title, string $content, int $authorId): Post
    {
        // Verify author exists
        $author = $this->entityManager->getRepository(User::class)->find($authorId);
        if (!$author) {
            throw new InvalidArgumentException('Author not found');
        }

        $post = new Post();
        $post->setTitle($title);
        $post->setContent($content);
        $post->setAuthorId($authorId);

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return $post;
    }
}
```

## Relationship Patterns

### One-to-Many Pattern

Model where one entity relates to many others (e.g., User has many Posts).

```php
// Parent entity (User)
#[MtEntity(tableName: 'users')]
class User
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $name;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255)]
    private string $email;

    // Helper method to get posts
    public function getPosts(EntityManagerInterface $entityManager): array
    {
        return $entityManager->getRepository(Post::class)
            ->findBy(['authorId' => $this->id]);
    }
}

// Child entity (Post)
#[MtEntity(tableName: 'posts')]
class Post
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255)]
    private string $title;

    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::INDEX
    )]
    private int $authorId;

    // Helper method to get author
    public function getAuthor(EntityManagerInterface $entityManager): ?User
    {
        return $entityManager->getRepository(User::class)->find($this->authorId);
    }
}
```

### Many-to-One Pattern

Multiple entities referencing a single parent entity.

```php
// Multiple comments belong to one post
#[MtEntity(tableName: 'comments')]
class Comment
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::TEXT)]
    private string $content;

    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::INDEX
    )]
    private int $postId;

    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::INDEX
    )]
    private int $authorId;

    public function getPost(EntityManagerInterface $entityManager): ?Post
    {
        return $entityManager->getRepository(Post::class)->find($this->postId);
    }

    public function getAuthor(EntityManagerInterface $entityManager): ?User
    {
        return $entityManager->getRepository(User::class)->find($this->authorId);
    }
}
```

### Many-to-Many Pattern

Use a junction table to represent many-to-many relationships.

```php
// User entity
#[MtEntity(tableName: 'users')]
class User
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $name;

    // Get user's roles
    public function getRoles(EntityManagerInterface $entityManager): array
    {
        $userRoles = $entityManager->getRepository(UserRole::class)
            ->findBy(['userId' => $this->id]);

        $roles = [];
        foreach ($userRoles as $userRole) {
            $role = $entityManager->getRepository(Role::class)
                ->find($userRole->getRoleId());
            if ($role) {
                $roles[] = $role;
            }
        }

        return $roles;
    }
}

// Role entity
#[MtEntity(tableName: 'roles')]
class Role
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 50)]
    private string $name;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255)]
    private string $description;

    // Get users with this role
    public function getUsers(EntityManagerInterface $entityManager): array
    {
        $userRoles = $entityManager->getRepository(UserRole::class)
            ->findBy(['roleId' => $this->id]);

        $users = [];
        foreach ($userRoles as $userRole) {
            $user = $entityManager->getRepository(User::class)
                ->find($userRole->getUserId());
            if ($user) {
                $users[] = $user;
            }
        }

        return $users;
    }
}

// Junction table entity
#[MtEntity(tableName: 'user_roles')]
class UserRole
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY
    )]
    private int $userId;

    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY
    )]
    private int $roleId;

    #[MtColumn(columnType: ColumnType::DATETIME)]
    private DateTime $assignedAt;

    public function __construct(int $userId, int $roleId)
    {
        $this->userId = $userId;
        $this->roleId = $roleId;
        $this->assignedAt = new DateTime();
    }

    // Getters and setters
    public function getUserId(): int { return $this->userId; }
    public function getRoleId(): int { return $this->roleId; }
    public function getAssignedAt(): DateTime { return $this->assignedAt; }
}
```

## Working with Relationships

### Repository Methods for Relationships

Create specialized repository methods for common relationship queries.

```php
class PostRepository extends EntityRepository
{
    /**
     * Find posts by author
     * 
     * @param int $authorId
     * @return array<Post>
     */
    public function findByAuthor(int $authorId): array
    {
        return $this->findBy(['authorId' => $authorId]);
    }

    /**
     * Find posts with author information
     * 
     * @return array<array{post: Post, author: User}>
     */
    public function findWithAuthors(): array
    {
        $queryBuilder = $this->createQueryBuilder();
        
        $results = $queryBuilder
            ->select('p.id', 'p.title', 'p.authorId', 'u.name as authorName')
            ->from('posts', 'p')
            ->join('users', 'u', 'p.authorId = u.id')
            ->orderBy('p.createdAt', 'DESC')
            ->getResult();

        $posts = [];
        foreach ($results as $row) {
            $post = $this->find($row['id']);
            $author = $this->getEntityManager()->getRepository(User::class)
                ->find($row['authorId']);
            
            $posts[] = [
                'post' => $post,
                'author' => $author
            ];
        }

        return $posts;
    }

    /**
     * Find recent posts by author
     * 
     * @param int $authorId
     * @param int $limit
     * @return array<Post>
     */
    public function findRecentByAuthor(int $authorId, int $limit = 10): array
    {
        return $this->createQueryBuilder()
            ->where('authorId', '=', $authorId)
            ->orderBy('createdAt', 'DESC')
            ->limit($limit)
            ->getResult();
    }
}

class UserRepository extends EntityRepository
{
    /**
     * Find users with their post counts
     * 
     * @return array<array{user: User, postCount: int}>
     */
    public function findWithPostCounts(): array
    {
        $queryBuilder = $this->createQueryBuilder();
        
        $results = $queryBuilder
            ->select('u.id', 'u.name', 'COUNT(p.id) as postCount')
            ->from('users', 'u')
            ->leftJoin('posts', 'p', 'u.id = p.authorId')
            ->groupBy('u.id', 'u.name')
            ->orderBy('postCount', 'DESC')
            ->getResult();

        $usersWithCounts = [];
        foreach ($results as $row) {
            $user = $this->find($row['id']);
            $usersWithCounts[] = [
                'user' => $user,
                'postCount' => (int) $row['postCount']
            ];
        }

        return $usersWithCounts;
    }
}
```

### Service Layer for Complex Operations

Use service classes to manage complex relationship operations.

```php
class UserRoleService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * Assign a role to a user
     */
    public function assignRole(int $userId, int $roleId): void
    {
        // Check if user exists
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }

        // Check if role exists
        $role = $this->entityManager->getRepository(Role::class)->find($roleId);
        if (!$role) {
            throw new InvalidArgumentException('Role not found');
        }

        // Check if assignment already exists
        $existing = $this->entityManager->getRepository(UserRole::class)
            ->findOneBy(['userId' => $userId, 'roleId' => $roleId]);
        
        if ($existing) {
            throw new InvalidArgumentException('User already has this role');
        }

        // Create assignment
        $userRole = new UserRole($userId, $roleId);
        $this->entityManager->persist($userRole);
        $this->entityManager->flush();
    }

    /**
     * Remove a role from a user
     */
    public function removeRole(int $userId, int $roleId): void
    {
        $userRole = $this->entityManager->getRepository(UserRole::class)
            ->findOneBy(['userId' => $userId, 'roleId' => $roleId]);

        if (!$userRole) {
            throw new InvalidArgumentException('User does not have this role');
        }

        $this->entityManager->remove($userRole);
        $this->entityManager->flush();
    }

    /**
     * Get all users with a specific role
     * 
     * @param int $roleId
     * @return array<User>
     */
    public function getUsersByRole(int $roleId): array
    {
        $userRoles = $this->entityManager->getRepository(UserRole::class)
            ->findBy(['roleId' => $roleId]);

        $users = [];
        foreach ($userRoles as $userRole) {
            $user = $this->entityManager->getRepository(User::class)
                ->find($userRole->getUserId());
            if ($user) {
                $users[] = $user;
            }
        }

        return $users;
    }

    /**
     * Check if user has role
     */
    public function userHasRole(int $userId, string $roleName): bool
    {
        $role = $this->entityManager->getRepository(Role::class)
            ->findOneBy(['name' => $roleName]);
        
        if (!$role) {
            return false;
        }

        $userRole = $this->entityManager->getRepository(UserRole::class)
            ->findOneBy(['userId' => $userId, 'roleId' => $role->getId()]);

        return $userRole !== null;
    }
}
```

## Query Builder with Relationships

Use Query Builder for efficient relationship queries.

```php
class BlogService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * Get blog posts with author and comment count
     * 
     * @return array
     */
    public function getPostsWithStats(): array
    {
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());

        return $queryBuilder
            ->select(
                'p.id',
                'p.title',
                'p.createdAt',
                'u.name as authorName',
                'COUNT(c.id) as commentCount'
            )
            ->from('posts', 'p')
            ->join('users', 'u', 'p.authorId = u.id')
            ->leftJoin('comments', 'c', 'p.id = c.postId')
            ->where('p.published', '=', true)
            ->groupBy('p.id', 'p.title', 'p.createdAt', 'u.name')
            ->orderBy('p.createdAt', 'DESC')
            ->getResult();
    }

    /**
     * Get user's posts with comment counts
     * 
     * @param int $userId
     * @return array
     */
    public function getUserPostsWithComments(int $userId): array
    {
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());

        return $queryBuilder
            ->select(
                'p.id',
                'p.title',
                'p.createdAt',
                'COUNT(c.id) as commentCount'
            )
            ->from('posts', 'p')
            ->leftJoin('comments', 'c', 'p.id = c.postId')
            ->where('p.authorId', '=', $userId)
            ->groupBy('p.id', 'p.title', 'p.createdAt')
            ->orderBy('p.createdAt', 'DESC')
            ->getResult();
    }
}
```

## Best Practices

### 1. Foreign Key Constraints

Always include proper indexing for foreign key fields:

```php
#[MtColumn(
    columnType: ColumnType::INT,
    columnKey: ColumnKey::INDEX,  // Important for performance
    nullable: false
)]
private int $authorId;
```

### 2. Validation in Services

Validate relationships in service layer:

```php
public function createPost(string $title, int $authorId): Post
{
    // Validate author exists
    $author = $this->entityManager->getRepository(User::class)->find($authorId);
    if (!$author) {
        throw new InvalidArgumentException('Author does not exist');
    }

    $post = new Post();
    $post->setTitle($title);
    $post->setAuthorId($authorId);
    
    $this->entityManager->persist($post);
    $this->entityManager->flush();
    
    return $post;
}
```

### 3. Lazy Loading Strategy

Load related entities only when needed:

```php
class Post
{
    private ?User $author = null;

    public function getAuthor(EntityManagerInterface $entityManager): User
    {
        if ($this->author === null) {
            $this->author = $entityManager->getRepository(User::class)
                ->find($this->authorId);
        }
        
        return $this->author;
    }
}
```

### 4. Cascading Operations

Handle cascading deletes in services:

```php
public function deleteUser(int $userId): void
{
    $user = $this->entityManager->getRepository(User::class)->find($userId);
    if (!$user) {
        throw new InvalidArgumentException('User not found');
    }

    // Delete related posts
    $posts = $this->entityManager->getRepository(Post::class)
        ->findBy(['authorId' => $userId]);
    
    foreach ($posts as $post) {
        $this->entityManager->remove($post);
    }

    // Delete user roles
    $userRoles = $this->entityManager->getRepository(UserRole::class)
        ->findBy(['userId' => $userId]);
    
    foreach ($userRoles as $userRole) {
        $this->entityManager->remove($userRole);
    }

    // Delete user
    $this->entityManager->remove($user);
    $this->entityManager->flush();
}
```

## Complete Example: Blog System

```php
// Create a complete blog post with author verification
class BlogPostService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function createPostWithValidation(
        string $title,
        string $content,
        int $authorId,
        array $tags = []
    ): Post {
        try {
            $this->entityManager->beginTransaction();

            // Validate author
            $author = $this->entityManager->getRepository(User::class)->find($authorId);
            if (!$author) {
                throw new InvalidArgumentException('Author not found');
            }

            // Create post
            $post = new Post();
            $post->setTitle($title);
            $post->setContent($content);
            $post->setAuthorId($authorId);
            $post->setPublished(true);

            $this->entityManager->persist($post);
            $this->entityManager->flush(); // Get post ID

            // Add tags (many-to-many relationship)
            foreach ($tags as $tagName) {
                $tag = $this->findOrCreateTag($tagName);
                
                $postTag = new PostTag($post->getId(), $tag->getId());
                $this->entityManager->persist($postTag);
            }

            $this->entityManager->commit();
            $this->entityManager->flush();

            return $post;

        } catch (Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    private function findOrCreateTag(string $name): Tag
    {
        $tag = $this->entityManager->getRepository(Tag::class)
            ->findOneBy(['name' => $name]);

        if (!$tag) {
            $tag = new Tag();
            $tag->setName($name);
            $this->entityManager->persist($tag);
            $this->entityManager->flush();
        }

        return $tag;
    }
}
```

## Next Steps

- [Entity Manager](../data-access/entity-manager.md) - Manage related entities
- [Repositories](../data-access/repositories.md) - Create relationship queries
- [Query Builder](../data-access/query-builder.md) - Build complex joins
