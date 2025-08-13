# Interfaces Publiques - Référence API

Ce document liste les interfaces présentes dans le code source.

## Table des matières
- [DriverInterface](#driverinterface)
- [PhpDatabaseInterface](#phpdatabaseinterface)
- [ConnectorInterface](#connectorinterface)
- [QueryExecutorInterface](#queryexecutorinterface)
- [DatabaseParameterParserInterface](#databaseparameterparserinterface)
- [CacheInterface](#cacheinterface)
- [TaggableCacheInterface](#taggablecacheinterface)

---
## DriverInterface
Génération d'un DSN PDO MySQL.

**Namespace**
```php
MulerTech\Database\Database\Interface\DriverInterface
```

**Méthode**
| Signature | Description |
|-----------|-------------|
| `generateDsn(array $dsnOptions): string` | Construit une chaîne DSN à partir des options (host, port, dbname, unix_socket, charset). |

**Exemple**
```php
$driver = new MySQLDriver();
$dsn = $driver->generateDsn([
    'host' => 'localhost',
    'port' => 3306,
    'dbname' => 'app',
    'charset' => 'utf8mb4'
]);
$pdo = new PDO($dsn, 'user', 'pass');
```

---
## PhpDatabaseInterface
Abstraction principale d'une connexion PDO + exécution de requêtes (wrapper typé). Retourne des objets `Statement` (classe interne) plutôt que des PDOStatement bruts.

**Namespace**
```php
MulerTech\Database\Database\Interface\PhpDatabaseInterface
```

**Méthodes clés**
| Signature | Rôle |
|-----------|------|
| `prepare(string $query, array $options = []): Statement` | Prépare une requête |
| `beginTransaction(): bool` | Démarre une transaction |
| `commit(): bool` | Commit |
| `rollBack(): bool` | Rollback |
| `inTransaction(): bool` | État transactionnel |
| `exec(string $statement): int` | Exec direct (DDL / DML) |
| `query(string $query, ?int $fetchMode = null, int|string|object $arg3 = '', ?array $constructorArgs = null): Statement` | Exécution directe |
| `lastInsertId(?string $name = null): string` | Dernier ID |
| `errorCode(): string|int|false` | Code erreur |
| `errorInfo(): array` | Infos erreur |
| `setAttribute(int $attribute, mixed $value): bool` | Set attribut PDO |
| `getAttribute(int $attribute): mixed` | Get attribut PDO |
| `quote(string $string, int $type = PDO::PARAM_STR): string` | Quote sécurisé |

---
## ConnectorInterface
Création bas-niveau d'un objet PDO initialisé.

**Namespace**
```php
MulerTech\Database\Database\Interface\ConnectorInterface
```

**Méthode**
| Signature | Description |
|-----------|-------------|
| `connect(array $parameters, string $username, string $password, ?array $options = null): PDO` | Construit et retourne une instance PDO prête. |

---
## QueryExecutorInterface
Exécution d'une requête avec mode de récupération dynamique.

**Namespace**
```php
MulerTech\Database\Database\Interface\QueryExecutorInterface
```

**Méthode**
| Signature | Description |
|-----------|-------------|
| `executeQuery(PDO $pdo, string $query, ?int $fetchMode = null, int|string|object $arg3 = '', ?array $constructorArgs = null): Statement` | Prépare + exécute, renvoie wrapper `Statement`. |

---
## DatabaseParameterParserInterface
Normalisation/validation de paramètres de connexion.

**Namespace**
```php
MulerTech\Database\Database\Interface\DatabaseParameterParserInterface
```

**Méthode**
| Signature | Description |
|-----------|-------------|
| `parseParameters(array $parameters = []): array` | Retourne un tableau de paramètres normalisés. |

---
## CacheInterface
API de cache simple clé/valeur + opérations multiples.

**Namespace**
```php
MulerTech\Database\Core\Cache\CacheInterface
```

**Méthodes**
| Signature | Description |
|-----------|-------------|
| `get(string $key): mixed` | Lecture |
| `set(string $key, mixed $value, int $ttl = 0): void` | Écriture (TTL optionnel) |
| `delete(string $key): void` | Suppression clé |
| `clear(): void` | Flush complet |
| `has(string $key): bool` | Existence |
| `getMultiple(array $keys): array` | Lecture groupée |
| `setMultiple(array $values, int $ttl = 0): void` | Écriture groupée |
| `deleteMultiple(array $keys): void` | Suppression groupée |

---
## TaggableCacheInterface
Extension ajoutant un système de tags invalidables.

**Namespace**
```php
MulerTech\Database\Core\Cache\TaggableCacheInterface
```

**Méthodes supplémentaires**
| Signature | Description |
|-----------|-------------|
| `tag(string $key, array $tags): void` | Associe des tags à une entrée |
| `invalidateTag(string $tag): void` | Invalide toutes les clés taguées |
| `invalidateTags(array $tags): void` | Invalidation multiple |

**Exemple minimal**
```php
class ArrayTagCache implements TaggableCacheInterface {
    private array $store = [];
    private array $tagIndex = [];
    public function get(string $key): mixed { return $this->store[$key] ?? null; }
    public function set(string $key, mixed $value, int $ttl = 0): void { $this->store[$key] = $value; }
    public function delete(string $key): void { unset($this->store[$key]); }
    public function clear(): void { $this->store = $this->tagIndex = []; }
    public function has(string $key): bool { return array_key_exists($key, $this->store); }
    public function getMultiple(array $keys): array { return array_intersect_key($this->store, array_flip($keys)); }
    public function setMultiple(array $values, int $ttl = 0): void { foreach ($values as $k=>$v) { $this->store[$k]=$v; } }
    public function deleteMultiple(array $keys): void { foreach ($keys as $k) unset($this->store[$k]); }
    public function tag(string $key, array $tags): void { foreach ($tags as $t) { $this->tagIndex[$t][] = $key; } }
    public function invalidateTag(string $tag): void { foreach ($this->tagIndex[$tag] ?? [] as $k) unset($this->store[$k]); unset($this->tagIndex[$tag]); }
    public function invalidateTags(array $tags): void { foreach ($tags as $t) $this->invalidateTag($t); }
}
```
