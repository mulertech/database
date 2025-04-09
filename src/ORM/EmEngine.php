<?php

namespace MulerTech\Database\ORM;

use _config\UpdateDatabaseMysql;
use App\Entity\Version;
use Exception;
use MulerTech\ArrayManipulation\ArrayManipulation;
use MulerTech\Collections\Collection;
use MulerTech\Database\Event\DbEvents;
use MulerTech\Database\Event\PostFlushEvent;
use MulerTech\Database\Event\PostPersistEvent;
use MulerTech\Database\Event\PostRemoveEvent;
use MulerTech\Database\Event\PostUpdateEvent;
use MulerTech\Database\Event\PrePersistEvent;
use MulerTech\Database\Event\PreUpdateEvent;
use MulerTech\Database\Mapping\MtFk;
use MulerTech\Database\Mapping\MtManyToMany;
use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use MulerTech\Database\PhpInterface\Statement;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Relational\Sql\SqlOperations;
use MulerTech\EventManager\EventManagerInterface;
use MulerTech\FileManipulation\FileType\Json;
use PDO;
use PDOStatement;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * Class EmEngine
 * @package MulerTech\Database\ORM
 * @author Sébastien Muler
 */
class EmEngine
{
    /**
     * @deprecated use DbMapping
     * @var string
     */
    private const string DB_STRUCTURE_PATH = ".." . DIRECTORY_SEPARATOR . "_config" . DIRECTORY_SEPARATOR;
    /**
     * @deprecated use DbMapping
     * @var string
     */
    private const string DB_STRUCTURE_NAME = 'dbstructure.json';

    /**
     * @var array<int, object> The list of managed entities for example :
     * [$objectId => $entity]
     */
    private array $managedEntities = [];
    /**
     * @var array<int, object> The list of entities to be inserted for example :
     * [$objectId => $entity]
     */
    private array $entityInsertions = [];
    /**
     * @var array<int, object> The list of entities to be updated for example :
     * [$objectId => $entity]
     */
    private array $entityUpdates = [];
    /**
     * @var array<int, object> The list of entities to be deleted for example :
     * [$objectId => $entity]
     */
    private array $entityDeletions = [];
    /**
     * @var array<object> The list of MtManyToMany objects to be inserted.
     */
    private array $manyToManyInsertions = [];
    /**
     * @var array<int, array<int, int>> The list of entities to be inserted into a pivot table. For example :
     * [$objectId1 => [$objectId2, $objectId3]]
     */
    private array $collectionUpdates = [];
    /**
     * @var array<int, array<string, array<int, string>>> Save all the entity changes example :
     * [$objectId => [$property => [$oldValue, $newValue]]]
     */
    private array $entityChanges = [];
    /**
     * @var array<int, array<string, mixed>> Save the original entity before update example :
     * [$objectId => [$property => $value]]
     */
    private array $originalEntityData = [];
    /**
     * @var array<int, array<int>> $entityInsertionOrder
     * * [$objectId1 => [$objectId2, $objectId3]] $objectId1 must be inserted after $objectId2 and $objectId3
     */
    private array $entityInsertionOrder = [];
    /**
     * @var array<class-string, array<int, string>> Save the entity and this event when updated example :
     * [$entityName => $eventCalled]
     */
    private array $eventCalled = [];
    /**
     * @deprecated
     * @var array
     */
    private array $tablesLinked = [];
    /**
     * @deprecated
     * @var string
     */
    private string $join = '';
    /**
     * @var EventManagerInterface|null $eventManager
     */
    private ?EventManagerInterface $eventManager;
    /**
     * @var class-string $entityName
     * @todo Replace this entityName by EntityMapping
     */
    private string $entityName;
    /**
     * @var EntityRelationLoader $entityRelationLoader
     */
    private EntityRelationLoader $entityRelationLoader;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        $this->eventManager = $this->entityManager->getEventManager();
        $this->entityRelationLoader = new EntityRelationLoader($this->entityManager);
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * @param object|class-string $entity
     * @deprecated do not use $this->entityName
     */
    protected function setEntity(object|string $entity): void
    {
        if (is_object($entity)) {
            $this->entityName = get_class($entity);
        } else {
            $this->entityName = $entity;
        }
    }

    /**
     * @return string
     * @deprecated do not use $this->entityName
     */
    protected function getEntity(): string
    {
        return $this->entityName;
    }

    /**
     * @return string
     * @deprecated do not use $this->entityName
     */
    protected function getEntityTable(): string
    {
        if (!isset($this->entityName)) {
            throw new RuntimeException(
                'Class : EmEngine, Function : getEntityTable. The entity variable of the EmEngine was not set.'
            );
        }
        $entityParts = explode('\\', $this->entityName);
        return strtolower(end($entityParts));
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param class-string $entityName
     * @param bool $loadRelations
     * @return object|null
     * @throws ReflectionException
     */
    public function getQueryBuilderObjectResult(
        QueryBuilder $queryBuilder,
        string $entityName,
        bool $loadRelations = true
    ): ?object {
        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();
        $pdoStatement->SetFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $entityName);

        $fetchClass = $pdoStatement->fetch();
        $pdoStatement->closeCursor();

        if ($fetchClass === false) {
            return null;
        }

        $this->manageNewEntity($fetchClass, $loadRelations);

        return $fetchClass;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param class-string $entityName
     * @param bool $loadRelations
     * @return array|null
     * @throws ReflectionException
     */
    public function getQueryBuilderListResult(
        QueryBuilder $queryBuilder,
        string $entityName,
        bool $loadRelations = true
    ): ?array {
        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();

        $classList = $pdoStatement->fetchAll(PDO::FETCH_CLASS, $entityName);

        if ($classList === []) {
            return null;
        }

        $pdoStatement->closeCursor();

        foreach ($classList as $class) {
            $this->manageNewEntity($class, $loadRelations);
        }

        return $classList;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param class-string $entityName
     * @param bool $loadRelations
     * @return array|null
     * @throws ReflectionException
     */
    public function getQueryBuilderListKeyByIdResult(
        QueryBuilder $queryBuilder,
        string $entityName,
        bool $loadRelations = true
    ): ?array {
        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();

        $classList = $pdoStatement->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_CLASS, $entityName);

        if ($classList === []) {
            return null;
        }

        $pdoStatement->closeCursor();

        foreach ($classList as $class) {
            $this->manageNewEntity($class, $loadRelations);
        }

        return $classList;
    }

    /**
     * @param Object $entity
     * @param bool $loadRelations
     * @throws ReflectionException
     */
    private function manageNewEntity(Object $entity, bool $loadRelations): void
    {
        $this->computeOriginalEntity($entity);

        if ($loadRelations) {
            $this->loadEntityRelations($entity);
        }
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    private function loadEntityRelations(object $entity): void
    {
        $this->entityRelationLoader->loadRelations($entity);
    }

    /**
     * @param string $request
     * @param array|null $execute_values
     * @param string|null $output
     * @param string|null $field
     * @param array|null $bind_param
     * @return array|bool|mixed|PDOStatement|string|null
     * @deprecated use QueryBuilder
     */
    public function prepareRequest(
        string $request,
        ?array $execute_values = null,
        ?string $output = null,
        ?string $field = null,
        array $bind_param = null
    ) {
        //prepare request
        $pdoStatement = $this->entityManager->getPdm()->prepare($request);
        if (!empty($bind_param)) {
            if (!empty($bind_param['data_type'])) {
                $pdoStatement->bindParam($bind_param['parameter'], $bind_param['variable'], $bind_param['data_type']);
            } else {
                $pdoStatement->bindParam($bind_param['parameter'], $bind_param['variable']);
            }
        }
        if (!empty($execute_values)) {
            $pdoStatement->execute($execute_values);
        } else {
            $pdoStatement->execute();
        }
        //return request
        $return = null;
        if (strtolower($output) === "array" && $pdoStatement->rowCount() !== 0) {
            $return = $pdoStatement->fetch(PDO::FETCH_ASSOC);
        } elseif (!empty($field) && strtolower($output) === "field" && $pdoStatement->rowCount() !== 0) {
            $return = $pdoStatement->fetch(PDO::FETCH_ASSOC)[$field];
        } elseif ((strtolower($output) === "arraylist" || strtolower(
                    $output
                ) === "default" || ($output === null && $bind_param === null)) && $pdoStatement->rowCount() !== 0) {
            $return = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
        } elseif (strtolower($output) === "column" && $pdoStatement->rowCount() !== 0) {
            $return = $pdoStatement->fetchAll(PDO::FETCH_COLUMN);
        } elseif (strtolower($output) === "count") {
            $return = $pdoStatement->rowCount();
        } elseif (strtolower($output) === "class" || strtolower($output) === "object") {
            $pdoStatement->SetFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $this->getEntity());
            $class = $pdoStatement->fetch();
            $return = (empty($class)) ? null : $class;
        } elseif (strtolower($output) === "classlist") {
            $classList = $pdoStatement->fetchAll(PDO::FETCH_CLASS, $this->getEntity());
            $return = ($classList === []) ? null : $classList;
        } elseif (strtolower($output) === "classlistid") {
            //List of classes with array Key = id of this entity.
            $classList = $pdoStatement->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_CLASS, $this->getEntity());
            $return = ($classList === []) ? null : $classList;
        }
        //close cursor
        $pdoStatement->closeCursor();
        //Return
        return $return;
    }

