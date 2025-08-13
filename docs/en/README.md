# MulerTech Database Documentation

Complete guide for the modern ORM and Query Builder for PHP 8.4+.

## 🚀 Quick start

**New to MulerTech Database?** Start with these essential guides:

- **[Installation](quick-start/installation.md)** - Configuration and prerequisites
- **[First steps](quick-start/first-steps.md)** - Your first entity and CRUD operations  
- **[Basic examples](quick-start/basic-examples.md)** - Practical use cases

## 📚 Main guides

### 🏗️ Fundamental concepts
- **[Architecture](fundamentals/architecture.md)** - System overview
- **[Dependency injection](fundamentals/dependency-injection.md)** - Configuration and DI
- **[Core classes](fundamentals/core-classes.md)** - Base API
- **[Interfaces](fundamentals/interfaces.md)** - Contracts and abstractions

### 📊 Entity mapping
- **[Attributes](entity-mapping/attributes.md)** - `#[MtEntity]`, `#[MtColumn]`, etc.
- **[Types and columns](entity-mapping/types-and-columns.md)** - Field configuration
- **[Relationships](entity-mapping/relationships.md)** - OneToMany, ManyToMany, etc.

### 💾 Data access
- **[Entity Manager](data-access/entity-manager.md)** - Entity management
- **[Repositories](data-access/repositories.md)** - Data access layer
- **[Query Builder](data-access/query-builder.md)** - Query construction
- **[Raw SQL queries](data-access/raw-queries.md)** - Direct SQL
- **[Change tracking](data-access/change-tracking.md)** - Automatic detection
- **[Event system](data-access/events.md)** - Hooks and callbacks

### 🔄 Schema migrations
- **[Migrations](schema-migrations/migrations.md)** - Complete migration guide
- **[Migration tools](schema-migrations/migration-tools.md)** - CLI and schema comparison

## 🎯 Recommended learning path

### Beginner
1. [Installation](quick-start/installation.md)
2. [First steps](quick-start/first-steps.md)
3. [Architecture](fundamentals/architecture.md)
4. [Attributes](entity-mapping/attributes.md)

### Intermediate  
1. [Entity Manager](data-access/entity-manager.md)
2. [Query Builder](data-access/query-builder.md)
3. [Relationships](entity-mapping/relationships.md)
4. [Migrations](schema-migrations/migrations.md)

### Advanced
1. [Change tracking](data-access/change-tracking.md)
2. [Event system](data-access/events.md)
3. [Raw queries](data-access/raw-queries.md)
4. [Migration tools](schema-migrations/migration-tools.md)

## 💡 Tips and best practices

- **Use attributes** for clean, modern mapping
- **Leverage repositories** for domain-specific queries
- **Enable change tracking** for automatic updates
- **Use migrations** for schema versioning
- **Follow PSR-12** coding standards
