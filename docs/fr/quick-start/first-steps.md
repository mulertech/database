# Premiers Pas

🌍 **Languages:** [🇫🇷 Français](first-steps.md) | [🇬🇧 English](../../en/quick-start/first-steps.md)

---


## 📋 Table des Matières

- [Initialisation du Projet](#initialisation-du-projet)
- [Votre Première Entité](#votre-première-entité)
- [Configuration de Base](#configuration-de-base)
- [Opérations CRUD de Base](#opérations-crud-de-base)
- [Votre Premier Repository](#votre-premier-repository)
- [Gestion des Relations](#gestion-des-relations)
- [Vérification et Tests](#vérification-et-tests)

---

## Initialisation du Projet

### 🚀 Setup Minimal

Créez votre fichier principal `bootstrap.php` :

```php
<?php
require_once 'vendor/autoload.php';

use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Mapping\MetadataRegistry;

// Configuration
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'my_app',
    'username' => 'user',
    'password' => 'password',
    'charset' => 'utf8mb4',
    'driver' => 'mysql'
];

// Initialisation
$pdm = new PhpDatabaseManager($config);
$metadataRegistry = new MetadataRegistry();
$entityManager = new EntityManager($pdm, $metadataRegistry);

// Test de connexion
try {
    $result = $pdm->executeQuery("SELECT 1");
    echo "✅ Connexion établie avec succès!\\n";
} catch (Exception $e) {
    echo "❌ Erreur de connexion: " . $e->getMessage() . "\\n";
    exit(1);
}
```

### 🗂️ Structure de Projet Recommandée

```
my-project/
├── src/
│   ├── Entity/          # Vos entités
│   ├── Repository/      # Repositories personnalisés
│   └── Service/         # Services métier
├── config/
│   └── database.php     # Configuration
├── migrations/          # Fichiers de migration
├── tests/              # Tests unitaires
├── bootstrap.php       # Initialisation
└── composer.json
```

---

## Votre Première Entité

### 👤 Entité User Complète

Créez `src/Entity/User.php` :

```php
<?php

namespace App\Entity;

use DateTime;
use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey};

#[MtEntity(tableName: 'users')]
class User
{
    #[MtColumn(
        columnType: ColumnType::INT,
        isUnsigned: true,
        isNullable: false,
        extra: 'auto_increment',
        columnKey: ColumnKey::PRIMARY_KEY
    )]
    private ?int $id = null;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 100,
        isNullable: false
    )]
    private string $name;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 255,
        isNullable: false,
        columnKey: ColumnKey::UNIQUE
    )]
    private string $email;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 255,
        isNullable: true
    )]
    private ?string $password = null;

    #[MtColumn(
        columnType: ColumnType::DATETIME,
        isNullable: false
    )]
    private DateTime $createdAt;

    #[MtColumn(
        columnType: ColumnType::DATETIME,
        isNullable: true
    )]
    private ?DateTime $updatedAt = null;

    #[MtColumn(
        columnType: ColumnType::TINYINT,
        isUnsigned: true,
        isNullable: false,
        columnDefault: '1'
    )]
    private int $isActive = 1;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function isActive(): bool
    {
        return $this->isActive === 1;
    }

    // Setters
    public function setName(string $name): self
    {
        $this->name = $name;
        $this->touch();
        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        $this->touch();
        return $this;
    }

    public function setPassword(string $password): self
    {
        $this->password = password_hash($password, PASSWORD_DEFAULT);
        $this->touch();
        return $this;
    }

    public function setActive(bool $isActive): self
    {
        $this->isActive = $isActive ? 1 : 0;
        $this->touch();
        return $this;
    }

    // Méthodes utilitaires
    private function touch(): void
    {
        $this->updatedAt = new DateTime();
    }

    public function verifyPassword(string $password): bool
    {
        return $this->password && password_verify($password, $this->password);
    }
}
```

### 📝 Points Clés de l'Entité

1. **Namespace** : Organisation claire du code
2. **Attributs #[MtEntity]** : Définit la table
3. **Attributs #[MtColumn]** : Configure chaque colonne
4. **Types stricts** : PHP 8.4+ avec types de retour
5. **Méthodes fluides** : Setters retournent `$this`
6. **Logique métier** : Validation et transformation des données

---

## Configuration de Base

### 🔧 Enregistrement de l'Entité

```php
<?php
// Dans bootstrap.php, après l'initialisation

// Enregistrer vos entités dans le MetadataRegistry
$metadataRegistry->registerEntity(App\Entity\User::class);

// Ou enregistrement automatique d'un dossier
$metadataRegistry->autoRegisterEntities(__DIR__ . '/src/Entity');
```

### 🗄️ Création de la Table

Vous pouvez créer la table manuellement ou utiliser le système de migration :

```sql
-- Création manuelle (pour tests rapides)
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1
);
```

---

## Opérations CRUD de Base

### 🆕 Create - Créer un Utilisateur

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

try {
    // Créer un nouvel utilisateur
    $user = new User();
    $user->setName('John Doe')
         ->setEmail('john.doe@example.com')
         ->setPassword('motdepasse123')
         ->setActive(true);

    // Persister en base
    $entityManager->persist($user);
    $entityManager->flush();

    echo "✅ Utilisateur créé avec l'ID: " . $user->getId() . "\\n";
    echo "📧 Email: " . $user->getEmail() . "\\n";
    echo "📅 Créé le: " . $user->getCreatedAt()->format('Y-m-d H:i:s') . "\\n";

} catch (Exception $e) {
    echo "❌ Erreur lors de la création: " . $e->getMessage() . "\\n";
}
```

### 🔍 Read - Lire des Utilisateurs

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

try {
    // Récupérer par ID
    $user = $entityManager->find(User::class, 1);
    if ($user) {
        echo "👤 Utilisateur trouvé: " . $user->getName() . "\\n";
    } else {
        echo "❌ Utilisateur non trouvé\\n";
    }

    // Récupérer tous les utilisateurs
    $users = $entityManager->getRepository(User::class)->findAll();
    echo "📊 Nombre total d'utilisateurs: " . count($users) . "\\n";

    // Recherche par critères
    $activeUsers = $entityManager->getRepository(User::class)->findBy([
        'isActive' => 1
    ]);
    echo "✅ Utilisateurs actifs: " . count($activeUsers) . "\\n";

    // Recherche par email
    $userByEmail = $entityManager->getRepository(User::class)->findOneBy([
        'email' => 'john.doe@example.com'
    ]);
    
    if ($userByEmail) {
        echo "📧 Utilisateur trouvé par email: " . $userByEmail->getName() . "\\n";
    }

} catch (Exception $e) {
    echo "❌ Erreur lors de la lecture: " . $e->getMessage() . "\\n";
}
```

### ✏️ Update - Modifier un Utilisateur

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

try {
    // Récupérer l'utilisateur
    $user = $entityManager->find(User::class, 1);
    
    if ($user) {
        // Modifier les données
        $user->setName('John Smith')
             ->setEmail('john.smith@example.com');
        
        // L'EntityManager détecte automatiquement les changements
        $entityManager->flush();
        
        echo "✅ Utilisateur mis à jour\\n";
        echo "📝 Nouveau nom: " . $user->getName() . "\\n";
        echo "🕒 Mis à jour le: " . $user->getUpdatedAt()->format('Y-m-d H:i:s') . "\\n";
    } else {
        echo "❌ Utilisateur non trouvé\\n";
    }

} catch (Exception $e) {
    echo "❌ Erreur lors de la mise à jour: " . $e->getMessage() . "\\n";
}
```

### 🗑️ Delete - Supprimer un Utilisateur

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

try {
    // Récupérer l'utilisateur
    $user = $entityManager->find(User::class, 1);
    
    if ($user) {
        $userName = $user->getName();
        
        // Supprimer
        $entityManager->remove($user);
        $entityManager->flush();
        
        echo "✅ Utilisateur '$userName' supprimé\\n";
    } else {
        echo "❌ Utilisateur non trouvé\\n";
    }

} catch (Exception $e) {
    echo "❌ Erreur lors de la suppression: " . $e->getMessage() . "\\n";
}
```

---

## Votre Premier Repository

### 🗂️ Repository Personnalisé

Créez `src/Repository/UserRepository.php` :

```php
<?php

namespace App\Repository;

use App\Entity\User;
use MulerTech\Database\ORM\EntityRepository;
use MulerTech\Database\Query\Builder\QueryBuilder;

class UserRepository extends EntityRepository
{
    /**
     * Trouver les utilisateurs actifs
     */
    public function findActiveUsers(): array
    {
        return $this->findBy(['isActive' => 1]);
    }

    /**
     * Trouver un utilisateur par email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Compter les utilisateurs actifs
     */
    public function countActiveUsers(): int
    {
        $queryBuilder = new QueryBuilder($this->getEntityManager()->getEmEngine());
        
        $result = $queryBuilder
            ->select('COUNT(*) as total')
            ->from('users', 'u')
            ->where('u.is_active', '=', 1)
            ->getResult();
            
        return (int)$result[0]['total'];
    }

    /**
     * Rechercher par nom (LIKE)
     */
    public function searchByName(string $name): array
    {
        $queryBuilder = new QueryBuilder($this->getEntityManager()->getEmEngine());
        
        $results = $queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->where('u.name', 'LIKE', "%$name%")
            ->orderBy('u.name', 'ASC')
            ->getResult();
            
        return $this->hydrateResults($results);
    }

    /**
     * Utilisateurs créés récemment
     */
    public function findRecentUsers(int $days = 7): array
    {
        $queryBuilder = new QueryBuilder($this->getEntityManager()->getEmEngine());
        
        $results = $queryBuilder
            ->select('*')
            ->from('users', 'u')
            ->where('u.created_at', '>=', date('Y-m-d H:i:s', strtotime("-$days days")))
            ->orderBy('u.created_at', 'DESC')
            ->getResult();
            
        return $this->hydrateResults($results);
    }

    /**
     * Hydrate raw results to entities
     */
    private function hydrateResults(array $results): array
    {
        $entities = [];
        foreach ($results as $result) {
            $entities[] = $this->getEntityManager()->getEmEngine()->hydrateEntity(User::class, $result);
        }
        return $entities;
    }
}
```

### 🎯 Utilisation du Repository

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;
use App\Repository\UserRepository;

// Mise à jour de l'entité pour utiliser le repository personnalisé
// Dans User.php, modifiez l'attribut:
// #[MtEntity(tableName: 'users', repository: UserRepository::class)]

try {
    /** @var UserRepository $userRepo */
    $userRepo = $entityManager->getRepository(User::class);
    
    // Utiliser les méthodes personnalisées
    $activeUsers = $userRepo->findActiveUsers();
    echo "👥 Utilisateurs actifs: " . count($activeUsers) . "\\n";
    
    $totalActive = $userRepo->countActiveUsers();
    echo "📊 Total actifs: $totalActive\\n";
    
    $user = $userRepo->findByEmail('john.smith@example.com');
    if ($user) {
        echo "📧 Trouvé: " . $user->getName() . "\\n";
    }
    
    $recentUsers = $userRepo->findRecentUsers(30);
    echo "🆕 Utilisateurs récents (30j): " . count($recentUsers) . "\\n";
    
    $searchResults = $userRepo->searchByName('John');
    echo "🔍 Recherche 'John': " . count($searchResults) . "\\n";

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\\n";
}
```

---

## Gestion des Relations

### 📝 Entité Post Simple

Créez `src/Entity/Post.php` :

```php
<?php

namespace App\Entity;

use DateTime;
use MulerTech\Database\Mapping\Attributes\\{MtEntity, MtColumn, MtFk, MtManyToOne};
use MulerTech\\Database\\Mapping\\Types\\{ColumnType, ColumnKey, FkRule};

#[MtEntity(tableName: 'posts')]
class Post
{
    #[MtColumn(
        columnType: ColumnType::INT,
        isUnsigned: true,
        isNullable: false,
        extra: 'auto_increment',
        columnKey: ColumnKey::PRIMARY_KEY
    )]
    private ?int $id = null;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 255,
        isNullable: false
    )]
    private string $title;

    #[MtColumn(
        columnType: ColumnType::TEXT,
        isNullable: false
    )]
    private string $content;

    #[MtColumn(
        columnType: ColumnType::INT,
        isUnsigned: true,
        isNullable: false
    )]
    #[MtFk(
        referencedTable: 'users',
        referencedColumn: 'id',
        onDelete: FkRule::CASCADE,
        onUpdate: FkRule::CASCADE
    )]
    private int $userId;

    #[MtManyToOne(targetEntity: User::class, joinColumn: 'userId')]
    private ?User $user = null;

    #[MtColumn(
        columnType: ColumnType::DATETIME,
        isNullable: false
    )]
    private DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    // Getters et Setters
    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getContent(): string { return $this->content; }
    public function getUserId(): int { return $this->userId; }
    public function getUser(): ?User { return $this->user; }
    public function getCreatedAt(): DateTime { return $this->createdAt; }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        $this->userId = $user->getId();
        return $this;
    }
}
```

### 🔗 Utilisation des Relations

```php
<?php
require_once 'bootstrap.php';

