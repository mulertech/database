<?php

declare(strict_types=1);

/**
 * Script to cleanup old structure after successful migration
 * Run from project root: php CleanupOldStructure.php
 *
 * WARNING: This will delete directories and files! Make sure you have a backup.
 */

class StructureCleanup
{
    /**
     * @var array<string>
     */
    private array $directoriesToRemove = [
        'src/PhpInterface',      // Moved to src/Database/Interface
        'src/Relational',        // Content distributed to SQL/ and Schema/
        'src/Migration',         // Moved to src/Schema/Migration
    ];

    /**
     * @var array<string>
     */
    private array $emptyDirectoriesToClean = [
        'src/Query',             // If only subdirectories remain
        'src/ORM/Engine',        // If only subdirectories remain
    ];

    /**
     * @var array<string>
     */
    private array $deletedItems = [];

    /**
     * @var array<string>
     */
    private array $errors = [];

    /**
     * @var bool
     */
    private bool $dryRun = true;

    /**
     * Run the cleanup process
     */
    public function run(bool $dryRun = true): void
    {
        $this->dryRun = $dryRun;

        echo "=== Structure Cleanup Tool ===\n";
        echo $dryRun ? "DRY RUN MODE - No files will be deleted\n\n" : "LIVE MODE - Files will be deleted!\n\n";

        if (!$dryRun) {
            echo "Are you sure you want to proceed? (yes/no): ";
            $confirmation = trim(fgets(STDIN));
            if (strtolower($confirmation) !== 'yes') {
                echo "Cleanup cancelled.\n";
                return;
            }
        }

        $this->cleanupOldDirectories();
        $this->cleanupEmptyDirectories();
        $this->findOrphanedFiles();
        $this->printReport();
    }

    /**
     * Remove old directories that have been completely migrated
     */
    private function cleanupOldDirectories(): void
    {
        echo "Checking old directories...\n";

        foreach ($this->directoriesToRemove as $dir) {
            if (is_dir($dir)) {
                $files = $this->getAllFiles($dir);

                if (empty($files)) {
                    $this->removeDirectory($dir);
                } else {
                    echo "  ⚠ Directory $dir still contains files:\n";
                    foreach ($files as $file) {
                        echo "    - $file\n";
                    }
                    $this->errors[] = "Directory $dir not empty";
                }
            } else {
                echo "  ✓ Directory $dir already removed\n";
            }
        }
    }

    /**
     * Clean up directories that should only contain subdirectories
     */
    private function cleanupEmptyDirectories(): void
    {
        echo "\nChecking for empty directories...\n";

        // Find all empty directories recursively
        $emptyDirs = $this->findEmptyDirectories('src');

        foreach ($emptyDirs as $dir) {
            $this->removeDirectory($dir);
        }
    }

    /**
     * Find files that might have been missed in migration
     */
    private function findOrphanedFiles(): void
    {
        echo "\nLooking for orphaned files...\n";

        $patterns = [
            'src/**/test_*.php',     // Test files in wrong locations
            'src/**/*_old.php',      // Backup files
            'src/**/*.bak',          // Backup files
            'src/**/.*.swp',         // Editor swap files
        ];

        foreach ($patterns as $pattern) {
            $files = glob($pattern, GLOB_BRACE);
            foreach ($files as $file) {
                echo "  Found orphaned file: $file\n";
                if (!$this->dryRun) {
                    if (unlink($file)) {
                        $this->deletedItems[] = "File: $file";
                    } else {
                        $this->errors[] = "Failed to delete file: $file";
                    }
                }
            }
        }
    }

    /**
     * @param string $directory
     * @return array<string>
     */
    private function getAllFiles(string $directory): array
    {
        $files = [];

        if (!is_dir($directory)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $files[] = $item->getPathname();
            }
        }

        return $files;
    }

    /**
     * @param string $directory
     * @return array<string>
     */
    private function findEmptyDirectories(string $directory): array
    {
        $emptyDirs = [];

        if (!is_dir($directory)) {
            return $emptyDirs;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $isEmpty = true;
                $dir = $item->getPathname();

                // Check if directory is empty
                $contents = scandir($dir);
                foreach ($contents as $content) {
                    if ($content !== '.' && $content !== '..') {
                        $isEmpty = false;
                        break;
                    }
                }

                if ($isEmpty) {
                    $emptyDirs[] = $dir;
                }
            }
        }

        // Sort by depth (deepest first)
        usort($emptyDirs, function($a, $b) {
            return substr_count($b, DIRECTORY_SEPARATOR) - substr_count($a, DIRECTORY_SEPARATOR);
        });

        return $emptyDirs;
    }

    /**
     * @param string $directory
     */
    private function removeDirectory(string $directory): void
    {
        if ($this->dryRun) {
            echo "  Would remove: $directory\n";
            return;
        }

        if (rmdir($directory)) {
            echo "  ✓ Removed: $directory\n";
            $this->deletedItems[] = "Directory: $directory";
        } else {
            echo "  ✗ Failed to remove: $directory\n";
            $this->errors[] = "Failed to remove directory: $directory";
        }
    }

    /**
     * Print cleanup report
     */
    private function printReport(): void
    {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Cleanup Report\n";
        echo str_repeat('=', 50) . "\n";

        if ($this->dryRun) {
            echo "DRY RUN - No changes were made\n\n";
        }

        if (!empty($this->deletedItems)) {
            echo "Deleted items (" . count($this->deletedItems) . "):\n";
            foreach ($this->deletedItems as $item) {
                echo "  - $item\n";
            }
        }

        if (!empty($this->errors)) {
            echo "\nErrors (" . count($this->errors) . "):\n";
            foreach ($this->errors as $error) {
                echo "  - $error\n";
            }
        }

        if (empty($this->deletedItems) && empty($this->errors)) {
            echo "Nothing to clean up!\n";
        }

        echo "\nRecommendations:\n";
        echo "1. Run 'composer dump-autoload' to refresh autoloader\n";
        echo "2. Run your test suite to ensure everything works\n";
        echo "3. Commit these changes to version control\n";
    }
}

// Parse command line arguments
$dryRun = !in_array('--execute', $argv, true);

// Run the cleanup
$cleanup = new StructureCleanup();
$cleanup->run($dryRun);

if ($dryRun) {
    echo "\nTo execute cleanup, run: php CleanupOldStructure.php --execute\n";
}