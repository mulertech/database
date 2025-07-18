<?php

declare(strict_types=1);

/**
 * Script to verify the new folder structure and namespace consistency
 * Run from project root: php VerifyStructure.php
 */

class StructureVerifier
{
    /**
     * @var array<string>
     */
    private array $errors = [];

    /**
     * @var array<string>
     */
    private array $warnings = [];

    /**
     * @var array<string, string>
     */
    private array $expectedStructure = [
        'src/Core/Traits' => 'MulerTech\\Database\\Core\\Traits',
        'src/Core/Cache' => 'MulerTech\\Database\\Core\\Cache',
        'src/Core/Parameters' => 'MulerTech\\Database\\Core\\Parameters',
        'src/Query/Builder' => 'MulerTech\\Database\\Query\\Builder',
        'src/Query/Clause' => 'MulerTech\\Database\\Query\\Clause',
        'src/Query/Compiler' => 'MulerTech\\Database\\Query\\Compiler',
        'src/ORM/Engine/Persistence' => 'MulerTech\\Database\\ORM\\Engine\\Persistence',
        'src/ORM/Engine/Relations' => 'MulerTech\\Database\\ORM\\Engine\\Relations',
        'src/ORM/State' => 'MulerTech\\Database\\ORM\\State',
        'src/ORM/Repository' => 'MulerTech\\Database\\ORM\\Repository',
        'src/ORM/Metadata' => 'MulerTech\\Database\\ORM\\Metadata',
        'src/Schema/Migration' => 'MulerTech\\Database\\Schema\\Migration',
        'src/Schema/Information' => 'MulerTech\\Database\\Schema\\Information',
        'src/Schema/Builder' => 'MulerTech\\Database\\Schema\\Builder',
        'src/Schema/Diff' => 'MulerTech\\Database\\Schema\\Diff',
        'src/Mapping/Attributes' => 'MulerTech\\Database\\Mapping\\Attributes',
        'src/Mapping/Metadata' => 'MulerTech\\Database\\Mapping\\Metadata',
        'src/Database/Connection' => 'MulerTech\\Database\\Database\\Connection',
        'src/Database/Driver' => 'MulerTech\\Database\\Database\\MySQLDriver',
        'src/Database/Interface' => 'MulerTech\\Database\\Database\\Interface',
        'src/SQL/Operator' => 'MulerTech\\Database\\SQL\\Operator',
        'src/SQL/Expression' => 'MulerTech\\Database\\SQL\\Expression',
        'src/SQL/Type' => 'MulerTech\\Database\\SQL\\Type',
    ];

    /**
     * Run the verification process
     */
    public function run(): void
    {
        echo "Verifying folder structure and namespaces...\n\n";

        $this->verifyDirectories();
        $this->verifyNamespaces();
        $this->checkForOrphanedFiles();
        $this->checkUseStatements();

        $this->printReport();
    }

    /**
     * Verify that all expected directories exist
     */
    private function verifyDirectories(): void
    {
        echo "Checking directory structure...\n";

        foreach (array_keys($this->expectedStructure) as $dir) {
            if (!is_dir($dir)) {
                $this->errors[] = "Missing directory: $dir";
            } else {
                echo "  ✓ $dir\n";
            }
        }
    }

    /**
     * Verify namespace consistency
     */
    private function verifyNamespaces(): void
    {
        echo "\nChecking namespace consistency...\n";

        foreach ($this->expectedStructure as $dir => $expectedNamespace) {
            if (!is_dir($dir)) {
                continue;
            }

            $files = glob("$dir/*.php");
            foreach ($files as $file) {
                $this->verifyFileNamespace($file, $expectedNamespace);
            }
        }
    }

    /**
     * @param string $file
     * @param string $expectedNamespace
     */
    private function verifyFileNamespace(string $file, string $expectedNamespace): void
    {
        $content = file_get_contents($file);
        if ($content === false) {
            $this->errors[] = "Cannot read file: $file";
            return;
        }

        // Extract namespace
        if (preg_match('/^namespace\s+([^;]+);/m', $content, $matches)) {
            $actualNamespace = $matches[1];
            if ($actualNamespace !== $expectedNamespace) {
                $this->errors[] = "Namespace mismatch in $file:\n" .
                    "  Expected: $expectedNamespace\n" .
                    "  Actual: $actualNamespace";
            }
        } else {
            $this->warnings[] = "No namespace found in: $file";
        }
    }

