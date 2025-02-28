<?php

namespace MulerTech\Database\Relational\Sql\MySql;

use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use RuntimeException;

/**
 * Class Backup
 * @package MulerTech\Database\MySql
 * @author Sébastien Muler
 */
class Backup
{

    private $dbParameters;

    /**
     * Backup constructor.
     */
    public function __construct()
    {
        $this->dbParameters = PhpDatabaseManager::populateParameters();
    }

    /**
     * @param string $pathMysqldump Put '' if the mysqldump is into the path environment variable of the OS.
     * @param string $pathBackup
     * @param array|null $tableList
     * @return bool|string True if backup ok, false if backup ok but save nok or return this message : Error number : 'Number of Mysqldump error'.
     */
    public function backupDatabase(string $pathMysqldump, string $pathBackup, ?array $tableList = null)
    {
        $output = [];
        $tables = (!is_null($tableList)) ? implode(' ', $tableList) . ' ' : '';
        if (strpos($this->dbParameters['pass'], "'") !== false && strpos($this->dbParameters['pass'], '"') !== false) {
            throw new RuntimeException('Class : Backup, function : backupDatabase. The Mysql password have single and double quotes, this can\'t be escape for both.');
        }
        if (strpos($this->dbParameters['pass'], '"') !== false) {
            //The password have double quote, it worked with the single quote around the password.
            exec(
                $pathMysqldump . 'mysqldump --opt --host=' . $this->dbParameters['host'] . ' --user=' . $this->dbParameters['user'] . ' --password=\'' . $this->dbParameters['pass'] . '\' ' . $this->dbParameters['dbname'] . ' ' . $tables . '> ' . $pathBackup,
                $output,
                $worked
            );
        } else {
            //If password have single quote (or other special characters) it worked with the double quote around the password
            exec(
                $pathMysqldump . 'mysqldump --opt --host=' . $this->dbParameters['host'] . ' --user=' . $this->dbParameters['user'] . ' --password="' . $this->dbParameters['pass'] . '" ' . $this->dbParameters['dbname'] . ' ' . $tables . '> ' . $pathBackup,
                $output,
                $worked
            );
        }
        return ($worked === 0) ? file_exists($pathBackup) : 'Error number : ' . $worked;
    }
}