    /**
     */
    private function executeInsertions(): void
    {
        if (!empty($this->entityInsertions)) {
            $entities = [];

            foreach ($this->getEntityInsertions() as $uio => $entity) {
                $queryBuilder = $this->generateInsertQueryBuilder($entity);

                //prepare request
                $pdoStatement = $queryBuilder->getResult();
                $pdoStatement->execute();

                //set id
                if (!empty($lastId = $this->entityManager->getPdm()->lastInsertId())) {
                    $this->setIdEntity($uio, $lastId);
                }

                //close cursor
                $pdoStatement->closeCursor();
                unset($this->entityInsertions[$uio]);

                //For event
                $entities[] = $entity;
            }
            //Event Post persist
            if ($this->eventManager) {
                foreach ($entities as $entity) {
                    $this->eventManager->dispatch(new PostPersistEvent($entity, $this->entityManager));
                }
            }
        }
    }

    /**
     * @return array
     */
    private function getEntityInsertions(): array
    {
        if (empty($this->entityInsertionOrder)) {
            return $this->entityInsertions;
        }

        $first = [];
        $second = [];

        foreach ($this->entityInsertionOrder as $childEntities) {
            foreach ($childEntities as $childEntityId) {
                if (!isset($this->entityInsertionOrder[$childEntityId])) {
                    $first[$childEntityId] = $this->entityInsertions[$childEntityId];
                    continue;
                }
                $second[$childEntityId] = $this->entityInsertions[$childEntityId];
            }
        }

        if ($first === []) {
            return $second + $this->entityInsertions;
        }

        if ($second === []) {
            return $first + $this->entityInsertions;
        }

        return $first + $second + $this->entityInsertions;
    }

    /**
     * @param Object $entity
     * @return QueryBuilder
     */
    private function generateInsertQueryBuilder(Object $entity): QueryBuilder
    {
        $queryBuilder = new QueryBuilder($this);
        $queryBuilder->insert($this->entityManager->getDbMapping()->getTableName($entity::class));

        $entityChanges = $this->getEntityChanges($entity);

        foreach ($this->getPropertiesColumns($entity::class, false) as $property => $column) {
            if (!isset($entityChanges[$property][1])) {
                continue;
            }

            if (is_object($entityChanges[$property][1])) {
                $entityChanges[$property][1] = $entityChanges[$property][1]->getId();
            }

            $queryBuilder->setValue($column, $queryBuilder->addNamedParameter($entityChanges[$property][1]));
        }

        return $queryBuilder;
    }

    /**
     * @param Object $entity
     * @return array<int, array<string, array<int, string>>>
     */
    private function getEntityChanges(Object $entity): array
    {
        return $this->entityChanges[spl_object_id($entity)] ?? [];
    }

    /**
     * @param class-string $entityName
     * @return array
     */
    private function getPropertiesColumns(string $entityName, bool $keepId = true): array
    {
        $propertiesColumns = $this->entityManager->getDbMapping()->getPropertiesColumns($entityName);

        if ($keepId) {
            return $propertiesColumns;
        }

        if (isset($propertiesColumns['id'])) {
            unset($propertiesColumns['id']);
        }

        return $propertiesColumns;
    }

    /**
     * @param $hash
     * @param $id
     */
    private function setIdEntity($hash, $id): void
    {
        $this->entityInsertions[$hash]?->setId($id);
    }

    /**
     * read Mysql table and push it in object
     * @param class-string $entityName
     * @param int|string|SqlOperations|null $idOrWhere
     * @return Object|null Entity filled or null
     * @throws ReflectionException
     */
    public function find(string $entityName, null|int|string|SqlOperations $idOrWhere = null): ?Object
    {
        $queryBuilder = new QueryBuilder($this);
        $queryBuilder->select('*')->from($this->entityManager->getDbMapping()->getTableName($entityName));

        if (is_numeric($idOrWhere)) {
            $queryBuilder->where('id = ' . $queryBuilder->addNamedParameter($idOrWhere));
        } else {
            $queryBuilder->where($idOrWhere);
        }

        return $this->getQueryBuilderObjectResult($queryBuilder, $entityName);
    }

