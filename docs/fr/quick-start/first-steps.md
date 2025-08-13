# Premiers Pas

Guide pour démarrer avec MulerTech Database.

## Initialisation du Projet

### Setup Minimal

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
    echo "Connexion établie avec succès!\n";
} catch (Exception $e) {
    echo "Erreur de connexion: " . $e->getMessage() . "\n";
    exit(1);
}
```

### Structure de Projet Recommandée

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

## Votre Première Entité

### Entité User Simple

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
        columnType: ColumnType::DATETIME,
        isNullable: false
    )]
    private DateTime $createdAt;

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

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    // Setters
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }
}
```

### Points Clés

- **Attributs #[MtEntity]** : Définit la table
- **Attributs #[MtColumn]** : Configure chaque colonne
- **Types stricts** : PHP avec types de retour
- **Méthodes fluides** : Setters retournent `$this`

## Configuration de Base

### Enregistrement de l'Entité

```php
<?php
// Dans bootstrap.php, après l'initialisation

// Enregistrer vos entités dans le MetadataRegistry
$metadataRegistry->registerEntity(App\Entity\User::class);
```

### Création de la Table

```sql
-- Création manuelle
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL
);
```

## Opérations CRUD de Base

### Create - Créer un Utilisateur

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

try {
    // Créer un nouvel utilisateur
    $user = new User();
    $user->setName('John Doe');
    $user->setEmail('john.doe@example.com');

    // Persister en base
    $entityManager->persist($user);
    $entityManager->flush();

    echo "Utilisateur créé avec l'ID: " . $user->getId() . "\n";

} catch (Exception $e) {
    echo "Erreur lors de la création: " . $e->getMessage() . "\n";
}
```

### Read - Lire des Utilisateurs

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

try {
    // Récupérer par ID
    $user = $entityManager->find(User::class, 1);
    if ($user) {
        echo "Utilisateur trouvé: " . $user->getName() . "\n";
    }

    // Récupérer tous les utilisateurs
    $users = $entityManager->getRepository(User::class)->findAll();
    echo "Nombre total d'utilisateurs: " . count($users) . "\n";

    // Recherche par email
    $userByEmail = $entityManager->getRepository(User::class)->findOneBy([
        'email' => 'john.doe@example.com'
    ]);
    
    if ($userByEmail) {
        echo "Utilisateur trouvé par email: " . $userByEmail->getName() . "\n";
    }

} catch (Exception $e) {
    echo "Erreur lors de la lecture: " . $e->getMessage() . "\n";
}
```

### Update - Modifier un Utilisateur

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

try {
    // Récupérer l'utilisateur
    $user = $entityManager->find(User::class, 1);
    
    if ($user) {
        // Modifier les données
        $user->setName('John Smith');
        $user->setEmail('john.smith@example.com');
        
        // L'EntityManager détecte automatiquement les changements
        $entityManager->flush();
        
        echo "Utilisateur mis à jour\n";
    }

} catch (Exception $e) {
    echo "Erreur lors de la mise à jour: " . $e->getMessage() . "\n";
}
```

### Delete - Supprimer un Utilisateur

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

try {
    // Récupérer l'utilisateur
    $user = $entityManager->find(User::class, 1);
    
    if ($user) {
        // Supprimer
        $entityManager->remove($user);
        $entityManager->flush();
        
        echo "Utilisateur supprimé\n";
    }

} catch (Exception $e) {
    echo "Erreur lors de la suppression: " . $e->getMessage() . "\n";
}
```

## Repository Personnalisé

Créez `src/Repository/UserRepository.php` :

```php
<?php

namespace App\Repository;

use App\Entity\User;
use MulerTech\Database\ORM\EntityRepository;

class UserRepository extends EntityRepository
{
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function searchByName(string $name): array
    {
        $queryBuilder = $this->createQueryBuilder();
        
        return $queryBuilder
            ->raw('SELECT * FROM users WHERE name LIKE ? ORDER BY name')
            ->bind(["%$name%"])
            ->execute()
            ->fetchAll();
    }
}
```

### Utilisation du Repository

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

try {
    $userRepo = $entityManager->getRepository(User::class);
    
    $user = $userRepo->findByEmail('john.smith@example.com');
    if ($user) {
        echo "Trouvé: " . $user->getName() . "\n";
    }
    
    $searchResults = $userRepo->searchByName('John');
    echo "Recherche 'John': " . count($searchResults) . "\n";

} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
```

## Test Simple

Créez `test-first-steps.php` :

```php
<?php
require_once 'bootstrap.php';

use App\Entity\User;

echo "Test des premiers pas\n";
echo "=====================\n\n";

try {
    // Créer un utilisateur
    echo "1. Création d'un utilisateur...\n";
    $user = new User();
    $user->setName('Alice Test');
    $user->setEmail('alice.test@example.com');
    
    $entityManager->persist($user);
    $entityManager->flush();
    echo "   Utilisateur créé (ID: " . $user->getId() . ")\n\n";

    // Recherches
    echo "2. Tests de recherche...\n";
    $foundUser = $entityManager->find(User::class, $user->getId());
    echo "   Utilisateur trouvé par ID: " . $foundUser->getName() . "\n";
    
    $allUsers = $entityManager->getRepository(User::class)->findAll();
    echo "   Total utilisateurs: " . count($allUsers) . "\n";
    
    // Nettoyage
    $entityManager->remove($user);
    $entityManager->flush();
    echo "   Données de test supprimées\n\n";

    echo "Tous les tests sont passés avec succès !\n";

} catch (Exception $e) {
    echo "Erreur durant les tests: " . $e->getMessage() . "\n";
}
```

Exécutez le test :
```bash
php test-first-steps.php
```

## Étapes Suivantes

- [Exemples de Base](basic-examples.md)
- [Attributs de Mapping](../entity-mapping/attributes.md)
- [Query Builder](../query-builder/basic-queries.md)