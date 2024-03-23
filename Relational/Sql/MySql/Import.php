<?php

namespace MulerTech\Database\Relational\Sql\MySql;

use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use RuntimeException;

/**
 * Class Import
 * @package MulerTech\Database\MySql
 * @author Sébastien Muler
 */
class Import
{

    private $dbParameters;

    /**
     * Backup constructor.
     */
    public function __construct()
    {
        $dbParameters = ['DATABASE_URL' => getenv('DATABASE_URL')];
        $this->dbParameters = PhpDatabaseManager::populateParameters($dbParameters);
    }

    /**
     * @param string $pathFile
     * @return mixed
     */
    public function importDatabase(string $pathFile)
    {
        $output = [];
        if (strpos($this->dbParameters['pass'], "'") !== false && strpos($this->dbParameters['pass'], '"') !== false) {
            throw new RuntimeException('Class : Import, function : importDatabase. The Mysql password have single and double quotes, this can\'t be escape for both.');
        }
        if (strpos($this->dbParameters['pass'], '"') !== false) {
            //The password have double quote, it worked with the single quote around the password.
            exec(
                'mysql --host=' . $this->dbParameters['host'] . ' --user=' . $this->dbParameters['user'] . ' --password=\'' . $this->dbParameters['pass'] . '\' ' . $this->dbParameters['dbname'] . ' < ' . $pathFile,
                $output,
                $return
            );
        } else {
            //If password have single quote (or other special characters) it worked with the double quote around the password
            exec(
                'mysql --host=' . $this->dbParameters['host'] . ' --user=' . $this->dbParameters['user'] . ' --password="' . $this->dbParameters['pass'] . '" ' . $this->dbParameters['dbname'] . ' < ' . $pathFile,
                $output,
                $return
            );
        }
        return $return;
    }
}