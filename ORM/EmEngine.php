<?php

namespace mtphp\Database\ORM;

use _config\UpdateDatabaseMysql;
use Exception;
use App\Entity\Version;
use mtphp\ArrayManipulation\ArrayManipulation;
use mtphp\ClassManipulation\ClassManipulation;
use mtphp\Database\Event\PostFlushEvent;
use mtphp\Database\Event\PostPersistEvent;
use mtphp\Database\Event\PostRemoveEvent;
use mtphp\Database\Event\PostUpdateEvent;
use mtphp\Database\Event\PrePersistEvent;
use mtphp\Database\Event\PreUpdateEvent;
use mtphp\Database\NonRelational\DocumentStore\FileExtension\Json;
use mtphp\Database\PhpInterface\PhpDatabaseManager;
use mtphp\DateTimeFormat\DateFormat;
use mtphp\Entity\Entity;
use mtphp\EventManager\EventManagerInterface;
use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Class EmEngine
 * @package mtphp\Database\ORM
 * @author Sébastien Muler
 */
class EmEngine
{

    private const DB_STRUCTURE_PATH = ".." . DIRECTORY_SEPARATOR . "_config" . DIRECTORY_SEPARATOR;
    private const DB_STRUCTURE_NAME = 'dbstructure.json';

    /**
     * @var EntityManager Entity manager
     */
    private $em;
    private $entityInsertions = [];
    private $entityUpdates = [];
    private $entityDeletions = [];
    /**
     * @var array Save all the entity changes example :
     * [$objectId => [$field => [$oldValue, $newValue]]]
     */
    private $entityChanges = [];
    private $tableslinked = [];
    private $join = '';
    /**
     * @var EventManagerInterface $eventManager
     */
    private $eventManager;
    /**
     * @var UpdateDatabaseMysql $updateDatabase
     */
    private $updateDatabase;
    /**
     * @var string $entity
     * @todo Replace this entity by EntityMapping
     */
    private $entity;

    /**
     * @param EntityManagerInterface $entityManager
     * @param UpdateDatabaseMysql|null $updateDatabase
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        UpdateDatabaseMysql $updateDatabase = null
    ) {
        $this->em = $entityManager;
        $this->eventManager = $entityManager->getEventManager();
        $this->updateDatabase = $updateDatabase;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->em;
    }

    /**
     * @param string|object $entity
     */
    protected function setEntity($entity): void
    {
        if (is_object($entity)) {
            $this->entity = get_class($entity);
        } else {
            $this->entity = $entity;
        }

    }

    /**
     * @return string
     */
    protected function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * @return string
     */
    protected function getEntityTable(): string
    {
        if (!isset($this->entity)) {
            throw new RuntimeException('Class : EmEngine, Function : getEntityTable. The entity variable of the EmEngine was not set.');
        }
        $entityParts = explode('\\', $this->entity);
        return strtolower(end($entityParts));
    }