use App\\Entity\\{User, Post};

try {
    // Récupérer un utilisateur
    $user = $entityManager->find(User::class, 1);
    
    if ($user) {
        // Créer un post
        $post = new Post();
        $post->setTitle('Mon premier article')
             ->setContent('Ceci est le contenu de mon premier article...')
             ->setUser($user);
        
        $entityManager->persist($post);
        $entityManager->flush();
        
        echo "✅ Article créé avec l'ID: " . $post->getId() . "\\n";
        echo "👤 Auteur: " . $post->getUser()->getName() . "\\n";
    }

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\\n";
}
```

---

## Vérification et Tests

### 🧪 Script de Test Complet

Créez `test-first-steps.php` :

```php
<?php
require_once 'bootstrap.php';

use App\\Entity\\{User, Post};

echo "🚀 Test des premiers pas\\n";
echo "========================\\n\\n";

try {
    // 1. Créer un utilisateur
    echo "1️⃣ Création d'un utilisateur...\\n";
    $user = new User();
    $user->setName('Alice Test')
         ->setEmail('alice.test@example.com')
         ->setPassword('test123');
    
    $entityManager->persist($user);
    $entityManager->flush();
    echo "   ✅ Utilisateur créé (ID: " . $user->getId() . ")\\n\\n";

    // 2. Modifier l'utilisateur
    echo "2️⃣ Modification de l'utilisateur...\\n";
    $user->setName('Alice Modified');
    $entityManager->flush();
    echo "   ✅ Nom modifié: " . $user->getName() . "\\n\\n";

    // 3. Créer un post
    echo "3️⃣ Création d'un article...\\n";
    $post = new Post();
    $post->setTitle('Article de test')
         ->setContent('Contenu de l\\'article de test')
         ->setUser($user);
    
    $entityManager->persist($post);
    $entityManager->flush();
    echo "   ✅ Article créé (ID: " . $post->getId() . ")\\n\\n";

    // 4. Recherches
    echo "4️⃣ Tests de recherche...\\n";
    $foundUser = $entityManager->find(User::class, $user->getId());
    echo "   ✅ Utilisateur trouvé par ID: " . $foundUser->getName() . "\\n";
    
    $allUsers = $entityManager->getRepository(User::class)->findAll();
    echo "   ✅ Total utilisateurs: " . count($allUsers) . "\\n";
    
    $userByEmail = $entityManager->getRepository(User::class)->findOneBy([
        'email' => 'alice.test@example.com'
    ]);
    echo "   ✅ Utilisateur trouvé par email: " . $userByEmail->getName() . "\\n\\n";

    // 5. Nettoyage (optionnel)
    echo "5️⃣ Nettoyage...\\n";
    $entityManager->remove($post);
    $entityManager->remove($user);
    $entityManager->flush();
    echo "   ✅ Données de test supprimées\\n\\n";

    echo "🎉 Tous les tests sont passés avec succès !\\n";

} catch (Exception $e) {
    echo "❌ Erreur durant les tests: " . $e->getMessage() . "\\n";
    echo "📍 Trace: " . $e->getTraceAsString() . "\\n";
}
```

Exécutez le test :
```bash
php test-first-steps.php
```

---

## ➡️ Étapes Suivantes

Félicitations ! Vous maîtrisez maintenant les bases. Continuez avec :

1. 🎯 [Exemples de Base](basic-examples.md) - Cas d'usage plus complexes
2. 🏗️ [Architecture](../core-concepts/architecture.md) - Comprendre l'architecture
3. 🎨 [Attributs de Mapping](../entity-mapping/attributes.md) - Mapping avancé
4. 🔧 [Query Builder](../query-builder/basic-queries.md) - Requêtes personnalisées

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../README.md)
- ⬅️ [Installation](installation.md)
- ➡️ [Exemples de Base](basic-examples.md)
- 📖 [Documentation Complète](../README.md)