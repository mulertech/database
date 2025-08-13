# Contrôleurs Web

Ce chapitre présente les contrôleurs web qui gèrent les requêtes HTTP, les formulaires et le rendu des vues.

## Table des Matières
- [Architecture des contrôleurs](#architecture-des-contrôleurs)
- [HomeController](#homecontroller)
- [AuthController](#authcontroller)
- [PostController](#postcontroller)
- [CommentController](#commentcontroller)
- [AdminController](#admincontroller)
- [Middleware et sécurité](#middleware-et-sécurité)

## Architecture des contrôleurs

### Contrôleur de base

```php
<?php

declare(strict_types=1);

namespace Blog\Controller;

use Blog\Service\AuthenticationService;
use Blog\Entity\User;
use Psr\Log\LoggerInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
abstract class AbstractController
{
    protected AuthenticationService $authService;
    protected LoggerInterface $logger;

    public function __construct(AuthenticationService $authService, LoggerInterface $logger)
    {
        $this->authService = $authService;
        $this->logger = $logger;
    }

    /**
     * @return User|null
     */
    protected function getCurrentUser(): ?User
    {
        $token = $_SESSION['auth_token'] ?? null;
        
        if (!$token) {
            return null;
        }

        return $this->authService->validateSessionToken($token);
    }

    /**
     * @param string $permission
     * @return bool
     */
    protected function hasPermission(string $permission): bool
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return false;
        }

        return $this->authService->hasPermission($user, $permission);
    }

    /**
     * @param string $permission
     * @throws \Blog\Exception\UnauthorizedActionException
     */
    protected function requirePermission(string $permission): void
    {
        if (!$this->hasPermission($permission)) {
            throw new \Blog\Exception\UnauthorizedActionException("Permission '{$permission}' required");
        }
    }

    /**
     * @throws \Blog\Exception\UnauthorizedActionException
     */
    protected function requireAuthentication(): void
    {
        if (!$this->getCurrentUser()) {
            throw new \Blog\Exception\UnauthorizedActionException("Authentication required");
        }
    }

    /**
     * @param string $template
     * @param array<string, mixed> $variables
     * @return Response
     */
    protected function render(string $template, array $variables = []): Response
    {
        // Ajouter des variables globales
        $variables['current_user'] = $this->getCurrentUser();
        $variables['is_authenticated'] = $this->getCurrentUser() !== null;
        
        return new Response($this->renderTemplate($template, $variables));
    }

    /**
     * @param string $url
     * @param int $statusCode
     * @return Response
     */
    protected function redirect(string $url, int $statusCode = 302): Response
    {
        return new Response('', $statusCode, ['Location' => $url]);
    }

    /**
     * @param array<string, mixed> $data
     * @param int $statusCode
     * @return Response
     */
    protected function json(array $data, int $statusCode = 200): Response
    {
        return new Response(
            json_encode($data, JSON_THROW_ON_ERROR),
            $statusCode,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * @param string $message
     * @param string $type
     */
    protected function addFlashMessage(string $message, string $type = 'info'): void
    {
        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }
        
        $_SESSION['flash_messages'][] = ['message' => $message, 'type' => $type];
    }

    /**
     * @return array<array{message: string, type: string}>
     */
    protected function getFlashMessages(): array
    {
        $messages = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        return $messages;
    }

    /**
     * @param string $template
     * @param array<string, mixed> $variables
     * @return string
     */
    private function renderTemplate(string $template, array $variables): string
    {
        // Système de template simple - dans un vrai projet, utilisez Twig ou similaire
        extract($variables);
        
        ob_start();
        include __DIR__ . "/../templates/{$template}.php";
        return ob_get_clean();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     * @return array<string, string>
     */
    protected function validateForm(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            if ($rule === 'required' && empty($value)) {
                $errors[$field] = "Le champ {$field} est requis";
            } elseif ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "Le champ {$field} doit être un email valide";
            } elseif (str_starts_with($rule, 'min:')) {
                $minLength = (int) substr($rule, 4);
                if (strlen($value) < $minLength) {
                    $errors[$field] = "Le champ {$field} doit contenir au moins {$minLength} caractères";
                }
            }
        }

        return $errors;
    }
}
```

### Response HTTP

```php
<?php

declare(strict_types=1);

namespace Blog\Controller;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class Response
{
    private string $content;
    private int $statusCode;
    /** @var array<string, string> */
    private array $headers;

    /**
     * @param string $content
     * @param int $statusCode
     * @param array<string, string> $headers
     */
    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        
        echo $this->content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
```

## HomeController

```php
<?php

declare(strict_types=1);

namespace Blog\Controller;

use Blog\Service\PostService;
use Blog\Service\CategoryService;
use Blog\Service\TagService;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class HomeController extends AbstractController
{
    private PostService $postService;
    private CategoryService $categoryService;
    private TagService $tagService;

    public function __construct(
        \Blog\Service\AuthenticationService $authService,
        \Psr\Log\LoggerInterface $logger,
        PostService $postService,
        CategoryService $categoryService,
        TagService $tagService
    ) {
        parent::__construct($authService, $logger);
        $this->postService = $postService;
        $this->categoryService = $categoryService;
        $this->tagService = $tagService;
    }

    /**
     * Page d'accueil avec les derniers articles
     */
    public function index(): Response
    {
        try {
            $page = (int) ($_GET['page'] ?? 1);
            $perPage = 10;

            // Récupérer les derniers articles publiés
            $postsData = $this->postService->getPublishedPosts($page, $perPage);
            
            // Articles mis en avant
            $featuredPosts = $this->postService->getRepository()->findFeaturedPosts();
            
            // Catégories populaires
            $popularCategories = $this->categoryService->getPopularCategories(5);
            
            // Tags populaires
            $popularTags = $this->tagService->getPopularTags(10);

            return $this->render('home/index', [
                'posts' => $postsData['data'],
                'pagination' => $postsData['pagination'],
                'featured_posts' => $featuredPosts,
                'popular_categories' => $popularCategories,
                'popular_tags' => $popularTags,
                'page_title' => 'Accueil - Mon Blog'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in home page', ['error' => $e->getMessage()]);
            return $this->render('error/500', ['error' => 'Une erreur est survenue']);
        }
    }

    /**
     * Page de recherche
     */
    public function search(): Response
    {
        $query = $_GET['q'] ?? '';
        $results = [];
        $totalResults = 0;

        if (!empty($query)) {
            $results = $this->postService->searchPosts($query);
            $totalResults = count($results);
            
            $this->logger->info('Search performed', [
                'query' => $query,
                'results_count' => $totalResults
            ]);
        }

        return $this->render('home/search', [
            'query' => $query,
            'results' => $results,
            'total_results' => $totalResults,
            'page_title' => "Recherche : {$query}"
        ]);
    }

    /**
     * À propos
     */
    public function about(): Response
    {
        return $this->render('home/about', [
            'page_title' => 'À propos'
        ]);
    }

    /**
     * Contact
     */
    public function contact(): Response
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handleContactForm();
        }

        return $this->render('home/contact', [
            'page_title' => 'Contact'
        ]);
    }

    /**
     * @return Response
     */
    private function handleContactForm(): Response
    {
        $data = $_POST;
        $errors = $this->validateForm($data, [
            'name' => 'required',
            'email' => 'email|required',
            'message' => 'required|min:10'
        ]);

        if (!empty($errors)) {
            return $this->render('home/contact', [
                'errors' => $errors,
                'form_data' => $data,
                'page_title' => 'Contact'
            ]);
        }

        // Traiter le message de contact (email, sauvegarde, etc.)
        $this->logger->info('Contact form submitted', [
            'name' => $data['name'],
            'email' => $data['email']
        ]);

        $this->addFlashMessage('Votre message a été envoyé avec succès !', 'success');
        return $this->redirect('/contact');
    }
}
```

## AuthController

```php
<?php

declare(strict_types=1);

namespace Blog\Controller;

use Blog\Service\UserService;
use Blog\Exception\InvalidCredentialsException;
use Blog\Exception\DuplicateEmailException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class AuthController extends AbstractController
{
    private UserService $userService;

    public function __construct(
        \Blog\Service\AuthenticationService $authService,
        \Psr\Log\LoggerInterface $logger,
        UserService $userService
    ) {
        parent::__construct($authService, $logger);
        $this->userService = $userService;
    }

    /**
     * Page de connexion
     */
    public function login(): Response
    {
        // Rediriger si déjà connecté
        if ($this->getCurrentUser()) {
            return $this->redirect('/dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handleLogin();
        }

        return $this->render('auth/login', [
            'page_title' => 'Connexion'
        ]);
    }

    /**
     * Page d'inscription
     */
    public function register(): Response
    {
        // Rediriger si déjà connecté
        if ($this->getCurrentUser()) {
            return $this->redirect('/dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handleRegister();
        }

        return $this->render('auth/register', [
            'page_title' => 'Inscription'
        ]);
    }

    /**
     * Déconnexion
     */
    public function logout(): Response
    {
        session_destroy();
        $this->addFlashMessage('Vous avez été déconnecté avec succès', 'info');
        return $this->redirect('/');
    }

    /**
     * Tableau de bord utilisateur
     */
    public function dashboard(): Response
    {
        $this->requireAuthentication();
        
        $user = $this->getCurrentUser();
        
        // Statistiques utilisateur
        $userPosts = $this->postService->getRepository()->findByAuthor($user);
        $userComments = $this->commentService->getRepository()->findByUser($user);

        return $this->render('auth/dashboard', [
            'user' => $user,
            'user_posts' => $userPosts,
            'user_comments' => $userComments,
            'posts_count' => count($userPosts),
            'comments_count' => count($userComments),
            'page_title' => 'Tableau de bord'
        ]);
    }

    /**
     * Profil utilisateur
     */
    public function profile(): Response
    {
        $this->requireAuthentication();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handleProfileUpdate();
        }

        return $this->render('auth/profile', [
            'user' => $this->getCurrentUser(),
            'page_title' => 'Mon profil'
        ]);
    }

    /**
     * @return Response
     */
    private function handleLogin(): Response
    {
        $data = $_POST;
        $errors = $this->validateForm($data, [
            'email' => 'email|required',
            'password' => 'required'
        ]);

        if (!empty($errors)) {
            return $this->render('auth/login', [
                'errors' => $errors,
                'form_data' => $data,
                'page_title' => 'Connexion'
            ]);
        }

        try {
            $user = $this->authService->authenticate($data['email'], $data['password']);
            
            // Créer la session
            $_SESSION['auth_token'] = $this->authService->generateSessionToken($user);
            
            $this->addFlashMessage("Bienvenue {$user->getFirstName()} !", 'success');
            
            // Rediriger vers la page demandée ou le dashboard
            $redirectTo = $_SESSION['redirect_after_login'] ?? '/dashboard';
            unset($_SESSION['redirect_after_login']);
            
            return $this->redirect($redirectTo);

        } catch (InvalidCredentialsException $e) {
            return $this->render('auth/login', [
                'errors' => ['general' => 'Email ou mot de passe incorrect'],
                'form_data' => $data,
                'page_title' => 'Connexion'
            ]);
        }
    }

    /**
     * @return Response
     */
    private function handleRegister(): Response
    {
        $data = $_POST;
        $errors = $this->validateForm($data, [
            'firstName' => 'required',
            'lastName' => 'required',
            'email' => 'email|required',
            'password' => 'required|min:8',
            'password_confirmation' => 'required'
        ]);

        // Vérifier la confirmation du mot de passe
        if ($data['password'] !== $data['password_confirmation']) {
            $errors['password_confirmation'] = 'Les mots de passe ne correspondent pas';
        }

        if (!empty($errors)) {
            return $this->render('auth/register', [
                'errors' => $errors,
                'form_data' => $data,
                'page_title' => 'Inscription'
            ]);
        }

        try {
            $user = $this->userService->createUser([
                'firstName' => $data['firstName'],
                'lastName' => $data['lastName'],
                'email' => $data['email'],
                'password' => $data['password'],
                'bio' => $data['bio'] ?? null
            ]);

            // Connexion automatique après inscription
            $_SESSION['auth_token'] = $this->authService->generateSessionToken($user);
            
            $this->addFlashMessage('Votre compte a été créé avec succès !', 'success');
            return $this->redirect('/dashboard');

        } catch (DuplicateEmailException $e) {
            return $this->render('auth/register', [
                'errors' => ['email' => 'Cette adresse email est déjà utilisée'],
                'form_data' => $data,
                'page_title' => 'Inscription'
            ]);
        }
    }

    /**
     * @return Response
     */
    private function handleProfileUpdate(): Response
    {
        $user = $this->getCurrentUser();
        $data = $_POST;
        
        $errors = $this->validateForm($data, [
            'firstName' => 'required',
            'lastName' => 'required',
            'email' => 'email|required'
        ]);

        if (!empty($errors)) {
            return $this->render('auth/profile', [
                'errors' => $errors,
                'form_data' => $data,
                'user' => $user,
                'page_title' => 'Mon profil'
            ]);
        }

        try {
            $updateData = [
                'firstName' => $data['firstName'],
                'lastName' => $data['lastName'],
                'email' => $data['email'],
                'bio' => $data['bio'] ?? null
            ];

            // Changement de mot de passe si fourni
            if (!empty($data['new_password'])) {
                if ($data['new_password'] !== $data['new_password_confirmation']) {
                    $errors['new_password_confirmation'] = 'Les mots de passe ne correspondent pas';
                }
                
                if (!$user->verifyPassword($data['current_password'])) {
                    $errors['current_password'] = 'Mot de passe actuel incorrect';
                }

                if (!empty($errors)) {
                    return $this->render('auth/profile', [
                        'errors' => $errors,
                        'form_data' => $data,
                        'user' => $user,
                        'page_title' => 'Mon profil'
                    ]);
                }

                $this->userService->changePassword($user->getId(), $data['new_password']);
            }

            $this->userService->updateUser($user->getId(), $updateData);
            
            $this->addFlashMessage('Votre profil a été mis à jour avec succès !', 'success');
            return $this->redirect('/profile');

        } catch (DuplicateEmailException $e) {
            return $this->render('auth/profile', [
                'errors' => ['email' => 'Cette adresse email est déjà utilisée'],
                'form_data' => $data,
                'user' => $user,
                'page_title' => 'Mon profil'
            ]);
        }
    }
}
```

## PostController

```php
<?php

declare(strict_types=1);

namespace Blog\Controller;

use Blog\Service\PostService;
use Blog\Service\CategoryService;
use Blog\Service\TagService;
use Blog\Service\CommentService;
use Blog\Exception\PostNotFoundException;
use Blog\Exception\UnauthorizedActionException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PostController extends AbstractController
{
    private PostService $postService;
    private CategoryService $categoryService;
    private TagService $tagService;
    private CommentService $commentService;

    public function __construct(
        \Blog\Service\AuthenticationService $authService,
        \Psr\Log\LoggerInterface $logger,
        PostService $postService,
        CategoryService $categoryService,
        TagService $tagService,
        CommentService $commentService
    ) {
        parent::__construct($authService, $logger);
        $this->postService = $postService;
        $this->categoryService = $categoryService;
        $this->tagService = $tagService;
        $this->commentService = $commentService;
    }

    /**
     * Afficher un article
     */
    public function show(string $slug): Response
    {
        try {
            $post = $this->postService->findPublishedPostBySlug($slug);
            $comments = $this->commentService->getCommentsForPost($post);
            $relatedPosts = $this->postService->getRelatedPosts($post, 3);

            return $this->render('post/show', [
                'post' => $post,
                'comments' => $comments,
                'related_posts' => $relatedPosts,
                'page_title' => $post->getTitle()
            ]);

        } catch (PostNotFoundException $e) {
            return $this->render('error/404', [
                'message' => 'Article non trouvé'
            ], 404);
        }
    }

    /**
     * Liste des articles par catégorie
     */
    public function byCategory(string $categorySlug): Response
    {
        try {
            $category = $this->categoryService->findCategoryBySlug($categorySlug);
            $page = (int) ($_GET['page'] ?? 1);
            
            $posts = $this->postService->getRepository()->findByCategory($category);

            return $this->render('post/by_category', [
                'category' => $category,
                'posts' => $posts,
                'page_title' => "Articles - {$category->getName()}"
            ]);

        } catch (\Blog\Exception\CategoryNotFoundException $e) {
            return $this->render('error/404', [
                'message' => 'Catégorie non trouvée'
            ], 404);
        }
    }

    /**
     * Liste des articles par tag
     */
    public function byTag(string $tagSlug): Response
    {
        try {
            $tag = $this->tagService->findTagBySlug($tagSlug);
            $posts = $this->postService->getRepository()->findByTag($tag);

            return $this->render('post/by_tag', [
                'tag' => $tag,
                'posts' => $posts,
                'page_title' => "Articles - #{$tag->getName()}"
            ]);

        } catch (\Blog\Exception\TagNotFoundException $e) {
            return $this->render('error/404', [
                'message' => 'Tag non trouvé'
            ], 404);
        }
    }

    /**
     * Créer un nouvel article
     */
    public function create(): Response
    {
        $this->requirePermission('post.create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handleCreate();
        }

        $categories = $this->categoryService->getActiveCategories();

        return $this->render('post/create', [
            'categories' => $categories,
            'page_title' => 'Nouvel article'
        ]);
    }

    /**
     * Éditer un article
     */
    public function edit(int $postId): Response
    {
        try {
            $post = $this->postService->findPostById($postId);
            $user = $this->getCurrentUser();

            // Vérifier les permissions
            if (!$user->isAdmin() && $post->getAuthor()->getId() !== $user->getId()) {
                throw new UnauthorizedActionException('Vous ne pouvez pas éditer cet article');
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                return $this->handleEdit($postId);
            }

            $categories = $this->categoryService->getActiveCategories();
            $postTags = array_map(fn($tag) => $tag->getName(), $post->getTags()->toArray());

            return $this->render('post/edit', [
                'post' => $post,
                'categories' => $categories,
                'post_tags' => implode(', ', $postTags),
                'page_title' => "Éditer : {$post->getTitle()}"
            ]);

        } catch (PostNotFoundException | UnauthorizedActionException $e) {
            $this->addFlashMessage($e->getMessage(), 'error');
            return $this->redirect('/dashboard');
        }
    }

    /**
     * Supprimer un article
     */
    public function delete(int $postId): Response
    {
        try {
            $user = $this->getCurrentUser();
            $this->postService->deletePost($postId, $user);
            
            $this->addFlashMessage('Article supprimé avec succès', 'success');
            return $this->redirect('/dashboard');

        } catch (PostNotFoundException | UnauthorizedActionException $e) {
            $this->addFlashMessage($e->getMessage(), 'error');
            return $this->redirect('/dashboard');
        }
    }

    /**
     * @return Response
     */
    private function handleCreate(): Response
    {
        $data = $_POST;
        $errors = $this->validateForm($data, [
            'title' => 'required',
            'content' => 'required|min:50',
            'categoryId' => 'required'
        ]);

        if (!empty($errors)) {
            $categories = $this->categoryService->getActiveCategories();
            return $this->render('post/create', [
                'errors' => $errors,
                'form_data' => $data,
                'categories' => $categories,
                'page_title' => 'Nouvel article'
            ]);
        }

        try {
            $postData = [
                'title' => $data['title'],
                'content' => $data['content'],
                'excerpt' => $data['excerpt'] ?? null,
                'categoryId' => (int) $data['categoryId'],
                'tags' => !empty($data['tags']) ? explode(',', $data['tags']) : [],
                'status' => $data['status'] ?? 'draft',
                'allowComments' => isset($data['allowComments'])
            ];

            $post = $this->postService->createPost($postData, $this->getCurrentUser());
            
            $this->addFlashMessage('Article créé avec succès !', 'success');
            return $this->redirect("/post/{$post->getSlug()->getValue()}");

        } catch (\Exception $e) {
            $this->logger->error('Error creating post', ['error' => $e->getMessage()]);
            $categories = $this->categoryService->getActiveCategories();
            
            return $this->render('post/create', [
                'errors' => ['general' => 'Une erreur est survenue lors de la création'],
                'form_data' => $data,
                'categories' => $categories,
                'page_title' => 'Nouvel article'
            ]);
        }
    }

    /**
     * @param int $postId
     * @return Response
     */
    private function handleEdit(int $postId): Response
    {
        $data = $_POST;
        $errors = $this->validateForm($data, [
            'title' => 'required',
            'content' => 'required|min:50'
        ]);

        if (!empty($errors)) {
            $post = $this->postService->findPostById($postId);
            $categories = $this->categoryService->getActiveCategories();
            
            return $this->render('post/edit', [
                'errors' => $errors,
                'form_data' => $data,
                'post' => $post,
                'categories' => $categories,
                'page_title' => "Éditer : {$post->getTitle()}"
            ]);
        }

        try {
            $postData = [
                'title' => $data['title'],
                'content' => $data['content'],
                'excerpt' => $data['excerpt'] ?? null,
                'categoryId' => !empty($data['categoryId']) ? (int) $data['categoryId'] : null,
                'tags' => !empty($data['tags']) ? explode(',', $data['tags']) : [],
                'status' => $data['status'] ?? 'draft',
                'allowComments' => isset($data['allowComments'])
            ];

            $post = $this->postService->updatePost($postId, $postData, $this->getCurrentUser());
            
            $this->addFlashMessage('Article mis à jour avec succès !', 'success');
            return $this->redirect("/post/{$post->getSlug()->getValue()}");

        } catch (UnauthorizedActionException $e) {
            $this->addFlashMessage($e->getMessage(), 'error');
            return $this->redirect('/dashboard');
        }
    }
}
```

## CommentController

```php
<?php

declare(strict_types=1);

namespace Blog\Controller;

use Blog\Service\CommentService;
use Blog\Service\PostService;
use Blog\Exception\UnauthorizedActionException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class CommentController extends AbstractController
{
    private CommentService $commentService;
    private PostService $postService;

    public function __construct(
        \Blog\Service\AuthenticationService $authService,
        \Psr\Log\LoggerInterface $logger,
        CommentService $commentService,
        PostService $postService
    ) {
        parent::__construct($authService, $logger);
        $this->commentService = $commentService;
        $this->postService = $postService;
    }

    /**
     * Créer un commentaire
     */
    public function create(): Response
    {
        $this->requireAuthentication();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->redirect('/');
        }

        $data = $_POST;
        $errors = $this->validateForm($data, [
            'content' => 'required|min:10',
            'post_id' => 'required'
        ]);

        if (!empty($errors)) {
            $this->addFlashMessage('Erreur dans le formulaire de commentaire', 'error');
            return $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }

        try {
            $post = $this->postService->findPostById((int) $data['post_id']);
            $user = $this->getCurrentUser();
            
            $parent = null;
            if (!empty($data['parent_id'])) {
                $parent = $this->commentService->findCommentById((int) $data['parent_id']);
            }

            $this->commentService->createComment(
                ['content' => $data['content']],
                $post,
                $user,
                $parent
            );

            $this->addFlashMessage('Votre commentaire a été soumis !', 'success');
            return $this->redirect("/post/{$post->getSlug()->getValue()}#comments");

        } catch (\Exception $e) {
            $this->logger->error('Error creating comment', ['error' => $e->getMessage()]);
            $this->addFlashMessage('Erreur lors de la création du commentaire', 'error');
            return $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    /**
     * Approuver un commentaire (modération)
     */
    public function approve(int $commentId): Response
    {
        $this->requirePermission('comment.moderate');

        try {
            $this->commentService->approveComment($commentId, $this->getCurrentUser());
            $this->addFlashMessage('Commentaire approuvé', 'success');

        } catch (UnauthorizedActionException $e) {
            $this->addFlashMessage($e->getMessage(), 'error');
        }

        return $this->redirect('/admin/comments');
    }

    /**
     * Rejeter un commentaire
     */
    public function reject(int $commentId): Response
    {
        $this->requirePermission('comment.moderate');

        try {
            $this->commentService->rejectComment($commentId, $this->getCurrentUser());
            $this->addFlashMessage('Commentaire rejeté', 'success');

        } catch (UnauthorizedActionException $e) {
            $this->addFlashMessage($e->getMessage(), 'error');
        }

        return $this->redirect('/admin/comments');
    }

    /**
     * Marquer comme spam
     */
    public function markAsSpam(int $commentId): Response
    {
        $this->requirePermission('comment.moderate');

        try {
            $this->commentService->markCommentAsSpam($commentId, $this->getCurrentUser());
            $this->addFlashMessage('Commentaire marqué comme spam', 'success');

        } catch (UnauthorizedActionException $e) {
            $this->addFlashMessage($e->getMessage(), 'error');
        }

        return $this->redirect('/admin/comments');
    }
}
```

## AdminController

```php
<?php

declare(strict_types=1);

namespace Blog\Controller;

use Blog\Service\UserService;
use Blog\Service\PostService;
use Blog\Service\CommentService;
use Blog\Service\CategoryService;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class AdminController extends AbstractController
{
    private UserService $userService;
    private PostService $postService;
    private CommentService $commentService;
    private CategoryService $categoryService;

    public function __construct(
        \Blog\Service\AuthenticationService $authService,
        \Psr\Log\LoggerInterface $logger,
        UserService $userService,
        PostService $postService,
        CommentService $commentService,
        CategoryService $categoryService
    ) {
        parent::__construct($authService, $logger);
        $this->userService = $userService;
        $this->postService = $postService;
        $this->commentService = $commentService;
        $this->categoryService = $categoryService;
    }

    /**
     * Tableau de bord administrateur
     */
    public function dashboard(): Response
    {
        $this->requirePermission('admin.access');

        $userStats = $this->userService->getUserStatistics();
        $postStats = $this->postService->getPostStatistics();
        $commentStats = $this->commentService->getCommentStatistics();
        $categoryStats = $this->categoryService->getCategoryStatistics();

        $pendingComments = $this->commentService->getPendingComments();
        $recentPosts = $this->postService->getRepository()->findLatestPublished(5);

        return $this->render('admin/dashboard', [
            'user_stats' => $userStats,
            'post_stats' => $postStats,
            'comment_stats' => $commentStats,
            'category_stats' => $categoryStats,
            'pending_comments' => $pendingComments,
            'recent_posts' => $recentPosts,
            'page_title' => 'Administration'
        ]);
    }

    /**
     * Gestion des utilisateurs
     */
    public function users(): Response
    {
        $this->requirePermission('user.manage');

        $page = (int) ($_GET['page'] ?? 1);
        $filters = [
            'status' => $_GET['status'] ?? null,
            'role' => $_GET['role'] ?? null,
            'sort' => $_GET['sort'] ?? 'createdAt',
            'direction' => $_GET['direction'] ?? 'DESC'
        ];

        $usersData = $this->userService->getUsers($page, 20, $filters);

        return $this->render('admin/users', [
            'users' => $usersData['data'],
            'pagination' => $usersData['pagination'],
            'filters' => $filters,
            'page_title' => 'Gestion des utilisateurs'
        ]);
    }

    /**
     * Gestion des commentaires
     */
    public function comments(): Response
    {
        $this->requirePermission('comment.moderate');

        $status = $_GET['status'] ?? 'pending';
        $comments = [];

        switch ($status) {
            case 'pending':
                $comments = $this->commentService->getPendingComments();
                break;
            case 'approved':
                $comments = $this->commentService->getRepository()->findBy(['status' => 'approved'], ['createdAt' => 'DESC'], 50);
                break;
            case 'spam':
                $comments = $this->commentService->getRepository()->findBy(['status' => 'spam'], ['createdAt' => 'DESC'], 50);
                break;
        }

        return $this->render('admin/comments', [
            'comments' => $comments,
            'current_status' => $status,
            'page_title' => 'Modération des commentaires'
        ]);
    }

    /**
     * Statistiques avancées
     */
    public function statistics(): Response
    {
        $this->requirePermission('admin.access');

        $period = $_GET['period'] ?? '30';
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify("-{$period} days");

        $postsInPeriod = $this->postService->getRepository()->findPublishedBetween($startDate, $endDate);
        $mostViewed = $this->postService->getRepository()->findMostViewed(10);
        $mostCommented = $this->postService->getRepository()->findMostCommented(10);
        $topAuthors = $this->userService->getRepository()->findTopAuthors(10);

        return $this->render('admin/statistics', [
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'posts_in_period' => $postsInPeriod,
            'most_viewed' => $mostViewed,
            'most_commented' => $mostCommented,
            'top_authors' => $topAuthors,
            'page_title' => 'Statistiques'
        ]);
    }
}
```

## Middleware et sécurité

### Middleware CSRF

```php
<?php

declare(strict_types=1);

namespace Blog\Middleware;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class CsrfMiddleware
{
    public static function generateToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }

    public static function validateToken(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function requireValidToken(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        
        if (!self::validateToken($token)) {
            throw new \Exception('Token CSRF invalide');
        }
    }
}
```

### Router simple

```php
<?php

declare(strict_types=1);

namespace Blog;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class Router
{
    /** @var array<string, array{controller: string, action: string, method: string}> */
    private array $routes = [];
    private \Symfony\Component\DependencyInjection\ContainerInterface $container;

    public function __construct(\Symfony\Component\DependencyInjection\ContainerInterface $container)
    {
        $this->container = $container;
        $this->defineRoutes();
    }

    /**
     * @param string $method
     * @param string $uri
     * @return \Blog\Controller\Response
     */
    public function handle(string $method, string $uri): \Blog\Controller\Response
    {
        // Supprimer les paramètres de requête pour le matching
        $path = parse_url($uri, PHP_URL_PATH);
        
        foreach ($this->routes as $pattern => $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }
            
            $regex = $this->patternToRegex($pattern);
            if (preg_match($regex, $path, $matches)) {
                return $this->executeRoute($route, array_slice($matches, 1));
            }
        }

        return new \Blog\Controller\Response('Page non trouvée', 404);
    }

    private function defineRoutes(): void
    {
        // Routes publiques
        $this->routes['/'] = ['controller' => 'HomeController', 'action' => 'index', 'method' => 'GET'];
        $this->routes['/search'] = ['controller' => 'HomeController', 'action' => 'search', 'method' => 'GET'];
        $this->routes['/about'] = ['controller' => 'HomeController', 'action' => 'about', 'method' => 'GET'];
        $this->routes['/contact'] = ['controller' => 'HomeController', 'action' => 'contact', 'method' => 'ANY'];

        // Auth
        $this->routes['/login'] = ['controller' => 'AuthController', 'action' => 'login', 'method' => 'ANY'];
        $this->routes['/register'] = ['controller' => 'AuthController', 'action' => 'register', 'method' => 'ANY'];
        $this->routes['/logout'] = ['controller' => 'AuthController', 'action' => 'logout', 'method' => 'GET'];
        $this->routes['/dashboard'] = ['controller' => 'AuthController', 'action' => 'dashboard', 'method' => 'GET'];
        $this->routes['/profile'] = ['controller' => 'AuthController', 'action' => 'profile', 'method' => 'ANY'];

        // Posts
        $this->routes['/post/([^/]+)'] = ['controller' => 'PostController', 'action' => 'show', 'method' => 'GET'];
        $this->routes['/category/([^/]+)'] = ['controller' => 'PostController', 'action' => 'byCategory', 'method' => 'GET'];
        $this->routes['/tag/([^/]+)'] = ['controller' => 'PostController', 'action' => 'byTag', 'method' => 'GET'];
        $this->routes['/post/create'] = ['controller' => 'PostController', 'action' => 'create', 'method' => 'ANY'];
        $this->routes['/post/(\d+)/edit'] = ['controller' => 'PostController', 'action' => 'edit', 'method' => 'ANY'];
        $this->routes['/post/(\d+)/delete'] = ['controller' => 'PostController', 'action' => 'delete', 'method' => 'POST'];

        // Comments
        $this->routes['/comment/create'] = ['controller' => 'CommentController', 'action' => 'create', 'method' => 'POST'];
        $this->routes['/comment/(\d+)/approve'] = ['controller' => 'CommentController', 'action' => 'approve', 'method' => 'POST'];
        $this->routes['/comment/(\d+)/reject'] = ['controller' => 'CommentController', 'action' => 'reject', 'method' => 'POST'];
        $this->routes['/comment/(\d+)/spam'] = ['controller' => 'CommentController', 'action' => 'markAsSpam', 'method' => 'POST'];

        // Admin
        $this->routes['/admin'] = ['controller' => 'AdminController', 'action' => 'dashboard', 'method' => 'GET'];
        $this->routes['/admin/users'] = ['controller' => 'AdminController', 'action' => 'users', 'method' => 'GET'];
        $this->routes['/admin/comments'] = ['controller' => 'AdminController', 'action' => 'comments', 'method' => 'GET'];
        $this->routes['/admin/statistics'] = ['controller' => 'AdminController', 'action' => 'statistics', 'method' => 'GET'];
    }

    /**
     * @param string $pattern
     * @return string
     */
    private function patternToRegex(string $pattern): string
    {
        return '#^' . $pattern . '$#';
    }

    /**
     * @param array{controller: string, action: string, method: string} $route
     * @param array<string> $params
     * @return \Blog\Controller\Response
     */
    private function executeRoute(array $route, array $params): \Blog\Controller\Response
    {
        $controllerClass = "Blog\\Controller\\{$route['controller']}";
        $controller = $this->container->get(strtolower($route['controller']));
        
        return call_user_func_array([$controller, $route['action']], $params);
    }
}
```

---

**Prochaine étape :** [Migrations de base de données](06-migrations.md) - Création du schéma complet avec données de test.
