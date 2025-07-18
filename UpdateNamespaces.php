<?php

declare(strict_types=1);

/**
 * Script to update namespaces after folder reorganization
 * Run from project root: php UpdateNamespaces.php
 */

class NamespaceUpdater
{
    /**
     * @var array<string, string>
     */
    private array $namespaceMapping = [
        // Query
        'MulerTech\\Database\\Query\\SelectBuilder' => 'MulerTech\\Database\\Query\\Builder\\SelectBuilder',
        'MulerTech\\Database\\Query\\InsertBuilder' => 'MulerTech\\Database\\Query\\Builder\\InsertBuilder',
        'MulerTech\\Database\\Query\\UpdateBuilder' => 'MulerTech\\Database\\Query\\Builder\\UpdateBuilder',
        'MulerTech\\Database\\Query\\DeleteBuilder' => 'MulerTech\\Database\\Query\\Builder\\DeleteBuilder',
        'MulerTech\\Database\\Query\\AbstractQueryBuilder' => 'MulerTech\\Database\\Query\\Builder\\AbstractQueryBuilder',
        'MulerTech\\Database\\Query\\QueryCompiler' => 'MulerTech\\Database\\Query\\Compiler\\QueryCompiler',

        // Schema/Migration
        'MulerTech\\Database\\Migration\\' => 'MulerTech\\Database\\Schema\\Migration\\',
        'MulerTech\\Database\\Relational\\Sql\\InformationSchema' => 'MulerTech\\Database\\Schema\\Information\\InformationSchema',
        'MulerTech\\Database\\Relational\\Sql\\InformationSchemaTables' => 'MulerTech\\Database\\Schema\\Information\\InformationSchemaTables',
        'MulerTech\\Database\\Migration\\Schema\\' => 'MulerTech\\Database\\Schema\\Diff\\',

        // Database
        'MulerTech\\Database\\PhpInterface\\' => 'MulerTech\\Database\\Database\\Interface\\',

        // SQL
        'MulerTech\\Database\\Relational\\Sql\\SqlOperations' => 'MulerTech\\Database\\SQL\\Operator\\SqlOperations',
        'MulerTech\\Database\\Relational\\Sql\\SqlOperator' => 'MulerTech\\Database\\Query\\Types\\SqlOperator',
        'MulerTech\\Database\\Relational\\Sql\\Raw' => 'MulerTech\\Database\\Query\\Builder\\Raw',
        'MulerTech\\Database\\Relational\\Sql\\JoinType' => 'MulerTech\\Database\\Query\\Types\\JoinType',
        'MulerTech\\Database\\Relational\\Sql\\LinkOperator' => 'MulerTech\\Database\\SQL\\Type\\LinkOperator',

        // Mapping attributes
        'MulerTech\\Database\\Mapping\\Mt' => 'MulerTech\\Database\\Mapping\\Attributes\\Mt',
        'MulerTech\\Database\\Mapping\\ColumnType' => 'MulerTech\\Database\\Mapping\\Metadata\\ColumnType',
        'MulerTech\\Database\\Mapping\\Types\\ColumnKey' => 'MulerTech\\Database\\Mapping\\Metadata\\ColumnKey',
        'MulerTech\\Database\\Mapping\\FetchType' => 'MulerTech\\Database\\Mapping\\Metadata\\FetchType',
        'MulerTech\\Database\\Mapping\\OnActionType' => 'MulerTech\\Database\\Mapping\\Metadata\\OnActionType',

        // ORM
        'MulerTech\\Database\\ORM\\EntityRepository' => 'MulerTech\\Database\\ORM\\EntityRepository',
        'MulerTech\\Database\\ORM\\EntityMetadata' => 'MulerTech\\Database\\ORM\\EntityMetadata',
    ];

    /**
     * @var array<string>
     */
    private array $processedFiles = [];

    /**
     * @var array<string>
     */
    private array $errors = [];