    /**
     *
     * @param string $table (table of the first item)
     * @param array|null $cells (cells with ‘name’ as ‘alias’ and for items of an another table : ‘origin’ as ‘search’)
     * @param string|null $orderfor (column to order, for example : $cells[1][‘name’])
     * @param string|null $orderby (asc or desc)
     * @param string|null $idorwhere (search after the Mysql 'WHERE ')
     * @param int|null $limit (number of item by page)
     * @param int|null $page
     * @param string $sort
     * @param string|null $request
     * @param string|null $join
     * @return mixed
     */
    public function read(
        string $table,
        ?array $cells = null,
        ?string $orderfor = null,
        ?string $orderby = null,
        ?string $idorwhere = null,
        ?int $limit = null,
        ?int $page = null,
        string $sort = "default",
        ?string $request = null,
        ?string $join = null
    ) {
        $this->join = '';
        $this->tablesLinked = [];
        //If the model class was given.
        if (strpos($table, '\\')) {
            $this->setEntity($table);
            $entity = explode('\\', $table);
            $table = strtolower(end($entity));
        }
        //test if the table exist
        if (!in_array($table, $this->getEntityManager()->getDbMapping()->getTables(), true)) {
            throw new RuntimeException(
                sprintf('Class : EmEngine, Function : read. The table "%s" does not exist.', $table)
            );
        }
        //test if the cell name is indicated
        if (!empty($cells)) {
            foreach ($cells as $cellstest) {
                if (empty($cellstest['name'])) {
                    throw new RuntimeException(
                        'Class : EmEngine, Function : read. A column name does not exist into the cells variable.'
                    );
                }
            }
        }
        //test if the orderfor var is a cell
        /**
         * @todo remove this verification, if the column is an alias this can't verify the real table...
         */
//        if (!empty($orderfor) && (
//                (!is_null($cells) && (!in_array($orderfor, array_column($cells, 'name'), true))
//                ) && (
//                    (strpos($orderfor, '.') !== false) && !array_key_exists(
//                        explode('.', $orderfor)[1],
//                        $this->openDbStructure()['structure'][explode('.', $orderfor)[0]]
//                    )
//                ) && (
//                    (strpos($orderfor, '.') === false) && !array_key_exists(
//                        $orderfor,
//                        $this->openDbStructure()['structure'][$table]
//                    )
//                ))) {
//            throw new RuntimeException(
//                sprintf(
//                    'Class : EmEngine, Function : read. The column "%s" designed in orderfor variable is unknown.',
//                    $orderfor
//                )
//            );
//        }
        //test if the orderby var is ASC or DESC word
        if (!empty($orderby) && !(strcasecmp($orderby, 'asc') === 0 || strcasecmp($orderby, 'desc') === 0)) {
            throw new RuntimeException(
                sprintf(
                    'Class : EmEngine, Function : read. The orderby variable must be ASC or DESC, "%s" given.',
                    $orderby
                )
            );
        }
        //offset
        if ($page !== 0 && !is_null($limit)) {
            $offset = (is_null($page)) ? 0 : $limit * ($page - 1);
        }
        //prepare Mysql request
        $sqlreq = '';
        if (!empty($request)) {
            $sqlreq = $request;
        } elseif ($cells === null) {
            $sqlreq = "SELECT * FROM `" . $table . "`";
        } else {
            //SELECT
            foreach ($cells as $cvalue) {
                if (empty($sqlreq)) {
                    $sqlreq = "SELECT ";
                } else {
                    $sqlreq .= ", ";
                }
                //no search or search
                if (!empty($cvalue['table_linked_as'])) {
                    $cvalue['name'] = $cvalue['table_linked_as'] . '.' . explode('.', $cvalue['name'])[1];
                }
                $name_as = (!empty($cvalue['name_as'])) ? $cvalue['name_as'] : str_replace(
                    '.',
                    '',
                    $cvalue['name']
                );
                $sqlreq .= "{$cvalue['name']} AS " . $name_as;
                if (isset($cvalue['origin']) && $cvalue['origin'] !== $cvalue['name']) {
                    $origin_as = (!empty($cvalue['origin_as'])) ? $cvalue['origin_as'] : str_replace(
                        '.',
                        '',
                        $cvalue['origin']
                    );
                    $sqlreq .= ", {$cvalue['origin']} AS " . $origin_as;
                }
            }
            //FROM
            $sqlreq .= " FROM `" . $table . "` ";
            //JOIN
            /**
             * @todo link tables with the origin column name, 2 columns can be linked with the same table.
             */
            if (isset($join)) {
                $sqlreq .= $join . ' ';
            } else {
                $jcells = [];
                foreach ($cells as $jvalue) {
                    $jcell = explode(".", $jvalue['name']);
                    $jtable = $jcell[0];
                    if ($jtable !== $table && (!in_array($jtable, $this->tablesLinked, true) || in_array(
                                $jvalue['name'],
                                $jcells,
                                true
                            ))) {
                        $constraints = $this->constraintsList();
                        //Find joins level 1
                        $this->findJoins(
                            [$table, $jtable],
                            $constraints,
                            (!empty($jvalue['table_linked_as'])) ? $jvalue['table_linked_as'] : '',
                            (!empty($jvalue['table_linked_as'])) ? explode('.', $jvalue['origin'])[1] : ''
                        );
                        if (!in_array($jtable, $this->tablesLinked, true)) {
                            if (!empty($jvalue['path']) && is_array($jvalue['path'])) {
                                $this->findJoins($jvalue['path'], $constraints);
                            } elseif (!empty($jvalue['origin'])) {
                                throw new RuntimeException(
                                    sprintf(
                                        'Class : EmEngine, Function : read. Impossible to determine the link between these tables "%s" and "%s". You can define the path of this column into the cells variable.',
                                        $table,
                                        $jtable
                                    )
                                );
                            }
                        }
                        if (!in_array($jvalue['name'], $jcells, true)) {
                            $jcells[] = $jvalue['name'];
                        }
                    }
                }
                if (!empty($this->join)) {
                    $sqlreq .= $this->join . " ";
//                    echo $sqlreq, '<br>';
                }
            }
        }
        //if where is define
        if (!empty($idorwhere)) {
            $sqlreq .= (is_numeric($idorwhere)) ? ' WHERE id=' . $idorwhere : ' WHERE ' . $idorwhere;
        }
        //if orderfor is define
        if (!empty($orderfor)) {
            $sqlreq .= ' ORDER BY ' . $orderfor;
        }
        //if orderby is define
        if (!empty($orderfor) && !empty($orderby)) {
            $sqlreq .= ' ' . $orderby;
        }
        //if limit is define
        if (isset($offset)) {
            $sqlreq .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        }
//        echo $sqlreq;
        //prepare request
        $field = (strtolower($sort) === "field") ? str_replace('.', '', $cells[0]['name']) : null;
//        echo $sqlreq, '<br>';
        return $this->prepareRequest($sqlreq, null, $sort, $field);
    }

