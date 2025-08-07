<?php

declare(strict_types=1);

namespace MulerTech\Database\Database;

use MulerTech\Database\Database\Interface\DatabaseParameterParser;
use RuntimeException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MySQLBackupManager
{
    /**
     * @var array<int|string, mixed> $dbParameters
     */
    private array $dbParameters;

    public function __construct()
    {
        $this->dbParameters = new DatabaseParameterParser()->parseParameters();
    }

    /**
     * @param string $pathMysqldump
     * @param string $pathBackup
     * @param array<int, string>|null $tableList
     * @return bool
     * @throws RuntimeException
     */
    public function createBackup(string $pathMysqldump, string $pathBackup, ?array $tableList = null): bool
    {
        $output = [];
        $tables = $tableList !== null ? implode(' ', $tableList) . ' ' : '';
        $this->checkPasswordQuotes();

        $backupDir = dirname($pathBackup);

        if (!is_dir($backupDir) && !@mkdir($backupDir, 0o777, true) && !is_dir($backupDir)) {
            throw new RuntimeException('Unable to create backup directory: ' . $backupDir);
        }
        if (!is_writable($backupDir)) {
            throw new RuntimeException('Backup directory is not writable: ' . $backupDir);
        }

        if (file_exists($pathBackup) && !is_writable($pathBackup)) {
            throw new RuntimeException('Backup file is not writable: ' . $pathBackup);
        }
        if (!file_exists($pathBackup)) {
            $fp = @fopen($pathBackup, 'w');
            if ($fp === false) {
                throw new RuntimeException('Cannot create backup file: ' . $pathBackup);
            }
            fclose($fp);
            @unlink($pathBackup);
        }

        $command = $pathMysqldump . 'mysqldump --opt ';
        $command .= $this->getHostCommand();
        $command .= $this->getUserCommand();
        $command .= $this->getPasswordCommand();
        $command .= $this->getDbNameCommand();
        $command .= $tables . '> ' . $pathBackup . ' 2>/dev/null';

        exec($command, $output, $result);

        if ($result !== 0) {
            throw new RuntimeException('mysqldump failed with error code: ' . $result);
        }
        echo 'mysqldump command executed: ' . $command . "\n";

        if (!file_exists($pathBackup)) {
            echo '!file_exists($pathBackup)\n';
            throw new RuntimeException('Backup file was not created: ' . $pathBackup);
        }
        echo 'Backup file created successfully\n';

        return true;
    }

    /**
     * @param string $pathFile
     * @return void
     * @throws RuntimeException
     */
    public function restoreBackup(string $pathFile): void
    {
        if (!is_file($pathFile) || !is_readable($pathFile)) {
            throw new RuntimeException('Backup file does not exist or is not readable: ' . $pathFile);
        }

        $output = [];
        $this->checkPasswordQuotes();
        $command = 'mysql ';
        $command .= $this->getHostCommand();
        $command .= $this->getUserCommand();
        $command .= $this->getPasswordCommand();
        $command .= $this->getDbNameCommand();
        $command .= '< ' . $pathFile . ' 2>/dev/null';

        exec($command, $output, $result);

        if ($result !== 0) {
            throw new RuntimeException('mysql restore failed with error code: ' . $result);
        }
    }

    /**
     * @return void
     * @throws RuntimeException
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
