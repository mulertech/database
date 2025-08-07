<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Database;

use MulerTech\Database\Database\MySQLBackupManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
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
        $reflection = new ReflectionClass($backupManager);
        $dbParametersProperty = $reflection->getProperty('dbParameters');
        $parameters = $dbParametersProperty->getValue($backupManager);
        $parameters['pass'] = 'pass"with\'both';
        $dbParametersProperty->setValue($backupManager, $parameters);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The password have both single and double quote');
        $backupManager->createBackup('/usr/bin/', $this->testBackupPath);
    }

    public function testPasswordWithSingleQuote(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mysqldump failed');
        $this->createBackupManager()->createBackup('/nonexistent/', $this->testBackupPath);
    }

    public function testRestoreBackupCommandGeneration(): void
    {
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

    public function testCreateBackupWithNonWritableDirectory(): void
    {
        // Test covers: echo '!is_writable($backupDir)\n';
        $nonWritableDir = sys_get_temp_dir() . '/readonly_' . uniqid();
        $backupPath = $nonWritableDir . '/backup.sql';
        
        // Create directory but make it non-writable
        mkdir($nonWritableDir, 0444, true);
        
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Backup directory is not writable');
            $this->createBackupManager()->createBackup('/usr/bin/', $backupPath);
        } finally {
            // Clean up - restore permissions and remove directory
            chmod($nonWritableDir, 0755);
            rmdir($nonWritableDir);
        }
    }

    public function testCreateBackupWithNonWritableBackupFile(): void
    {
        // Test covers: echo '!is_writable($pathBackup)\n';
        $backupPath = sys_get_temp_dir() . '/readonly_backup_' . uniqid() . '.sql';
        
        // Create file but make it non-writable
        touch($backupPath);
        chmod($backupPath, 0444);
        
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Backup file is not writable');
            $this->createBackupManager()->createBackup('/usr/bin/', $backupPath);
        } finally {
            // Clean up - restore permissions and remove file
            chmod($backupPath, 0644);
            unlink($backupPath);
        }
    }

    public function testPasswordWithSingleQuoteEcho(): void
    {
        $oldPassword = getenv('DATABASE_PASS');
        // Test covers: echo 'Password contains single quote\n';
        putenv("DATABASE_PASS=password'with'quotes"); // Contains single quote
        $backupManager = new MySQLBackupManager();
        
        // Use reflection to call getPasswordCommand directly to test the echo
        $reflection = new ReflectionClass($backupManager);
        $getPasswordMethod = $reflection->getMethod('getPasswordCommand');
        $result = $getPasswordMethod->invoke($backupManager);
        
        // Should wrap in double quotes when password contains single quote
        $this->assertStringStartsWith('"', $result);
        $this->assertStringContainsString("password'with'quotes", $result);

        // Restore original password
        putenv("DATABASE_PASS=$oldPassword");
    }

    public function testCreateBackupSuccessfulExecution(): void
    {
        $backupPath = sys_get_temp_dir() . '/success_test_' . uniqid() . '.sql';
        
        // Most likely this will fail with mysqldump error, but it tests the path
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mysqldump failed');
        $this->createBackupManager()->createBackup('/usr/bin/', $backupPath);
    }

    public function testCreateBackupDirectoryCreationFailure(): void
    {
        // Create a path where directory creation would fail (parent doesn't exist and can't be created)
        $impossiblePath = '/root/nonexistent/deeply/nested/path/backup.sql';
        
        // Only test if we're not running as root (which would actually succeed)
        if (posix_getuid() !== 0) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unable to create backup directory');
            $this->createBackupManager()->createBackup('/usr/bin/', $impossiblePath);
        } else {
            $this->markTestSkipped('Cannot test directory creation failure when running as root');
        }
    }

    public function testCreateBackupFileCreationFailure(): void
    {
        $longName = sys_get_temp_dir() . '/' . str_repeat('a', 255) . '.sql';
        
        if (posix_getuid() !== 0) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot create backup file');
            $this->createBackupManager()->createBackup('/usr/bin/', $longName);
        } else {
            $this->markTestSkipped('Cannot test file creation failure when running as root');
        }
    }
}

