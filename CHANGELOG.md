# Changelog

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Non publié]

### Ajouté
- Architecture de documentation complète
- Système de migration de schéma
- Support pour les transactions complexes
- Tests d'intégration avec Docker
- Documentation multilingue (FR/EN)

### Modifié
- Amélioration des performances du QueryBuilder
- Optimisation du système de cache
- Refactoring de l'EntityManager

### Corrigé
- Correction des fuites mémoire dans le ChangeDetector
- Résolution des problèmes de sérialization des entités
- Amélioration de la gestion des connexions concurrentes

## [1.0.0] - 2024-XX-XX

### Ajouté
- Version initiale de MulerTech Database ORM
- Support MySQL avec driver natif
- EntityManager et système de mapping
- QueryBuilder fluide
- Système d'événements
- Cache intégré
- Gestion des relations (OneToOne, OneToMany, ManyToMany)
- Attributes PHP 8+ pour le mapping
- Documentation complète

### Sécurité
- Protection contre les injections SQL
- Validation des entrées utilisateur
- Chiffrement des données sensibles
