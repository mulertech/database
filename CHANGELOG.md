# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## v1.0.0 - 2025-08-14

### Added

#### Core ORM Features

- **Modern ORM architecture** with Entity Manager implementing Unit of Work pattern
- **PHP 8.4+ attribute-based mapping** (`#[MtEntity]`, `#[MtColumn]`, `#[MtRelation]`)
- **Comprehensive entity lifecycle management** with automatic change tracking
- **Identity map implementation** ensuring single instance per entity per session
- **Entity state management** (new, managed, detached, removed)
- **Advanced metadata registry** with reflection-based attribute parsing

#### Database Access Layer (DBAL)

- **MySQL database driver** with native PDO integration
- **Connection pooling** and transaction management
- **Prepared statement support** with automatic parameter binding
- **Database parameter parsing** from environment variables
- **Connection state monitoring** and automatic reconnection

#### Query Builder

- **Fluent API** for building complex SQL queries
- **Type-safe query construction** with IDE autocompletion support
- **Advanced JOIN support** (INNER, LEFT, RIGHT, FULL OUTER)
- **Subquery support** for complex data retrieval
- **Aggregation functions** (COUNT, SUM, AVG, MIN, MAX)
- **Raw SQL integration** for custom queries when needed
- **Query optimization** and performance monitoring

#### Entity Mapping System

- **Comprehensive column types** (VARCHAR, INT, DECIMAL, DATETIME, JSON, ENUM, etc.)
- **Column constraints** (NOT NULL, UNIQUE, PRIMARY KEY, FOREIGN KEY)
- **Automatic table and column name mapping** with snake_case conversion
- **Custom repository classes** for domain-specific query methods
- **Relationship mapping** (OneToOne, OneToMany, ManyToMany via foreign keys)
- **Data type validation** and conversion between PHP and database formats

#### Schema Management

- **Database migration system** with version control
- **Automatic migration generation** from entity definitions
- **Schema comparison tools** for detecting database drift
- **Migration rollback support** with down() methods
- **CLI commands** via MTerm framework integration
  - `migration:generate` - Generate migrations from entity changes
  - `migration:run` - Execute pending migrations
  - `migration:rollback` - Rollback last migration
  
- **Migration history tracking** with execution time monitoring

#### Event System

- **Comprehensive entity lifecycle events**:
  - `PrePersist` / `PostPersist` - Before/after entity creation
  - `PreUpdate` / `PostUpdate` - Before/after entity modification
  - `PreRemove` / `PostRemove` - Before/after entity deletion
  - `PreFlush` / `PostFlush` - Before/after batch operations
  
- **Event listener registration** and priority-based execution
- **Change set access** in update events for audit trails

#### Caching System

- **Multi-level caching architecture**:
  - Metadata caching for entity definitions
  - Query result caching for performance
  - Identity map caching for object consistency
  
- **Cache invalidation strategies** with automatic cleanup
- **Configurable cache backends** and TTL settings

#### Repository Pattern

- **Base repository class** with standard CRUD operations
- **Custom repository support** for domain-specific queries
- **Specification pattern implementation** for complex query logic
- **Pagination support** for large result sets
- **Bulk operation methods** for performance optimization

#### Change Tracking

- **Automatic change detection** for managed entities
- **Property-level change tracking** with old/new value comparison
- **Batch update optimization** with single SQL statements
- **Memory-efficient change storage** for large datasets
- **Change set validation** before database persistence

#### Data Access Features

- **Repository factory pattern** for dependency injection
- **Entity hydration** from database results
- **Lazy loading** for related entities and collections
- **Batch operations** for improved performance
- **Transaction support** with automatic rollback on errors

#### Developer Experience

- **Comprehensive documentation** in French and English
- **Code examples** for common use cases (blog, e-commerce)
- **IDE autocompletion** support with proper PHPDoc annotations
- **Error handling** with descriptive exception messages
- **Debugging tools** for query inspection and performance analysis

#### Testing Infrastructure

- **PHPUnit integration** with comprehensive test suite
- **Test utilities** for database setup and teardown
- **Docker support** for consistent testing environments
- **Code coverage** reporting and quality metrics
- **Static analysis** with PHPStan level 9 compliance

#### Documentation

- **Complete API documentation** with usage examples
- **Quick start guides** for rapid development
- **Advanced tutorials** for complex scenarios
- **Best practices** and performance guidelines
- **Troubleshooting guides** for common issues
- **Migration from other ORMs** documentation

### Technical Specifications

- **PHP 8.4+ requirement** with strict typing throughout
- **PSR-12 compliant** code formatting
- **Modern PHP features**: property promotion, union types, enums, attributes
- **Zero external dependencies** for core functionality
- **Memory efficient** with automatic cleanup
- **Thread-safe** operations for concurrent access

### Security

- **SQL injection prevention** through prepared statements
- **Input validation** at entity and database levels
- **Parameter sanitization** for all user inputs
- **Connection security** with encrypted database connections
- **Access control** patterns for multi-tenant applications

### Performance

- **Optimized query execution** with connection reuse
- **Batch operation support** for large datasets
- **Memory management** with automatic cleanup
- **Lazy loading** to reduce unnecessary queries
- **Query caching** for frequently accessed data
- **Index optimization** suggestions in migration tools

### Standards Compliance

- **PSR-1**: Basic Coding Standard
- **PSR-12**: Extended Coding Style
- **PSR-4**: Autoloader compliance
- **Semantic Versioning**: Version management
- **Keep a Changelog**: Documentation standard


---

## Upgrading

This is the initial release of MulerTech Database. No upgrade path is required.

## Credits

Developed by [Sébastien Muler](https://github.com/mulertech) at [MulerTech](https://mulertech.net).

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