    /**
     * Check for files that might have been left in old locations
     */
    private function checkForOrphanedFiles(): void
    {
        echo "\nChecking for orphaned files...\n";

        $oldDirectories = [
            'src/PhpInterface',
            'src/Relational/Sql',
            'src/Migration',
        ];

        foreach ($oldDirectories as $dir) {
            if (is_dir($dir)) {
                $files = $this->getAllPhpFiles($dir);
                if (!empty($files)) {
                    $this->warnings[] = "Found files in old directory $dir:\n  " .
                        implode("\n  ", $files);
                }
            }
        }
    }

    /**
     * Check use statements for consistency
     */
    private function checkUseStatements(): void
    {
        echo "\nChecking use statements...\n";

        $allFiles = $this->getAllPhpFiles('src');
        $classMap = $this->buildClassMap($allFiles);

        foreach ($allFiles as $file) {
            $this->checkFileUseStatements($file, $classMap);
        }
    }

    /**
     * @param string $file
     * @param array<string, string> $classMap
     */
    private function checkFileUseStatements(string $file, array $classMap): void
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return;
        }

        // Extract use statements
        preg_match_all('/^use\s+([^;]+);/m', $content, $matches);

        foreach ($matches[1] as $useStatement) {
            $parts = explode(' as ', $useStatement);
            $className = trim($parts[0]);

            // Check if class exists in our codebase
            $shortName = substr($className, strrpos($className, '\\') + 1);

            if (str_starts_with($className, 'MulerTech\\Database\\')) {
                if (!isset($classMap[$shortName])) {
                    $this->warnings[] = "Unknown class in use statement in $file: $className";
                } elseif ($classMap[$shortName] !== $className) {
                    $this->errors[] = "Incorrect namespace in use statement in $file:\n" .
                        "  Used: $className\n" .
                        "  Should be: " . $classMap[$shortName];
                }
            }
        }
    }

    /**
     * @param string $directory
     * @return array<string>
     */
    private function getAllPhpFiles(string $directory): array
    {
        $files = [];

        if (!is_dir($directory)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * @param array<string> $files
     * @return array<string, string>
     */
    private function buildClassMap(array $files): array
    {
        $classMap = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            // Extract namespace and class name - improved regex to handle readonly classes
            if (preg_match('/^namespace\s+([^;]+);/m', $content, $nsMatch) &&
                preg_match('/^(?:abstract\s+)?(?:final\s+)?(?:readonly\s+)?(?:class|interface|trait|enum)\s+(\w+)/m', $content, $classMatch)) {
                $namespace = $nsMatch[1];
                $className = $classMatch[1];
                $fullClassName = $namespace . '\\' . $className;

                $classMap[$className] = $fullClassName;
            }
        }

        return $classMap;
    }

    /**
     * Print verification report
     */
    private function printReport(): void
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "Verification Report\n";
        echo str_repeat('=', 60) . "\n";

        if (empty($this->errors) && empty($this->warnings)) {
            echo "✓ All checks passed! Structure is valid.\n";
        } else {
            if (!empty($this->errors)) {
                echo "\nErrors (" . count($this->errors) . "):\n";
                foreach ($this->errors as $error) {
                    echo "  ✗ $error\n";
                }
            }

            if (!empty($this->warnings)) {
                echo "\nWarnings (" . count($this->warnings) . "):\n";
                foreach ($this->warnings as $warning) {
                    echo "  ⚠ $warning\n";
                }
            }
        }

        echo "\nNext steps:\n";
        echo "1. Fix any errors listed above\n";
        echo "2. Run tests to ensure functionality\n";
        echo "3. Update composer.json autoload if needed\n";
        echo "4. Update any external references to these classes\n";
    }
}

// Run the verifier
$verifier = new StructureVerifier();
$verifier->run();