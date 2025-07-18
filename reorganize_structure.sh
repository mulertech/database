#!/bin/bash

# Script de réorganisation de la structure du repository database
# À exécuter depuis la racine du projet

echo "=== Réorganisation de la structure des dossiers ==="

# 1. Créer la nouvelle structure
echo "Création de la nouvelle structure..."

mkdir -p src/Core/Traits
mkdir -p src/Core/Cache
mkdir -p src/Core/Parameters

mkdir -p src/Query/Builder
mkdir -p src/Query/Clause
mkdir -p src/Query/Compiler

mkdir -p src/ORM/Engine/Persistence
mkdir -p src/ORM/Engine/Relations
mkdir -p src/ORM/State
mkdir -p src/ORM/Repository
mkdir -p src/ORM/Metadata

mkdir -p src/Schema/Migration
mkdir -p src/Schema/Information
mkdir -p src/Schema/Builder ************************* non créé
mkdir -p src/Schema/Diff

mkdir -p src/Mapping/Attributes
mkdir -p src/Mapping/Metadata

mkdir -p src/Database/Connection
mkdir -p src/Database/Driver
mkdir -p src/Database/Interface

mkdir -p src/SQL/Operator
mkdir -p src/SQL/Expression
mkdir -p src/SQL/Type

# 2. Déplacer les fichiers existants
echo "Déplacement des fichiers..."

# Core
mv src/Core/Traits/*.php src/Core/Traits/ 2>/dev/null || true
mv src/Core/Cache/*.php src/Core/Cache/ 2>/dev/null || true
mv src/Core/Parameters/*.php src/Core/Parameters/ 2>/dev/null || true

# Query
mv src/Query/*Builder.php src/Query/Builder/ 2>/dev/null || true
mv src/Query/QueryCompiler.php src/Query/Compiler/ 2>/dev/null || true
mv src/Query/AbstractQueryBuilder.php src/Query/Builder/ 2>/dev/null || true
mv src/Query/Clause/*.php src/Query/Clause/ 2>/dev/null || true

# ORM
mv src/ORM/Engine/Persistence/*.php src/ORM/Engine/Persistence/ 2>/dev/null || true
mv src/ORM/Engine/Relations/*.php src/ORM/Engine/Relations/ 2>/dev/null || true
mv src/ORM/State/*.php src/ORM/State/ 2>/dev/null || true
mv src/ORM/*Repository.php src/ORM/Repository/ 2>/dev/null || true
mv src/ORM/EntityMetadata.php src/ORM/Metadata/ 2>/dev/null || true

# Schema/Migration
mv src/Migration/*.php src/Schema/Migration/ 2>/dev/null || true
mv src/Migration/Schema/*.php src/Schema/Diff/ 2>/dev/null || true
mv src/Relational/Sql/InformationSchema*.php src/Schema/Information/ 2>/dev/null || true
mv src/Schema/*.php src/Schema/Builder/ 2>/dev/null || true

# Mapping
mv src/Mapping/Mt*.php src/Mapping/Attributes/ 2>/dev/null || true
mv src/Mapping/*Type.php src/Mapping/Metadata/ 2>/dev/null || true

# Database
mv src/PhpInterface/*.php src/Database/Interface/ 2>/dev/null || true
mv src/Database/Connection.php src/Database/Connection/ 2>/dev/null || true 2>/dev/null || true

# SQL
mv src/Relational/Sql/*Operator*.php src/SQL/Operator/ 2>/dev/null || true
mv src/Relational/Sql/Raw.php src/SQL/Expression/ 2>/dev/null || true
mv src/Relational/Sql/JoinType.php src/SQL/Type/ 2>/dev/null || true
mv src/Relational/Sql/LinkOperator.php src/SQL/Type/ 2>/dev/null || true

# 3. Supprimer les anciens dossiers vides
echo "Nettoyage des anciens dossiers..."
find src -type d -empty -delete 2>/dev/null || true

echo "=== Réorganisation terminée ==="
echo ""
echo "Nouvelle structure :"
tree src -d -L 3