<?php

declare(strict_types=1);

namespace MulerTech\Database\Database;

use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use RuntimeException;

/**
 * Class DatabaseBackupManager
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MySQLBackupManager
{
    /**
     * @var array<int|string, mixed> $dbParameters
     */
    private array $dbParameters;

    /**
     * DatabaseBackupManager constructor.
     */
    public function __construct()
    {
        $this->dbParameters = PhpDatabaseManager::populateParameters();
    }

    /**
     * @param string $pathMysqldump Path to mysqldump binary, or '' if in OS PATH.
     * @param string $pathBackup Path to save the backup file.
     * @param array<int, string>|null $tableList List of tables to backup, or null for all.
     * @return bool|string True if backup ok, false if backup ok but save nok, or error message as string.
     */
    public function createBackup(string $pathMysqldump, string $pathBackup, ?array $tableList = null): bool|string
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
     * @param string $pathFile Path to the SQL backup file to restore.
     * @return int Result code from exec (0 if success).
     */
    public function restoreBackup(string $pathFile): int
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