    /**
     * @param string $request
     * @param array|null $execute_values
     * @param string|null $output
     * @param string|null $field
     * @param array|null $bind_param
     * @return array|bool|mixed|PDOStatement|string|null
     */
    public function prepareRequest(
        string $request,
        ?array $execute_values = null,
        ?string $output = null,
        ?string $field = null,
        array $bind_param = null
    ) {
        //prepare request
        $pdoStatement = $this->em->getPdm()->prepare($request);
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
//        if (strtolower($output) === "lastid") {
//            $return = (!empty($lastId = $this->em->getPdm()->lastInsertId())) ? $lastId : null;
//        } else
//            if (strtolower($output) === "req" && $pdoStatement->rowCount() !== 0) {
//            $return = $pdoStatement;
//        } else
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
        } elseif (strtolower($output) === "class") {
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
            $eventEntities = [];
            foreach ($this->entityInsertions as $uio => $entity) {
                $table = ClassManipulation::getClassNameLower(get_class($entity));
                $values = $entity->properties($entity);
                unset($values['id']);
                $request = 'INSERT INTO `' . $table . '` (' . implode(
                        ', ',
                        array_keys($values)
                    ) . ') VALUES (:' . implode(', :', array_keys($values)) . ')';
                //prepare request
                $pdoStatement = $this->em->getPdm()->prepare($request);
                if (!empty($values)) {
                    $pdoStatement->execute($values);
                } else {
                    $pdoStatement->execute();
                }
                //set id
                if (!empty($lastId = $this->em->getPdm()->lastInsertId())) {
                    $this->setIdEntity($uio, $lastId);
                }
                //close cursor
                $pdoStatement->closeCursor();
                unset($this->entityInsertions[$uio]);
                //For event
                $eventEntities[] = $entity;
            }
            //Event Post persist
            if ($this->eventManager) {
                foreach ($eventEntities as $entity) {
                    $this->eventManager->dispatch(new PostPersistEvent($entity, $this->em));
                }
            }
        }
    }

    /**
     * @param $hash
     * @param $id
     */
    private function setIdEntity($hash, $id): void
    {
        $this->entityInsertions[$hash]->setId($id);
    }

    /**
     * read Mysql table and push it in object
     * @param object|string $entity
     * @param string|int|null $idorwhere Id of object or mysql where condition.
     * @return Entity|null Entity filled or null
     */
    public function find($entity, ?string $idorwhere = null): ?Entity
    {
        $this->setEntity($entity);
        if (!is_null($idorwhere)) {
            $request = 'SELECT * FROM `' . $this->getEntityTable() . '` WHERE ';
            $request .= (is_numeric($idorwhere)) ? 'id=' . $idorwhere : $idorwhere;
            return $this->prepareRequest($request, null, 'class');
        }
        return null;
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
        $this->tableslinked = [];
        //If the model class was given.
        if (strpos($table, '\\')) {
            $this->setEntity($table);
            $entity = explode('\\', $table);
            $table = strtolower(end($entity));
        }
        //test if the table exist
//        debx($this->getEntityManager()->getDbMapping()->getTableList());
        if (!in_array($table, $this->getEntityManager()->getDbMapping()->getTableList(), true)) {
//        if (!array_key_exists($table, $this->openDbStructure()['structure'])) {
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
                    if ($jtable !== $table && (!in_array($jtable, $this->tableslinked, true) || in_array(
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
                        if (!in_array($jtable, $this->tableslinked, true)) {
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
     * @param Entity $entity
     * @return void
     */
    public function persist(Entity $entity): void
    {
        if ($entity->isNew()) {
            //Event Pre persist
            if ($this->eventManager) {
                $this->eventManager->dispatch(new PrePersistEvent($entity, $this->em));
            }
            $this->entityInsertions[spl_object_hash($entity)] = $entity;
        } elseif (!isset($this->entityDeletions[spl_object_hash($entity)])) {
            //Prevent update after deletion, if the entity was removed it's impossible to update this.
            $this->setEntityUpdates($entity);
        }
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        if (!($this->entityInsertions || $this->entityUpdates || $this->entityDeletions)) {
            return;
        }
        $this->em->getPdm()->beginTransaction();
        if (!empty($this->entityInsertions)) {
            $this->executeInsertions();
        }
        if (!empty($this->entityUpdates)) {
            $this->executeUpdates();
        }
        if (!empty($this->entityDeletions)) {
            $this->executeDeletions();
        }
        $this->em->getPdm()->commit();
        //Event Post flush
        if ($this->eventManager) {
            $this->eventManager->dispatch(new PostFlushEvent($this->em));
        }
    }

    /**
     * @param Entity $entity
     * @return void
     */
    private function setEntityUpdates(Entity $entity): void
    {
        $this->entityUpdates[spl_object_hash($entity)] = $entity;
        /**
         * Prevent identical spl_object_hash
         */
        unset($this->entityChanges[spl_object_hash($entity)]);
    }
    /** Find MySql join from the path with the constraints schema
     * @param array $path
     * @param array $constraints
     * @param string $table_as
     * @param string $column_origin
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
            if (!in_array($destinationTable, $this->tableslinked, true) || (!empty($table_as) && !in_array(
                        $table_as,
                        $this->tableslinked,
                        true
                    ))) {
                $table_as_req = (empty($table_as) || count($path) > 1) ? '' : ' AS ' . $table_as;
                $ref_table = (empty($table_as) || count($path) > 1) ? $value['REFERENCED_TABLE_NAME'] : $table_as;
                $origin_column = (empty($column_origin) || count($path) > 1) ? $value['COLUMN_NAME'] : $column_origin;
                if (($value['TABLE_NAME'] === $destinationTable && $value['REFERENCED_TABLE_NAME'] === $originTable) || ($value['TABLE_NAME'] === $originTable && $value['REFERENCED_TABLE_NAME'] === $destinationTable)) {
                    $this->join .= ' ' . $joinType . ' `' . $destinationTable . '`' . $table_as_req . ' ON ' . $value['TABLE_NAME'] . '.' . $origin_column . ' = ' . $ref_table . '.' . $value['REFERENCED_COLUMN_NAME'];
                    $this->tableslinked[] = $destinationTable;
                    if (!empty($table_as)) {
                        $this->tableslinked[] = $table_as;
                    }
                }
            }
        }
        if (count($path) !== 1) {
            $this->findJoins($path, $constraints);
        }
    }

    /**
     */
    private function executeUpdates(): void
    {
        if (!empty($this->entityUpdates)) {
            foreach ($this->entityUpdates as $uio => $entity) {
                //Set the entity changes
                if (!is_null($this->getEntityChanges($uio))) {
                    //Pre update Event
                    if ($this->eventManager) {
                        $this->eventManager->dispatch(
                            new PreUpdateEvent($entity, $this->em, $this->entityChanges[$uio])
                        );
                    }
                    $request = 'UPDATE `' . strtolower(
                            $entity->getName()
                        ) . '` SET ';
                    //column = :column
                    $columns = [];
                    foreach ($this->entityChanges[$uio] as $key => $value) {
                        $columns[] = $key . ' =:' . $key;
                    }
                    $request .= implode(', ', $columns);
                    //Where
                    $request .= ' WHERE id=' . $entity->id();
                    //prepare request
                    $pdoStatement = $this->em->getPdm()->prepare($request);
                    foreach ($this->entityChanges[$uio] as $column => $change) {
                        $pdoStatement->bindParam(':' . $column, $change[1]);
                    }
                    $pdoStatement->execute();
                }
                unset($this->entityUpdates[$uio]);
                //Post update Event
                if ($this->eventManager) {
                    $this->eventManager->dispatch(new PostUpdateEvent($entity, $this->em));
                }
            }
        }
    }

    /**
     * @param string $uio
     * @return array|null
     */
    public function getEntityChanges(string $uio): ?array
    {
        if (!empty($this->entityChanges[$uio])) {
            return $this->entityChanges[$uio];
        }

        if (empty($this->entityUpdates[$uio])) {
            return null;
        }

        $entity = $this->entityUpdates[$uio];
        if (is_null($old_entity = $this->find($entity, $entity->id()))) {
            return null;
        }
        $new_properties = $entity->properties($entity);
        $old_properties = $old_entity->properties($old_entity);
        $oldDiffProperties = array_diff_assoc($old_properties, $new_properties);
        $differences = [];
        foreach ($oldDiffProperties as $key => $value) {
            if ($value !== $new_properties[$key]) {
                $differences[$key] = [$value, $new_properties[$key]];
            }
        }
        $this->entityChanges[$uio] = $differences;
        return (!empty($differences)) ? $differences : null;


    }

    /**
     * @param Entity $entity
     */
    public function remove(Entity $entity): void
    {
        if (isset($this->entityUpdates[spl_object_hash($entity)])) {
            unset($this->entityUpdates[spl_object_hash($entity)]);
        }
        if (isset($this->entityInsertions[spl_object_hash($entity)])) {
            //It's useless to insert an entity and remove them after.
            unset($this->entityInsertions[spl_object_hash($entity)]);
            return;
        }
        $this->entityDeletions[spl_object_hash($entity)] = $entity;
    }

    /**
     * Execute all deletions from entityDeletions variable.
     * @todo Replace class_name_lower by the table name of the entity (with metadata if exists) or leave class_name_lower if not.
     */
    private function executeDeletions(): void
    {
        if (!empty($this->entityDeletions)) {
            $entitiesEvent = [];
            foreach ($this->entityDeletions as $uio => $entity) {
                $req = "DELETE FROM `" . ClassManipulation::getClassNameLower(
                        get_class($entity)
                    ) . "` WHERE id = :object_id ";
                //prepare and execute request
                $pdoStatement = $this->em->getPdm()->prepare($req);
                $object_id = $entity->id();
                $pdoStatement->bindParam(':object_id', $object_id, PDO::PARAM_INT);
                try {
                    $pdoStatement->execute();
                } catch (\PDOException $exception) {
                    //Errors
                    if ($pdoStatement->errorCode() !== '00000') {
                        if ($pdoStatement->errorCode() === '23000') {
                            throw new RuntimeException(
                                sprintf(
                                    'Class : EmEngine, Function : deleteObj. Impossible to delete this entity "%s", this entity is linked with another one.',
                                    $entity
                                )
                            );
                        }
                        throw new RuntimeException(
                            sprintf(
                                'Class : EmEngine, Function : deleteObj. Error where delete this entity "%s"',
                                $entity
                            )
                        );
                    }
                }
                unset($this->entityDeletions[$uio]);
                $entitiesEvent[] = $entity;
            }
            //Event Post remove
            if ($this->eventManager) {
                foreach ($entitiesEvent as $entity) {
                    $this->eventManager->dispatch(new PostRemoveEvent($entity, $this->em));
                }
            }
        }
    }

    /**
     * List of this database tables (Mysql)
     * @return array
     */
    public function tablesList(): array
    {
        $dbParameters = PhpDatabaseManager::populateParameters(['DATABASE_URL' => getenv('DATABASE_URL')]);
        $dbName = $dbParameters['dbname'];
        //prepare and execute request
        $success = $this->em->getPdm()->query('SHOW TABLES');
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
            $success = $this->em->getPdm()->query('SELECT COUNT(*) FROM `' . $table . '`');
        } else {
            $success = $this->em->getPdm()->query('SELECT COUNT(*) FROM `' . $table . '` WHERE ' . $where);
        }
        $count = $success->fetchColumn();
        //close cursor
        $success->closeCursor();
        return $count;
    }

    /**
     * Make a structure of database for save in a json file
     * @return array
     */
    private function onlineDatabaseStructure(): array
    {
        $dbParameters = PhpDatabaseManager::populateParameters(['DATABASE_URL' => getenv('DATABASE_URL')]);
        $dbName = $dbParameters['dbname'];
        //Array db
        $arraydb = ['structure' => []];

        //COLUMNS req
        $columnsreq = "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, EXTRA, COLUMN_DEFAULT, COLUMN_KEY FROM `information_schema`.`COLUMNS` WHERE TABLE_SCHEMA = '" . $dbName . "'";
        $reqcolumns = $this->em->getPdm()->query($columnsreq);
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
        $reqtables = $this->em->getPdm()->query($tablesreq);
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
        $reqconstraints = $this->em->getPdm()->query($constraintreq);
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
                $arraydb['structure'][$table_name]['foreign_keys'][${'line' . $table_name}] = $constraint;
                unset($table_name);
            }
        }
        return $arraydb;
    }

    /**
     * List of constraints of this database
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
        $success = $this->em->getPdm()->query($req);
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
        $success = $this->em->getPdm()->query($req);
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
        $success = $this->em->getPdm()->query($req);
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
        $success = $this->em->getPdm()->query($req);
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
        $success = $this->em->getPdm()->query($req);
        $success->closeCursor();
    }

    /** Alter Mysql table, add foreign key
     * @param string $table
     * @param array $foreign_key
     */
    private function alterTableForeignKey(string $table, array $foreign_key): void
    {
        if (!empty($foreign_key['CONSTRAINT_NAME']) && !empty($foreign_key['COLUMN_NAME']) && !empty($foreign_key['REFERENCED_TABLE_NAME']) && !empty($foreign_key['REFERENCED_COLUMN_NAME']) && !empty($foreign_key['DELETE_RULE']) && !empty($foreign_key['UPDATE_RULE'])) {
            $req = "ALTER TABLE `" . $table . "` ADD CONSTRAINT `" . $foreign_key['CONSTRAINT_NAME'] . "` FOREIGN KEY (`" . $foreign_key['COLUMN_NAME'] . "`) REFERENCES `" . $foreign_key['REFERENCED_TABLE_NAME'] . "` (`" . $foreign_key['REFERENCED_COLUMN_NAME'] . "`) ON DELETE " . $foreign_key['DELETE_RULE'] . " ON UPDATE " . $foreign_key['UPDATE_RULE'];
            //request
            $success = $this->em->getPdm()->query($req);
            $success->closeCursor();
        }
    }

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
        $this->em->getPdm()->exec($req);
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
         * @var Entity $version
         */
        $online_version = (!is_null($version = $this->find(Version::class, 1))) ? $version->version() : null;
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
//                                        if (!empty($differentcolumns['COLUMN_TYPE']) && substr($differentcolumns['COLUMN_TYPE'][0], 0, 4) === 'int(' && substr(
//                                                $differentcolumns['COLUMN_TYPE'][1], 0, 4) === 'int ') {
//                                            unset($differentcolumns['COLUMN_TYPE']);
//                                        }
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
            foreach ($site_structure as $fktablekey => $fktableval) {
                //check if the foreigns keys exists ($fk : foreign key on site structure)
                if (!empty($fk[$fktablekey]) && is_array($fk[$fktablekey])) {
                    foreach ($fk[$fktablekey] as $fklist) {
                        $fkexists = false;
                        //foreach site structure fk check if it exists online
                        if (!empty($online_structure[$fktablekey]['foreign_keys']) && is_array(
                                $online_structure[$fktablekey]['foreign_keys']
                            )) {
                            foreach ($online_structure[$fktablekey]['foreign_keys'] as $foreign_key) {
                                if (array_search($fklist['CONSTRAINT_NAME'], $foreign_key)) {
                                    $fkexists = true;
                                }
                            }
                        } else {
                            $fkexists = false;
                        }
                        //If the fk don't exists create it
                        if ($fkexists === false) {
                            //create fk
                            $this->alterTableForeignKey($fktablekey, $fklist);
                        }
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
            $version->setDate_version((new DateFormat())->dateTime());
            $this->persist($version);
            $this->flush();
        }
    }

    /**
     * @param string|null $path
     * @param string|null $file_name
     * @return array
     */
    private function openDbStructure(?string $path = null, string $file_name = null): array
    {
        if (empty($path)) {
            $path = self::DB_STRUCTURE_PATH;
        }
        if (empty($file_name)) {
            $file_name = self::DB_STRUCTURE_NAME;
        }
        return Json::openFile($path . $file_name);
    }

    /**
     * @param string $uio Unique Id of the Object.
     * @return array|null
     */
//    public function getEntityChanges(string $uio): ?array
//    {
//        return $this->entityChanges[$uio] ?? null;
//    }

    /**
     * @param Entity $old_item
     * @param Entity $new_item
     * @return array|null
     */
    protected function compareUpdateItem(Entity $old_item, Entity $new_item): ?array
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