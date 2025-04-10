<?php

     namespace MulerTech\Database\Mapping;

     use MulerTech\FileManipulation\FileType\Php;
     use ReflectionClass;
     use ReflectionException;
     use RuntimeException;

     class DbMapping implements DbMappingInterface
     {
         /** @var array<class-string, string> $tables */
         private array $tables = [];
         /** @var array<string, array<string, string>> $columns */
         private array $columns = [];

         /**
          * @param string $entitiesPath
          * @param bool $recursive
          */
         public function __construct(
             private readonly string $entitiesPath,
             private readonly bool $recursive = true
         ) {}

         /**
          * @param class-string $entityName
          * @return string|null
          * @throws ReflectionException
          */
         public function getTableName(string $entityName): ?string
         {
             $this->initializeTables();
             return $this->tables[$entityName] ?? null;
         }

         /**
          * @return array<string>
          * @throws ReflectionException
          */
         public function getTables(): array
         {
             $this->initializeTables();
             $tables = $this->tables;
             sort($tables);
             return $tables;
         }

         /**
          * @return array<class-string>
          * @throws ReflectionException
          */
         public function getEntities(): array
         {
             $this->initializeTables();
             $entities = array_keys($this->tables);
             sort($entities);
             return $entities;
         }

         /**
          * @param class-string $entityName
          * @return class-string|null
          * @throws ReflectionException
          */
         public function getRepository(string $entityName): ?string
         {
             $mtEntity = $this->getMtEntity($entityName);

             if (is_null($mtEntity)) {
                 throw new RuntimeException("The MtEntity mapping is not implemented into the $entityName class.");
             }

             return $mtEntity->repository;
         }

         /**
          * @param class-string $entityName
          * @return int|null
          * @throws ReflectionException
          */
         public function getAutoIncrement(string $entityName): ?int
         {
             return $this->getMtEntity($entityName)?->autoIncrement;
         }

         /**
          * @param class-string $entityName
          * @return array<string>
          * @throws ReflectionException
          */
         public function getColumns(string $entityName): array
         {
             $this->initializeColumns($entityName);
             return array_values($this->columns[$entityName]);
         }

         /**
          * @param class-string $entityName
          * @return array<string, string>
          * @throws ReflectionException
          */
         public function getPropertiesColumns(string $entityName): array
         {
             $this->initializeColumns($entityName);
             return $this->columns[$entityName];
         }

         /**
          * @param class-string $entityName
          * @param string $property
          * @return string|null
          * @throws ReflectionException
          */
         public function getColumnName(string $entityName, string $property): ?string
         {
             $columns = $this->getPropertiesColumns($entityName);
             return $columns[$property] ?? null;
         }

         /**
          * @param class-string $entityName
          * @param string $property
          * @return string|null
          * @throws ReflectionException
          */
         public function getColumnType(string $entityName, string $property): ?string
         {
             return $this->getMtColumns($entityName)[$property]->columnType ?? null;
         }

         /**
          * @param class-string $entityName
          * @param string $property
          * @return bool|null
          * @throws ReflectionException
          */
         public function isNullable(string $entityName, string $property): ?bool
         {
             return $this->getMtColumns($entityName)[$property]->isNullable ?? null;
         }

         /**
          * @param class-string $entityName
          * @param string $property
          * @return string|null
          * @throws ReflectionException
          */
         public function getExtra(string $entityName, string $property): ?string
         {
             return $this->getMtColumns($entityName)[$property]->extra ?? null;
         }

         /**
          * @param class-string $entityName
          * @param string $property
          * @return string|null
          * @throws ReflectionException
          */
         public function getColumnDefault(string $entityName, string $property): ?string
         {
             return $this->getMtColumns($entityName)[$property]->columnDefault ?? null;
         }

         /**
          * @param class-string $entityName
          * @param string $property
          * @return string|null
          * @throws ReflectionException
          */
         public function getColumnKey(string $entityName, string $property): ?string
         {
             return $this->getMtColumns($entityName)[$property]->columnKey->value ?? null;
         }

         /**
          * @param class-string $entityName
          * @param string $property
          * @return MtFk|null
          * @throws ReflectionException
          */
         public function getForeignKey(string $entityName, string $property): ?MtFk
         {
             return $this->getMtFk($entityName)[$property] ?? null;
         }

         /**
          * @param class-string $entityName
          * @param string $property
          * @return string|null
          * @throws ReflectionException
          */
         public function getConstraintName(string $entityName, string $property): ?string
         {
             $mtFk = $this->getForeignKey($entityName, $property);

             if ($mtFk === null || $mtFk->referencedTable === null) {
                 return null;
             }

             $referencedTable = $this->getTableName($mtFk->referencedTable);
             $column = $this->getColumnName($entityName, $property);
             $table = $this->getTableName($entityName);

             if (!$referencedTable || !$column || !$table) {
                 return null;
             }

             return "fk_{$table}_{$column}_{$referencedTable}";
         }

         /**
          * @param class-string $entityName
          * @param string $property
          * @return string|null
          * @throws ReflectionException
          */
         public function getReferencedTable(string $entityName, string $property): ?string
         {
             $mtFk = $this->getForeignKey($entityName, $property);

             if ($mtFk === null || $mtFk->referencedTable === null) {
                 return null;
             }

             return $this->getTableName($mtFk->referencedTable);
         }

         /**
          * @param class-string $entityName
          * @param string $property
          * @return string|null
          * @throws ReflectionException
          */
         public function getReferencedColumn(string $entityName, string $property): ?string
         {
             $mtFk = $this->getForeignKey($entityName, $property);

             if ($mtFk === null || $mtFk->referencedTable === null) {
                 return null;
             }

             return $mtFk->referencedColumn ?? null;
         }

         /**
          * @param class-string $entityName
          * @param string $property
          * @return string|null
          * @throws ReflectionException
          */
         public function getDeleteRule(string $entityName, string $property): ?string
         {
             $mtFk = $this->getForeignKey($entityName, $property);

             if ($mtFk === null || $mtFk->referencedTable === null) {
                 return null;
             }

             return $mtFk->deleteRule->value ?? null;
         }

         /**
          * @param class-string $entityName
          * @param string $property
          * @return string|null
          * @throws ReflectionException
          */
         public function getUpdateRule(string $entityName, string $property): ?string
         {
             $mtFk = $this->getForeignKey($entityName, $property);

             if ($mtFk === null || $mtFk->referencedTable === null) {
                 return null;
             }

             return $mtFk->updateRule->value ?? null;
         }

         /**
          * @param class-string $entityName
          * @return array|null
          * @throws ReflectionException
          */
         public function getOneToOne(string $entityName): ?array
         {
             return Php::getInstanceOfPropertiesAttributesNamed($entityName, MtOneToOne::class);
         }

         /**
          * @param class-string $entityName
          * @return array|null
          * @throws ReflectionException
          */
         public function getOneToMany(string $entityName): ?array
         {
             return Php::getInstanceOfPropertiesAttributesNamed($entityName, MtOneToMany::class);
         }

         /**
          * @param class-string $entityName
          * @return array|null
          * @throws ReflectionException
          */
         public function getManyToOne(string $entityName): ?array
         {
             return Php::getInstanceOfPropertiesAttributesNamed($entityName, MtManyToOne::class);
         }

         /**
          * @param class-string $entityName
          * @return array|null
          * @throws ReflectionException
          */
         public function getManyToMany(string $entityName): ?array
         {
             return Php::getInstanceOfPropertiesAttributesNamed($entityName, MtManyToMany::class);
         }

         /**
          * Todo : if it not used delete it
          * @param class-string $entityName
          * @param string $property
          * @return MtOneToOne|MtOneToMany|MtManyToOne|MtManyToMany|null
          * @throws ReflectionException
          */
         public function getRelatedProperty(
             string $entityName,
             string $property
         ): MtOneToOne|MtOneToMany|MtManyToOne|MtManyToMany|null {
             $relation = $this->getRelation($entityName, $property, MtOneToOne::class)
                 ?? $this->getRelation($entityName, $property, MtOneToMany::class)
                 ?? $this->getRelation($entityName, $property, MtManyToOne::class)
                 ?? $this->getRelation($entityName, $property, MtManyToMany::class);

             return $relation;
         }

         /**
          * @return void
          * @throws ReflectionException
          */
         private function initializeTables(): void
         {
             if (empty($this->tables)) {
                 $classNames = Php::getClassNames($this->entitiesPath, $this->recursive);
                 foreach ($classNames as $className) {
                     $table = $this->generateTableName($className);
                     if ($table) {
                         $this->tables[$className] = $table;
                     }
                 }
             }
         }

         /**
          * @param class-string $entityName
          * @return string|null
          * @throws ReflectionException
          */
         private function generateTableName(string $entityName): ?string
         {
             $mtEntity = $this->getMtEntity($entityName);

             if (!$mtEntity) {
                 return null;
             }

             return $mtEntity->tableName ?? strtolower(new ReflectionClass($entityName)->getShortName());
         }

         /**
          * @param class-string $entityName
          * @return void
          * @throws ReflectionException
          */
         private function initializeColumns(string $entityName): void
         {
             if (!isset($this->columns[$entityName])) {
                 $result = [];
                 foreach ($this->getMtColumns($entityName) as $property => $mtColumn) {
                     $result[$property] = $mtColumn->columnName ?? $property;
                 }

                 $this->columns[$entityName] = $result;
             }
         }

         /**
          * @param class-string $entityName
          * @return MtEntity|null
          * @throws ReflectionException
          */
         private function getMtEntity(string $entityName): ?MtEntity
         {
             $entity = Php::getInstanceOfClassAttributeNamed($entityName, MtEntity::class);
             return $entity instanceof MtEntity ? $entity : null;
         }

         /**
          * @param class-string $entityName
          * @return array<string, MtColumn>
          * @throws ReflectionException
          */
         private function getMtColumns(string $entityName): array
         {
             $columns = Php::getInstanceOfPropertiesAttributesNamed($entityName, MtColumn::class);
             return array_filter($columns, static fn ($column) => $column instanceof MtColumn);
         }

         /**
          * @param class-string $entityName
          * @return array<string, MtFk>
          * @throws ReflectionException
          */
         private function getMtFk(string $entityName): array
         {
             return array_filter(
                 Php::getInstanceOfPropertiesAttributesNamed($entityName, MtFk::class),
                 static fn ($fk) => $fk instanceof MtFk
             );
         }

         /**
          * @param class-string $entityName
          * @param string $property
          * @param class-string $relationClass
          * @return MtOneToOne|MtOneToMany|MtManyToOne|MtManyToMany|null
          * @throws ReflectionException
          */
         private function getRelation(
             string $entityName,
             string $property,
             string $relationClass
         ): MtOneToOne|MtOneToMany|MtManyToOne|MtManyToMany|null {
             return Php::getInstanceOfPropertiesAttributesNamed($entityName, $relationClass)[$property] ?? null;
         }
     }