    /**
     * Run the namespace update process
     */
    public function run(): void
    {
        echo "Starting namespace update process...\n\n";

        $srcDir = __DIR__ . '/src';
        if (!is_dir($srcDir)) {
            die("Error: src directory not found. Run this script from the project root.\n");
        }

        $this->processDirectory($srcDir);

        $this->printSummary();
    }

    /**
     * @param string $directory
     */
    private function processDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->processFile($file->getPathname());
            }
        }
    }

    /**
     * @param string $filePath
     */
    private function processFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            $this->errors[] = "Failed to read: $filePath";
            return;
        }

        $originalContent = $content;
        $modified = false;

        // Update namespace declaration
        $content = preg_replace_callback(
            '/^namespace\s+([^;]+);/m',
            function ($matches) use (&$modified) {
                $oldNamespace = $matches[1];
                $newNamespace = $this->mapNamespace($oldNamespace);

                if ($oldNamespace !== $newNamespace) {
                    $modified = true;
                    echo "  Namespace: $oldNamespace → $newNamespace\n";
                    return "namespace $newNamespace;";
                }

                return $matches[0];
            },
            $content
        );

        // Update use statements
        $content = preg_replace_callback(
            '/^use\s+([^;]+);/m',
            function ($matches) use (&$modified) {
                $oldUse = $matches[1];
                $parts = explode(' as ', $oldUse);
                $className = $parts[0];
                $alias = $parts[1] ?? null;

                $newClassName = $this->mapFullClassName($className);

                if ($className !== $newClassName) {
                    $modified = true;
                    $newUse = $alias ? "$newClassName as $alias" : $newClassName;
                    echo "  Use: $oldUse → $newUse\n";
                    return "use $newUse;";
                }

                return $matches[0];
            },
            $content
        );

        // Update in PHPDoc
        $content = preg_replace_callback(
            '/@(param|return|var|throws)\s+([\w\\\\]+)/',
            function ($matches) use (&$modified) {
                $tag = $matches[1];
                $type = $matches[2];

                if (str_contains($type, '\\')) {
                    $newType = $this->mapFullClassName($type);
                    if ($type !== $newType) {
                        $modified = true;
                        return "@$tag $newType";
                    }
                }

                return $matches[0];
            },
            $content
        );

        // Save if modified
        if ($modified) {
            if (file_put_contents($filePath, $content) === false) {
                $this->errors[] = "Failed to write: $filePath";
            } else {
                $this->processedFiles[] = $filePath;
                echo "Updated: $filePath\n";
            }
        }
    }

    /**
     * @param string $namespace
     * @return string
     */
    private function mapNamespace(string $namespace): string
    {
        foreach ($this->namespaceMapping as $old => $new) {
            if (str_starts_with($namespace . '\\', $old)) {
                return $new . substr($namespace, strlen($old));
            }
        }
        return $namespace;
    }

    /**
     * @param string $className
     * @return string
     */
    private function mapFullClassName(string $className): string
    {
        // Remove leading backslash if present
        $className = ltrim($className, '\\');

        // Try exact match first
        if (isset($this->namespaceMapping[$className])) {
            return $this->namespaceMapping[$className];
        }

        // Try prefix match
        foreach ($this->namespaceMapping as $old => $new) {
            if (str_starts_with($className . '\\', $old)) {
                return $new . substr($className, strlen($old));
            }
        }

        return $className;
    }

    /**
     * Print summary of the update process
     */
    private function printSummary(): void
    {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Namespace Update Summary\n";
        echo str_repeat('=', 50) . "\n";
        echo "Files updated: " . count($this->processedFiles) . "\n";
        echo "Errors: " . count($this->errors) . "\n";

        if (!empty($this->errors)) {
            echo "\nErrors:\n";
            foreach ($this->errors as $error) {
                echo "  - $error\n";
            }
        }

        echo "\nDone!\n";
    }
}

// Run the updater
$updater = new NamespaceUpdater();
$updater->run();