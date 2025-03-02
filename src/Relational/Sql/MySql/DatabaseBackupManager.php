<?php

namespace MulerTech\Database\Relational\Sql\MySql;

use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use RuntimeException;

/**
 * Class Import
 * @package MulerTech\Database\MySql
 * @author Sébastien Muler
 */
class DatabaseBackupManager
{
    private array $dbParameters;

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
    public function createBackup(string $pathMysqldump, string $pathBackup, ?array $tableList = null)
    {
        $output = [];
        $tables = (!is_null($tableList)) ? implode(' ', $tableList) . ' ' : '';
        $this->checkPasswordQuotes();

        $command = $pathMysqldump . 'mysqldump --opt ';
        $command .= $this->getHostCommand();
        $command .= $this->getUserCommand();
        $command .= $this->getPasswordCommand();
        $command .= $this->getDbNameCommand();
        $command .= $tables . '> ' . $pathBackup;

        exec($command, $output, $result);

        return ($result === 0) ? file_exists($pathBackup) : 'Error number : ' . $result;
    }

    /**
     * @param string $pathFile
     * @return mixed
     */
    public function restoreBackup(string $pathFile)
    {
        $output = [];
        $this->checkPasswordQuotes();
        $command = 'mysql ';
        $command .= $this->getHostCommand();
        $command .= $this->getUserCommand();
        $command .= $this->getPasswordCommand();
        $command .= $this->getDbNameCommand();
        $command .= '< ' . $pathFile;

        exec($command, $output, $result);

        return $result;
    }

    /**
     * @return void
     */
    private function checkPasswordQuotes(): void
    {
        if (str_contains($this->dbParameters['pass'], "'") && str_contains($this->dbParameters['pass'], '"')) {
            throw new RuntimeException(
                'The password have both single and double quote, please change the password to use only one type of quote.'
            );
        }
    }

    /**
     * @return string
     */
    private function getHostCommand(): string
    {
        return '--host=' . $this->dbParameters['host'] . ' ';
    }

    /**
     * @return string
     */
    private function getUserCommand(): string
    {
        return '--user=' . $this->dbParameters['user'] . ' ';
    }

    /**
     * @return string
     */
    private function getPasswordCommand(): string
    {
        return str_contains($this->dbParameters['pass'], "'")
            ? '"' . $this->dbParameters['pass'] . '" '
            : "'" . $this->dbParameters['pass'] . "' ";
    }

    /**
     * @return string
     */
    private function getDbNameCommand(): string
    {
        return $this->dbParameters['dbname'] . ' ';
    }
}