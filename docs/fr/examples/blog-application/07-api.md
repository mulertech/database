# API REST - Application Blog

Cette section présente l'implémentation d'une API REST complète pour l'application blog, démontrant les bonnes pratiques avec MulerTech Database ORM.

## Table des matières

- [Architecture API](#architecture-api)
- [Contrôleurs API](#contrôleurs-api)
- [Authentification](#authentification)
- [Sérialization](#sérialization)
- [Validation](#validation)
- [Pagination](#pagination)
- [Gestion des erreurs](#gestion-des-erreurs)
- [Documentation OpenAPI](#documentation-openapi)

## Architecture API

### Structure des endpoints

```
/api/v1/
├── auth/
│   ├── POST /login
│   ├── POST /logout
│   ├── POST /refresh
│   └── GET  /me
├── posts/
│   ├── GET    /posts
│   ├── POST   /posts
│   ├── GET    /posts/{id}
│   ├── PUT    /posts/{id}
│   ├── DELETE /posts/{id}
│   └── GET    /posts/{id}/comments
├── categories/
│   ├── GET    /categories
│   ├── POST   /categories
│   ├── GET    /categories/{id}
│   ├── PUT    /categories/{id}
│   └── DELETE /categories/{id}
├── tags/
│   ├── GET    /tags
│   ├── POST   /tags
│   ├── GET    /tags/{id}
│   ├── PUT    /tags/{id}
│   └── DELETE /tags/{id}
├── comments/
│   ├── GET    /comments
│   ├── POST   /comments
│   ├── GET    /comments/{id}
│   ├── PUT    /comments/{id}
│   └── DELETE /comments/{id}
└── users/
    ├── GET    /users
    ├── POST   /users
    ├── GET    /users/{id}
    ├── PUT    /users/{id}
    └── DELETE /users/{id}
```

### Classe de base pour les contrôleurs API

```php
<?php

namespace App\Controller\Api;

use MulerTech\Database\ORM\EntityManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractApiController
{
    protected EntityManager $em;
    protected SerializerInterface $serializer;
    protected ValidatorInterface $validator;
    
    public function __construct(
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ) {
        $this->em = $em;
        $this->serializer = $serializer;
        $this->validator = $validator;
    }
    
    protected function jsonResponse(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        if (is_object($data) || is_array($data)) {
            $json = $this->serializer->serialize($data, 'json', [
                'groups' => $this->getSerializationGroups()
            ]);
            
            return new JsonResponse($json, $status, $headers, true);
        }
        
        return new JsonResponse($data, $status, $headers);
    }
    
    protected function createErrorResponse(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $data = [
            'error' => [
                'message' => $message,
                'code' => $status,
            ]
        ];
        
        if (!empty($errors)) {
            $data['error']['details'] = $errors;
        }
        
        return $this->jsonResponse($data, $status);
    }
    
    protected function validateEntity(object $entity): array
    {
        $violations = $this->validator->validate($entity);
        $errors = [];
        
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }
        
        return $errors;
    }
    
    protected function getSerializationGroups(): array
    {
        return ['api'];
    }
}
```

## Contrôleurs API

### Contrôleur Posts

```php
<?php

namespace App\Controller\Api;

use App\Entity\Post;
use App\Repository\PostRepository;
use App\Service\PostService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/posts')]
class PostController extends AbstractApiController
{
    private PostRepository $postRepository;
    private PostService $postService;
    
    public function __construct(
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        PostRepository $postRepository,
        PostService $postService
    ) {
        parent::__construct($em, $serializer, $validator);
        $this->postRepository = $postRepository;
        $this->postService = $postService;
    }
    
    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $limit = min((int) $request->query->get('limit', 10), 50);
        $status = $request->query->get('status');
        $categoryId = $request->query->get('category_id');
        $search = $request->query->get('search');
        
        $criteria = [];
        if ($status) {
            $criteria['status'] = $status;
        }
        if ($categoryId) {
            $criteria['category_id'] = $categoryId;
        }
        
        if ($search) {
            $posts = $this->postRepository->searchPosts($search, $criteria, $page, $limit);
            $total = $this->postRepository->countSearchResults($search, $criteria);
        } else {
            $posts = $this->postRepository->findPaginated($criteria, $page, $limit);
            $total = $this->postRepository->countByCriteria($criteria);
        }
        
        return $this->jsonResponse([
            'data' => $posts,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $post = new Post();
        $post->setTitle($data['title'] ?? '')
             ->setContent($data['content'] ?? '')
             ->setStatus($data['status'] ?? Post::STATUS_DRAFT)
             ->setUserId($this->getCurrentUserId())
             ->setCategoryId($data['category_id'] ?? null);
        
        // Gérer les tags
        if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
            $this->postService->assignTags($post, $data['tag_ids']);
        }
        
        $errors = $this->validateEntity($post);
        if (!empty($errors)) {
            return $this->createErrorResponse('Données invalides', 422, $errors);
        }
        
        try {
            $this->em->persist($post);
            $this->em->flush();
            
            return $this->jsonResponse($post, 201);
        } catch (\Exception $e) {
            return $this->createErrorResponse('Erreur lors de la création du post', 500);
        }
    }
    
    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $post = $this->postRepository->findWithRelations($id);
        
        if (!$post) {
            return $this->createErrorResponse('Post non trouvé', 404);
        }
        
        return $this->jsonResponse($post);
    }
    
    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $post = $this->postRepository->find($id);
        
        if (!$post) {
            return $this->createErrorResponse('Post non trouvé', 404);
        }
        
        if ($post->getUserId() !== $this->getCurrentUserId() && !$this->isAdmin()) {
            return $this->createErrorResponse('Accès non autorisé', 403);
        }
        
        $data = json_decode($request->getContent(), true);
        
        if (isset($data['title'])) {
            $post->setTitle($data['title']);
        }
        if (isset($data['content'])) {
            $post->setContent($data['content']);
        }
        if (isset($data['status'])) {
            $post->setStatus($data['status']);
        }
        if (isset($data['category_id'])) {
            $post->setCategoryId($data['category_id']);
        }
        
        // Gérer les tags
        if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
            $this->postService->assignTags($post, $data['tag_ids']);
        }
        
        $errors = $this->validateEntity($post);
        if (!empty($errors)) {
            return $this->createErrorResponse('Données invalides', 422, $errors);
        }
        
        try {
            $this->em->flush();
            return $this->jsonResponse($post);
        } catch (\Exception $e) {
            return $this->createErrorResponse('Erreur lors de la mise à jour', 500);
        }
    }
    
    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $post = $this->postRepository->find($id);
        
        if (!$post) {
            return $this->createErrorResponse('Post non trouvé', 404);
        }
        
        if ($post->getUserId() !== $this->getCurrentUserId() && !$this->isAdmin()) {
            return $this->createErrorResponse('Accès non autorisé', 403);
        }
        
        try {
            // Soft delete
            $post->setDeletedAt(new \DateTimeImmutable());
            $this->em->flush();
            
            return $this->jsonResponse(['message' => 'Post supprimé avec succès']);
        } catch (\Exception $e) {
            return $this->createErrorResponse('Erreur lors de la suppression', 500);
        }
    }
    
    #[Route('/{id}/comments', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getComments(int $id, Request $request): JsonResponse
    {
        $post = $this->postRepository->find($id);
        
        if (!$post) {
            return $this->createErrorResponse('Post non trouvé', 404);
        }
        
        $page = (int) $request->query->get('page', 1);
        $limit = min((int) $request->query->get('limit', 10), 50);
        
        $comments = $this->em->getRepository(Comment::class)
                           ->findPostComments($id, $page, $limit);
        
        return $this->jsonResponse([
            'data' => $comments,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit
            ]
        ]);
    }
    
    protected function getSerializationGroups(): array
    {
        return ['api', 'post_details'];
    }
    
    private function getCurrentUserId(): int
    {
        // Récupérer l'ID de l'utilisateur authentifié
        // Implementation dépend du système d'auth utilisé
        return 1; // Exemple
    }
    
    private function isAdmin(): bool
    {
        // Vérifier si l'utilisateur est admin
        return false; // Exemple
    }
}
```

### Contrôleur Comments

```php
<?php

namespace App\Controller\Api;

use App\Entity\Comment;
use App\Repository\CommentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/comments')]
class CommentController extends AbstractApiController
{
    private CommentRepository $commentRepository;
    
    public function __construct(
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CommentRepository $commentRepository
    ) {
        parent::__construct($em, $serializer, $validator);
        $this->commentRepository = $commentRepository;
    }
    
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $comment = new Comment();
        $comment->setContent($data['content'] ?? '')
                ->setPostId($data['post_id'] ?? null)
                ->setUserId($this->getCurrentUserId())
                ->setParentId($data['parent_id'] ?? null)
                ->setStatus(Comment::STATUS_PUBLISHED);
        
        $errors = $this->validateEntity($comment);
        if (!empty($errors)) {
            return $this->createErrorResponse('Données invalides', 422, $errors);
        }
        
        // Vérifier que le post existe
        $post = $this->em->getRepository(Post::class)->find($comment->getPostId());
        if (!$post) {
            return $this->createErrorResponse('Post non trouvé', 404);
        }
        
        // Vérifier que le commentaire parent existe si spécifié
        if ($comment->getParentId()) {
            $parentComment = $this->commentRepository->find($comment->getParentId());
            if (!$parentComment || $parentComment->getPostId() !== $comment->getPostId()) {
                return $this->createErrorResponse('Commentaire parent invalide', 400);
            }
        }
        
        try {
            $this->em->persist($comment);
            $this->em->flush();
            
            return $this->jsonResponse($comment, 201);
        } catch (\Exception $e) {
            return $this->createErrorResponse('Erreur lors de la création du commentaire', 500);
        }
    }
    
    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $comment = $this->commentRepository->find($id);
        
        if (!$comment) {
            return $this->createErrorResponse('Commentaire non trouvé', 404);
        }
        
        if ($comment->getUserId() !== $this->getCurrentUserId() && !$this->isAdmin()) {
            return $this->createErrorResponse('Accès non autorisé', 403);
        }
        
        $data = json_decode($request->getContent(), true);
        
        if (isset($data['content'])) {
            $comment->setContent($data['content']);
        }
        
        $errors = $this->validateEntity($comment);
        if (!empty($errors)) {
            return $this->createErrorResponse('Données invalides', 422, $errors);
        }
        
        try {
            $this->em->flush();
            return $this->jsonResponse($comment);
        } catch (\Exception $e) {
            return $this->createErrorResponse('Erreur lors de la mise à jour', 500);
        }
    }
    
    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $comment = $this->commentRepository->find($id);
        
        if (!$comment) {
            return $this->createErrorResponse('Commentaire non trouvé', 404);
        }
        
        if ($comment->getUserId() !== $this->getCurrentUserId() && !$this->isAdmin()) {
            return $this->createErrorResponse('Accès non autorisé', 403);
        }
        
        try {
            $this->em->remove($comment);
            $this->em->flush();
            
            return $this->jsonResponse(['message' => 'Commentaire supprimé avec succès']);
        } catch (\Exception $e) {
            return $this->createErrorResponse('Erreur lors de la suppression', 500);
        }
    }
    
    protected function getSerializationGroups(): array
    {
        return ['api', 'comment_details'];
    }
}
```

## Authentification

### JWT Token Service

```php
<?php

namespace App\Service;

use App\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtTokenService
{
    private string $secretKey;
    private int $tokenTtl;
    
    public function __construct(string $secretKey = 'your-secret-key', int $tokenTtl = 3600)
    {
        $this->secretKey = $secretKey;
        $this->tokenTtl = $tokenTtl;
    }
    
    public function createToken(User $user): string
    {
        $payload = [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
            'iat' => time(),
            'exp' => time() + $this->tokenTtl
        ];
        
        return JWT::encode($payload, $this->secretKey, 'HS256');
    }
    
    public function validateToken(string $token): array|null
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function refreshToken(string $token): string|null
    {
        $payload = $this->validateToken($token);
        
        if (!$payload) {
            return null;
        }
        
        // Créer un nouveau token avec les mêmes données
        $newPayload = [
            'user_id' => $payload['user_id'],
            'email' => $payload['email'],
            'role' => $payload['role'],
            'iat' => time(),
            'exp' => time() + $this->tokenTtl
        ];
        
        return JWT::encode($newPayload, $this->secretKey, 'HS256');
    }
}
```

### Contrôleur d'authentification

```php
<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\JwtTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/auth')]
class AuthController extends AbstractApiController
{
    private JwtTokenService $jwtService;
    private UserPasswordHasherInterface $passwordHasher;
    
    public function __construct(
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        JwtTokenService $jwtService,
        UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct($em, $serializer, $validator);
        $this->jwtService = $jwtService;
        $this->passwordHasher = $passwordHasher;
    }
    
    #[Route('/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            return $this->createErrorResponse('Email et mot de passe requis', 400);
        }
        
        $user = $this->em->getRepository(User::class)->findByEmail($email);
        
        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->createErrorResponse('Identifiants invalides', 401);
        }
        
        if ($user->getDeletedAt()) {
            return $this->createErrorResponse('Compte désactivé', 403);
        }
        
        $token = $this->jwtService->createToken($user);
        
        return $this->jsonResponse([
            'token' => $token,
            'user' => $user,
            'expires_in' => 3600
        ]);
    }
    
    #[Route('/refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->createErrorResponse('Token manquant', 401);
        }
        
        $token = substr($authHeader, 7);
        $newToken = $this->jwtService->refreshToken($token);
        
        if (!$newToken) {
            return $this->createErrorResponse('Token invalide', 401);
        }
        
        return $this->jsonResponse([
            'token' => $newToken,
            'expires_in' => 3600
        ]);
    }
    
    #[Route('/me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        $user = $this->getCurrentUser($request);
        
        if (!$user) {
            return $this->createErrorResponse('Non authentifié', 401);
        }
        
        return $this->jsonResponse($user);
    }
    
    private function getCurrentUser(Request $request): ?User
    {
        $authHeader = $request->headers->get('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }
        
        $token = substr($authHeader, 7);
        $payload = $this->jwtService->validateToken($token);
        
        if (!$payload) {
            return null;
        }
        
        return $this->em->getRepository(User::class)->find($payload['user_id']);
    }
}
```

## Sérialization

### Configuration des groupes de sérialisation

```php
<?php

namespace App\Entity;

use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use Symfony\Component\Serializer\Annotation\Groups;

#[MtEntity(table: 'posts')]
class Post extends BaseEntity
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    #[Groups(['api', 'post_list', 'post_details'])]
    private int $id;
    
    #[MtColumn(type: 'varchar', length: 255)]
    #[Groups(['api', 'post_list', 'post_details'])]
    private string $title;
    
    #[MtColumn(type: 'varchar', length: 255, unique: true)]
    #[Groups(['api', 'post_details'])]
    private string $slug;
    
    #[MtColumn(type: 'text')]
    #[Groups(['api', 'post_details'])]
    private string $content;
    
    #[MtColumn(type: 'varchar', length: 20, default: 'draft')]
    #[Groups(['api', 'post_list', 'post_details'])]
    private string $status = self::STATUS_DRAFT;
    
    #[MtColumn(name: 'user_id', type: 'int')]
    #[Groups(['post_details'])]
    private int $userId;
    
    #[MtColumn(name: 'category_id', type: 'int', nullable: true)]
    #[Groups(['api', 'post_details'])]
    private ?int $categoryId = null;
    
    // Relations
    #[Groups(['post_details'])]
    private ?User $user = null;
    
    #[Groups(['api', 'post_details'])]
    private ?Category $category = null;
    
    #[Groups(['post_details'])]
    private array $tags = [];
    
    #[Groups(['post_details'])]
    private array $comments = [];
    
    // ... getters et setters
}
```

## Validation

### Règles de validation personnalisées

```php
<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use App\Repository\PostRepository;

class UniqueSlugValidator extends ConstraintValidator
{
    private PostRepository $postRepository;
    
    public function __construct(PostRepository $postRepository)
    {
        $this->postRepository = $postRepository;
    }
    
    public function validate($value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }
        
        $existingPost = $this->postRepository->findBySlug($value);
        
        if ($existingPost) {
            $this->context->buildViolation($constraint->message)
                          ->setParameter('{{ value }}', $value)
                          ->addViolation();
        }
    }
}

#[Attribute]
class UniqueSlug extends Constraint
{
    public string $message = 'Le slug "{{ value }}" est déjà utilisé.';
}
```

## Pagination

### Trait de pagination

```php
<?php

namespace App\Trait;

trait PaginationTrait
{
    protected function createPaginationResponse(
        array $data, 
        int $currentPage, 
        int $perPage, 
        int $total
    ): array {
        $totalPages = ceil($total / $perPage);
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next_page' => $currentPage < $totalPages,
                'has_previous_page' => $currentPage > 1,
                'next_page' => $currentPage < $totalPages ? $currentPage + 1 : null,
                'previous_page' => $currentPage > 1 ? $currentPage - 1 : null,
            ],
            'links' => [
                'first' => $this->generatePageUrl(1),
                'last' => $this->generatePageUrl($totalPages),
                'prev' => $currentPage > 1 ? $this->generatePageUrl($currentPage - 1) : null,
                'next' => $currentPage < $totalPages ? $this->generatePageUrl($currentPage + 1) : null,
            ]
        ];
    }
    
    private function generatePageUrl(int $page): string
    {
        // Logique pour générer l'URL de pagination
        return "/api/v1/posts?page={$page}";
    }
}
```

## Gestion des erreurs

### Middleware de gestion d'erreurs

```php
<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Données invalides',
                    'details' => $e->getMessage(),
                    'code' => 400
                ]
            ], 400);
        } catch (\UnauthorizedHttpException $e) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Non autorisé',
                    'code' => 401
                ]
            ], 401);
        } catch (\AccessDeniedHttpException $e) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Accès refusé',
                    'code' => 403
                ]
            ], 403);
        } catch (\NotFoundHttpException $e) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Ressource non trouvée',
                    'code' => 404
                ]
            ], 404);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Erreur interne du serveur',
                    'code' => 500
                ]
            ], 500);
        }
    }
}
```

## Documentation OpenAPI

### Annotations pour la documentation

```php
<?php

namespace App\Controller\Api;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Posts', description: 'Gestion des articles de blog')]
#[Route('/api/v1/posts')]
class PostController extends AbstractApiController
{
    #[OA\Get(
        path: '/api/v1/posts',
        summary: 'Liste des posts',
        description: 'Récupère une liste paginée des posts avec filtres optionnels',
        tags: ['Posts']
    )]
    #[OA\Parameter(
        name: 'page',
        description: 'Numéro de page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Nombre d\'éléments par page (max 50)',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 10, maximum: 50)
    )]
    #[OA\Parameter(
        name: 'status',
        description: 'Filtrer par statut',
        in: 'query',
        schema: new OA\Schema(type: 'string', enum: ['draft', 'published', 'archived'])
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des posts récupérée avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Post')
                ),
                new OA\Property(
                    property: 'pagination',
                    ref: '#/components/schemas/Pagination'
                )
            ]
        )
    )]
    public function index(Request $request): JsonResponse
    {
        // Implementation...
    }
    
    #[OA\Post(
        path: '/api/v1/posts',
        summary: 'Créer un post',
        description: 'Crée un nouveau post de blog',
        tags: ['Posts']
    )]
    #[OA\RequestBody(
        description: 'Données du post à créer',
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'content'],
            properties: [
                new OA\Property(property: 'title', type: 'string', maxLength: 255),
                new OA\Property(property: 'content', type: 'string'),
                new OA\Property(property: 'status', type: 'string', enum: ['draft', 'published'], default: 'draft'),
                new OA\Property(property: 'category_id', type: 'integer', nullable: true),
                new OA\Property(property: 'tag_ids', type: 'array', items: new OA\Items(type: 'integer'))
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Post créé avec succès',
        content: new OA\JsonContent(ref: '#/components/schemas/Post')
    )]
    #[OA\Response(
        response: 422,
        description: 'Données invalides',
        content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
    )]
    public function create(Request $request): JsonResponse
    {
        // Implementation...
    }
}
```

### Schémas OpenAPI

```yaml
# config/openapi_schemas.yaml
components:
  schemas:
    Post:
      type: object
      properties:
        id:
          type: integer
          readOnly: true
        title:
          type: string
          maxLength: 255
        slug:
          type: string
          readOnly: true
        content:
          type: string
        status:
          type: string
          enum: [draft, published, archived]
        user_id:
          type: integer
          readOnly: true
        category_id:
          type: integer
          nullable: true
        created_at:
          type: string
          format: date-time
          readOnly: true
        updated_at:
          type: string
          format: date-time
          readOnly: true
        user:
          $ref: '#/components/schemas/User'
        category:
          $ref: '#/components/schemas/Category'
        tags:
          type: array
          items:
            $ref: '#/components/schemas/Tag'
    
    Pagination:
      type: object
      properties:
        current_page:
          type: integer
        per_page:
          type: integer
        total:
          type: integer
        total_pages:
          type: integer
        has_next_page:
          type: boolean
        has_previous_page:
          type: boolean
    
    ValidationError:
      type: object
      properties:
        error:
          type: object
          properties:
            message:
              type: string
            code:
              type: integer
            details:
              type: object
              additionalProperties:
                type: string
```

## Tests de l'API

### Tests d'intégration

```php
<?php

namespace App\Tests\Controller\Api;

use App\Entity\Post;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class PostControllerTest extends WebTestCase
{
    private $client;
    private $em;
    
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }
    
    public function testGetPostsSuccess(): void
    {
        $response = $this->client->request('GET', '/api/v1/posts');
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
    }
    
    public function testCreatePostSuccess(): void
    {
        $user = $this->createTestUser();
        $token = $this->generateTokenForUser($user);
        
        $postData = [
            'title' => 'Mon nouveau post',
            'content' => 'Contenu du post de test',
            'status' => 'draft'
        ];
        
        $this->client->request(
            'POST',
            '/api/v1/posts',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode($postData)
        );
        
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Mon nouveau post', $data['title']);
        $this->assertEquals('draft', $data['status']);
    }
    
    public function testCreatePostValidationError(): void
    {
        $user = $this->createTestUser();
        $token = $this->generateTokenForUser($user);
        
        $postData = [
            'title' => '', // Titre vide - erreur de validation
            'content' => 'Contenu du post'
        ];
        
        $this->client->request(
            'POST',
            '/api/v1/posts',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode($postData)
        );
        
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('details', $data['error']);
    }
    
    private function createTestUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com')
             ->setName('Test User')
             ->setPassword('hashed_password')
             ->setRole('user');
        
        $this->em->persist($user);
        $this->em->flush();
        
        return $user;
    }
    
    private function generateTokenForUser(User $user): string
    {
        $jwtService = static::getContainer()->get(JwtTokenService::class);
        return $jwtService->createToken($user);
    }
}
```

---

Cette API REST complète démontre l'utilisation de MulerTech Database ORM dans un contexte d'application moderne avec authentification JWT, validation, pagination et documentation OpenAPI.
