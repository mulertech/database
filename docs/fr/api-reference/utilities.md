# Classes Utilitaires - API Reference

Cette section documente les classes utilitaires de MulerTech Database ORM qui fournissent des fonctionnalités de support et d'aide au développement.

## 📋 Table des matières

- [StringHelper](#stringhelper)
- [ArrayHelper](#arrayhelper)
- [DateTimeHelper](#datetimehelper)
- [ValidationHelper](#validationhelper)
- [SqlHelper](#sqlhelper)
- [ClassHelper](#classhelper)
- [CacheHelper](#cachehelper)
- [DebugHelper](#debughelper)

## StringHelper

Utilitaires pour la manipulation de chaînes de caractères.

### Namespace
```php
MulerTech\Database\Utility\StringHelper
```

### Méthodes statiques

#### toCamelCase()
```php
/**
 * Convertit une chaîne en camelCase
 *
 * @param string $string
 * @return string
 */
public static function toCamelCase(string $string): string
```

#### toSnakeCase()
```php
/**
 * Convertit une chaîne en snake_case
 *
 * @param string $string
 * @return string
 */
public static function toSnakeCase(string $string): string
```

#### toPascalCase()
```php
/**
 * Convertit une chaîne en PascalCase
 *
 * @param string $string
 * @return string
 */
public static function toPascalCase(string $string): string
```

#### toKebabCase()
```php
/**
 * Convertit une chaîne en kebab-case
 *
 * @param string $string
 * @return string
 */
public static function toKebabCase(string $string): string
```

#### pluralize()
```php
/**
 * Pluralise un nom (règles anglaises simples)
 *
 * @param string $word
 * @return string
 */
public static function pluralize(string $word): string
```

#### singularize()
```php
/**
 * Singularise un nom (règles anglaises simples)
 *
 * @param string $word
 * @return string
 */
public static function singularize(string $word): string
```

#### slugify()
```php
/**
 * Convertit une chaîne en slug URL-friendly
 *
 * @param string $string
 * @param string $separator
 * @return string
 */
public static function slugify(string $string, string $separator = '-'): string
```

### Exemple d'usage

```php
use MulerTech\Database\Utility\StringHelper;

// Conversions de casse
$camelCase = StringHelper::toCamelCase('user_name'); // 'userName'
$snakeCase = StringHelper::toSnakeCase('UserName'); // 'user_name'
$pascalCase = StringHelper::toPascalCase('user-name'); // 'UserName'
$kebabCase = StringHelper::toKebabCase('UserName'); // 'user-name'

// Pluralisation
$plural = StringHelper::pluralize('user'); // 'users'
$singular = StringHelper::singularize('categories'); // 'category'

// Slugification
$slug = StringHelper::slugify('Mon Article Génial!'); // 'mon-article-genial'
```

## ArrayHelper

Utilitaires pour la manipulation de tableaux et collections.

### Namespace
```php
MulerTech\Database\Utility\ArrayHelper
```

### Méthodes statiques

#### get()
```php
/**
 * Récupère une valeur dans un tableau avec chemin en notation pointée
 *
 * @param array<string, mixed> $array
 * @param string $path
 * @param mixed $default
 * @return mixed
 */
public static function get(array $array, string $path, mixed $default = null): mixed
```

#### set()
```php
/**
 * Définit une valeur dans un tableau avec chemin en notation pointée
 *
 * @param array<string, mixed> $array
 * @param string $path
 * @param mixed $value
 */
public static function set(array &$array, string $path, mixed $value): void
```

#### has()
```php
/**
 * Vérifie si une clé existe avec chemin en notation pointée
 *
 * @param array<string, mixed> $array
 * @param string $path
 * @return bool
 */
public static function has(array $array, string $path): bool
```

#### flatten()
```php
/**
 * Aplatit un tableau multidimensionnel
 *
 * @param array<mixed> $array
 * @param string $separator
 * @return array<string, mixed>
 */
public static function flatten(array $array, string $separator = '.'): array
```

#### groupBy()
```php
/**
 * Groupe les éléments d'un tableau par une clé
 *
 * @param array<array<string, mixed>> $array
 * @param string $key
 * @return array<string, array<array<string, mixed>>>
 */
public static function groupBy(array $array, string $key): array
```

#### pluck()
```php
/**
 * Extrait les valeurs d'une colonne spécifique
 *
 * @param array<array<string, mixed>> $array
 * @param string $column
 * @param string|null $key
 * @return array<mixed>
 */
public static function pluck(array $array, string $column, ?string $key = null): array
```

#### filter()
```php
/**
 * Filtre un tableau avec une fonction callback
 *
 * @param array<mixed> $array
 * @param callable $callback
 * @return array<mixed>
 */
public static function filter(array $array, callable $callback): array
```

### Exemple d'usage

```php
use MulerTech\Database\Utility\ArrayHelper;

$data = [
    'user' => [
        'profile' => [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]
    ]
];

// Accès avec notation pointée
$name = ArrayHelper::get($data, 'user.profile.name'); // 'John Doe'
$age = ArrayHelper::get($data, 'user.profile.age', 25); // 25 (défaut)

// Modification avec notation pointée
ArrayHelper::set($data, 'user.profile.age', 30);

// Vérification d'existence
$hasEmail = ArrayHelper::has($data, 'user.profile.email'); // true

// Groupement
$users = [
    ['name' => 'John', 'role' => 'admin'],
    ['name' => 'Jane', 'role' => 'user'],
    ['name' => 'Bob', 'role' => 'admin']
];
$byRole = ArrayHelper::groupBy($users, 'role');
// ['admin' => [...], 'user' => [...]]

// Extraction de colonnes
$names = ArrayHelper::pluck($users, 'name'); // ['John', 'Jane', 'Bob']
```

## DateTimeHelper

Utilitaires pour la gestion des dates et heures.

### Namespace
```php
MulerTech\Database\Utility\DateTimeHelper
```

### Méthodes statiques

#### now()
```php
/**
 * Retourne la date/heure actuelle
 *
 * @param \DateTimeZone|null $timezone
 * @return \DateTimeImmutable
 */
public static function now(?\DateTimeZone $timezone = null): \DateTimeImmutable
```

#### createFromFormat()
```php
/**
 * Crée une DateTimeImmutable à partir d'un format
 *
 * @param string $format
 * @param string $datetime
 * @param \DateTimeZone|null $timezone
 * @return \DateTimeImmutable|null
 */
public static function createFromFormat(
    string $format,
    string $datetime,
    ?\DateTimeZone $timezone = null
): ?\DateTimeImmutable
```

#### formatForDatabase()
```php
/**
 * Formate une date pour la base de données
 *
 * @param \DateTimeInterface $datetime
 * @return string
 */
public static function formatForDatabase(\DateTimeInterface $datetime): string
```

#### parseFromDatabase()
```php
/**
 * Parse une date depuis la base de données
 *
 * @param string $value
 * @return \DateTimeImmutable|null
 */
public static function parseFromDatabase(string $value): ?\DateTimeImmutable
```

#### diffInDays()
```php
/**
 * Calcule la différence en jours entre deux dates
 *
 * @param \DateTimeInterface $from
 * @param \DateTimeInterface $to
 * @return int
 */
public static function diffInDays(\DateTimeInterface $from, \DateTimeInterface $to): int
```

#### isWeekend()
```php
/**
 * Vérifie si une date est un weekend
 *
 * @param \DateTimeInterface $date
 * @return bool
 */
public static function isWeekend(\DateTimeInterface $date): bool
```

#### addBusinessDays()
```php
/**
 * Ajoute des jours ouvrables à une date
 *
 * @param \DateTimeInterface $date
 * @param int $days
 * @return \DateTimeImmutable
 */
public static function addBusinessDays(\DateTimeInterface $date, int $days): \DateTimeImmutable
```

### Exemple d'usage

```php
use MulerTech\Database\Utility\DateTimeHelper;

// Date actuelle
$now = DateTimeHelper::now();
$utcNow = DateTimeHelper::now(new \DateTimeZone('UTC'));

// Création depuis format
$date = DateTimeHelper::createFromFormat('d/m/Y', '15/03/2024');

// Format pour base de données
$dbFormat = DateTimeHelper::formatForDatabase($now); // '2024-03-15 14:30:25'

// Parse depuis base de données
$parsed = DateTimeHelper::parseFromDatabase('2024-03-15 14:30:25');

// Calculs de dates
$diff = DateTimeHelper::diffInDays($date, $now);
$isWeekend = DateTimeHelper::isWeekend($now);
$nextBusinessDay = DateTimeHelper::addBusinessDays($now, 5);
```

## ValidationHelper

Utilitaires pour la validation de données.

### Namespace
```php
MulerTech\Database\Utility\ValidationHelper
```

### Méthodes statiques

#### isEmail()
```php
/**
 * Valide une adresse email
 *
 * @param string $email
 * @return bool
 */
public static function isEmail(string $email): bool
```

#### isUrl()
```php
/**
 * Valide une URL
 *
 * @param string $url
 * @return bool
 */
public static function isUrl(string $url): bool
```

#### isUuid()
```php
/**
 * Valide un UUID
 *
 * @param string $uuid
 * @return bool
 */
public static function isUuid(string $uuid): bool
```

#### isJson()
```php
/**
 * Valide une chaîne JSON
 *
 * @param string $json
 * @return bool
 */
public static function isJson(string $json): bool
```

#### validateRequired()
```php
/**
 * Valide que des champs requis sont présents
 *
 * @param array<string, mixed> $data
 * @param array<string> $required
 * @return array<string> Champs manquants
 */
public static function validateRequired(array $data, array $required): array
```

#### sanitizeString()
```php
/**
 * Nettoie une chaîne de caractères
 *
 * @param string $string
 * @param bool $allowHtml
 * @return string
 */
public static function sanitizeString(string $string, bool $allowHtml = false): string
```

#### validateLength()
```php
/**
 * Valide la longueur d'une chaîne
 *
 * @param string $string
 * @param int $min
 * @param int|null $max
 * @return bool
 */
public static function validateLength(string $string, int $min, ?int $max = null): bool
```

### Exemple d'usage

```php
use MulerTech\Database\Utility\ValidationHelper;

// Validations simples
$isValidEmail = ValidationHelper::isEmail('user@example.com'); // true
$isValidUrl = ValidationHelper::isUrl('https://example.com'); // true
$isValidUuid = ValidationHelper::isUuid('550e8400-e29b-41d4-a716-446655440000'); // true

// Validation de champs requis
$data = ['name' => 'John', 'email' => 'john@example.com'];
$required = ['name', 'email', 'password'];
$missing = ValidationHelper::validateRequired($data, $required); // ['password']

// Nettoyage de chaînes
$clean = ValidationHelper::sanitizeString('<script>alert("xss")</script>Hello'); // 'Hello'
$cleanHtml = ValidationHelper::sanitizeString('<b>Hello</b>', true); // '<b>Hello</b>'

// Validation de longueur
$validLength = ValidationHelper::validateLength('password123', 8, 20); // true
```

## SqlHelper

Utilitaires pour la manipulation SQL.

### Namespace
```php
MulerTech\Database\Utility\SqlHelper
```

### Méthodes statiques

#### escapeIdentifier()
```php
/**
 * Échappe un identifiant SQL (table, colonne)
 *
 * @param string $identifier
 * @return string
 */
public static function escapeIdentifier(string $identifier): string
```

#### buildWhereClause()
```php
/**
 * Construit une clause WHERE à partir de critères
 *
 * @param array<string, mixed> $criteria
 * @return array{string, array<mixed>} [sql, parameters]
 */
public static function buildWhereClause(array $criteria): array
```

#### buildOrderClause()
```php
/**
 * Construit une clause ORDER BY
 *
 * @param array<string, string> $orderBy
 * @return string
 */
public static function buildOrderClause(array $orderBy): string
```

#### buildLimitClause()
```php
/**
 * Construit une clause LIMIT avec OFFSET
 *
 * @param int|null $limit
 * @param int|null $offset
 * @return string
 */
public static function buildLimitClause(?int $limit, ?int $offset = null): string
```

#### formatTableName()
```php
/**
 * Formate un nom de table avec préfixe si nécessaire
 *
 * @param string $tableName
 * @param string $prefix
 * @return string
 */
public static function formatTableName(string $tableName, string $prefix = ''): string
```

### Exemple d'usage

```php
use MulerTech\Database\Utility\SqlHelper;

// Échappement d'identifiants
$table = SqlHelper::escapeIdentifier('user-table'); // '`user-table`'

// Construction de clauses WHERE
$criteria = ['active' => true, 'role' => 'admin'];
[$where, $params] = SqlHelper::buildWhereClause($criteria);
// WHERE `active` = ? AND `role` = ?, [true, 'admin']

// Construction ORDER BY
$orderBy = ['name' => 'ASC', 'created_at' => 'DESC'];
$order = SqlHelper::buildOrderClause($orderBy);
// ORDER BY `name` ASC, `created_at` DESC

// Construction LIMIT
$limit = SqlHelper::buildLimitClause(10, 20); // LIMIT 10 OFFSET 20

// Format de nom de table
$tableName = SqlHelper::formatTableName('users', 'app_'); // 'app_users'
```

## ClassHelper

Utilitaires pour la réflexion et manipulation de classes.

### Namespace
```php
MulerTech\Database\Utility\ClassHelper
```

### Méthodes statiques

#### getShortName()
```php
/**
 * Obtient le nom court d'une classe (sans namespace)
 *
 * @param string|object $class
 * @return string
 */
public static function getShortName(string|object $class): string
```

#### getNamespace()
```php
/**
 * Obtient le namespace d'une classe
 *
 * @param string|object $class
 * @return string
 */
public static function getNamespace(string|object $class): string
```

#### hasMethod()
```php
/**
 * Vérifie si une classe a une méthode
 *
 * @param string|object $class
 * @param string $method
 * @return bool
 */
public static function hasMethod(string|object $class, string $method): bool
```

#### getPropertyValue()
```php
/**
 * Obtient la valeur d'une propriété (même privée)
 *
 * @param object $object
 * @param string $property
 * @return mixed
 */
public static function getPropertyValue(object $object, string $property): mixed
```

#### setPropertyValue()
```php
/**
 * Définit la valeur d'une propriété (même privée)
 *
 * @param object $object
 * @param string $property
 * @param mixed $value
 */
public static function setPropertyValue(object $object, string $property, mixed $value): void
```

#### getClassConstants()
```php
/**
 * Obtient toutes les constantes d'une classe
 *
 * @param string|object $class
 * @return array<string, mixed>
 */
public static function getClassConstants(string|object $class): array
```

### Exemple d'usage

```php
use MulerTech\Database\Utility\ClassHelper;
use App\Entity\User;

$user = new User();

// Informations sur la classe
$shortName = ClassHelper::getShortName($user); // 'User'
$namespace = ClassHelper::getNamespace(User::class); // 'App\Entity'

// Vérification de méthodes
$hasGetName = ClassHelper::hasMethod($user, 'getName'); // true

// Manipulation de propriétés privées
ClassHelper::setPropertyValue($user, 'id', 123);
$id = ClassHelper::getPropertyValue($user, 'id'); // 123

// Constantes de classe
$constants = ClassHelper::getClassConstants(User::class);
```

## CacheHelper

Utilitaires pour la gestion du cache.

### Namespace
```php
MulerTech\Database\Utility\CacheHelper
```

### Méthodes statiques

#### generateKey()
```php
/**
 * Génère une clé de cache à partir de paramètres
 *
 * @param string $prefix
 * @param mixed ...$parts
 * @return string
 */
public static function generateKey(string $prefix, mixed ...$parts): string
```

#### hashKey()
```php
/**
 * Crée un hash d'une clé longue
 *
 * @param string $key
 * @return string
 */
public static function hashKey(string $key): string
```

#### parseTtl()
```php
/**
 * Parse une durée TTL depuis différents formats
 *
 * @param string|int|\DateInterval $ttl
 * @return int
 */
public static function parseTtl(string|int|\DateInterval $ttl): int
```

#### isExpired()
```php
/**
 * Vérifie si un timestamp d'expiration est dépassé
 *
 * @param int $expiry
 * @return bool
 */
public static function isExpired(int $expiry): bool
```

### Exemple d'usage

```php
use MulerTech\Database\Utility\CacheHelper;

// Génération de clés
$key = CacheHelper::generateKey('user', 123, 'profile'); // 'user:123:profile'
$hashKey = CacheHelper::hashKey('very-long-key-that-exceeds-limits'); // 'hash:abc123...'

// Parsing TTL
$ttl1 = CacheHelper::parseTtl('1 hour'); // 3600
$ttl2 = CacheHelper::parseTtl(new \DateInterval('PT1H')); // 3600

// Vérification d'expiration
$expiry = time() + 3600;
$expired = CacheHelper::isExpired($expiry); // false
```

## DebugHelper

Utilitaires pour le debugging et le développement.

### Namespace
```php
MulerTech\Database\Utility\DebugHelper
```

### Méthodes statiques

#### dump()
```php
/**
 * Affiche une variable de manière formatée
 *
 * @param mixed $var
 * @param bool $return
 * @return string|null
 */
public static function dump(mixed $var, bool $return = false): ?string
```

#### formatBytes()
```php
/**
 * Formate une taille en octets de manière lisible
 *
 * @param int $bytes
 * @param int $precision
 * @return string
 */
public static function formatBytes(int $bytes, int $precision = 2): string
```

#### getMemoryUsage()
```php
/**
 * Obtient l'utilisation mémoire actuelle
 *
 * @param bool $realUsage
 * @return string
 */
public static function getMemoryUsage(bool $realUsage = true): string
```

#### measureTime()
```php
/**
 * Mesure le temps d'exécution d'une fonction
 *
 * @param callable $callback
 * @return array{result: mixed, time: float}
 */
public static function measureTime(callable $callback): array
```

#### getQueryInfo()
```php
/**
 * Obtient des informations de debug sur une requête
 *
 * @param string $sql
 * @param array<mixed> $parameters
 * @return array<string, mixed>
 */
public static function getQueryInfo(string $sql, array $parameters = []): array
```

### Exemple d'usage

```php
use MulerTech\Database\Utility\DebugHelper;

// Debug de variables
DebugHelper::dump($complexObject);
$output = DebugHelper::dump($data, true);

// Informations mémoire
$memory = DebugHelper::getMemoryUsage(); // '15.2 MB'
$formatted = DebugHelper::formatBytes(1048576); // '1.00 MB'

// Mesure de performance
$result = DebugHelper::measureTime(function() {
    // Code à mesurer
    return expensiveOperation();
});
// ['result' => ..., 'time' => 0.145]

// Debug de requêtes
$info = DebugHelper::getQueryInfo(
    'SELECT * FROM users WHERE id = ?',
    [123]
);
```

---

Ces classes utilitaires fournissent des fonctionnalités de support essentielles pour développer efficacement avec MulerTech Database ORM, simplifiant les tâches courantes et améliorant la productivité du développement.