    /**
     * Manage new entity for creation, if this entity must be deleted, it will be removed from the entityInsertions.
     * @param Object $entity
     * @return void
     */
    public function persist(Object $entity): void
    {
        if ($entity->getId() === null && !isset($this->entityInsertions[spl_object_id($entity)])) {
            //Event Pre persist
            if ($this->eventManager) {
                $this->eventManager->dispatch(new PrePersistEvent($entity, $this->entityManager));
            }
            $this->entityInsertions[spl_object_id($entity)] = $entity;
        }
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function flush(): void
    {
        // Before begin transaction, prepare entity changes
        $this->removeDeleteEntitiesFromUpdates();
        $this->computeEntitiesChanges();

        if (!($this->entityInsertions || $this->entityUpdates || $this->entityDeletions)) {
            return;
        }

        // process flush entities
        $this->entityManager->getPdm()->beginTransaction();

        if (!empty($this->entityInsertions)) {
            $this->executeInsertions();
        }

        if (!empty($this->entityUpdates)) {
            $this->executeUpdates();
        }

        if (!empty($this->entityDeletions)) {
            $this->executeDeletions();
        }

        if (!empty($this->manyToManyInsertions)) {
            $this->executeManyToManyRelations();
        }

        $this->entityManager->getPdm()->commit();

        //Event Post flush
        $this->eventManager?->dispatch(new PostFlushEvent($this->entityManager));
    }

    /**
     * @return void
     */
    private function removeDeleteEntitiesFromUpdates(): void
    {
        foreach ($this->entityDeletions as $entity) {
            if (isset($this->entityUpdates[spl_object_id($entity)])) {
                unset($this->entityUpdates[spl_object_id($entity)]);
            }
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void
    {
        $objectId = spl_object_id($entity);

        unset(
            $this->managedEntities[$objectId],
            $this->entityInsertions[$objectId],
            $this->entityUpdates[$objectId],
            $this->entityDeletions[$objectId],
            $this->entityChanges[$objectId],
            $this->originalEntityData[$objectId]
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    private function computeEntitiesChanges(): void
    {
        foreach ($this->entityInsertions as $entity) {
            $this->computeEntityChanges($entity);
        }

        foreach ($this->managedEntities as $entity) {
            if (isset($this->entityDeletions[spl_object_id($entity)])) {
                continue;
            }

            $this->computeEntityChanges($entity);
        }
    }

    /**
     * @throws ReflectionException
     */
    private function computeOriginalEntity(Object $entity): void
    {
        $properties = $this->getPropertiesColumns($entity::class);
        $entityReflection = new ReflectionClass($entity);

        $originalEntity = [];
        foreach ($properties as $property => $column) {
            $originalEntity[$property] = $entityReflection->getProperty($property)->getValue($entity);
        }
        
        $this->managedEntities[spl_object_id($entity)] = $entity;
        $this->originalEntityData[spl_object_id($entity)] = $originalEntity;
    }

    /**
     * @throws ReflectionException
     * Todo : set field_id if $property is a relational property into originalEntityData setter
     */
    private function computeEntityChanges(Object $entity): void
    {
        $originalEntityData = $this->originalEntityData[spl_object_id($entity)] ?? null;

        // properties = keys
        $properties = $this->getPropertiesColumns($entity::class);
        $entityReflection = new ReflectionClass($entity);

        $entityChanges = [];
        foreach ($properties as $property => $column) {
            $newValue = $entityReflection->getProperty($property)->getValue($entity);
            $oldValue = $originalEntityData ? $originalEntityData[$property] : null;

            if ($oldValue === $newValue && !is_null($originalEntityData)) {
                continue;
            }

            // One-to-one entity relation who is not persisted yet (not id)
            // Add the entity to the entityInsertions list and persist it
            if (is_object($newValue)
                && is_null($newValue->getId())
            ) {
                if (!isset($this->entityInsertions[spl_object_id($newValue)])) {
                    $this->persist($newValue);
                    $this->computeEntityChanges($newValue);
                }
                $this->entityInsertionOrder[spl_object_id($entity)] = [spl_object_id($newValue)];
            }

            $entityChanges[$property] = [$oldValue, $newValue];
        }

        // If there is an update, add the entity to the updates list
        if (
            $entityChanges !== [] &&
            !is_null($originalEntityData) &&
            !isset($this->entityUpdates[spl_object_id($entity)])
        ) {
            $this->entityUpdates[spl_object_id($entity)] = $entity;
        }

        $this->manageOneToManyRelations($entity, $entityReflection);
        $this->manageManyToManyRelations($entity, $entityReflection);

        $this->entityChanges[spl_object_id($entity)] = $entityChanges;
    }

    /**
     * This method is used to manage the related entities not persisted yet
     * @param Object $entity
     * @param ReflectionClass $entityReflection
     * @throws ReflectionException
     */
    private function manageOneToManyRelations(Object $entity, ReflectionClass $entityReflection): void
    {
        $entityName = get_class($entity);
        foreach ($this->entityManager->getDbMapping()->getOneToMany($entityName) as $property => $oneToMany) {
            $entities = $entityReflection->getProperty($property)->getValue($entity);

            if (!$entities instanceof Collection) {
                continue;
            }

            foreach ($entities->items() as $relatedEntity) {
                // Check if the related entity is not already managed
                if (is_object($relatedEntity) && $relatedEntity->getId() === null) {
                    $this->persist($relatedEntity);
                    $this->computeEntityChanges($relatedEntity);
                }
            }
        }
    }

    /**
     * @throws ReflectionException
     */
    private function manageManyToManyRelations(Object $entity, ReflectionClass $entityReflection): void
    {
        $entityName = get_class($entity);
        foreach ($this->entityManager->getDbMapping()->getManyToMany($entityName) as $property => $manyToMany) {
            $entities = $entityReflection->getProperty($property)->getValue($entity);

            if ($entities instanceof Collection) {
                if (count($entities) === 0) {
                    continue;
                }

                // If the collection is instance of Collection, all the related entities are not persisted yet
                foreach ($entities->items() as $relatedEntity) {
                    $this->manyToManyInsertions[] = [
                        'entity' => $entity,
                        'related' => $relatedEntity,
                        'manyToMany' => $manyToMany,
                    ];
                }
                continue;
            }

            // Check for any changes in the collection
            $addedEntities = $entities->getAddedEntities();
            if (!empty($addedEntities)) {
                // Process added entities
                foreach ($entities->getAddedEntities() as $relatedEntity) {
                    $this->manyToManyInsertions[] = [
                        'entity' => $entity,
                        'related' => $relatedEntity,
                        'manyToMany' => $manyToMany,
                    ];
                }
            }

            // Check for any deletions
            $deletedEntities = $entities->getDeletedEntities();
            if (!empty($deletedEntities)) {
                // Process deleted entities
                foreach ($deletedEntities as $relatedEntity) {
                    $this->manyToManyInsertions[] = [
                        'entity' => $entity,
                        'related' => $relatedEntity,
                        'manyToMany' => $manyToMany,
                        'action' => 'delete'
                    ];
                }
            }
        }
    }

    private function createLinkEntity(
        MtManyToMany $manyToMany,
        Object $entity,
        Object $relatedEntity
    ): object {
        $linkEntity = new $manyToMany->mappedBy();
        $setEntity = 'set' . ucfirst($manyToMany->joinProperty);
        $setRelatedEntity = 'set' . ucfirst($manyToMany->inverseJoinProperty);
        $linkEntity->$setEntity($entity);
        $linkEntity->$setRelatedEntity($relatedEntity);
        return $linkEntity;
    }

    /** Find MySql join from the path with the constraints schema
     * @param array<string> $path (origin table, ...intermediate table if needed, destination table)
     * @param array $constraints
     * @param string $table_as (alias of the destination table)
     * @param string $column_origin (if the table_as is an alias, the column of the origin table)
     * @param string $joinType
     */
    private function findJoins(
        array $path,
        array $constraints,
        string $table_as = '',
        string $column_origin = '',
        string $joinType = 'LEFT JOIN'
    ): void {
        $originTable = array_shift($path);
        $destinationTable = $path[0];






        foreach ($constraints as $value) {
            if (
                !in_array($destinationTable, $this->tablesLinked, true) ||
                (!empty($table_as) && !in_array($table_as, $this->tablesLinked, true))
            ) {
                $table_as_req = (empty($table_as) || count($path) > 1) ? '' : ' AS ' . $table_as;
                $ref_table = (empty($table_as) || count($path) > 1) ? $value['REFERENCED_TABLE_NAME'] : $table_as;
                $origin_column = (empty($column_origin) || count($path) > 1) ? $value['COLUMN_NAME'] : $column_origin;
                if (
                    ($value['TABLE_NAME'] === $destinationTable && $value['REFERENCED_TABLE_NAME'] === $originTable) ||
                    ($value['TABLE_NAME'] === $originTable && $value['REFERENCED_TABLE_NAME'] === $destinationTable)
                ) {
                    $this->join .=
                        ' ' .
                        $joinType .
                        ' `' .
                        $destinationTable .
                        '`' .
                        $table_as_req .
                        ' ON ' .
                        $value['TABLE_NAME'] .
                        '.' .
                        $origin_column .
                        ' = ' .
                        $ref_table .
                        '.' .
                        $value['REFERENCED_COLUMN_NAME'];
                    $this->tablesLinked[] = $destinationTable;
                    if (!empty($table_as)) {
                        $this->tablesLinked[] = $table_as;
                    }
                }
            }
        }
        if (count($path) !== 1) {
            $this->findJoins($path, $constraints);
        }
    }
//    private function findJoins(
//        array $path,
//        array $constraints,
//        string $table_as = '',
//        string $column_origin = '',
//        string $joinType = 'LEFT JOIN'
//    ): void {
//        $originTable = array_shift($path);
//        $destinationTable = $path[0];
//        foreach ($constraints as $value) {
//            if (
//                !in_array($destinationTable, $this->tableslinked, true) ||
//                (!empty($table_as) && !in_array($table_as, $this->tableslinked, true))
//            ) {
//                $table_as_req = (empty($table_as) || count($path) > 1) ? '' : ' AS ' . $table_as;
//                $ref_table = (empty($table_as) || count($path) > 1) ? $value['REFERENCED_TABLE_NAME'] : $table_as;
//                $origin_column = (empty($column_origin) || count($path) > 1) ? $value['COLUMN_NAME'] : $column_origin;
//                if (
//                    ($value['TABLE_NAME'] === $destinationTable && $value['REFERENCED_TABLE_NAME'] === $originTable) ||
//                    ($value['TABLE_NAME'] === $originTable && $value['REFERENCED_TABLE_NAME'] === $destinationTable)
//                ) {
//                    $this->join .=
//                        ' ' .
//                        $joinType .
//                        ' `' .
//                        $destinationTable .
//                        '`' .
//                        $table_as_req .
//                        ' ON ' .
//                        $value['TABLE_NAME'] .
//                        '.' .
//                        $origin_column .
//                        ' = ' .
//                        $ref_table .
//                        '.' .
//                        $value['REFERENCED_COLUMN_NAME'];
//                    $this->tableslinked[] = $destinationTable;
//                    if (!empty($table_as)) {
//                        $this->tableslinked[] = $table_as;
//                    }
//                }
//            }
//        }
//        if (count($path) !== 1) {
//            $this->findJoins($path, $constraints);
//        }
//    }

    /**
     *
     */
    private function executeUpdates(): void
    {
        if (!empty($this->entityUpdates)) {
            foreach ($this->entityUpdates as $uio => $entity) {
                $entityChanges = $this->getEntityChanges($entity);

                //Pre update Event
                if ($this->eventManager && !$this->isEventCalled($entity::class, DbEvents::preUpdate->value)) {
                    $this->eventCalled($entity::class, DbEvents::preUpdate->value);
                    $this->eventManager->dispatch(new PreUpdateEvent($entity, $this->entityManager, $entityChanges));
                }

                $queryBuilder = $this->generateUpdateQueryBuilder($entity);

                // prepare request
                $pdoStatement = $queryBuilder->getResult();
                $pdoStatement->execute();

                // close cursor
                $pdoStatement->closeCursor();
                unset($this->entityUpdates[$uio]);

                //Post update Event
                if ($this->eventManager && !$this->isEventCalled($entity::class, DbEvents::postUpdate->value)) {
                    $this->eventCalled($entity::class, DbEvents::postUpdate->value);
                    $this->eventManager->dispatch(new PostUpdateEvent($entity, $this->entityManager));
                }
            }
        }
    }

    /**
     * @param class-string $entityName
     * @param string $event
     * @return void
     */
    private function eventCalled(string $entityName, string $event): void
    {
        if (isset($this->eventCalled[$entityName])) {
            $this->eventCalled[$entityName][] = $event;
            return;
        }

        $this->eventCalled[$entityName] = [$event];
    }

    /**
     * @param string $entityName
     * @param string $event
     * @return bool
     */
    private function isEventCalled(string $entityName, string $event): bool
    {
        if (!isset($this->eventCalled[$entityName])) {
            return false;
        }

        return in_array($event, $this->eventCalled[$entityName], true);
    }

    /**
     * @param Object $entity
     * @return QueryBuilder
     */
    private function generateUpdateQueryBuilder(Object $entity): QueryBuilder
    {
        $queryBuilder = new QueryBuilder($this);
        $queryBuilder->update($this->entityManager->getDbMapping()->getTableName($entity::class));

        $entityChanges = $this->getEntityChanges($entity);

        foreach ($this->getPropertiesColumns($entity::class, false) as $property => $column) {
            if (!isset($entityChanges[$property][1])) {
                continue;
            }

            if (is_object($entityChanges[$property][1])) {
                $entityChanges[$property][1] = $entityChanges[$property][1]->getId();
            }

            $queryBuilder->setValue($column, $queryBuilder->addDynamicParameter($entityChanges[$property][1]));
        }
        $queryBuilder->where(SqlOperations::equal('id', $queryBuilder->addDynamicParameter($entity->getId())));

        return $queryBuilder;
    }

    /**
     * @param Object $entity
     */
    public function remove(Object $entity): void
    {
        if (isset($this->entityUpdates[spl_object_id($entity)])) {
            unset($this->entityUpdates[spl_object_id($entity)]);
        }

        if (isset($this->entityInsertions[spl_object_id($entity)])) {
            // It is not necessary to insert an entity and then remove it.
            unset($this->entityInsertions[spl_object_id($entity)]);
            return;
        }

        $this->entityDeletions[spl_object_id($entity)] = $entity;
    }

    /**
     * @return void
     */
    private function executeDeletions(): void
    {
        if (!empty($this->entityDeletions)) {
            $entitiesEvent = [];
            foreach ($this->entityDeletions as $uio => $entity) {
                $queryBuilder = new QueryBuilder($this);
                $queryBuilder->delete($this->entityManager->getDbMapping()->getTableName($entity::class));
                $queryBuilder->where(
                    SqlOperations::equal('id', $queryBuilder->addNamedParameter($entity->getId()))
                );

                // prepare request
                $pdoStatement = $queryBuilder->getResult();
                $pdoStatement->execute();

                // close cursor
                $pdoStatement->closeCursor();
                unset($this->entityDeletions[$uio]);
                $entitiesEvent[] = $entity;
            }

            //Event Post remove
            if ($this->eventManager) {
                foreach ($entitiesEvent as $entity) {
                    $this->eventManager->dispatch(new PostRemoveEvent($entity, $this->entityManager));
                }
            }
        }
    }

    /**
     * Process many-to-many relationship changes in database
     * This method should be called during flush
     * @throws ReflectionException
     */
    private function executeManyToManyRelations(): void
    {
        if (empty($this->manyToManyInsertions)) {
            return;
        }

        foreach ($this->manyToManyInsertions as $key => $relation) {
            $entity = $relation['entity'];
            $relatedEntity = $relation['related'];
            $manyToMany = $relation['manyToMany'];
            $action = $relation['action'] ?? 'insert';

            $linkRelation = $this->getLinkRelation($manyToMany, $entity, $relatedEntity);
            if (!is_null($linkRelation)) {
                if ($action === 'delete') {
                    $this->remove($linkRelation);
                }

                unset($this->manyToManyInsertions[$key]);
                continue;
            }

            // Unmanaged entities
            $linkEntity = $this->createLinkEntity($manyToMany, $entity, $relatedEntity);
            if ($action === 'insert') {
                $this->persist($linkEntity);
            } else {
                $this->remove($linkEntity);
            }

            unset($this->manyToManyInsertions[$key]);
        }
        $this->flush();
    }

    private function getLinkRelation(MtManyToMany $manyToMany, object $entity, object $relatedEntity): ?object
    {
        if ($manyToMany->entity === null) {
            $manyToMany->entity = $entity::class;
        }

        if ($manyToMany->joinProperty === null || $manyToMany->inverseJoinProperty === null) {
            throw new RuntimeException(
                sprintf(
                    'Invalid MtManyToMany relation configuration between %s and %s classes.',
                    $entity::class,
                    $relatedEntity::class
                )
            );
        }

        foreach ($this->managedEntities as $managedEntity) {
            if (!($managedEntity instanceof $manyToMany->mappedBy)) {
                continue;
            }

            $getEntity = 'get' . ucfirst($manyToMany->joinProperty);
            $getRelatedEntity = 'get' . ucfirst($manyToMany->inverseJoinProperty);

            if ($managedEntity->$getEntity() === $entity->id
                && $managedEntity->$getRelatedEntity() === $relatedEntity->id
            ) {
                return $managedEntity;
            }
        }

        return null;
    }

    /**
     * List of this database tables (Mysql)
     * @return array
     */
    public function tablesList(): array
    {
        $dbParameters = PhpDatabaseManager::populateParameters();
        $dbName = $dbParameters['dbname'];
        //prepare and execute request
        $success = $this->entityManager->getPdm()->query('SHOW TABLES');
        $tables_list = [];
        foreach ($success as $value) {
            $tables_list[] = $value['Tables_in_' . $dbName];
        }
        //close cursor
        $success->closeCursor();
        return $tables_list;
    }

    /**
     * Count the result of the request with the table $table and the $where conditions
     * @param string $table
     * @param string|null $where
     * @return int
     */
    public function rowsCount(string $table, ?string $where = null): int
    {
        //prepare and execute request
        if ($where === null) {
            $success = $this->entityManager->getPdm()->query('SELECT COUNT(*) FROM `' . $table . '`');
        } else {
            $success = $this->entityManager->getPdm()->query('SELECT COUNT(*) FROM `' . $table . '` WHERE ' . $where);
        }
        $count = $success->fetchColumn();
        //close cursor
        $success->closeCursor();
        return $count;
    }

    private function computeChanges(Object $entity): void
    {
        $name = $entity::class;
    }

    /**
     * todo : move this below in migration class
     */

    /**
     * Make a structure of database for save in a json file
     * @return array
     */
    private function onlineDatabaseStructure(): array
    {
        $dbParameters = PhpDatabaseManager::populateParameters();
        $dbName = $dbParameters['dbname'];
        //Array db
        $arraydb = ['structure' => []];

        //COLUMNS req
        $columnsreq = "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, EXTRA, COLUMN_DEFAULT, COLUMN_KEY FROM `information_schema`.`COLUMNS` WHERE TABLE_SCHEMA = '" . $dbName . "'";
        $reqcolumns = $this->entityManager->getPdm()->query($columnsreq);
        $column_structure = $reqcolumns->fetchAll(PDO::FETCH_ASSOC);
        $reqcolumns->closeCursor();
        foreach ($column_structure as $column) {
            if (array_key_exists($column['TABLE_NAME'], $arraydb['structure'])) {
                //add in structure -> column name -> fields
                $table_name = array_shift($column);
                $column_name = array_shift($column);
                $arraydb['structure'][$table_name][$column_name] = $column;
                unset($table_name, $column_name);
            } else {
                //create structure -> column name
                $table_name = array_shift($column);
                $column_name = array_shift($column);
                $arraydb['structure'][$table_name][$column_name] = $column;
                unset($table_name, $column_name);
            }
        }
        //TABLES req
        $tablesreq = "SELECT TABLE_NAME, AUTO_INCREMENT FROM `information_schema`.`TABLES` WHERE TABLE_SCHEMA = '" . $dbName . "'";
        $reqtables = $this->entityManager->getPdm()->query($tablesreq);
        $tables_structure = $reqtables->fetchAll(PDO::FETCH_ASSOC);
        $reqtables->closeCursor();
        foreach ($tables_structure as $table) {
            if (array_key_exists($table['TABLE_NAME'], $arraydb['structure'])) {
                //add in structure -> table name -> auto_increment
                $table_name = array_shift($table);
                $arraydb['structure'][$table_name]['auto_increment'] = $table['AUTO_INCREMENT'];
                unset($table_name);
            }
        }
        //KEY_COLUMN_USAGE AND REFERENTIAL_CONSTRAINTS
        $constraintreq = "SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_SCHEMA, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE 
            FROM `information_schema`.`KEY_COLUMN_USAGE` AS k LEFT JOIN `information_schema`.`REFERENTIAL_CONSTRAINTS` AS r 
            ON k.CONSTRAINT_NAME = r.CONSTRAINT_NAME 
            WHERE k.CONSTRAINT_SCHEMA = '" . $dbName . "' 
            AND k.REFERENCED_TABLE_SCHEMA IS NOT NULL 
            AND k.REFERENCED_TABLE_NAME IS NOT NULL 
            AND k.REFERENCED_COLUMN_NAME IS NOT NULL";
        $reqconstraints = $this->entityManager->getPdm()->query($constraintreq);
        $constraints_structure = $reqconstraints->fetchAll(PDO::FETCH_ASSOC);
        $reqconstraints->closeCursor();
        foreach ($constraints_structure as $constraint) {
            if (array_key_exists($constraint['TABLE_NAME'], $arraydb['structure'])) {
                //add in structure -> table name -> auto_increment
                $table_name = array_shift($constraint);
                if (isset(${'line' . $table_name})) {
                    ${'line' . $table_name}++;
                } else {
                    ${'line' . $table_name} = 0;
                }
                $arraydb['structure'][$table_name]['foreign_keys'][$constraint['CONSTRAINT_NAME']] = $constraint;
                unset($table_name);
            }
        }
        return $arraydb;
    }

    /**
     * List of constraints of this database [
     *     ['TABLE_NAME' => 'table_name', 'COLUMN_NAME' => 'column_name', 'REFERENCED_TABLE_NAME' => 'referenced_table_name', 'REFERENCED_COLUMN_NAME' => 'referenced_column_name']
     * ]
     * @return array|null
     */
    private function constraintsList(): ?array
    {
        if (!empty($db_structure = $this->openDbStructure())) {
            $constraint_list = [];
            foreach ($db_structure['structure'] as $table => $table_content) {
                if (!empty($table_content['foreign_keys'])) {
                    foreach ($table_content['foreign_keys'] as $fk) {
                        $constraint_list[] = [
                            'TABLE_NAME' => $table,
                            'COLUMN_NAME' => $fk['COLUMN_NAME'],
                            'REFERENCED_TABLE_NAME' => $fk['REFERENCED_TABLE_NAME'],
                            'REFERENCED_COLUMN_NAME' => $fk['REFERENCED_COLUMN_NAME']
                        ];
                    }
                }
            }
            return $constraint_list;
        }

        return null;
    }

    /** Create Mysql table
     * @param string $table
     * @param array $columns
     * @param bool $ifnotexists
     */
    private function createTable(string $table, array $columns, bool $ifnotexists = true): void
    {
        $ine = ($ifnotexists) ? 'IF NOT EXISTS ' : '';
        $req = "CREATE TABLE " . $ine . "`" . $table . "` (";
        foreach ($columns as $key => $value) {
            $req .= $key . ' ' . $value['COLUMN_TYPE'];
            if ($value['IS_NULLABLE'] === 'NO') {
                $req .= ' NOT NULL';
            }
            if ($value['COLUMN_DEFAULT'] === null && $value['IS_NULLABLE'] === 'YES') {
                $req .= ' DEFAULT NULL';
            } elseif ($value['COLUMN_DEFAULT'] === 'CURRENT_TIMESTAMP') {
                $req .= " DEFAULT " . $value['COLUMN_DEFAULT'];
            } elseif (is_string($value['COLUMN_DEFAULT']) && $value['COLUMN_DEFAULT'] !== '') {
                $req .= " DEFAULT '" . $value['COLUMN_DEFAULT'] . "'";
            }
            $req .= ', ';
        }
        $req = trim($req, ', ') . ')';
        //request
        $success = $this->entityManager->getPdm()->query($req);
        $success->closeCursor();
    }

    /** Alter Mysql table with one column changes.
     * @param string $table
     * @param string $column_name
     * @param array $column
     */
    private function alterTable(string $table, string $column_name, array $column): void
    {
        $req = "ALTER TABLE `" . $table . "` CHANGE " . $column_name . ' ' . $column_name . ' ' . $column['COLUMN_TYPE'];
        if ($column['IS_NULLABLE'] === 'NO') {
            $req .= ' NOT NULL';
        }
        if ($column['COLUMN_DEFAULT'] === null && $column['IS_NULLABLE'] === 'YES') {
            $req .= ' DEFAULT NULL';
        } elseif ($column['COLUMN_DEFAULT'] === 'CURRENT_TIMESTAMP') {
            $req .= " DEFAULT " . $column['COLUMN_DEFAULT'];
        } elseif (is_string($column['COLUMN_DEFAULT']) && $column['COLUMN_DEFAULT'] !== '') {
            $req .= " DEFAULT '" . $column['COLUMN_DEFAULT'] . "'";
        }
        //request
        $success = $this->entityManager->getPdm()->query($req);
        $success->closeCursor();
    }

    /** Alter Mysql table with one column changes.
     * @param string $table
     * @param string $column_name
     * @param array $column
     */
    private function alterTableAddColumn(string $table, string $column_name, array $column): void
    {
        $req = "ALTER TABLE `" . $table . "` ADD " . $column_name . ' ' . $column['COLUMN_TYPE'];
        if ($column['IS_NULLABLE'] === 'NO') {
            $req .= ' NOT NULL';
        }
        if ($column['COLUMN_DEFAULT'] === null && $column['IS_NULLABLE'] === 'YES') {
            $req .= ' DEFAULT NULL';
        } elseif ($column['COLUMN_DEFAULT'] === 'CURRENT_TIMESTAMP') {
            $req .= " DEFAULT " . $column['COLUMN_DEFAULT'];
        } elseif (is_string($column['COLUMN_DEFAULT']) && $column['COLUMN_DEFAULT'] !== '') {
            $req .= " DEFAULT '" . $column['COLUMN_DEFAULT'] . "'";
        }
        //request
        $success = $this->entityManager->getPdm()->query($req);
        $success->closeCursor();
    }

    /** Alter Mysql table, add keys.
     * @param string $table
     * @param array $table_keys
     */
    private function alterTableAddKey(string $table, array $table_keys): void
    {
        $req = "ALTER TABLE `" . $table . "`";
        foreach ($table_keys as $key => $value) {
            if ($value[1] === 'PRI') {
                $req .= " ADD PRIMARY KEY (`" . $key . "`),";
            } elseif ($value[1] === 'MUL') {
                $req .= " ADD KEY `" . $key . "`" . " (`" . $key . "`),";
            }
        }
        $req = trim($req, ',');
        //request
        $success = $this->entityManager->getPdm()->query($req);
        $success->closeCursor();
    }

    /** Alter Mysql table, add auto increment with the number of it (if exists)
     * @param string $table
     * @param string $column_name
     * @param array $column
     * @param string|null $auto_increment
     */
    private function alterTableAutoIncrement(
        string $table,
        string $column_name,
        array $column,
        string $auto_increment = null
    ): void {
        $req = "ALTER TABLE `" . $table . "` MODIFY " . $column_name . " " . $column['COLUMN_TYPE'];
        if ($column['IS_NULLABLE'] === 'NO') {
            $req .= ' NOT NULL';
        }
        if ($column['COLUMN_DEFAULT'] === null && $column['IS_NULLABLE'] === 'YES') {
            $req .= ' DEFAULT NULL';
        } elseif ($column['COLUMN_DEFAULT'] === 'CURRENT_TIMESTAMP') {
            $req .= " DEFAULT " . $column['COLUMN_DEFAULT'];
        } elseif (is_string($column['COLUMN_DEFAULT']) && $column['COLUMN_DEFAULT'] !== '') {
            $req .= " DEFAULT '" . $column['COLUMN_DEFAULT'] . "'";
        }
        $req .= ' AUTO_INCREMENT';
        if (!empty($auto_increment)) {
            $req .= ', AUTO_INCREMENT=' . $auto_increment;
        }
        //request
        $success = $this->entityManager->getPdm()->query($req);
        $success->closeCursor();
    }

    /** Alter Mysql table, add foreign key
     * @param string $table
     * @param string $column
     * @param MtFk $fk
     */
    private function alterTableForeignKey(string $table, string $column, MtFk $fk): void
    {
        if (
            !is_null($fk->getConstraintName()) &&
            !is_null($fk->referencedTable) &&
            !is_null($fk->referencedColumn) &&
            !is_null($fk->deleteRule) &&
            !is_null($fk->updateRule)
        ) {
            $req = "ALTER TABLE `" .
                $table .
                "` ADD CONSTRAINT `" .
                $fk->getConstraintName() .
                "` FOREIGN KEY (`" .
                $column .
                "`) REFERENCES `" .
                $fk->referencedTable .
                "` (`" .
                $fk->referencedColumn .
                "`) ON DELETE " .
                $fk->deleteRule .
                " ON UPDATE " .
                $fk->updateRule;
            //request
            $success = $this->entityManager->getPdm()->query($req);
            $success->closeCursor();
        }
    }
//    private function alterTableForeignKey(string $table, array $foreign_key): void
//    {
//        if (
//            !empty($foreign_key['CONSTRAINT_NAME']) &&
//            !empty($foreign_key['COLUMN_NAME']) &&
//            !empty($foreign_key['REFERENCED_TABLE_NAME']) &&
//            !empty($foreign_key['REFERENCED_COLUMN_NAME']) &&
//            !empty($foreign_key['DELETE_RULE']) &&
//            !empty($foreign_key['UPDATE_RULE'])
//        ) {
//            $req = "ALTER TABLE `" .
//                $table .
//                "` ADD CONSTRAINT `" .
//                $foreign_key['CONSTRAINT_NAME'] .
//                "` FOREIGN KEY (`" .
//                $foreign_key['COLUMN_NAME'] .
//                "`) REFERENCES `" .
//                $foreign_key['REFERENCED_TABLE_NAME'] .
//                "` (`" .
//                $foreign_key['REFERENCED_COLUMN_NAME'] .
//                "`) ON DELETE " .
//                $foreign_key['DELETE_RULE'] .
//                " ON UPDATE " .
//                $foreign_key['UPDATE_RULE'];
//            //request
//            $success = $this->em->getPdm()->query($req);
//            $success->closeCursor();
//        }
//    }

    /** Insert values
     * @param array $values
     * @param bool $insert_ignore
     */
    private function insertValues(array $values, bool $insert_ignore = true): void
    {
        $req = '';
        $ignore = ($insert_ignore) ? 'IGNORE ' : '';
        foreach ($values as $key => $value) {
            foreach ($value as $item) {
                $columns = '';
                $data = '';
                foreach ($item as $insertKey => $insertValue) {
                    $columns .= (!empty($columns)) ? ', `' . $insertKey . '`' : '`' . $insertKey . '`';
                    if (is_numeric($insertValue)) {
                        $data .= (!empty($data)) ? ", " . $insertValue : $insertValue;
                    } else {
                        $data .= (!empty($data)) ? ", '" . $insertValue . "'" : "'" . $insertValue . "'";
                    }
                }
                $req .= 'INSERT ' . $ignore . 'INTO `' . $key . '` (' . $columns . ') VALUES (' . $data . '); ';
                unset($columns, $data);
            }
        }
        //request
        $this->entityManager->getPdm()->exec($req);
    }

    /**
     * @var bool $installationMode
     * @throws Exception
     */
    public function automaticUpdate(bool $installationMode = false): void
    {
        //first check manual update
        if ($this->updateDatabase) {
            $this->updateDatabase->Update();
        }
        //second step automatic update with Database Structure
        /**
         * @var Object $version
         */
        $online_version = (!is_null($version = $this->find(Version::class, 1))) ? $version->getVersion() : null;
        if ($installationMode || (!empty($online_version) && (float)$online_version < (float)$this->openDbStructure(
                )['dbversion'])) {
            //structure array
            $online_structure = $this->onlineDatabaseStructure()['structure'];
            $site_structure = $this->openDbStructure()['structure'];
            //check tables
            $structuretocreate = (!empty($online_structure)) ? array_diff_key(
                $site_structure,
                $online_structure
            ) : $site_structure;
            foreach ($structuretocreate as $tablekey => $tableval) {
                //create table if not exists
                $cols = $site_structure[$tablekey];
                if (isset($cols['auto_increment'])) {
                    unset($cols['auto_increment']);
                }
                if (isset($cols['foreign_keys'])) {
                    unset($cols['foreign_keys']);
                }
                $this->createTable($tablekey, $cols);
            }
            //check differences between tables
            $online_structure = $this->onlineDatabaseStructure()['structure'];
            if (!empty($online_structure)) {
                foreach ($site_structure as $checktablekey => $checktableval) {
                    if (!empty($site_structure[$checktablekey]) && is_array($site_structure[$checktablekey])) {
                        foreach ($site_structure[$checktablekey] as $checkcolumnkey => $checkcolumnval) {
                            if ($checkcolumnkey !== 'auto_increment' && $checkcolumnkey !== 'foreign_keys') {
                                if (!empty($online_structure[$checktablekey][$checkcolumnkey])) {
                                    $differentcolumns = ArrayManipulation::findDifferencesByName(
                                        $online_structure[$checktablekey][$checkcolumnkey],
                                        $checkcolumnval
                                    );
                                    //Do not update when the column is int( for a column int
                                    if (!empty($differentcolumns['COLUMN_TYPE']) && strpos(
                                            $differentcolumns['COLUMN_TYPE'][0],
                                            'int('
                                        ) === 0 && strpos(
                                            $differentcolumns['COLUMN_TYPE'][1],
                                            'int '
                                        ) === 0) {
                                        unset($differentcolumns['COLUMN_TYPE']);
                                    }
                                    //if key not exists create it (after this step)
                                    if (!empty($differentcolumns['COLUMN_KEY']) && empty($online_structure[$checktablekey][$checkcolumnkey]['COLUMN_KEY'])) {
                                        $keys[$checktablekey][$checkcolumnkey] = $differentcolumns['COLUMN_KEY'];
                                        unset($differentcolumns['COLUMN_KEY']);
                                    }
                                    if (!empty($differentcolumns['EXTRA']) && $differentcolumns['EXTRA'][1] === 'auto_increment') {
                                        $autoincrements[$checktablekey][$checkcolumnkey] = $differentcolumns['EXTRA'][1];
                                        unset($differentcolumns['EXTRA']);
                                    }
                                    if (!empty($differentcolumns)) {
                                        //update column
                                        $this->alterTable($checktablekey, $checkcolumnkey, $checkcolumnval);
                                    }
                                } else {
                                    //Create column
                                    if (!empty($checkcolumnval['COLUMN_KEY']) && empty($online_structure[$checktablekey][$checkcolumnkey]['COLUMN_KEY'])) {
                                        $keys[$checktablekey][$checkcolumnkey] = $checkcolumnval['COLUMN_KEY'];
                                        unset($checkcolumnval['COLUMN_KEY']);
                                    }
                                    if (!empty($checkcolumnval['EXTRA']) && $checkcolumnval['EXTRA'][1] === 'auto_increment') {
                                        $autoincrements[$checktablekey][$checkcolumnkey] = $checkcolumnval['EXTRA'][1];
                                        unset($checkcolumnval['EXTRA']);
                                    }
                                    $this->alterTableAddColumn($checktablekey, $checkcolumnkey, $checkcolumnval);
                                }
                            } elseif ($checkcolumnkey === 'foreign_keys') {
                                // store foreign keys of db structure
                                $fk[$checktablekey] = $checkcolumnval;
                            }
                        }
                    }
                }
            }
            //create or modify keys and index
            if (!empty($keys)) {
                foreach ($keys as $keystablekey => $keystableval) {
                    $this->alterTableAddKey($keystablekey, $keys[$keystablekey]);
                }
            }
            //create auto increment and affect a number if needed
            if (!empty($autoincrements)) {
                foreach ($autoincrements as $aitablekey => $aitableval) {
                    foreach ($autoincrements[$aitablekey] as $aicolumnkey => $aicolumnval) {
                        //update column
                        $this->alterTableAutoIncrement(
                            $aitablekey,
                            $aicolumnkey,
                            $site_structure[$aitablekey][$aicolumnkey],
                            (!empty($site_structure[$aitablekey]['auto_increment'])) ? $site_structure[$aitablekey]['auto_increment'] : null
                        );
                    }
                }
            }
            //create constraints if needed
            $online_structure = $this->onlineDatabaseStructure()['structure'];
            foreach ($this->getEntityManager()->getDbMapping()->getEntities() as $entity) {
                foreach ($this->getEntityManager()->getDbMapping()->getPropertiesColumns($entity) as $property => $column) {
                    $fk = $this->getEntityManager()->getDbMapping()->getForeignKey($entity, $property);

                    if (($fk !== null) && !empty($online_structure[$entity][$property]['foreign_keys'][$fk['CONSTRAINT_NAME']])) {
                        $this->alterTableForeignKey($entity, $column, $fk);
                    }
                }
            }
            //create necessary values
            if (!empty($values = $this->openDbStructure()['values'])) {
                $this->insertValues($values);
            }
            //update online version
            if (is_null($version)) {
                //create version
                $version = new Version();
                $version->setId(1);
            }
            $version->setVersion($this->openDbStructure()['dbversion']);
            $version->setDate_version(date('Y-m-d H:i:s'));
            $this->persist($version);
            $this->flush();
        }
    }

    /**
     * @param string|null $path
     * @param string|null $file_name
     * @return array
     * @throws \JsonException
     * @deprecated use DbMapping
     */
    private function openDbStructure(?string $path = null, string $file_name = null): array
    {
        if (empty($path)) {
            $path = self::DB_STRUCTURE_PATH;
        }
        if (empty($file_name)) {
            $file_name = self::DB_STRUCTURE_NAME;
        }
        return (new Json($path . $file_name))->openFile();
    }

    /**
     * @param Object $old_item
     * @param Object $new_item
     * @return array|null
     */
    protected function compareUpdateItem(Object $old_item, Object $new_item): ?array
    {
        $new_properties = $new_item->properties($new_item);
        $old_properties = $old_item->properties($old_item);
        $oldDiffProperties = array_diff_assoc($old_properties, $new_properties);
        $differences = [];
        foreach ($oldDiffProperties as $key => $value) {
            if ($value !== $new_properties[$key]) {
                $differences[$key] = [$value, $new_properties[$key]];
            }
        }
        return (!empty($differences)) ? $differences : null;
    }

}