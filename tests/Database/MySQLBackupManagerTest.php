<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Database;

use MulerTech\Database\Database\MySQLBackupManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MySQLBackupManager::class)]
final class MySQLBackupManagerTest extends TestCase
{
    private string $testBackupPath;

    protected function setUp(): void
    {
        $this->testBackupPath = sys_get_temp_dir() . '/test_backup_' . uniqid() . '.sql';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testBackupPath)) {
            unlink($this->testBackupPath);
        }
    }

    private function createBackupManager(): MySQLBackupManager
    {
        return new MySQLBackupManager();
    }

    public function testCreateBackupWithInvalidMysqldumpPath(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mysqldump failed');
        $this->createBackupManager()->createBackup(
            '/nonexistent/path/to/',
            $this->testBackupPath
        );
    }

    public function testCreateBackupWithSpecificTables(): void
    {
        $tableList = ['users', 'posts', 'comments'];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mysqldump failed');
        $this->createBackupManager()->createBackup(
            '/nonexistent/path/to/',
            $this->testBackupPath,
            $tableList
        );
    }

    public function testCreateBackupWithAllTables(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mysqldump failed');
        $this->createBackupManager()->createBackup(
            '/nonexistent/path/to/',
            $this->testBackupPath,
            null
        );
    }

    public function testRestoreBackupWithNonExistentFile(): void
    {
        $nonExistentFile = '/nonexistent/backup.sql';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Backup file does not exist');
        $this->createBackupManager()->restoreBackup($nonExistentFile);
    }

    public function testRestoreBackupWithValidFile(): void
    {
        $tempSqlFile = sys_get_temp_dir() . '/test_restore_' . uniqid() . '.sql';
        file_put_contents($tempSqlFile, 'SELECT 1;');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('mysql restore failed');
            $this->createBackupManager()->restoreBackup($tempSqlFile);
        } finally {
            unlink($tempSqlFile);
        }
    }

    public function testPasswordWithBothQuotesThrowsException(): void
    {
        $backupManager = $this->createBackupManager();
        $reflection = new \ReflectionClass($backupManager);
        $dbParametersProperty = $reflection->getProperty('dbParameters');
        $dbParametersProperty->setAccessible(true);
        $parameters = $dbParametersProperty->getValue($backupManager);
        $parameters['pass'] = 'pass"with\'both';
        $dbParametersProperty->setValue($backupManager, $parameters);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The password have both single and double quote');
        $backupManager->createBackup('/usr/bin/', $this->testBackupPath);
    }

    public function testPasswordWithSingleQuote(): void
    {
        $_ENV['DATABASE_PASS'] = "pass'with'single";
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mysqldump failed');
        $this->createBackupManager()->createBackup('/nonexistent/', $this->testBackupPath);
    }

    public function testPasswordWithDoubleQuote(): void
    {
        $_ENV['DATABASE_PASS'] = 'pass"with"double';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mysqldump failed');
        $this->createBackupManager()->createBackup('/nonexistent/', $this->testBackupPath);
    }

    public function testPasswordWithNoQuotes(): void
    {
        $_ENV['DATABASE_PASS'] = 'simplepassword';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mysqldump failed');
        $this->createBackupManager()->createBackup('/nonexistent/', $this->testBackupPath);
    }

    public function testEmptyPassword(): void
    {
        $_ENV['DATABASE_PASS'] = '';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mysqldump failed');
        $this->createBackupManager()->createBackup('/nonexistent/', $this->testBackupPath);
    }

    public function testMissingDatabaseParameters(): void
    {
        unset($_ENV['DATABASE_HOST'], $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASS'], $_ENV['DATABASE_PATH']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mysqldump failed');
        $backupManager = new MySQLBackupManager();
        $backupManager->createBackup('/nonexistent/', $this->testBackupPath);
    }

    public function testNonStringDatabaseParameters(): void
    {
        $_ENV['DATABASE_HOST'] = '127.0.0.1';
        $_ENV['DATABASE_USER'] = 'root';
        $_ENV['DATABASE_PASS'] = '';
        $_ENV['DATABASE_PATH'] = '/mysql';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mysqldump failed');
        $this->createBackupManager()->createBackup('/nonexistent/', $this->testBackupPath);
    }

    public function testCreateBackupCommandGeneration(): void
    {
        $_ENV['DATABASE_HOST'] = 'testhost';
        $_ENV['DATABASE_USER'] = 'testuser';
        $_ENV['DATABASE_PASS'] = 'testpass';
        $_ENV['DATABASE_PATH'] = '/testdb';

        $backupManager = new MySQLBackupManager();

        $this->expectException(RuntimeException::class);
        $backupManager->createBackup('/usr/bin/', $this->testBackupPath, []);
    }

    public function testRestoreBackupCommandGeneration(): void
    {
        $_ENV['DATABASE_HOST'] = 'testhost';
        $_ENV['DATABASE_USER'] = 'testuser';
        $_ENV['DATABASE_PASS'] = 'testpass';
        $_ENV['DATABASE_PATH'] = '/testdb';

        $backupManager = new MySQLBackupManager();
        $tempFile = sys_get_temp_dir() . '/dummy.sql';
        file_put_contents($tempFile, 'SELECT 1;');

        try {
            $this->expectException(RuntimeException::class);
            $backupManager->restoreBackup($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function testDatabaseParameterParsingFromEnvironment(): void
    {
        $_ENV['DATABASE_SCHEME'] = 'mysql';
        $_ENV['DATABASE_HOST'] = 'localhost';
        $_ENV['DATABASE_PORT'] = '3306';
        $_ENV['DATABASE_USER'] = 'user';
        $_ENV['DATABASE_PASS'] = 'pass';
        $_ENV['DATABASE_PATH'] = '/db';

        $backupManager = new MySQLBackupManager();
        $this->assertInstanceOf(MySQLBackupManager::class, $backupManager);

        unset($_ENV['DATABASE_SCHEME'], $_ENV['DATABASE_PORT']);
    }

    public function testBackupPathCreation(): void
    {
        $backupPath = sys_get_temp_dir() . '/nested/directory/backup.sql';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mysqldump failed');
        $this->createBackupManager()->createBackup('/nonexistent/', $backupPath);
    }
}

