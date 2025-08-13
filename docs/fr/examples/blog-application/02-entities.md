# Définition des Entités

Ce chapitre présente toutes les entités du blog avec leurs relations, attributs et fonctionnalités avancées.

## Table des Matières
- [Entité User](#entité-user)
- [Entité Category](#entité-category)
- [Entité Post](#entité-post)
- [Entité Tag](#entité-tag)
- [Entité Comment](#entité-comment)
- [Value Objects](#value-objects)
- [Énumérations](#énumérations)

## Entité User

### Définition complète

```php
<?php

declare(strict_types=1);

namespace Blog\Entity;

use MulerTech\Database\Mapping\Attributes as ORM;
use Blog\ValueObject\Email;
use Blog\Enum\UserRole;
use Blog\Enum\UserStatus;
use DateTime;
use DateTimeInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[ORM\MtEntity(table: 'users')]
class User
{
    #[ORM\MtColumn(type: 'integer', primaryKey: true, autoIncrement: true)]
    private ?int $id = null;

    #[ORM\MtColumn(type: 'email', unique: true)]
    private Email $email;

    #[ORM\MtColumn(type: 'string', length: 100)]
    private string $firstName;

    #[ORM\MtColumn(type: 'string', length: 100)]
    private string $lastName;

    #[ORM\MtColumn(type: 'string', length: 255)]
    private string $passwordHash;

    #[ORM\MtColumn(type: 'user_role', default: 'user')]
    private UserRole $role = UserRole::USER;

    #[ORM\MtColumn(type: 'user_status', default: 'active')]
    private UserStatus $status = UserStatus::ACTIVE;

    #[ORM\MtColumn(type: 'string', length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\MtColumn(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\MtColumn(type: 'datetime')]
    private DateTimeInterface $createdAt;

    #[ORM\MtColumn(type: 'datetime')]
    private DateTimeInterface $updatedAt;

    #[ORM\MtColumn(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $lastLoginAt = null;

    #[ORM\MtColumn(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $deletedAt = null;

    /** @var Collection<int, Post> */
    #[ORM\MtOneToMany(targetEntity: Post::class, mappedBy: 'author', cascade: ['persist'])]
    private Collection $posts;

    /** @var Collection<int, Comment> */
    #[ORM\MtOneToMany(targetEntity: Comment::class, mappedBy: 'author', cascade: ['persist'])]
    private Collection $comments;

    public function __construct(Email $email, string $firstName, string $lastName)
    {
        $this->email = $email;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
        $this->posts = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    // Getters et setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function setEmail(Email $email): void
    {
        $this->email = $email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function setRole(UserRole $role): void
    {
        $this->role = $role;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function setStatus(UserStatus $status): void
    {
        $this->status = $status;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE && $this->deletedAt === null;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): void
    {
        $this->avatar = $avatar;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): void
    {
        $this->bio = $bio;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getLastLoginAt(): ?DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?DateTimeInterface $lastLoginAt): void
    {
        $this->lastLoginAt = $lastLoginAt;
    }

    public function recordLogin(): void
    {
        $this->lastLoginAt = new DateTime();
    }

    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function delete(): void
    {
        $this->deletedAt = new DateTime();
        $this->status = UserStatus::DELETED;
    }

    public function restore(): void
    {
        $this->deletedAt = null;
        $this->status = UserStatus::ACTIVE;
    }

    /** @return Collection<int, Post> */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): void
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setAuthor($this);
        }
    }

    public function removePost(Post $post): void
    {
        if ($this->posts->contains($post)) {
            $this->posts->removeElement($post);
            $post->setAuthor(null);
        }
    }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): void
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setAuthor($this);
        }
    }
}
```

## Entité Category

```php
<?php

declare(strict_types=1);

namespace Blog\Entity;

use MulerTech\Database\Mapping\Attributes as ORM;
use Blog\ValueObject\Slug;
use DateTime;
use DateTimeInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[ORM\MtEntity(table: 'categories')]
class Category
{
    #[ORM\MtColumn(type: 'integer', primaryKey: true, autoIncrement: true)]
    private ?int $id = null;

    #[ORM\MtColumn(type: 'string', length: 100)]
    private string $name;

    #[ORM\MtColumn(type: 'slug', unique: true)]
    private Slug $slug;

    #[ORM\MtColumn(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\MtColumn(type: 'string', length: 7, default: '#6B7280')]
    private string $color = '#6B7280';

    #[ORM\MtColumn(type: 'string', length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\MtColumn(type: 'integer', default: 0)]
    private int $sortOrder = 0;

    #[ORM\MtColumn(type: 'boolean', default: true)]
    private bool $isActive = true;

    #[ORM\MtManyToOne(targetEntity: self::class, mappedBy: 'parent')]
    private ?Category $parent = null;

    /** @var Collection<int, Category> */
    #[ORM\MtOneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist'])]
    private Collection $children;

    /** @var Collection<int, Post> */
    #[ORM\MtOneToMany(targetEntity: Post::class, mappedBy: 'category')]
    private Collection $posts;

    #[ORM\MtColumn(type: 'datetime')]
    private DateTimeInterface $createdAt;

    #[ORM\MtColumn(type: 'datetime')]
    private DateTimeInterface $updatedAt;

    public function __construct(string $name, ?Category $parent = null)
    {
        $this->name = $name;
        $this->slug = Slug::fromString($name);
        $this->parent = $parent;
        $this->children = new ArrayCollection();
        $this->posts = new ArrayCollection();
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }


    // Getters et setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->slug = Slug::fromString($name);
    }

    public function getSlug(): Slug
    {
        return $this->slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): void
    {
        $this->color = $color;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): void
    {
        $this->image = $image;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getParent(): ?Category
    {
        return $this->parent;
    }

    public function setParent(?Category $parent): void
    {
        $this->parent = $parent;
    }

    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    public function getLevel(): int
    {
        return $this->parent ? $this->parent->getLevel() + 1 : 0;
    }

    public function getPath(): string
    {
        $path = [];
        $current = $this;
        
        while ($current !== null) {
            array_unshift($path, $current->getName());
            $current = $current->getParent();
        }
        
        return implode(' > ', $path);
    }

    /** @return Collection<int, Category> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Category $child): void
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
    }

    public function removeChild(Category $child): void
    {
        if ($this->children->contains($child)) {
            $this->children->removeElement($child);
            $child->setParent(null);
        }
    }

    /** @return Collection<int, Post> */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function getPostCount(): int
    {
        return $this->posts->count();
    }

    public function getActivePostCount(): int
    {
        return $this->posts->filter(fn(Post $post) => $post->isPublished())->count();
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }
}
```

## Entité Post

```php
<?php

declare(strict_types=1);

namespace Blog\Entity;

use MulerTech\Database\Mapping\Attributes as ORM;
use Blog\ValueObject\Slug;
use Blog\Enum\PostStatus;
use DateTime;
use DateTimeInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[ORM\MtEntity(table: 'posts')]
class Post
{
    #[ORM\MtColumn(type: 'integer', primaryKey: true, autoIncrement: true)]
    private ?int $id = null;

    #[ORM\MtColumn(type: 'string', length: 255)]
    private string $title;

    #[ORM\MtColumn(type: 'slug', unique: true)]
    private Slug $slug;

    #[ORM\MtColumn(type: 'text', nullable: true)]
    private ?string $excerpt = null;

    #[ORM\MtColumn(type: 'longtext')]
    private string $content;

    #[ORM\MtColumn(type: 'string', length: 255, nullable: true)]
    private ?string $featuredImage = null;

    #[ORM\MtColumn(type: 'post_status', default: 'draft')]
    private PostStatus $status = PostStatus::DRAFT;

    #[ORM\MtColumn(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $publishedAt = null;

    #[ORM\MtColumn(type: 'integer', default: 0)]
    private int $viewCount = 0;

    #[ORM\MtColumn(type: 'boolean', default: true)]
    private bool $allowComments = true;

    #[ORM\MtColumn(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\MtManyToOne(targetEntity: User::class, mappedBy: 'author')]
    private User $author;

    #[ORM\MtManyToOne(targetEntity: Category::class, mappedBy: 'category')]
    private ?Category $category = null;

    /** @var Collection<int, Tag> */
    #[ORM\MtManyToMany(targetEntity: Tag::class, mappedBy: 'PostTag')]
    private Collection $tags;

    /** @var Collection<int, Comment> */
    #[ORM\MtOneToMany(targetEntity: Comment::class, mappedBy: 'post', cascade: ['persist', 'remove'])]
    private Collection $comments;

    #[ORM\MtColumn(type: 'datetime')]
    private DateTimeInterface $createdAt;

    #[ORM\MtColumn(type: 'datetime')]
    private DateTimeInterface $updatedAt;

    #[ORM\MtColumn(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $deletedAt = null;

    public function __construct(string $title, string $content, User $author)
    {
        $this->title = $title;
        $this->slug = Slug::fromString($title);
        $this->content = $content;
        $this->author = $author;
        $this->tags = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }


    // Getters et setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
        $this->slug = Slug::fromString($title);
    }

    public function getSlug(): Slug
    {
        return $this->slug;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt ?? $this->generateExcerpt();
    }

    public function setExcerpt(?string $excerpt): void
    {
        $this->excerpt = $excerpt;
    }

    private function generateExcerpt(int $length = 160): string
    {
        $text = strip_tags($this->content);
        return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getFeaturedImage(): ?string
    {
        return $this->featuredImage;
    }

    public function setFeaturedImage(?string $featuredImage): void
    {
        $this->featuredImage = $featuredImage;
    }

    public function getStatus(): PostStatus
    {
        return $this->status;
    }

    public function setStatus(PostStatus $status): void
    {
        $this->status = $status;
        
        if ($status === PostStatus::PUBLISHED && $this->publishedAt === null) {
            $this->publishedAt = new DateTime();
        }
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::PUBLISHED 
            && $this->publishedAt !== null 
            && $this->publishedAt <= new DateTime()
            && $this->deletedAt === null;
    }

    public function isDraft(): bool
    {
        return $this->status === PostStatus::DRAFT;
    }

    public function publish(): void
    {
        $this->status = PostStatus::PUBLISHED;
        $this->publishedAt = new DateTime();
    }

    public function unpublish(): void
    {
        $this->status = PostStatus::DRAFT;
    }

    public function getPublishedAt(): ?DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?DateTimeInterface $publishedAt): void
    {
        $this->publishedAt = $publishedAt;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function incrementViewCount(): void
    {
        $this->viewCount++;
    }

    public function getAllowComments(): bool
    {
        return $this->allowComments;
    }

    public function setAllowComments(bool $allowComments): void
    {
        $this->allowComments = $allowComments;
    }

    /** @return array<string, mixed>|null */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed>|null $metadata */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): void
    {
        $this->author = $author;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): void
    {
        $this->category = $category;
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): void
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            $tag->addPost($this);
        }
    }

    public function removeTag(Tag $tag): void
    {
        if ($this->tags->contains($tag)) {
            $this->tags->removeElement($tag);
            $tag->removePost($this);
        }
    }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function getApprovedComments(): Collection
    {
        return $this->comments->filter(fn(Comment $comment) => $comment->isApproved());
    }

    public function getCommentCount(): int
    {
        return $this->getApprovedComments()->count();
    }

    public function addComment(Comment $comment): void
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setPost($this);
        }
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function delete(): void
    {
        $this->deletedAt = new DateTime();
    }

    public function restore(): void
    {
        $this->deletedAt = null;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}
```

## Entité Tag

```php
<?php

declare(strict_types=1);

namespace Blog\Entity;

use MulerTech\Database\Mapping\Attributes as ORM;
use Blog\ValueObject\Slug;
use DateTime;
use DateTimeInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[ORM\MtEntity(table: 'tags')]
class Tag
{
    #[ORM\MtColumn(type: 'integer', primaryKey: true, autoIncrement: true)]
    private ?int $id = null;

    #[ORM\MtColumn(type: 'string', length: 50)]
    private string $name;

    #[ORM\MtColumn(type: 'slug', unique: true)]
    private Slug $slug;

    #[ORM\MtColumn(type: 'string', length: 7, default: '#3B82F6')]
    private string $color = '#3B82F6';

    #[ORM\MtColumn(type: 'text', nullable: true)]
    private ?string $description = null;

    /** @var Collection<int, Post> */
    #[ORM\MtManyToMany(targetEntity: Post::class, mappedBy: 'tags')]
    private Collection $posts;

    #[ORM\MtColumn(type: 'datetime')]
    private DateTimeInterface $createdAt;

    #[ORM\MtColumn(type: 'datetime')]
    private DateTimeInterface $updatedAt;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->slug = Slug::fromString($name);
        $this->posts = new ArrayCollection();
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->slug = Slug::fromString($name);
    }

    public function getSlug(): Slug
    {
        return $this->slug;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): void
    {
        $this->color = $color;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /** @return Collection<int, Post> */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function getPostCount(): int
    {
        return $this->posts->count();
    }

    public function getPublishedPostCount(): int
    {
        return $this->posts->filter(fn(Post $post) => $post->isPublished())->count();
    }

    public function addPost(Post $post): void
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
        }
    }

    public function removePost(Post $post): void
    {
        $this->posts->removeElement($post);
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }
}
```

## Entité Comment

```php
<?php

declare(strict_types=1);

namespace Blog\Entity;

use MulerTech\Database\Mapping\Attributes as ORM;
use Blog\Enum\CommentStatus;
use DateTime;
use DateTimeInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[ORM\MtEntity(table: 'comments')]
class Comment
{
    #[ORM\MtColumn(type: 'integer', primaryKey: true, autoIncrement: true)]
    private ?int $id = null;

    #[ORM\MtColumn(type: 'text')]
    private string $content;

    #[ORM\MtColumn(type: 'comment_status', default: 'pending')]
    private CommentStatus $status = CommentStatus::PENDING;

    #[ORM\MtColumn(type: 'string', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\MtColumn(type: 'text', nullable: true)]
    private ?string $userAgent = null;

    #[ORM\MtManyToOne(targetEntity: Post::class, mappedBy: 'post')]
    private Post $post;

    #[ORM\MtManyToOne(targetEntity: User::class, mappedBy: 'author')]
    private User $author;

    #[ORM\MtManyToOne(targetEntity: self::class, mappedBy: 'parent')]
    private ?Comment $parent = null;

    /** @var Collection<int, Comment> */
    #[ORM\MtOneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist', 'remove'])]
    private Collection $replies;

    #[ORM\MtColumn(type: 'datetime')]
    private DateTimeInterface $createdAt;

    #[ORM\MtColumn(type: 'datetime')]
    private DateTimeInterface $updatedAt;

    public function __construct(string $content, Post $post, User $author, ?Comment $parent = null)
    {
        $this->content = $content;
        $this->post = $post;
        $this->author = $author;
        $this->parent = $parent;
        $this->replies = new ArrayCollection();
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getStatus(): CommentStatus
    {
        return $this->status;
    }

    public function setStatus(CommentStatus $status): void
    {
        $this->status = $status;
    }

    public function isApproved(): bool
    {
        return $this->status === CommentStatus::APPROVED;
    }

    public function approve(): void
    {
        $this->status = CommentStatus::APPROVED;
    }

    public function reject(): void
    {
        $this->status = CommentStatus::REJECTED;
    }

    public function markAsSpam(): void
    {
        $this->status = CommentStatus::SPAM;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    public function getPost(): Post
    {
        return $this->post;
    }

    public function setPost(Post $post): void
    {
        $this->post = $post;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): void
    {
        $this->author = $author;
    }

    public function getParent(): ?Comment
    {
        return $this->parent;
    }

    public function setParent(?Comment $parent): void
    {
        $this->parent = $parent;
    }

    public function isReply(): bool
    {
        return $this->parent !== null;
    }

    public function getLevel(): int
    {
        return $this->parent ? $this->parent->getLevel() + 1 : 0;
    }

    /** @return Collection<int, Comment> */
    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function getApprovedReplies(): Collection
    {
        return $this->replies->filter(fn(Comment $reply) => $reply->isApproved());
    }

    public function addReply(Comment $reply): void
    {
        if (!$this->replies->contains($reply)) {
            $this->replies->add($reply);
            $reply->setParent($this);
        }
    }

    public function removeReply(Comment $reply): void
    {
        if ($this->replies->contains($reply)) {
            $this->replies->removeElement($reply);
            $reply->setParent(null);
        }
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }
}
```

## Value Objects

### Email Value Object

```php
<?php

declare(strict_types=1);

namespace Blog\ValueObject;

use InvalidArgumentException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
readonly class Email
{
    private string $value;

    public function __construct(string $email)
    {
        $email = trim(strtolower($email));
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email format: {$email}");
        }

        $this->value = $email;
    }

    public static function fromString(string $email): self
    {
        return new self($email);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getDomain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }

    public function getLocalPart(): string
    {
        return substr($this->value, 0, strpos($this->value, '@'));
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

### Slug Value Object

```php
<?php

declare(strict_types=1);

namespace Blog\ValueObject;

use InvalidArgumentException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
readonly class Slug
{
    private string $value;

    public function __construct(string $slug)
    {
        $slug = $this->normalize($slug);
        
        if (!$this->isValid($slug)) {
            throw new InvalidArgumentException("Invalid slug format: {$slug}");
        }

        $this->value = $slug;
    }

    public static function fromString(string $text): self
    {
        return new self($text);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Slug $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function normalize(string $text): string
    {
        // Convertir en minuscules
        $text = strtolower($text);
        
        // Remplacer les caractères accentués
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        
        // Remplacer les caractères non alphanumériques par des tirets
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        
        // Supprimer les tirets en début/fin
        $text = trim($text, '-');
        
        return $text;
    }

    private function isValid(string $slug): bool
    {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }
}
```

## Énumérations

### UserRole Enum

```php
<?php

declare(strict_types=1);

namespace Blog\Enum;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
enum UserRole: string
{
    case USER = 'user';
    case EDITOR = 'editor';
    case ADMIN = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::USER => 'Utilisateur',
            self::EDITOR => 'Éditeur',
            self::ADMIN => 'Administrateur',
        };
    }

    /** @return array<string> */
    public function getPermissions(): array
    {
        return match ($this) {
            self::USER => ['comment', 'profile.edit'],
            self::EDITOR => ['comment', 'profile.edit', 'post.create', 'post.edit'],
            self::ADMIN => ['*'],
        };
    }
}
```

### PostStatus Enum

```php
<?php

declare(strict_types=1);

namespace Blog\Enum;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
enum PostStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::PUBLISHED => 'Publié',
            self::ARCHIVED => 'Archivé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PUBLISHED => 'green',
            self::ARCHIVED => 'yellow',
        };
    }
}
```

---

**Prochaine étape :** [Repositories personnalisés](03-repositories.md) - Création des couches d'accès aux données avec logique métier.
