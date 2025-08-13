# Types et Colonnes

🌍 **Languages:** [🇫🇷 Français](types-and-columns.md) | [🇬🇧 English](../../en/entity-mapping/types-and-columns.md)

---

## 📋 Table des Matières

- [Vue d'Ensemble](#vue-densemble)
- [Types Numériques](#types-numériques)
- [Types de Chaînes](#types-de-chaînes)
- [Types de Date et Heure](#types-de-date-et-heure)
- [Types Spéciaux](#types-spéciaux)
- [Méthodes Utilitaires](#méthodes-utilitaires)
- [Guide de Sélection](#guide-de-sélection)
- [Exemples Avancés](#exemples-avancés)

---

## Vue d'Ensemble

MulerTech Database propose un système de types complet basé sur les types de colonnes MySQL. L'enum `ColumnType` offre tous les types supportés ainsi que des méthodes utilitaires pour faciliter leur utilisation.

### 📦 Import Nécessaire

```php
<?php
use MulerTech\Database\Mapping\Types\ColumnType;
```

---

## Types Numériques

### Types Entiers

```php
enum ColumnType: string
{
    case TINYINT = 'TINYINT';      // -128 à 127 (ou 0 à 255 si unsigned)
    case SMALLINT = 'SMALLINT';    // -32,768 à 32,767 (ou 0 à 65,535)
    case MEDIUMINT = 'MEDIUMINT';  // -8,388,608 à 8,388,607 (ou 0 à 16,777,215)
    case INT = 'INT';              // -2,147,483,648 à 2,147,483,647 (ou 0 à 4,294,967,295)
    case BIGINT = 'BIGINT';        // -9,223,372,036,854,775,808 à 9,223,372,036,854,775,807
}
```

### Types Décimaux

```php
enum ColumnType: string
{
    case DECIMAL = 'DECIMAL';      // Nombre décimal exact (recommandé pour les montants)
    case FLOAT = 'FLOAT';          // Nombre flottant simple précision
    case DOUBLE = 'DOUBLE';        // Nombre flottant double précision
}
```

### 📝 Exemples d'Utilisation

```php
// ID auto-incrémenté
#[MtColumn(
    columnType: ColumnType::INT,
    isUnsigned: true,
    columnKey: ColumnKey::PRIMARY_KEY,
    extra: 'auto_increment'
)]
private ?int $id = null;

// Prix avec précision
#[MtColumn(
    columnType: ColumnType::DECIMAL,
    length: 10,        // Précision : 10 chiffres au total
    scale: 2,          // Échelle : 2 chiffres après la virgule
    isUnsigned: true
)]
private float $price;

// Pourcentage
#[MtColumn(
    columnType: ColumnType::FLOAT,
    isUnsigned: true
)]
private float $percentage;
```

---

## Types de Chaînes

### Chaînes de Longueur Fixe/Variable

```php
enum ColumnType: string
{
    case CHAR = 'CHAR';            // Longueur fixe (0-255 caractères)
    case VARCHAR = 'VARCHAR';      // Longueur variable (0-65,535 caractères)
}
```

### Types de Texte

```php
enum ColumnType: string
{
    case TINYTEXT = 'TINYTEXT';    // Jusqu'à 255 caractères
    case TEXT = 'TEXT';            // Jusqu'à 65,535 caractères
    case MEDIUMTEXT = 'MEDIUMTEXT'; // Jusqu'à 16,777,215 caractères
    case LONGTEXT = 'LONGTEXT';    // Jusqu'à 4,294,967,295 caractères
}
```

### Types Binaires

```php
enum ColumnType: string
{
    case BINARY = 'BINARY';        // Données binaires longueur fixe
    case VARBINARY = 'VARBINARY';  // Données binaires longueur variable
    case TINYBLOB = 'TINYBLOB';    // BLOB jusqu'à 255 octets
    case BLOB = 'BLOB';            // BLOB jusqu'à 65,535 octets
    case MEDIUMBLOB = 'MEDIUMBLOB'; // BLOB jusqu'à 16,777,215 octets
    case LONGBLOB = 'LONGBLOB';    // BLOB jusqu'à 4,294,967,295 octets
}
```

### 📝 Exemples d'Utilisation

```php
// Code pays (longueur fixe)
#[MtColumn(
    columnType: ColumnType::CHAR,
    length: 2,
    isNullable: false
)]
private string $countryCode;

// Email (longueur variable)
#[MtColumn(
    columnType: ColumnType::VARCHAR,
    length: 320,
    columnKey: ColumnKey::UNIQUE_KEY
)]
private string $email;

// Description longue
#[MtColumn(columnType: ColumnType::TEXT)]
private ?string $description = null;

// Fichier uploadé
#[MtColumn(columnType: ColumnType::BLOB)]
private ?string $fileData = null;
```

---

## Types de Date et Heure

```php
enum ColumnType: string
{
    case DATE = 'DATE';            // Date uniquement (YYYY-MM-DD)
    case TIME = 'TIME';            // Heure uniquement (HH:MM:SS)
    case DATETIME = 'DATETIME';    // Date et heure (YYYY-MM-DD HH:MM:SS)
    case TIMESTAMP = 'TIMESTAMP';  // Timestamp avec timezone
    case YEAR = 'YEAR';            // Année uniquement (YYYY)
}
```

### 📝 Exemples d'Utilisation

```php
// Date de naissance
#[MtColumn(columnType: ColumnType::DATE)]
private ?DateTime $birthDate = null;

// Heure d'ouverture
#[MtColumn(columnType: ColumnType::TIME)]
private ?DateTime $openingTime = null;

// Date de création
#[MtColumn(
    columnType: ColumnType::DATETIME,
    columnDefault: 'CURRENT_TIMESTAMP'
)]
private DateTime $createdAt;

// Dernière modification (mise à jour automatique)
#[MtColumn(
    columnType: ColumnType::TIMESTAMP,
    columnDefault: 'CURRENT_TIMESTAMP',
    extra: 'ON UPDATE CURRENT_TIMESTAMP'
)]
private DateTime $updatedAt;

// Année de production
#[MtColumn(columnType: ColumnType::YEAR)]
private ?int $productionYear = null;
```

---

## Types Spéciaux

### Types d'Énumération

```php
enum ColumnType: string
{
    case ENUM = 'ENUM';            // Choix parmi une liste de valeurs
    case SET = 'SET';              // Combinaison de valeurs d'une liste
}
```

### Type JSON

```php
enum ColumnType: string
{
    case JSON = 'JSON';            // Données JSON (MySQL 5.7.8+)
}
```

### Types Géométriques

```php
enum ColumnType: string
{
    case GEOMETRY = 'GEOMETRY';    // Données géométriques génériques
    case POINT = 'POINT';          // Point géographique
    case LINESTRING = 'LINESTRING'; // Ligne géographique
    case POLYGON = 'POLYGON';      // Polygone géographique
}
```

### 📝 Exemples d'Utilisation

```php
// Statut avec choix limités
#[MtColumn(
    columnType: ColumnType::ENUM,
    choices: ['draft', 'published', 'archived'],
    columnDefault: 'draft'
)]
private string $status;

// Données JSON
#[MtColumn(columnType: ColumnType::JSON)]
private ?array $metadata = null;

// Coordonnées géographiques
#[MtColumn(columnType: ColumnType::POINT)]
private ?string $coordinates = null;
```

---

## Méthodes Utilitaires

L'enum `ColumnType` fournit des méthodes utilitaires pour faciliter son utilisation :

### Vérification des Propriétés

```php
// Vérifier si un type peut être unsigned
if ($columnType->canBeUnsigned()) {
    // Appliquer isUnsigned: true
}

// Vérifier si un type nécessite une longueur
if ($columnType->isTypeWithLength()) {
    // Spécifier le paramètre length
}

// Vérifier si un type nécessite une précision
if ($columnType->requiresPrecision()) {
    // Spécifier les paramètres length (précision) et scale
}
```

### 📝 Exemple d'Utilisation

```php
class ColumnHelper
{
    public function validateColumnDefinition(
        ColumnType $type,
        ?int $length = null,
        ?int $scale = null,
        bool $unsigned = false
    ): bool {
        // Vérifier si unsigned est applicable
        if ($unsigned && !$type->canBeUnsigned()) {
            throw new InvalidArgumentException(
                "Type {$type->value} ne peut pas être unsigned"
            );
        }
        
        // Vérifier si la longueur est requise
        if ($type->isTypeWithLength() && $length === null) {
            throw new InvalidArgumentException(
                "Type {$type->value} nécessite une longueur"
            );
        }
        
        // Vérifier la précision pour les décimaux
        if ($type->requiresPrecision() && $length === null) {
            throw new InvalidArgumentException(
                "Type {$type->value} nécessite une précision"
            );
        }
        
        return true;
    }
}
```

---

## Guide de Sélection

### Types Numériques

| Cas d'Usage | Type Recommandé | Remarques |
|-------------|-----------------|-----------|
| ID/Clé primaire | `INT` unsigned | Auto-increment |
| Compteurs | `INT` unsigned | Valeurs positives |
| Booléens | `TINYINT` | 0/1 ou nullable |
| Prix/Montants | `DECIMAL(10,2)` | Précision exacte |
| Pourcentages | `FLOAT` | Précision suffisante |
| Très grandes valeurs | `BIGINT` | > 2 milliards |

### Types de Texte

| Cas d'Usage | Type Recommandé | Remarques |
|-------------|-----------------|-----------|
| Noms courts | `VARCHAR(100)` | Longueur adaptée |
| Emails | `VARCHAR(320)` | RFC 5321 standard |
| URLs | `VARCHAR(2048)` | Longueur maximale courante |
| Descriptions | `TEXT` | Contenu variable |
| Articles/Contenus | `MEDIUMTEXT` | Gros volumes |
| Codes fixes | `CHAR(n)` | Longueur constante |

### Types de Date

| Cas d'Usage | Type Recommandé | Remarques |
|-------------|-----------------|-----------|
| Dates simples | `DATE` | Anniversaires, échéances |
| Horodatage complet | `DATETIME` | Logs, historique |
| Gestion automatique | `TIMESTAMP` | Created/updated |
| Heures seulement | `TIME` | Horaires, durées |
| Années seulement | `YEAR` | Archives, statistiques |

---

## Exemples Avancés

### Entité E-commerce Complète

```php
#[MtEntity(tableName: 'products')]
class Product
{
    #[MtColumn(
        columnType: ColumnType::INT,
        isUnsigned: true,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 200,
        isNullable: false
    )]
    private string $name;

    #[MtColumn(
        columnType: ColumnType::CHAR,
        length: 13,
        columnKey: ColumnKey::UNIQUE_KEY
    )]
    private string $sku; // Code produit

    #[MtColumn(
        columnType: ColumnType::DECIMAL,
        length: 10,
        scale: 2,
        isUnsigned: true
    )]
    private float $price;

    #[MtColumn(
        columnType: ColumnType::ENUM,
        choices: ['draft', 'active', 'discontinued'],
        columnDefault: 'draft'
    )]
    private string $status;

    #[MtColumn(columnType: ColumnType::TEXT)]
    private ?string $description = null;

    #[MtColumn(columnType: ColumnType::JSON)]
    private ?array $specifications = null;

    #[MtColumn(
        columnType: ColumnType::INT,
        isUnsigned: true,
        columnDefault: '0'
    )]
    private int $stockQuantity = 0;

    #[MtColumn(columnType: ColumnType::FLOAT)]
    private ?float $weight = null;

    #[MtColumn(
        columnType: ColumnType::TIMESTAMP,
        columnDefault: 'CURRENT_TIMESTAMP'
    )]
    private DateTime $createdAt;

    #[MtColumn(
        columnType: ColumnType::TIMESTAMP,
        columnDefault: 'CURRENT_TIMESTAMP',
        extra: 'ON UPDATE CURRENT_TIMESTAMP'
    )]
    private DateTime $updatedAt;

    // Getters et setters...
}
```

---

## ➡️ Étapes Suivantes

Pour approfondir votre compréhension :

1. 🎨 [Attributs de Mapping](attributes.md) - Utilisation des attributs
2. 🔗 [Relations](relationships.md) - Relations entre entités
3. 🗄️ [Repositories](../../fr/orm/repositories.md) - Gestion des requêtes

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../fr/../README.md)
- 📖 [Documentation Complète](../../fr/README.md)
- 🚀 [Démarrage Rapide](../../fr/quick-start/installation.md)
