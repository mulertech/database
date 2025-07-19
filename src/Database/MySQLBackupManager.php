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
        $password = $this->dbParameters['pass'] ?? '';
        $passwordStr = is_string($password) ? $password : '';

        if (str_contains($passwordStr, "'") && str_contains($passwordStr, '"')) {
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
        $host = $this->dbParameters['host'] ?? '';
        return '--host=' . (is_string($host) ? $host : '') . ' ';
    }

    /**
     * @return string
     */
    private function getUserCommand(): string
    {
        $user = $this->dbParameters['user'] ?? '';
        return '--user=' . (is_string($user) ? $user : '') . ' ';
    }

    /**
     * @return string
     */
    private function getPasswordCommand(): string
    {
        $password = $this->dbParameters['pass'] ?? '';
        $passwordStr = is_string($password) ? $password : '';

        if (str_contains($passwordStr, "'")) {
            return '"' . $passwordStr . '" ';
        }

        return "'" . $passwordStr . "' ";
    }

    /**
     * @return string
     */
    private function getDbNameCommand(): string
    {
        $dbname = $this->dbParameters['dbname'] ?? '';
        return (is_string($dbname) ? $dbname : '') . ' ';
    }
}
